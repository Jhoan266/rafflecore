<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Ticket Model — Acceso a la tabla rc_tickets.
 */
class RaffleCore_Ticket_Model {

    private static function table() {
        global $wpdb;
        return $wpdb->prefix . 'rc_tickets';
    }

    public static function get_by_purchase( $purchase_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM " . self::table() . " WHERE purchase_id = %d ORDER BY ticket_number ASC",
            $purchase_id
        ) );
    }

    public static function get_by_raffle( $raffle_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM " . self::table() . " WHERE raffle_id = %d ORDER BY ticket_number ASC",
            $raffle_id
        ) );
    }

    public static function count_by_raffle( $raffle_id ) {
        global $wpdb;
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM " . self::table() . " WHERE raffle_id = %d",
            $raffle_id
        ) );
    }

    public static function get_used_numbers( $raffle_id ) {
        global $wpdb;
        return $wpdb->get_col( $wpdb->prepare(
            "SELECT ticket_number FROM " . self::table() . " WHERE raffle_id = %d",
            $raffle_id
        ) );
    }

    public static function insert( $data ) {
        global $wpdb;
        return $wpdb->insert( self::table(), $data, array( '%d', '%d', '%d', '%s', '%s' ) );
    }

    public static function find( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM " . self::table() . " WHERE id = %d",
            $id
        ) );
    }

    public static function find_by_number( $raffle_id, $ticket_number ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT t.*, p.buyer_name, p.buyer_email as purchase_email
             FROM " . self::table() . " t
             JOIN {$wpdb->prefix}rc_purchases p ON t.purchase_id = p.id
             WHERE t.raffle_id = %d AND t.ticket_number = %d",
            $raffle_id,
            $ticket_number
        ) );
    }
}
