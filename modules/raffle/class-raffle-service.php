<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Raffle Service — Lógica de negocio para rifas.
 */
class RaffleCore_Raffle_Service {

    /**
     * Valida y prepara datos de formulario para crear/editar una rifa.
     */
    public static function prepare_data( $post ) {
        $data = array(
            'title'         => sanitize_text_field( wp_unslash( $post['title'] ?? '' ) ),
            'description'   => sanitize_textarea_field( wp_unslash( $post['description'] ?? '' ) ),
            'prize_value'   => floatval( $post['prize_value'] ?? 0 ),
            'prize_image'   => esc_url_raw( wp_unslash( $post['prize_image'] ?? '' ) ),
            'total_tickets' => absint( $post['total_tickets'] ?? 0 ),
            'ticket_price'  => floatval( $post['ticket_price'] ?? 0 ),
            'draw_date'     => sanitize_text_field( wp_unslash( $post['draw_date'] ?? '' ) ),
            'status'        => sanitize_text_field( wp_unslash( $post['status'] ?? 'active' ) ),
        );

        // Packages: "5:20000, 10:35000" → [{"qty":5,"price":20000}, ...]
        $packages_raw = sanitize_text_field( wp_unslash( $post['packages'] ?? '' ) );
        $packages     = array();
        if ( ! empty( $packages_raw ) ) {
            $parts = array_map( 'trim', explode( ',', $packages_raw ) );
            foreach ( $parts as $part ) {
                if ( strpos( $part, ':' ) !== false ) {
                    list( $qty, $price ) = explode( ':', $part, 2 );
                    $qty   = absint( $qty );
                    $price = absint( $price );
                    if ( $qty > 0 && $price > 0 ) {
                        $packages[] = array( 'qty' => $qty, 'price' => $price );
                    }
                }
            }
        }
        $data['packages'] = wp_json_encode( $packages );

        return $data;
    }

    /**
     * Calcula el progreso de venta.
     */
    public static function get_progress( $raffle ) {
        if ( $raffle->total_tickets <= 0 ) {
            return 0;
        }
        return min( 100, round( ( $raffle->sold_tickets / $raffle->total_tickets ) * 100 ) );
    }

    /**
     * Obtiene paquetes válidos (que caben en los boletos restantes).
     */
    public static function get_available_packages( $raffle ) {
        $packages  = json_decode( $raffle->packages, true ) ?: array();
        $remaining = $raffle->total_tickets - $raffle->sold_tickets;
        return array_values( array_filter( $packages, function ( $pkg ) use ( $remaining ) {
            $qty = is_array( $pkg ) ? ( $pkg['qty'] ?? 0 ) : (int) $pkg;
            return $qty > 0 && $qty <= $remaining;
        } ) );
    }
}
