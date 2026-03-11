<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * WC Product Manager — Gestiona productos virtuales ocultos vinculados a rifas.
 *
 * Cada rifa tiene un producto WooCommerce virtual+oculto que:
 *   - Sirve como line item real en los pedidos (mejor contabilidad WC).
 *   - Se almacena en rc_raffles.wc_product_id.
 *   - Se oculta del catálogo público (visibility = hidden, catalog_visibility = hidden).
 *   - Precio dinámico: se inyecta via metadata del carrito según el paquete elegido.
 */
class RaffleCore_WC_Product_Manager {

    /**
     * Crea o recupera el producto WC vinculado a una rifa.
     *
     * @param int    $raffle_id ID de la rifa.
     * @param string $title     Título de la rifa.
     * @param float  $price     Precio base (ticket_price).
     * @return int|WP_Error     Product ID o error.
     */
    public static function ensure_product( $raffle_id, $title, $price = 0 ) {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return new WP_Error( 'wc_unavailable', __( 'WooCommerce no está disponible.', 'rafflecore' ) );
        }

        // Verificar si ya existe
        $existing_id = self::get_product_id( $raffle_id );
        if ( $existing_id && wc_get_product( $existing_id ) ) {
            return $existing_id;
        }

        // Crear producto virtual oculto
        $product = new WC_Product_Simple();
        $product->set_name( sprintf( __( 'Boletos — %s', 'rafflecore' ), $title ) );
        $product->set_status( 'publish' );
        $product->set_catalog_visibility( 'hidden' );
        $product->set_virtual( true );
        $product->set_sold_individually( false );
        $product->set_regular_price( (string) $price );
        $product->set_manage_stock( false );
        $product->update_meta_data( '_rc_raffle_id', $raffle_id );
        $product->update_meta_data( '_rc_is_raffle_product', 'yes' );

        $product_id = $product->save();

        if ( ! $product_id ) {
            return new WP_Error( 'wc_product_error', __( 'Error al crear el producto WC.', 'rafflecore' ) );
        }

        // Vincular en rc_raffles
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'rc_raffles',
            array( 'wc_product_id' => $product_id ),
            array( 'id' => $raffle_id ),
            array( '%d' ),
            array( '%d' )
        );

        return $product_id;
    }

    /**
     * Obtiene el product_id vinculado a una rifa.
     */
    public static function get_product_id( $raffle_id ) {
        global $wpdb;
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT wc_product_id FROM {$wpdb->prefix}rc_raffles WHERE id = %d",
            $raffle_id
        ) );
    }

    /**
     * Actualiza el producto WC cuando se edita la rifa.
     */
    public static function sync_product( $raffle_id, $title, $price ) {
        $product_id = self::get_product_id( $raffle_id );
        if ( ! $product_id ) {
            return;
        }

        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            return;
        }

        $product->set_name( sprintf( __( 'Boletos — %s', 'rafflecore' ), $title ) );
        $product->set_regular_price( (string) $price );
        $product->save();
    }

    /**
     * Elimina el producto WC al borrar una rifa.
     */
    public static function delete_product( $raffle_id ) {
        $product_id = self::get_product_id( $raffle_id );
        if ( ! $product_id ) {
            return;
        }

        $product = wc_get_product( $product_id );
        if ( $product ) {
            $product->delete( true );
        }
    }

    /**
     * Agrega el producto de rifa al carrito WC con metadata del paquete.
     *
     * @param int   $raffle_id ID de la rifa.
     * @param int   $quantity  Cantidad de boletos.
     * @param float $total     Precio total del paquete.
     * @param array $buyer     Datos del comprador: name, email, phone.
     * @return string|WP_Error Cart item key o error.
     */
    public static function add_to_cart( $raffle_id, $quantity, $total, $buyer = array() ) {
        if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
            return new WP_Error( 'wc_unavailable', __( 'WooCommerce no está disponible.', 'rafflecore' ) );
        }

        $product_id = self::get_product_id( $raffle_id );
        if ( ! $product_id ) {
            return new WP_Error( 'no_product', __( 'No hay producto WC vinculado a esta rifa.', 'rafflecore' ) );
        }

        // Limpiar carrito (una compra de rifa a la vez)
        WC()->cart->empty_cart();

        $cart_item_data = array(
            '_rc_raffle_id'   => $raffle_id,
            '_rc_ticket_qty'  => $quantity,
            '_rc_total_price' => $total,
            '_rc_buyer_name'  => $buyer['name'] ?? '',
            '_rc_buyer_email' => $buyer['email'] ?? '',
            '_rc_buyer_phone' => $buyer['phone'] ?? '',
        );

        $key = WC()->cart->add_to_cart( $product_id, 1, 0, array(), $cart_item_data );

        return $key ?: new WP_Error( 'cart_error', __( 'Error al agregar al carrito.', 'rafflecore' ) );
    }

    /**
     * Hook: Sobreescribir precio del ítem en carrito con el precio del paquete.
     * Se registra en woocommerce_before_calculate_totals.
     */
    public static function override_cart_price( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            return;
        }

        foreach ( $cart->get_cart() as $item ) {
            if ( ! empty( $item['_rc_total_price'] ) ) {
                $item['data']->set_price( floatval( $item['_rc_total_price'] ) );
            }
        }
    }

    /**
     * Hook: Mostrar datos de rifa en el carrito/checkout.
     * Se registra en woocommerce_get_item_data.
     */
    public static function display_cart_data( $item_data, $cart_item ) {
        if ( ! empty( $cart_item['_rc_ticket_qty'] ) ) {
            $item_data[] = array(
                'key'   => __( 'Boletos', 'rafflecore' ),
                'value' => absint( $cart_item['_rc_ticket_qty'] ),
            );
        }
        return $item_data;
    }

    /**
     * Hook: Guardar metadata del carrito en la orden.
     * Se registra en woocommerce_checkout_create_order_line_item.
     */
    public static function save_order_item_meta( $item, $cart_item_key, $values, $order ) {
        $meta_keys = array( '_rc_raffle_id', '_rc_ticket_qty', '_rc_total_price', '_rc_buyer_name', '_rc_buyer_email', '_rc_buyer_phone' );
        foreach ( $meta_keys as $key ) {
            if ( ! empty( $values[ $key ] ) ) {
                $item->add_meta_data( $key, $values[ $key ] );
            }
        }
    }
}
