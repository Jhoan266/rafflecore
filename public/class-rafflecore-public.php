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

        // Vacation mode overrides everything
        if ( get_option( 'rafflecore_vacation_mode', 'no' ) === 'yes' ) {
            return $this->render_vacation_banner();
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
     * Render vacation mode banner when no active raffles.
     */
    private function render_vacation_banner() {
        $logo     = get_option( 'rafflecore_vacation_logo', '' );
        $title    = get_option( 'rafflecore_vacation_title', 'GRACIAS POR PARTICIPAR!' );
        $subtitle = get_option( 'rafflecore_vacation_subtitle', 'NOS VEMOS PRONTO CON UN NUEVO EVENTO!' );

        ob_start();
        ?>
        <div class="rc-vacation-wrapper">
            <div class="rc-vacation-smoke"></div>
            <div class="rc-vacation-card">
                <?php if ( $logo ) : ?>
                    <img src="<?php echo esc_url( $logo ); ?>" alt="Logo" class="rc-vacation-logo">
                <?php endif; ?>
                <h2 class="rc-vacation-title"><?php echo esc_html( $title ); ?></h2>
                <p class="rc-vacation-subtitle"><?php echo esc_html( $subtitle ); ?></p>
            </div>
        </div>
        <style>
            .rc-vacation-wrapper {
                position: fixed;
                inset: 0;
                z-index: 99999;
                display: flex;
                align-items: center;
                justify-content: center;
                overflow: hidden;
            }
            .rc-vacation-smoke {
                position: absolute;
                inset: -20px;
                background:
                    radial-gradient(ellipse at 20% 50%, rgba(100, 140, 200, 0.3) 0%, transparent 60%),
                    radial-gradient(ellipse at 80% 30%, rgba(80, 120, 180, 0.25) 0%, transparent 55%),
                    radial-gradient(ellipse at 50% 80%, rgba(60, 100, 160, 0.2) 0%, transparent 50%),
                    radial-gradient(ellipse at 60% 20%, rgba(90, 150, 210, 0.2) 0%, transparent 45%),
                    linear-gradient(180deg, #1a2a4a 0%, #2a4a7a 30%, #1e3a5e 60%, #0f1f35 100%);
                animation: rc-smoke-drift 8s ease-in-out infinite alternate;
                filter: blur(30px);
            }
            @keyframes rc-smoke-drift {
                0%   { transform: scale(1) translate(0, 0); opacity: 0.8; }
                33%  { transform: scale(1.05) translate(10px, -5px); opacity: 1; }
                66%  { transform: scale(1.02) translate(-8px, 3px); opacity: 0.9; }
                100% { transform: scale(1.08) translate(5px, -8px); opacity: 1; }
            }
            .rc-vacation-card {
                position: relative;
                z-index: 2;
                display: flex;
                flex-direction: column;
                align-items: center;
                text-align: center;
                padding: 0 40px 35px;
                background: rgba(15, 25, 50, 0.75);
                backdrop-filter: blur(12px);
                -webkit-backdrop-filter: blur(12px);
                border-radius: 16px;
                border: 1px solid rgba(100, 150, 220, 0.15);
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
                max-width: 500px;
                width: 90%;
            }
            .rc-vacation-logo {
                max-width: 320px;
                max-height: 220px;
                margin-top: 0;
                margin-bottom: 10px;
                object-fit: contain;
                filter: drop-shadow(0 4px 12px rgba(0, 0, 0, 0.4));
            }
            .rc-vacation-title {
                color: #fff;
                font-size: 28px;
                font-weight: 800;
                letter-spacing: 2px;
                margin: 0 0 14px;
                text-transform: uppercase;
                text-shadow: 0 2px 8px rgba(0, 0, 0, 0.5);
            }
            .rc-vacation-subtitle {
                color: rgba(255, 255, 255, 0.85);
                font-size: 18px;
                font-weight: 700;
                letter-spacing: 1px;
                margin: 0;
                text-transform: uppercase;
                text-shadow: 0 1px 4px rgba(0, 0, 0, 0.4);
            }
            @media (max-width: 600px) {
                .rc-vacation-card { padding: 40px 24px; }
                .rc-vacation-title { font-size: 20px; }
                .rc-vacation-subtitle { font-size: 14px; }
                .rc-vacation-logo { max-width: 160px; }
            }
        </style>
        <script>document.body.appendChild(document.querySelector('.rc-vacation-wrapper'));</script>
        <?php
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
