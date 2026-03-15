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
            'title'               => '',
            'description'         => '',
            'lottery'             => '',
            'prize_value'         => 0,
            'prize_image'         => '',
            'total_tickets'       => 0,
            'ticket_digits'       => 2,
            'sold_tickets'        => 0,
            'ticket_price'        => 0,
            'packages'            => '[]',
            'lucky_numbers'       => '[]',
            'prize_gallery'       => '[]',
            'draw_date'           => null,
            'status'              => 'active',
            'type'                => 'quantity',
            'max_number'          => 0,
            'countdown_threshold' => 0,
            'font_family'         => '',
            'custom_font_url'     => '',
            'color_palette'       => '',
            'min_custom_qty'      => 0,
            'lucky_numbers_text'  => '',
            'created_at'          => current_time( 'mysql' ),
        );

        $data = wp_parse_args( $data, $defaults );

        // Ensure we only have keys that exist in defaults to avoid DB errors
        $data = array_intersect_key( $data, $defaults );

        // Define formats explicitly for each key to avoid mismatch
        $formats = array(
            'title'               => '%s',
            'description'         => '%s',
            'lottery'             => '%s',
            'prize_value'         => '%f',
            'prize_image'         => '%s',
            'total_tickets'       => '%d',
            'ticket_digits'       => '%d',
            'sold_tickets'        => '%d',
            'ticket_price'        => '%f',
            'packages'            => '%s',
            'lucky_numbers'       => '%s',
            'prize_gallery'       => '%s',
            'draw_date'           => '%s',
            'status'              => '%s',
            'type'                => '%s',
            'max_number'          => '%d',
            'countdown_threshold' => '%d',
            'font_family'         => '%s',
            'custom_font_url'     => '%s',
            'color_palette'       => '%s',
            'min_custom_qty'      => '%d',
            'lucky_numbers_text'  => '%s',
            'created_at'          => '%s',
        );

        // Sort data and formats to be in the same order
        $final_data = array();
        $final_formats = array();
        foreach ( $formats as $key => $fmt ) {
            if ( isset( $data[ $key ] ) ) {
                $final_data[ $key ] = $data[ $key ];
                $final_formats[]    = $fmt;
            }
        }

        $wpdb->insert( self::table(), $final_data, $final_formats );

        return $wpdb->insert_id ?: new WP_Error( 'db_error', __( 'Error al crear la rifa en la base de datos.', 'rafflecore' ) );
    }

    public static function update( $id, $data ) {
        global $wpdb;

        // Define expected formats for all possible columns
        $all_formats = array(
            'title'               => '%s',
            'description'         => '%s',
            'lottery'             => '%s',
            'prize_value'         => '%f',
            'prize_image'         => '%s',
            'total_tickets'       => '%d',
            'ticket_digits'       => '%d',
            'sold_tickets'        => '%d',
            'ticket_price'        => '%f',
            'packages'            => '%s',
            'lucky_numbers'       => '%s',
            'prize_gallery'       => '%s',
            'draw_date'           => '%s',
            'status'              => '%s',
            'type'                => '%s',
            'max_number'          => '%d',
            'countdown_threshold' => '%d',
            'lucky_numbers_text'  => '%s',
            'winner_ticket_id'    => '%d',
            'wc_product_id'       => '%d',
            'font_family'         => '%s',
            'custom_font_url'     => '%s',
            'color_palette'       => '%s',
            'min_custom_qty'      => '%d',
            'created_at'          => '%s',
        );

        $final_data = array();
        $final_formats = array();

        foreach ( $data as $key => $value ) {
            if ( isset( $all_formats[ $key ] ) ) {
                $final_data[ $key ] = $value;
                $final_formats[]    = $all_formats[ $key ];
            }
        }

        if ( empty( $final_data ) ) {
            return 0;
        }

        $result = $wpdb->update( 
            self::table(), 
            $final_data, 
            array( 'id' => $id ), 
            $final_formats, 
            array( '%d' ) 
        );

        return ( false === $result ) ? new WP_Error( 'db_error', $wpdb->last_error ) : $result;
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
