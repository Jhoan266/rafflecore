<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * RaffleCore Export — Exportación de datos a CSV.
 */
class RaffleCore_Export {

    /**
     * AJAX: Exportar compradores a CSV.
     */
    public static function ajax_export_buyers() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'No autorizado.', 'rafflecore' ) );
        }

        check_ajax_referer( 'rc_admin_nonce', 'nonce' );

        global $wpdb;
        $p = $wpdb->prefix;

        $raffle_id = isset( $_GET['raffle_id'] ) ? absint( $_GET['raffle_id'] ) : 0;

        $where  = "p.status = 'completed'";
        $params = array();

        if ( $raffle_id ) {
            $where   .= ' AND p.raffle_id = %d';
            $params[] = $raffle_id;
        }

        $sql = "SELECT p.buyer_name, p.buyer_email, p.quantity, p.amount_paid,
                       p.status, p.purchase_date, r.title as raffle_title
                FROM {$p}rc_purchases p
                JOIN {$p}rc_raffles r ON p.raffle_id = r.id
                WHERE {$where}
                ORDER BY p.purchase_date DESC";

        $rows = empty( $params )
            ? $wpdb->get_results( $sql, ARRAY_A )
            : $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );

        RaffleCore_Logger::log( 'export_generated', 'buyers', $raffle_id, 'CSV export' );

        self::output_csv( 'compradores', array(
            __( 'Nombre', 'rafflecore' ),
            __( 'Email', 'rafflecore' ),
            __( 'Boletos', 'rafflecore' ),
            __( 'Monto', 'rafflecore' ),
            __( 'Estado', 'rafflecore' ),
            __( 'Fecha', 'rafflecore' ),
            __( 'Rifa', 'rafflecore' ),
        ), $rows );
    }

    /**
     * AJAX: Exportar boletos a CSV.
     */
    public static function ajax_export_tickets() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'No autorizado.', 'rafflecore' ) );
        }

        check_ajax_referer( 'rc_admin_nonce', 'nonce' );

        global $wpdb;
        $p = $wpdb->prefix;

        $raffle_id = isset( $_GET['raffle_id'] ) ? absint( $_GET['raffle_id'] ) : 0;

        $where  = '1=1';
        $params = array();

        if ( $raffle_id ) {
            $where   .= ' AND t.raffle_id = %d';
            $params[] = $raffle_id;
        }

        $sql = "SELECT t.ticket_number, t.buyer_email, t.created_at,
                       r.title as raffle_title, p.buyer_name
                FROM {$p}rc_tickets t
                JOIN {$p}rc_raffles r ON t.raffle_id = r.id
                JOIN {$p}rc_purchases p ON t.purchase_id = p.id
                WHERE {$where}
                ORDER BY t.raffle_id, t.ticket_number ASC";

        $rows = empty( $params )
            ? $wpdb->get_results( $sql, ARRAY_A )
            : $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );

        RaffleCore_Logger::log( 'export_generated', 'tickets', $raffle_id, 'CSV export' );

        self::output_csv( 'boletos', array(
            __( 'Número', 'rafflecore' ),
            __( 'Email', 'rafflecore' ),
            __( 'Fecha', 'rafflecore' ),
            __( 'Rifa', 'rafflecore' ),
            __( 'Comprador', 'rafflecore' ),
        ), $rows );
    }

    /**
     * AJAX: Exportar transacciones a CSV.
     */
    public static function ajax_export_transactions() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'No autorizado.', 'rafflecore' ) );
        }

        // Accept either nonce
        $valid = wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ?? '' ) ), 'rc_admin_nonce' )
              || wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ?? '' ) ), 'rc_analytics_nonce' );
        if ( ! $valid ) {
            wp_die( __( 'Nonce inválido.', 'rafflecore' ) );
        }

        global $wpdb;
        $p = $wpdb->prefix;

        $sql = "SELECT p.id, p.buyer_name, p.buyer_email, p.quantity, p.amount_paid,
                       p.status, p.purchase_date, p.order_id, r.title as raffle_title
                FROM {$p}rc_purchases p
                JOIN {$p}rc_raffles r ON p.raffle_id = r.id
                ORDER BY p.purchase_date DESC";

        $rows = $wpdb->get_results( $sql, ARRAY_A );

        RaffleCore_Logger::log( 'export_generated', 'transactions', 0, 'CSV export' );

        self::output_csv( 'transacciones', array(
            'ID',
            __( 'Nombre', 'rafflecore' ),
            __( 'Email', 'rafflecore' ),
            __( 'Boletos', 'rafflecore' ),
            __( 'Monto', 'rafflecore' ),
            __( 'Estado', 'rafflecore' ),
            __( 'Fecha', 'rafflecore' ),
            __( 'Orden WC', 'rafflecore' ),
            __( 'Rifa', 'rafflecore' ),
        ), $rows );
    }

    /**
     * Envía el CSV al navegador.
     */
    private static function output_csv( $filename, $headers, $rows ) {
        $filename = 'rafflecore-' . $filename . '-' . gmdate( 'Y-m-d' ) . '.csv';

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        $output = fopen( 'php://output', 'w' );

        // BOM para Excel
        fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );

        fputcsv( $output, $headers );

        foreach ( $rows as $row ) {
            fputcsv( $output, array_values( $row ) );
        }

        fclose( $output );
        exit;
    }
}
