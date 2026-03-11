<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Purchase Model — Acceso a la tabla rc_purchases.
 */
class RaffleCore_Purchase_Model {

    private static function table() {
        global $wpdb;
        return $wpdb->prefix . 'rc_purchases';
    }

    public static function find( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . self::table() . " WHERE id = %d",
            $id
        ) );
    }

    public static function create( $data ) {
        global $wpdb;

        $defaults = array(
            'raffle_id'      => 0,
            'buyer_name'     => '',
            'buyer_email'    => '',
            'quantity'       => 0,
            'amount_paid'    => 0,
            'order_id'       => null,
            'status'         => 'pending',
            'purchase_date'  => current_time( 'mysql' ),
        );

        $data = wp_parse_args( $data, $defaults );

        $wpdb->insert( self::table(), $data, array(
            '%d', '%s', '%s', '%d', '%f', '%s', '%d', '%s',
        ) );

        return $wpdb->insert_id ?: new WP_Error( 'db_error', 'Error al crear la compra.' );
    }

    public static function update( $id, $data ) {
        global $wpdb;

        $formats = array();
        foreach ( $data as $key => $value ) {
            if ( in_array( $key, array( 'amount_paid' ), true ) ) {
                $formats[] = '%f';
            } elseif ( in_array( $key, array( 'raffle_id', 'quantity', 'order_id' ), true ) ) {
                $formats[] = '%d';
            } else {
                $formats[] = '%s';
            }
        }

        return $wpdb->update( self::table(), $data, array( 'id' => $id ), $formats, array( '%d' ) );
    }

    public static function get_by_raffle( $raffle_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM " . self::table() . " WHERE raffle_id = %d ORDER BY purchase_date DESC",
            $raffle_id
        ) );
    }

    public static function get_all_buyers( $args = array() ) {
        global $wpdb;
        $p = $wpdb->prefix;

        $limit  = isset( $args['per_page'] ) ? absint( $args['per_page'] ) : 20;
        $offset = isset( $args['offset'] ) ? absint( $args['offset'] ) : 0;

        $where  = "p.status = 'completed'";
        $params = array();

        if ( ! empty( $args['search'] ) ) {
            $like    = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
            $where  .= ' AND (p.buyer_name LIKE %s OR p.buyer_email LIKE %s)';
            $params[] = $like;
            $params[] = $like;
        }

        if ( ! empty( $args['raffle_id'] ) ) {
            $where   .= ' AND p.raffle_id = %d';
            $params[] = absint( $args['raffle_id'] );
        }

        $params[] = $limit;
        $params[] = $offset;

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT p.*, r.title as raffle_title,
                    (SELECT COUNT(*) FROM {$p}rc_tickets t WHERE t.purchase_id = p.id) as ticket_count
             FROM {$p}rc_purchases p
             JOIN {$p}rc_raffles r ON p.raffle_id = r.id
             WHERE {$where}
             ORDER BY p.purchase_date DESC
             LIMIT %d OFFSET %d",
            $params
        ) );
    }

    public static function count_buyers( $args = array() ) {
        global $wpdb;
        $p = $wpdb->prefix;

        $where  = "status = 'completed'";
        $params = array();

        if ( ! empty( $args['search'] ) ) {
            $like    = '%' . $wpdb->esc_like( sanitize_text_field( $args['search'] ) ) . '%';
            $where  .= ' AND (buyer_name LIKE %s OR buyer_email LIKE %s)';
            $params[] = $like;
            $params[] = $like;
        }

        if ( ! empty( $args['raffle_id'] ) ) {
            $where   .= ' AND raffle_id = %d';
            $params[] = absint( $args['raffle_id'] );
        }

        if ( ! empty( $params ) ) {
            return (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$p}rc_purchases WHERE {$where}",
                $params
            ) );
        }

        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}rc_purchases WHERE {$where}" );
    }
}
