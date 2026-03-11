<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Draw Service — Sistema de sorteo del ganador.
 */
class RaffleCore_Draw_Service {

    private $api;

    public function __construct( $api = null ) {
        $this->api = $api;
    }

    /**
     * Ejecuta el sorteo de una rifa (operación local).
     *
     * Selecciona aleatoriamente un boleto vendido como ganador.
     * Usa FOR UPDATE + transacción para prevenir sorteos concurrentes.
     */
    public static function execute_draw( $raffle_id ) {
        global $wpdb;

        $t_raffles = $wpdb->prefix . 'rc_raffles';
        $t_tickets = $wpdb->prefix . 'rc_tickets';

        $wpdb->query( 'START TRANSACTION' );

        $raffle = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$t_raffles} WHERE id = %d FOR UPDATE",
            $raffle_id
        ) );

        if ( ! $raffle ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'not_found', 'Rifa no encontrada.' );
        }

        if ( $raffle->winner_ticket_id ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'already_drawn', 'Esta rifa ya tiene un ganador.' );
        }

        if ( $raffle->sold_tickets < 1 ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'no_tickets', 'No hay boletos vendidos para sortear.' );
        }

        // Seleccionar número aleatorio entre los vendidos
        $sold_tickets = $wpdb->get_col( $wpdb->prepare(
            "SELECT id FROM {$t_tickets} WHERE raffle_id = %d",
            $raffle_id
        ) );

        $winner_index = random_int( 0, count( $sold_tickets ) - 1 );
        $winner_id    = $sold_tickets[ $winner_index ];

        // Actualizar rifa
        $wpdb->update(
            $t_raffles,
            array(
                'winner_ticket_id' => $winner_id,
                'status'           => 'finished',
            ),
            array( 'id' => $raffle_id ),
            array( '%d', '%s' ),
            array( '%d' )
        );

        $wpdb->query( 'COMMIT' );

        // Obtener datos del ganador
        $winner = $wpdb->get_row( $wpdb->prepare(
            "SELECT t.*, p.buyer_name, p.buyer_email as winner_email
             FROM {$t_tickets} t
             JOIN {$wpdb->prefix}rc_purchases p ON t.purchase_id = p.id
             WHERE t.id = %d",
            $winner_id
        ) );

        return $winner;
    }

    /**
     * AJAX handler para el sorteo (admin).
     */
    public function ajax_draw() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Sin permisos.' ) );
        }

        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'rc_admin_nonce' ) ) {
            wp_send_json_error( array( 'message' => 'Error de seguridad.' ) );
        }

        $raffle_id = isset( $_POST['raffle_id'] ) ? absint( $_POST['raffle_id'] ) : 0;

        $result = $this->api->draw_winner( $raffle_id );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        $raffle      = $this->api->get_raffle( $raffle_id );
        $total_digits = strlen( (string) $raffle->total_tickets );

        wp_send_json_success( array(
            'winner_name'   => $result->buyer_name,
            'winner_email'  => $result->winner_email,
            'ticket_number' => str_pad( $result->ticket_number, $total_digits, '0', STR_PAD_LEFT ),
        ) );
    }
}
