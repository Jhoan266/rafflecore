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

        wp_enqueue_style( 'rc-google-fonts', 'https://fonts.googleapis.com/css2?family=Fredoka:wght@400;500;600;700&family=Nunito:wght@400;600;700;800&display=swap', array(), null );
        $css_ver = file_exists( RAFFLECORE_PATH . 'assets/css/public.css' ) ? filemtime( RAFFLECORE_PATH . 'assets/css/public.css' ) : RAFFLECORE_VERSION;
        wp_enqueue_style( 'rc-public', RAFFLECORE_URL . 'assets/css/public.css', array( 'rc-google-fonts' ), $css_ver );
        // Load per-raffle custom font if set
        $raffle_id = self::extract_raffle_id_from_content( $post->post_content );
        if ( $raffle_id ) {
            $raffle_obj = $this->api->get_raffle( $raffle_id );
            if ( $raffle_obj && ! empty( $raffle_obj->font_family ) ) {
                if ( $raffle_obj->font_family === 'custom' && ! empty( $raffle_obj->custom_font_url ) ) {
                    // Custom uploaded font: inject @font-face inline
                    $font_url = esc_url( $raffle_obj->custom_font_url );
                    $ext = strtolower( pathinfo( wp_parse_url( $font_url, PHP_URL_PATH ), PATHINFO_EXTENSION ) );
                    $format_map = array( 'woff2' => 'woff2', 'woff' => 'woff', 'ttf' => 'truetype', 'otf' => 'opentype' );
                    $format = isset( $format_map[ $ext ] ) ? $format_map[ $ext ] : 'truetype';
                    wp_add_inline_style( 'rc-public', sprintf(
                        "@font-face { font-family: 'RCCustomFont'; src: url('%s') format('%s'); font-weight: 100 900; font-display: swap; }",
                        $font_url, $format
                    ) );
                } else {
                    $font_slug = str_replace( ' ', '+', $raffle_obj->font_family );
                    wp_enqueue_style( 'rc-custom-font', 'https://fonts.googleapis.com/css2?family=' . $font_slug . ':wght@400;500;600;700;800;900&display=swap', array(), null );
                }
            }
        }
        wp_enqueue_script( 'rc-public', RAFFLECORE_URL . 'assets/js/public.js', array( 'jquery' ), RAFFLECORE_VERSION, true );

        $wc_integration = new RaffleCore_WooCommerce( $this->api );

        wp_localize_script( 'rc-public', 'rcPublic', array(
            'ajax_url'   => admin_url( 'admin-ajax.php' ),
            'nonce'      => wp_create_nonce( 'rc_public_nonce' ),
            'wc_enabled' => $wc_integration->is_available(),
            'currency'   => '$',
            'i18n'       => array(
                'ticketsFor'      => __( 'boletos por', 'rafflecore' ),
                'nameRequired'    => __( 'Por favor ingresa tu nombre completo.', 'rafflecore' ),
                'emailInvalid'    => __( 'Por favor ingresa un correo electrónico válido.', 'rafflecore' ),
                'phoneInvalid'    => __( 'Por favor ingresa un número de teléfono válido.', 'rafflecore' ),
                'orderError'      => __( 'Error al crear la orden.', 'rafflecore' ),
                'purchaseError'   => __( 'Error al procesar la compra.', 'rafflecore' ),
                'connectionError' => __( 'Error de conexión. Intenta de nuevo.', 'rafflecore' ),
                'validating'      => __( 'Validando...', 'rafflecore' ),
                'connectionErr'   => __( 'Error de conexión.', 'rafflecore' ),
            ),
        ) );
    }

    /**
     * Extracts raffle ID from post content shortcode.
     */
    private static function extract_raffle_id_from_content( $content ) {
        if ( preg_match( '/\[rafflecore\s+id=["\']?(\d+)["\']?/', $content, $m ) ) {
            return absint( $m[1] );
        }
        return 0;
    }

    /**
     * Shortcode [rafflecore id="X"]
     */
    public function render_shortcode( $atts ) {
        $atts = shortcode_atts( array( 'id' => 0 ), $atts, 'rafflecore' );

        if ( ! $atts['id'] ) {
            return '<p style="color:red;text-align:center;">' . esc_html__( 'RaffleCore: falta el atributo id.', 'rafflecore' ) . '</p>';
        }

        $raffle = $this->api->get_raffle( absint( $atts['id'] ) );

        if ( ! $raffle ) {
            return '<p style="color:red;text-align:center;">' . esc_html__( 'RaffleCore: rifa no encontrada.', 'rafflecore' ) . '</p>';
        }

        if ( $raffle->status !== 'active' ) {
            return '<p style="text-align:center;">' . esc_html__( 'Esta rifa no está disponible actualmente.', 'rafflecore' ) . '</p>';
        }

        $progress = RaffleCore_Raffle_Service::get_progress( $raffle );
        $packages = RaffleCore_Raffle_Service::get_available_packages( $raffle );

        $theme     = get_option( 'rafflecore_display_theme', 'theme1' );
        $view_file = ( $theme === 'theme2' )
            ? RAFFLECORE_PATH . 'public/views/raffle-display-theme2.php'
            : RAFFLECORE_PATH . 'public/views/raffle-display.php';

        ob_start();
        include $view_file;
        return ob_get_clean();
    }

    /**
     * Shortcode [rafflecore_tickets] — Página "Mis Boletos"
     */
    public function render_my_tickets( $atts ) {
        wp_enqueue_script( 'jquery' );
        ob_start();
        include RAFFLECORE_PATH . 'public/views/my-tickets.php';
        return ob_get_clean();
    }
}
