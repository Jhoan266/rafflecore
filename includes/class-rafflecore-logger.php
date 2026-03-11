<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * RaffleCore Logger — Sistema de logs de actividad administrativa.
 */
class RaffleCore_Logger {

    private static function table() {
        global $wpdb;
        return $wpdb->prefix . 'rc_activity_log';
    }

    /**
     * Registra una acción en el log con cadena de integridad (hash chain).
     *
     * Cada entrada incluye un hash SHA-256 que depende del hash anterior,
     * formando una cadena inmutable verificable para disputas financieras.
     *
     * @param string $action      Acción realizada (raffle_created, draw_executed, etc.)
     * @param string $object_type Tipo de objeto (raffle, purchase, coupon, etc.)
     * @param int    $object_id   ID del objeto.
     * @param string $details     Detalles adicionales (opcional).
     */
    public static function log( $action, $object_type = '', $object_id = 0, $details = '' ) {
        global $wpdb;

        $user_id    = get_current_user_id();
        $ip         = self::get_ip();
        $created_at = current_time( 'mysql' );

        // Obtener hash del último registro para encadenar
        $prev_hash = $wpdb->get_var( "SELECT entry_hash FROM " . self::table() . " ORDER BY id DESC LIMIT 1" );
        if ( ! $prev_hash ) {
            $prev_hash = '0';
        }

        // Generar hash de esta entrada: SHA-256( prev_hash + datos del registro )
        $hash_payload = $prev_hash . '|' . $user_id . '|' . $action . '|' . $object_type . '|' . $object_id . '|' . $details . '|' . $ip . '|' . $created_at;
        $entry_hash   = hash( 'sha256', $hash_payload );

        $wpdb->insert( self::table(), array(
            'user_id'     => $user_id,
            'action'      => sanitize_text_field( $action ),
            'object_type' => sanitize_text_field( $object_type ),
            'object_id'   => absint( $object_id ),
            'details'     => sanitize_text_field( $details ),
            'ip_address'  => $ip,
            'entry_hash'  => $entry_hash,
            'created_at'  => $created_at,
        ), array( '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s' ) );
    }

    /**
     * Obtiene entradas del log con paginación.
     */
    public static function get_entries( $args = array() ) {
        global $wpdb;
        $table = self::table();

        $limit  = isset( $args['per_page'] ) ? absint( $args['per_page'] ) : 30;
        $offset = isset( $args['offset'] ) ? absint( $args['offset'] ) : 0;

        $where  = '1=1';
        $params = array();

        if ( ! empty( $args['action'] ) ) {
            $where   .= ' AND l.action = %s';
            $params[] = sanitize_text_field( $args['action'] );
        }

        if ( ! empty( $args['object_type'] ) ) {
            $where   .= ' AND l.object_type = %s';
            $params[] = sanitize_text_field( $args['object_type'] );
        }

        $params[] = $limit;
        $params[] = $offset;

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT l.*, u.display_name as user_name
             FROM {$table} l
             LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID
             WHERE {$where}
             ORDER BY l.created_at DESC
             LIMIT %d OFFSET %d",
            $params
        ) );
    }

    /**
     * Cuenta total de entradas.
     */
    public static function count( $args = array() ) {
        global $wpdb;
        $table = self::table();

        $where  = '1=1';
        $params = array();

        if ( ! empty( $args['action'] ) ) {
            $where   .= ' AND action = %s';
            $params[] = sanitize_text_field( $args['action'] );
        }

        if ( empty( $params ) ) {
            return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where}" );
        }

        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE {$where}",
            $params
        ) );
    }

    private static function get_ip() {
        if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $ips = explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) );
            return trim( $ips[0] );
        }
        return isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
    }

    /**
     * Label legible para cada acción.
     */
    public static function action_label( $action ) {
        $labels = array(
            'raffle_created'    => __( 'Rifa creada', 'rafflecore' ),
            'raffle_updated'    => __( 'Rifa actualizada', 'rafflecore' ),
            'raffle_deleted'    => __( 'Rifa eliminada', 'rafflecore' ),
            'draw_executed'     => __( 'Sorteo realizado', 'rafflecore' ),
            'purchase_completed'=> __( 'Compra completada', 'rafflecore' ),
            'reservation_expired' => __( 'Reserva expirada', 'rafflecore' ),
            'export_generated'  => __( 'Exportación generada', 'rafflecore' ),
            'coupon_created'    => __( 'Cupón creado', 'rafflecore' ),
            'coupon_used'       => __( 'Cupón utilizado', 'rafflecore' ),
            'settings_updated'  => __( 'Configuración actualizada', 'rafflecore' ),
            'webhook_fired'     => __( 'Webhook enviado', 'rafflecore' ),
        );
        return isset( $labels[ $action ] ) ? $labels[ $action ] : $action;
    }

    /**
     * Verifica la integridad de la cadena de hashes.
     *
     * Recalcula cada hash y lo compara con el almacenado.
     * Devuelve true si toda la cadena es íntegra, o un array con los IDs alterados.
     *
     * @return true|array  True si íntegro, array de IDs corruptos si no.
     */
    public static function verify_integrity() {
        global $wpdb;
        $table = self::table();

        $entries = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id ASC" );

        if ( empty( $entries ) ) {
            return true;
        }

        $prev_hash  = '0';
        $corrupted  = array();

        foreach ( $entries as $entry ) {
            $hash_payload = $prev_hash . '|' . $entry->user_id . '|' . $entry->action . '|' . $entry->object_type . '|' . $entry->object_id . '|' . $entry->details . '|' . $entry->ip_address . '|' . $entry->created_at;
            $expected     = hash( 'sha256', $hash_payload );

            if ( $entry->entry_hash !== $expected ) {
                $corrupted[] = (int) $entry->id;
            }

            $prev_hash = $entry->entry_hash;
        }

        return empty( $corrupted ) ? true : $corrupted;
    }
}
