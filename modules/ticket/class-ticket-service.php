<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Ticket Service — Generación segura de boletos aleatorios.
 *
 * Anticolisión v2.0.0:
 *   - Siempre pool-based: calcula números disponibles, Fisher-Yates shuffle con random_int, slice.
 *   - Sin bucles de adivinanza (rejection sampling eliminado).
 *   - Rango [1, total_tickets] (no empieza en 0).
 *   - UNIQUE KEY (raffle_id, ticket_number) como última línea de defensa en BD.
 *   - FOR UPDATE lock en la rifa para prevenir race conditions.
 */
class RaffleCore_Ticket_Service {

    /**
     * Genera boletos aleatorios para una compra.
     *
     * @param int    $raffle_id   ID de la rifa.
     * @param int    $purchase_id ID de la compra.
     * @param int    $quantity    Cantidad de boletos.
     * @param string $buyer_email Email del comprador.
     * @return array|WP_Error     Array de números generados o error.
     */
    public static function generate( $raffle_id, $purchase_id, $quantity, $buyer_email ) {
        global $wpdb;

        $t_raffles = $wpdb->prefix . 'rc_raffles';
        $t_tickets = $wpdb->prefix . 'rc_tickets';

        // Lock: obtener rifa con FOR UPDATE
        $raffle = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$t_raffles} WHERE id = %d FOR UPDATE",
            $raffle_id
        ) );

        if ( ! $raffle ) {
            return new WP_Error( 'not_found', 'Rifa no encontrada.' );
        }

        $available = $raffle->total_tickets - $raffle->sold_tickets;
        if ( $quantity > $available ) {
            return new WP_Error( 'insufficient', "Solo quedan {$available} boletos disponibles." );
        }

        // Obtener números ya usados
        $used_numbers = $wpdb->get_col( $wpdb->prepare(
            "SELECT ticket_number FROM {$t_tickets} WHERE raffle_id = %d",
            $raffle_id
        ) );

        $used_set = array_flip( $used_numbers );
        $total    = (int) $raffle->total_tickets;

        // Pool-based: construir lista de disponibles [1, total]
        $pool = array();
        for ( $i = 1; $i <= $total; $i++ ) {
            if ( ! isset( $used_set[ $i ] ) ) {
                $pool[] = $i;
            }
        }

        if ( count( $pool ) < $quantity ) {
            return new WP_Error( 'insufficient', 'No hay suficientes números disponibles.' );
        }

        // Fisher-Yates shuffle con CSPRNG (random_int)
        for ( $i = count( $pool ) - 1; $i > 0; $i-- ) {
            $j = random_int( 0, $i );
            $tmp = $pool[ $i ];
            $pool[ $i ] = $pool[ $j ];
            $pool[ $j ] = $tmp;
        }

        $tickets = array_slice( $pool, 0, $quantity );
        sort( $tickets );

        // Insertar boletos
        foreach ( $tickets as $number ) {
            $wpdb->insert( $t_tickets, array(
                'raffle_id'     => $raffle_id,
                'purchase_id'   => $purchase_id,
                'ticket_number' => $number,
                'buyer_email'   => $buyer_email,
                'created_at'    => current_time( 'mysql' ),
            ), array( '%d', '%d', '%d', '%s', '%s' ) );
        }

        // Actualizar contador
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$t_raffles} SET sold_tickets = sold_tickets + %d WHERE id = %d",
            $quantity,
            $raffle_id
        ) );

        return $tickets;
    }

    /**
     * Formatea números con ceros a la izquierda según el total de la rifa.
     */
    public static function format_numbers( $tickets, $total_tickets ) {
        $digits = strlen( (string) $total_tickets );
        return array_map( function ( $n ) use ( $digits ) {
            return str_pad( $n, $digits, '0', STR_PAD_LEFT );
        }, $tickets );
    }
}
