<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Raffle Model — Acceso directo a la tabla rc_raffles.
 */
class RaffleCore_Raffle_Model {

    private static function table() {
        global $wpdb;
        return $wpdb->prefix . 'rc_raffles';
    }

    public static function find( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . self::table() . " WHERE id = %d",
            $id
        ) );
    }

    public static function get_by_status( $status ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM " . self::table() . " WHERE status = %s ORDER BY created_at DESC",
            $status
        ) );
    }

    public static function get_all( $args = array() ) {
        global $wpdb;
        $table = self::table();

        $where  = "status != 'deleted'";
        $params = array();

        if ( ! empty( $args['status'] ) ) {
            $where   .= ' AND status = %s';
            $params[] = $args['status'];
        }

        $order  = isset( $args['orderby'] ) ? sanitize_sql_orderby( $args['orderby'] ) : 'created_at DESC';
        $limit  = isset( $args['per_page'] ) ? absint( $args['per_page'] ) : 20;
        $offset = isset( $args['offset'] ) ? absint( $args['offset'] ) : 0;

        $sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY {$order} LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;

        return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
    }

    public static function count( $status = '' ) {
        global $wpdb;
        if ( $status ) {
            return (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM " . self::table() . " WHERE status = %s",
                $status
            ) );
        }
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM " . self::table() . " WHERE status != 'deleted'" );
    }

    public static function create( $data ) {
        global $wpdb;

        $defaults = array(
            'title'         => '',
            'description'   => '',
            'prize_value'   => 0,
            'prize_image'   => '',
            'total_tickets' => 0,
            'sold_tickets'  => 0,
            'ticket_price'  => 0,
            'packages'      => '[]',
            'draw_date'     => null,
            'status'        => 'active',
            'font_family'   => '',
            'custom_font_url' => '',
            'created_at'    => current_time( 'mysql' ),
        );

        $data = wp_parse_args( $data, $defaults );

        $wpdb->insert( self::table(), $data, array(
            '%s', '%s', '%f', '%s', '%d', '%d', '%f', '%s', '%s', '%s', '%s', '%s', '%s',
        ) );

        return $wpdb->insert_id ?: new WP_Error( 'db_error', __( 'Error al crear la rifa.', 'rafflecore' ) );
    }

    public static function update( $id, $data ) {
        global $wpdb;

        $formats = array();
        foreach ( $data as $key => $value ) {
            if ( in_array( $key, array( 'prize_value', 'ticket_price' ), true ) ) {
                $formats[] = '%f';
            } elseif ( in_array( $key, array( 'total_tickets', 'sold_tickets', 'winner_ticket_id' ), true ) ) {
                $formats[] = '%d';
            } else {
                $formats[] = '%s';
            }
        }

        return $wpdb->update( self::table(), $data, array( 'id' => $id ), $formats, array( '%d' ) );
    }

    public static function delete( $id ) {
        global $wpdb;
        return $wpdb->update(
            self::table(),
            array( 'status' => 'deleted' ),
            array( 'id' => $id ),
            array( '%s' ),
            array( '%d' )
        );
    }

    public static function find_active( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . self::table() . " WHERE id = %d AND status = 'active'",
            $id
        ) );
    }
}
