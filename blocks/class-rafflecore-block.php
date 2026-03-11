<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * RaffleCore Gutenberg Block — Bloque para insertar rifas en el editor.
 */
class RaffleCore_Block {

    public static function register() {
        if ( ! function_exists( 'register_block_type' ) ) {
            return;
        }

        // Register block via PHP (no build required)
        register_block_type( 'rafflecore/raffle', array(
            'api_version'     => 2,
            'editor_script'   => 'rc-block-editor',
            'render_callback' => array( __CLASS__, 'render' ),
            'attributes'      => array(
                'raffleId' => array(
                    'type'    => 'number',
                    'default' => 0,
                ),
            ),
        ) );

        add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue_editor' ) );
    }

    public static function enqueue_editor() {
        wp_register_script(
            'rc-block-editor',
            RAFFLECORE_URL . 'assets/js/block-editor.js',
            array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-server-side-render' ),
            RAFFLECORE_VERSION,
            true
        );

        // Pass available raffles to the block editor
        $api = new RaffleCore_API_Service();
        $raffles = $api->get_all_raffles( array( 'per_page' => 100 ) );

        $options = array( array( 'label' => __( 'Seleccionar rifa...', 'rafflecore' ), 'value' => 0 ) );
        if ( $raffles ) {
            foreach ( $raffles as $r ) {
                $options[] = array(
                    'label' => $r->title . ' (#' . $r->id . ')',
                    'value' => (int) $r->id,
                );
            }
        }

        wp_localize_script( 'rc-block-editor', 'rcBlock', array(
            'raffles' => $options,
            'i18n'    => array(
                'panelTitle'  => __( 'Configuración de Rifa', 'rafflecore' ),
                'selectHint'  => __( 'Selecciona una rifa en el panel lateral.', 'rafflecore' ),
                'description' => __( 'Muestra una rifa de RaffleCore.', 'rafflecore' ),
            ),
        ) );
    }

    public static function render( $attributes ) {
        $raffle_id = isset( $attributes['raffleId'] ) ? absint( $attributes['raffleId'] ) : 0;

        if ( ! $raffle_id ) {
            return '<p style="color:#999;text-align:center;">' . esc_html__( 'Selecciona una rifa en el editor.', 'rafflecore' ) . '</p>';
        }

        // Delegate to the existing shortcode logic
        $public = new RaffleCore_Public( new RaffleCore_API_Service() );
        return $public->render_shortcode( array( 'id' => $raffle_id ) );
    }
}
