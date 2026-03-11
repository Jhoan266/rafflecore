<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Coupon Service — Lógica de validación y aplicación de cupones.
 */
class RaffleCore_Coupon_Service {

    /**
     * Valida un código de cupón.
     *
     * @param string $code      Código del cupón.
     * @param int    $raffle_id ID de la rifa.
     * @param int    $quantity  Cantidad de boletos.
     * @return object|WP_Error  Cupón válido o error.
     */
    public static function validate( $code, $raffle_id, $quantity ) {
        $coupon = RaffleCore_Coupon_Model::find_by_code( $code );

        if ( ! $coupon ) {
            return new WP_Error( 'not_found', __( 'Cupón no válido.', 'rafflecore' ) );
        }

        if ( $coupon->status !== 'active' ) {
            return new WP_Error( 'inactive', __( 'Este cupón no está activo.', 'rafflecore' ) );
        }

        if ( $coupon->max_uses > 0 && $coupon->used_count >= $coupon->max_uses ) {
            return new WP_Error( 'exhausted', __( 'Este cupón ha alcanzado su límite de uso.', 'rafflecore' ) );
        }

        if ( $coupon->expires_at && strtotime( $coupon->expires_at ) < time() ) {
            return new WP_Error( 'expired', __( 'Este cupón ha expirado.', 'rafflecore' ) );
        }

        if ( $coupon->raffle_id && (int) $coupon->raffle_id !== (int) $raffle_id ) {
            return new WP_Error( 'wrong_raffle', __( 'Este cupón no aplica a esta rifa.', 'rafflecore' ) );
        }

        if ( $quantity < (int) $coupon->min_tickets ) {
            return new WP_Error( 'min_tickets', sprintf(
                __( 'Este cupón requiere mínimo %d boletos.', 'rafflecore' ),
                $coupon->min_tickets
            ) );
        }

        return $coupon;
    }

    /**
     * Calcula el precio con descuento.
     *
     * @param float  $original_price Precio original.
     * @param object $coupon         Objeto cupón.
     * @return float                 Precio final.
     */
    public static function apply_discount( $original_price, $coupon ) {
        if ( $coupon->discount_type === 'percentage' ) {
            $discount = $original_price * ( $coupon->discount_value / 100 );
        } else {
            $discount = $coupon->discount_value;
        }

        return max( 0, $original_price - $discount );
    }

    /**
     * AJAX handler para validar cupón desde el frontend.
     */
    public static function ajax_validate_coupon() {
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'rc_public_nonce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Error de seguridad.', 'rafflecore' ) ) );
        }

        $code      = isset( $_POST['coupon_code'] ) ? sanitize_text_field( wp_unslash( $_POST['coupon_code'] ) ) : '';
        $raffle_id = isset( $_POST['raffle_id'] ) ? absint( $_POST['raffle_id'] ) : 0;
        $quantity  = isset( $_POST['quantity'] ) ? absint( $_POST['quantity'] ) : 0;
        $price     = isset( $_POST['original_price'] ) ? floatval( $_POST['original_price'] ) : 0;

        $coupon = self::validate( $code, $raffle_id, $quantity );

        if ( is_wp_error( $coupon ) ) {
            wp_send_json_error( array( 'message' => $coupon->get_error_message() ) );
        }

        $final_price = self::apply_discount( $price, $coupon );

        wp_send_json_success( array(
            'coupon_id'      => $coupon->id,
            'discount_type'  => $coupon->discount_type,
            'discount_value' => $coupon->discount_value,
            'original_price' => $price,
            'final_price'    => $final_price,
            'savings'        => $price - $final_price,
        ) );
    }

    /**
     * Prepara datos del formulario admin.
     */
    public static function prepare_data( $post ) {
        return array(
            'code'           => strtoupper( sanitize_text_field( wp_unslash( $post['coupon_code'] ?? '' ) ) ),
            'discount_type'  => sanitize_text_field( wp_unslash( $post['discount_type'] ?? 'percentage' ) ),
            'discount_value' => floatval( $post['discount_value'] ?? 0 ),
            'max_uses'       => absint( $post['max_uses'] ?? 0 ),
            'raffle_id'      => ! empty( $post['raffle_id'] ) ? absint( $post['raffle_id'] ) : null,
            'min_tickets'    => max( 1, absint( $post['min_tickets'] ?? 1 ) ),
            'expires_at'     => ! empty( $post['expires_at'] ) ? sanitize_text_field( wp_unslash( $post['expires_at'] ) ) : null,
            'status'         => sanitize_text_field( wp_unslash( $post['coupon_status'] ?? 'active' ) ),
        );
    }
}
