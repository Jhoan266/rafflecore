<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Webhook Service — Notificaciones HTTP salientes a sistemas externos.
 */
class RaffleCore_Webhook_Service {

    private static function table() {
        global $wpdb;
        return $wpdb->prefix . 'rc_webhooks';
    }

    /**
     * Eventos soportados.
     */
    public static function get_events() {
        return array(
            'purchase.completed' => __( 'Compra completada', 'rafflecore' ),
            'raffle.created'     => __( 'Rifa creada', 'rafflecore' ),
            'raffle.sold_out'    => __( 'Rifa agotada', 'rafflecore' ),
            'draw.executed'      => __( 'Sorteo realizado', 'rafflecore' ),
            'coupon.used'        => __( 'Cupón utilizado', 'rafflecore' ),
        );
    }

    /**
     * Registra un webhook.
     */
    public static function create( $data ) {
        global $wpdb;

        $secret = wp_generate_password( 32, false );

        $wpdb->insert( self::table(), array(
            'event'      => sanitize_text_field( $data['event'] ?? '' ),
            'url'        => esc_url_raw( $data['url'] ?? '' ),
            'secret'     => $secret,
            'status'     => 'active',
            'created_at' => current_time( 'mysql' ),
        ), array( '%s', '%s', '%s', '%s', '%s' ) );

        return $wpdb->insert_id ?: new WP_Error( 'db_error', __( 'Error al crear webhook.', 'rafflecore' ) );
    }

    /**
     * Dispara webhooks para un evento.
     *
     * @param string $event   Nombre del evento.
     * @param array  $payload Datos a enviar.
     */
    public static function fire( $event, $payload = array() ) {
        global $wpdb;

        $hooks = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM " . self::table() . " WHERE event = %s AND status = 'active'",
            $event
        ) );

        if ( empty( $hooks ) ) {
            return;
        }

        $payload['event']     = $event;
        $payload['timestamp'] = current_time( 'c' );
        $payload['site_url']  = home_url();

        foreach ( $hooks as $hook ) {
            $signature = hash_hmac( 'sha256', wp_json_encode( $payload ), $hook->secret );

            wp_remote_post( $hook->url, array(
                'body'    => wp_json_encode( $payload ),
                'headers' => array(
                    'Content-Type'         => 'application/json',
                    'X-RaffleCore-Event'   => $event,
                    'X-RaffleCore-Signature' => $signature,
                ),
                'timeout'   => 10,
                'blocking'  => false,
                'sslverify' => true,
            ) );

            RaffleCore_Logger::log( 'webhook_fired', 'webhook', $hook->id, $event );
        }
    }

    /**
     * Obtiene todos los webhooks.
     */
    public static function get_all() {
        global $wpdb;
        return $wpdb->get_results( "SELECT * FROM " . self::table() . " ORDER BY created_at DESC" );
    }

    /**
     * Elimina un webhook.
     */
    public static function delete( $id ) {
        global $wpdb;
        return $wpdb->delete( self::table(), array( 'id' => $id ), array( '%d' ) );
    }

    /**
     * Prepara datos del formulario.
     */
    public static function prepare_data( $post ) {
        return array(
            'event' => sanitize_text_field( wp_unslash( $post['webhook_event'] ?? '' ) ),
            'url'   => esc_url_raw( wp_unslash( $post['webhook_url'] ?? '' ) ),
        );
    }
}
