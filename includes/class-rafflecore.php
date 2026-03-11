<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Orquestador principal del plugin. Registra todos los módulos y hooks.
 */
class RaffleCore {

    private $loader;
    private $api;

    public function __construct() {
        $this->loader = new RaffleCore_Loader();
        $this->api    = new RaffleCore_API_Service();
    }

    public function run() {
        // Admin
        $admin = new RaffleCore_Admin( $this->api );
        $this->loader->add_action( 'admin_menu', $admin, 'add_menus' );
        $this->loader->add_action( 'admin_enqueue_scripts', $admin, 'enqueue_assets' );
        $this->loader->add_action( 'admin_init', $admin, 'handle_form' );
        $this->loader->add_action( 'admin_post_rc_admin_form', $admin, 'handle_form' );
        $this->loader->add_action( 'admin_post_rc_save_coupon', $admin, 'handle_save_coupon' );
        $this->loader->add_action( 'admin_post_rc_save_webhook', $admin, 'handle_save_webhook' );

        // Public
        $public = new RaffleCore_Public( $this->api );
        $this->loader->add_action( 'wp_enqueue_scripts', $public, 'enqueue_assets' );

        // Draw (AJAX admin)
        $draw = new RaffleCore_Draw_Service( $this->api );
        $this->loader->add_action( 'wp_ajax_rc_draw_winner', $draw, 'ajax_draw' );
        $this->loader->add_action( 'wp_ajax_rc_external_draw', $draw, 'ajax_external_draw' );

        // WooCommerce integration — solo si WC está activo
        if ( RaffleCore_WooCommerce::is_available() ) {
            $wc = new RaffleCore_WooCommerce( $this->api );
            $this->loader->add_action( 'wp_ajax_rc_create_order', $wc, 'ajax_create_order' );
            $this->loader->add_action( 'wp_ajax_nopriv_rc_create_order', $wc, 'ajax_create_order' );
            $this->loader->add_action( 'woocommerce_payment_complete', $wc, 'on_payment_complete' );
            $this->loader->add_action( 'woocommerce_order_status_completed', $wc, 'on_payment_complete' );
            $this->loader->add_action( 'woocommerce_order_status_processing', $wc, 'on_payment_complete' );
            $this->loader->add_action( 'woocommerce_order_status_cancelled', $wc, 'on_order_cancelled' );
            $this->loader->add_action( 'woocommerce_order_status_failed', $wc, 'on_order_cancelled' );
            $this->loader->add_action( 'woocommerce_thankyou', $wc, 'thankyou_page' );
            $this->loader->add_action( 'woocommerce_admin_order_data_after_billing_address', $wc, 'admin_order_meta' );
            $this->loader->add_action( 'woocommerce_checkout_order_created', $wc, 'on_order_created' );

            // WC Product Manager — precio dinámico y metadata en carrito/orden
            $this->loader->add_action( 'woocommerce_before_calculate_totals', 'RaffleCore_WC_Product_Manager', 'override_cart_price' );
            $this->loader->add_filter( 'woocommerce_get_item_data', 'RaffleCore_WC_Product_Manager', 'display_cart_data', 10, 2 );
            $this->loader->add_action( 'woocommerce_checkout_create_order_line_item', 'RaffleCore_WC_Product_Manager', 'save_order_item_meta', 10, 4 );
        } else {
            // WooCommerce no activo — mostrar aviso en admin
            $this->loader->add_action( 'admin_notices', $this, 'woocommerce_missing_notice' );
        }

        // Coupon AJAX (public) — independiente de WC
        $coupon_service = new RaffleCore_Coupon_Service();
        $this->loader->add_action( 'wp_ajax_rc_validate_coupon', $coupon_service, 'ajax_validate_coupon' );
        $this->loader->add_action( 'wp_ajax_nopriv_rc_validate_coupon', $coupon_service, 'ajax_validate_coupon' );

        // AJAX hydration — datos frescos para compatibilidad con caché de página
        $this->loader->add_action( 'wp_ajax_rc_hydrate_raffle', $this, 'ajax_hydrate_raffle' );
        $this->loader->add_action( 'wp_ajax_nopriv_rc_hydrate_raffle', $this, 'ajax_hydrate_raffle' );

        // Reservation cleanup cron
        $this->loader->add_filter( 'cron_schedules', 'RaffleCore_Reservation_Service', 'add_cron_interval' );
        $this->loader->add_action( 'rc_cleanup_reservations', 'RaffleCore_Reservation_Service', 'cleanup_expired' );
        RaffleCore_Reservation_Service::schedule_cleanup();

        // Execute all hooks
        $this->loader->run();

        // Allow font file uploads
        add_filter( 'upload_mimes', array( $this, 'allow_font_mimes' ) );

        // Shortcode (no usa loader)
        add_shortcode( 'rafflecore', array( $public, 'render_shortcode' ) );
        add_shortcode( 'rafflecore_tickets', array( $public, 'render_my_tickets' ) );

        // Load text domain
        load_plugin_textdomain( 'rafflecore', false, dirname( RAFFLECORE_BASENAME ) . '/languages' );
    }

    public function allow_font_mimes( $mimes ) {
        $mimes['woff']  = 'font/woff';
        $mimes['woff2'] = 'font/woff2';
        $mimes['ttf']   = 'font/ttf';
        $mimes['otf']   = 'font/otf';
        return $mimes;
    }

    public function woocommerce_missing_notice() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        echo '<div class="notice notice-warning is-dismissible">';
        echo '<p><strong>RaffleCore:</strong> ' . esc_html__( 'WooCommerce no está activo. Las funciones de pago estarán deshabilitadas hasta que actives WooCommerce.', 'rafflecore' ) . '</p>';
        echo '</div>';
    }

    /**
     * AJAX: Devuelve datos frescos de inventario para hidratación post-caché.
     */
    public function ajax_hydrate_raffle() {
        $raffle_id = isset( $_GET['raffle_id'] ) ? absint( $_GET['raffle_id'] ) : 0;
        if ( ! $raffle_id ) {
            wp_send_json_error( array( 'message' => __( 'ID inválido.', 'rafflecore' ) ) );
        }

        $raffle = $this->api->get_raffle( $raffle_id );
        if ( ! $raffle ) {
            wp_send_json_error( array( 'message' => __( 'Rifa no encontrada.', 'rafflecore' ) ) );
        }

        $available = (int) $raffle->total_tickets - (int) $raffle->sold_tickets;
        $progress  = RaffleCore_Raffle_Service::get_progress( $raffle );
        $packages  = RaffleCore_Raffle_Service::get_available_packages( $raffle );

        wp_send_json_success( array(
            'total_tickets' => (int) $raffle->total_tickets,
            'sold_tickets'  => (int) $raffle->sold_tickets,
            'available'     => $available,
            'progress'      => $progress,
            'status'        => $raffle->status,
            'ticket_price'  => (float) $raffle->ticket_price,
            'packages'      => $packages,
        ) );
    }
}
