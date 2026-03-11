<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Public Controller — Shortcode y assets del frontend.
 */
class RaffleCore_Public {

    private $api;

    public function __construct( $api ) {
        $this->api = $api;
    }

    public function enqueue_assets() {
        if ( ! is_singular() ) return;

        global $post;
        if ( ! $post || ! has_shortcode( $post->post_content, 'rafflecore' ) ) return;

        wp_enqueue_style( 'rc-public', RAFFLECORE_URL . 'assets/css/public.css', array(), RAFFLECORE_VERSION );
        wp_enqueue_script( 'rc-public', RAFFLECORE_URL . 'assets/js/public.js', array( 'jquery' ), RAFFLECORE_VERSION, true );

        $wc_integration = new RaffleCore_WooCommerce_Integration( $this->api );

        wp_localize_script( 'rc-public', 'rcPublic', array(
            'ajax_url'   => admin_url( 'admin-ajax.php' ),
            'nonce'      => wp_create_nonce( 'rc_public_nonce' ),
            'wc_enabled' => $wc_integration->is_available(),
            'currency'   => '$',
        ) );
    }

    /**
     * Shortcode [rafflecore id="X"]
     */
    public function render_shortcode( $atts ) {
        $atts = shortcode_atts( array( 'id' => 0 ), $atts, 'rafflecore' );

        if ( ! $atts['id'] ) {
            return '<p style="color:red;text-align:center;">RaffleCore: falta el atributo <code>id</code>.</p>';
        }

        $raffle = $this->api->get_raffle( absint( $atts['id'] ) );

        if ( ! $raffle ) {
            return '<p style="color:red;text-align:center;">RaffleCore: rifa no encontrada.</p>';
        }

        if ( $raffle->status !== 'active' ) {
            return '<p style="text-align:center;">Esta rifa no está disponible actualmente.</p>';
        }

        $progress = RaffleCore_Raffle_Service::get_progress( $raffle );
        $packages = RaffleCore_Raffle_Service::get_available_packages( $raffle );

        ob_start();
        include RAFFLECORE_PATH . 'public/views/raffle-display.php';
        return ob_get_clean();
    }
}
