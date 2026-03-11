<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Reservation Service — Reserva pre-pago de boletos para prevenir race conditions.
 *
 * Problema que resuelve:
 *   Dos usuarios solicitan los últimos 5 boletos al mismo tiempo.
 *   Sin reserva, ambos pasan la validación y uno de los pagos genera tickets que no existen.
 *
 * Solución:
 *   1. Al crear el pedido, se reservan N boletos (incrementa sold_tickets con FOR UPDATE).
 *   2. Si el pago no se completa en TTL minutos, un cron libera la reserva (decrementa sold_tickets).
 *   3. Al completar el pago se generan los tickets sobre la reserva ya hecha.
 *   4. Si el pedido se cancela, se libera la reserva inmediatamente.
 *
 * Las reservas se almacenan como order meta (_rc_reserved = 'yes') y en la tabla rc_purchases (status = 'reserved').
 */
class RaffleCore_Reservation_Service {

    /** Minutos antes de que una reserva expire. */
    const RESERVATION_TTL = 30;

    /**
     * Reserva boletos para una compra pre-pago.
     *
     * Usa SELECT ... FOR UPDATE para evitar que dos reservas simultáneas
     * excedan el total de boletos.
     *
     * @param int $raffle_id ID de la rifa.
     * @param int $quantity  Cantidad de boletos a reservar.
     * @return true|WP_Error
     */
    public static function reserve( $raffle_id, $quantity ) {
        global $wpdb;

        $t_raffles = $wpdb->prefix . 'rc_raffles';

        $wpdb->query( 'START TRANSACTION' );

        // Lock de la fila con FOR UPDATE
        $raffle = $wpdb->get_row( $wpdb->prepare(
            "SELECT total_tickets, sold_tickets, status FROM {$t_raffles} WHERE id = %d FOR UPDATE",
            $raffle_id
        ) );

        if ( ! $raffle ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'not_found', 'Rifa no encontrada.' );
        }

        if ( $raffle->status !== 'active' ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'inactive', 'La rifa no está activa.' );
        }

        $available = $raffle->total_tickets - $raffle->sold_tickets;
        if ( $quantity > $available ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'insufficient', "Solo quedan {$available} boletos disponibles." );
        }

        // Incrementar sold_tickets como reserva
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$t_raffles} SET sold_tickets = sold_tickets + %d WHERE id = %d",
            $quantity,
            $raffle_id
        ) );

        $wpdb->query( 'COMMIT' );

        return true;
    }

    /**
     * Libera una reserva de boletos (decrementar sold_tickets).
     *
     * Se llama cuando un pedido se cancela o expira.
     *
     * @param int $raffle_id ID de la rifa.
     * @param int $quantity  Cantidad de boletos a liberar.
     * @return true|WP_Error
     */
    public static function release( $raffle_id, $quantity ) {
        global $wpdb;

        $t_raffles = $wpdb->prefix . 'rc_raffles';

        $wpdb->query( 'START TRANSACTION' );

        $raffle = $wpdb->get_row( $wpdb->prepare(
            "SELECT sold_tickets FROM {$t_raffles} WHERE id = %d FOR UPDATE",
            $raffle_id
        ) );

        if ( ! $raffle ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'not_found', 'Rifa no encontrada.' );
        }

        // No permitir que sold_tickets baje de 0
        $new_sold = max( 0, (int) $raffle->sold_tickets - $quantity );

        $wpdb->update(
            $t_raffles,
            array( 'sold_tickets' => $new_sold ),
            array( 'id' => $raffle_id ),
            array( '%d' ),
            array( '%d' )
        );

        $wpdb->query( 'COMMIT' );

        return true;
    }

    /**
     * Libera reservas expiradas.
     *
     * Busca compras con status='reserved' que excedieron el TTL
     * y libera sus boletos.
     *
     * Se ejecuta en un WP-Cron programado.
     */
    public static function cleanup_expired() {
        global $wpdb;

        $t_purchases = $wpdb->prefix . 'rc_purchases';
        $cutoff      = gmdate( 'Y-m-d H:i:s', time() - ( self::RESERVATION_TTL * 60 ) );

        $expired = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, raffle_id, quantity FROM {$t_purchases}
             WHERE status = 'reserved' AND purchase_date < %s",
            $cutoff
        ) );

        if ( empty( $expired ) ) {
            return 0;
        }

        $count = 0;
        foreach ( $expired as $purchase ) {
            $released = self::release( (int) $purchase->raffle_id, (int) $purchase->quantity );
            if ( ! is_wp_error( $released ) ) {
                $wpdb->update(
                    $t_purchases,
                    array( 'status' => 'expired' ),
                    array( 'id' => $purchase->id ),
                    array( '%s' ),
                    array( '%d' )
                );
                $count++;
            }
        }

        return $count;
    }

    /**
     * Programa el cron de limpieza.
     */
    public static function schedule_cleanup() {
        if ( ! wp_next_scheduled( 'rc_cleanup_reservations' ) ) {
            wp_schedule_event( time(), 'rc_every_15min', 'rc_cleanup_reservations' );
        }
    }

    /**
     * Cancela el cron de limpieza.
     */
    public static function unschedule_cleanup() {
        $timestamp = wp_next_scheduled( 'rc_cleanup_reservations' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'rc_cleanup_reservations' );
        }
    }

    /**
     * Agrega intervalo personalizado de 15 minutos al cron de WordPress.
     */
    public static function add_cron_interval( $schedules ) {
        $schedules['rc_every_15min'] = array(
            'interval' => 900,
            'display'  => 'Cada 15 minutos (RaffleCore)',
        );
        return $schedules;
    }
}
