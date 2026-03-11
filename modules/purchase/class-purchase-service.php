<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Purchase Service — Lógica de negocio para compras.
 */
class RaffleCore_Purchase_Service {

    /**
     * Valida los datos de una compra.
     *
     * @return true|WP_Error
     */
    public static function validate( $raffle, $quantity, $buyer_name, $buyer_email ) {
        if ( ! $raffle ) {
            return new WP_Error( 'not_found', 'Rifa no encontrada o no activa.' );
        }

        if ( empty( $buyer_name ) || empty( $buyer_email ) ) {
            return new WP_Error( 'missing_fields', 'Todos los campos son obligatorios.' );
        }

        if ( ! is_email( $buyer_email ) ) {
            return new WP_Error( 'invalid_email', 'Correo electrónico no válido.' );
        }

        $packages = json_decode( $raffle->packages, true );
        if ( is_array( $packages ) && count( $packages ) > 0 ) {
            $valid_qtys = array_map( function( $pkg ) {
                return is_array( $pkg ) ? ( $pkg['qty'] ?? 0 ) : (int) $pkg;
            }, $packages );
            if ( ! in_array( $quantity, $valid_qtys, true ) ) {
                return new WP_Error( 'invalid_package', 'Paquete de boletos no válido.' );
            }
        }

        $available = $raffle->total_tickets - $raffle->sold_tickets;
        if ( $quantity > $available ) {
            return new WP_Error( 'insufficient', "No hay suficientes boletos. Quedan {$available}." );
        }

        return true;
    }
}
