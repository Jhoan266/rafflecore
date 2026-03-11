<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Coupon Model — Acceso a la tabla rc_coupons.
 */
class RaffleCore_Coupon_Model {

    private static function table() {
        global $wpdb;
        return $wpdb->prefix . 'rc_coupons';
    }

    public static function find( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . self::table() . " WHERE id = %d",
            $id
        ) );
    }

    public static function find_by_code( $code ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . self::table() . " WHERE code = %s AND status = 'active'",
            strtoupper( $code )
        ) );
    }

    public static function create( $data ) {
        global $wpdb;

        $defaults = array(
            'code'           => '',
            'discount_type'  => 'percentage',
            'discount_value' => 0,
            'max_uses'       => 0,
            'used_count'     => 0,
            'raffle_id'      => null,
            'min_tickets'    => 1,
            'expires_at'     => null,
            'status'         => 'active',
            'created_at'     => current_time( 'mysql' ),
        );

        $data = wp_parse_args( $data, $defaults );
        $data['code'] = strtoupper( $data['code'] );

        $wpdb->insert( self::table(), $data, array(
            '%s', '%s', '%f', '%d', '%d', '%d', '%d', '%s', '%s', '%s',
        ) );

        return $wpdb->insert_id ?: new WP_Error( 'db_error', __( 'Error al crear el cupón.', 'rafflecore' ) );
    }

    public static function increment_usage( $id ) {
        global $wpdb;
        $wpdb->query( $wpdb->prepare(
            "UPDATE " . self::table() . " SET used_count = used_count + 1 WHERE id = %d",
            $id
        ) );
    }

    public static function get_all( $args = array() ) {
        global $wpdb;
        $table = self::table();

        $limit  = isset( $args['per_page'] ) ? absint( $args['per_page'] ) : 20;
        $offset = isset( $args['offset'] ) ? absint( $args['offset'] ) : 0;

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT c.*, r.title as raffle_title
             FROM {$table} c
             LEFT JOIN {$wpdb->prefix}rc_raffles r ON c.raffle_id = r.id
             ORDER BY c.created_at DESC
             LIMIT %d OFFSET %d",
            $limit, $offset
        ) );
    }

    public static function count() {
        global $wpdb;
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . self::table() );
    }

    public static function delete( $id ) {
        global $wpdb;
        return $wpdb->delete( self::table(), array( 'id' => $id ), array( '%d' ) );
    }

    public static function update( $id, $data ) {
        global $wpdb;

        $formats = array();
        foreach ( $data as $key => $value ) {
            if ( in_array( $key, array( 'discount_value' ), true ) ) {
                $formats[] = '%f';
            } elseif ( in_array( $key, array( 'max_uses', 'used_count', 'raffle_id', 'min_tickets' ), true ) ) {
                $formats[] = '%d';
            } else {
                $formats[] = '%s';
            }
        }

        return $wpdb->update( self::table(), $data, array( 'id' => $id ), $formats, array( '%d' ) );
    }
}
