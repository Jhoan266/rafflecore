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

        // Public
        $public = new RaffleCore_Public( $this->api );
        $this->loader->add_action( 'wp_enqueue_scripts', $public, 'enqueue_assets' );

        // Draw (AJAX admin)
        $draw = new RaffleCore_Draw_Service( $this->api );
        $this->loader->add_action( 'wp_ajax_rc_draw_winner', $draw, 'ajax_draw' );

        // WooCommerce integration
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

        // Reservation cleanup cron
        $this->loader->add_filter( 'cron_schedules', 'RaffleCore_Reservation_Service', 'add_cron_interval' );
        $this->loader->add_action( 'rc_cleanup_reservations', 'RaffleCore_Reservation_Service', 'cleanup_expired' );
        RaffleCore_Reservation_Service::schedule_cleanup();

        // Execute all hooks
        $this->loader->run();

        // Shortcode (no usa loader)
        add_shortcode( 'rafflecore', array( $public, 'render_shortcode' ) );
    }
}
