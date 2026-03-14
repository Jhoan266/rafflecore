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
    public static function generate( $raffle_id, $purchase_id, $quantity, $buyer_email, $specific_numbers = array() ) {
        global $wpdb;

        $t_raffles = $wpdb->prefix . 'rc_tickets'; // Error en código original, corregido abajo
        $t_raffles = $wpdb->prefix . 'rc_raffles';
        $t_tickets = $wpdb->prefix . 'rc_tickets';

        // Lock: obtener rifa con FOR UPDATE
        $raffle = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$t_raffles} WHERE id = %d FOR UPDATE",
            $raffle_id
        ) );

        if ( ! $raffle ) {
            return new WP_Error( 'not_found', __( 'Rifa no encontrada.', 'rafflecore' ) );
        }

        // Determinar rango válido según ticket_digits
        $digits = isset($raffle->ticket_digits) ? (int)$raffle->ticket_digits : (strlen((string)$raffle->total_tickets));
        $min_ticket = 1;
        $max_ticket = (int)str_repeat('9', $digits);

        $available = $raffle->total_tickets - $raffle->sold_tickets;
        if ( $quantity > $available ) {
            return new WP_Error( 'insufficient', sprintf( __( 'Solo quedan %d boletos disponibles.', 'rafflecore' ), $available ) );
        }

        // Obtener números ya usados
        $used_numbers = $wpdb->get_col( $wpdb->prepare(
            "SELECT ticket_number FROM {$t_tickets} WHERE raffle_id = %d",
            $raffle_id
        ) );

        $used_set = array_flip( $used_numbers );
        $tickets  = array();

        if ( ! empty( $specific_numbers ) ) {
            // Verificar que todos los números específicos sean válidos y estén disponibles
            foreach ( $specific_numbers as $num ) {
                $num = (int) $num;
                if ( $num < $min_ticket || $num > $max_ticket ) {
                    return new WP_Error( 'invalid_range', sprintf( __( 'Número %d fuera de rango para %d dígitos.', 'rafflecore' ), $num, $digits ) );
                }
                if ( isset( $used_set[ $num ] ) ) {
                    return new WP_Error( 'already_sold', sprintf( __( 'El número %d ya ha sido vendido.', 'rafflecore' ), $num ) );
                }
                $tickets[] = $num;
            }
            // Validar que la cantidad coincida
            if ( count( $tickets ) !== (int) $quantity ) {
                 return new WP_Error( 'mismatch', __( 'La cantidad de números no coincide con la compra.', 'rafflecore' ) );
            }
        } else {
            // Pool-based: construir lista de disponibles [min_ticket, max_ticket]
            $pool = array();
            for ( $i = $min_ticket; $i <= $max_ticket; $i++ ) {
                if ( ! isset( $used_set[ $i ] ) ) {
                    $pool[] = $i;
                }
            }
            if ( count( $pool ) < $quantity ) {
                return new WP_Error( 'insufficient', __( 'No hay suficientes números disponibles.', 'rafflecore' ) );
            }
            // Fisher-Yates shuffle con CSPRNG (random_int)
            for ( $i = count( $pool ) - 1; $i > 0; $i-- ) {
                $j = random_int( 0, $i );
                $tmp = $pool[ $i ];
                $pool[ $i ] = $pool[ $j ];
                $pool[ $j ] = $tmp;
            }
            $tickets = array_slice( $pool, 0, $quantity );
        }

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
        // Permitir pasar un array ['digits'=>N] como segundo parámetro para máxima compatibilidad
        $digits = 3;
        if (is_array($total_tickets) && isset($total_tickets['digits'])) {
            $digits = (int)$total_tickets['digits'];
        } elseif (is_object($total_tickets) && isset($total_tickets->ticket_digits)) {
            $digits = (int)$total_tickets->ticket_digits;
        } elseif (is_numeric($total_tickets)) {
            // User requested:
            // 3 digits (001-999) if max is 999
            // 4 digits (0001-9999) if max is 9999
            if ($total_tickets >= 1000) $digits = 4;
            elseif ($total_tickets >= 100) $digits = 3;
            else $digits = 2;
            
            if ($total_tickets > 9999) $digits = 5;
        }
        return array_map( function ( $n ) use ( $digits ) {
            return str_pad( $n, $digits, '0', STR_PAD_LEFT );
        }, $tickets );
    }
}
