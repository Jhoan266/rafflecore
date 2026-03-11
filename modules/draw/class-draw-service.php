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
            return new WP_Error( 'not_found', __( 'Rifa no encontrada.', 'rafflecore' ) );
        }

        if ( $raffle->winner_ticket_id ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'already_drawn', __( 'Esta rifa ya tiene un ganador.', 'rafflecore' ) );
        }

        if ( $raffle->sold_tickets < 1 ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'no_tickets', __( 'No hay boletos vendidos para sortear.', 'rafflecore' ) );
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
     * Ejecuta sorteo con número ganador externo (tipo Baloto/Lotería).
     */
    public static function execute_external_draw( $raffle_id, $ticket_number ) {
        global $wpdb;

        $t_raffles = $wpdb->prefix . 'rc_raffles';

        $wpdb->query( 'START TRANSACTION' );

        $raffle = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$t_raffles} WHERE id = %d FOR UPDATE",
            $raffle_id
        ) );

        if ( ! $raffle ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'not_found', __( 'Rifa no encontrada.', 'rafflecore' ) );
        }

        if ( $raffle->winner_ticket_id ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'already_drawn', __( 'Esta rifa ya tiene un ganador.', 'rafflecore' ) );
        }

        $winner = RaffleCore_Ticket_Model::find_by_number( $raffle_id, $ticket_number );

        if ( ! $winner ) {
            $wpdb->query( 'ROLLBACK' );
            return new WP_Error( 'ticket_not_sold', sprintf(
                __( 'El boleto #%s no ha sido vendido en esta rifa.', 'rafflecore' ),
                $ticket_number
            ) );
        }

        $wpdb->update(
            $t_raffles,
            array(
                'winner_ticket_id' => $winner->id,
                'status'           => 'finished',
            ),
            array( 'id' => $raffle_id ),
            array( '%d', '%s' ),
            array( '%d' )
        );

        $wpdb->query( 'COMMIT' );

        return $winner;
    }

    /**
     * AJAX handler para sorteo con número externo (Baloto/Lotería).
     */
    public function ajax_external_draw() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Sin permisos.', 'rafflecore' ) ) );
        }

        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'rc_admin_nonce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Error de seguridad.', 'rafflecore' ) ) );
        }

        $raffle_id     = isset( $_POST['raffle_id'] ) ? absint( $_POST['raffle_id'] ) : 0;
        $ticket_number = isset( $_POST['ticket_number'] ) ? absint( $_POST['ticket_number'] ) : 0;
        $message       = isset( $_POST['winner_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['winner_message'] ) ) : '';

        if ( ! $ticket_number ) {
            wp_send_json_error( array( 'message' => __( 'Ingresa un número de boleto válido.', 'rafflecore' ) ) );
        }

        $result = self::execute_external_draw( $raffle_id, $ticket_number );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        $raffle       = $this->api->get_raffle( $raffle_id );
        $total_digits = strlen( (string) $raffle->total_tickets );

        // Log, notify admin, webhook
        RaffleCore_Logger::log( 'draw_executed', 'raffle', $raffle_id, $result->buyer_name . ' (Número externo: ' . $ticket_number . ')' );
        RaffleCore_Email_Service::notify_admin_draw( $raffle, $result );
        RaffleCore_Webhook_Service::fire( 'draw.executed', array(
            'raffle_id'     => $raffle_id,
            'raffle_title'  => $raffle->title,
            'winner_name'   => $result->buyer_name,
            'winner_email'  => $result->purchase_email,
            'ticket_number' => $ticket_number,
            'source'        => 'external',
        ) );

        // Notify the winner by email
        if ( $message ) {
            RaffleCore_Email_Service::send_winner_notification( $raffle, $result, $message );
        }

        wp_send_json_success( array(
            'buyer_name'    => $result->buyer_name,
            'buyer_email'   => $result->purchase_email,
            'ticket_number' => str_pad( $ticket_number, $total_digits, '0', STR_PAD_LEFT ),
            'notified'      => ! empty( $message ),
        ) );
    }

    /**
     * AJAX handler para el sorteo (admin).
     */
    public function ajax_draw() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Sin permisos.', 'rafflecore' ) ) );
        }

        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'rc_admin_nonce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Error de seguridad.', 'rafflecore' ) ) );
        }

        $raffle_id = isset( $_POST['raffle_id'] ) ? absint( $_POST['raffle_id'] ) : 0;

        $result = $this->api->draw_winner( $raffle_id );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        $raffle      = $this->api->get_raffle( $raffle_id );
        $total_digits = strlen( (string) $raffle->total_tickets );

        // Log, notify, webhook
        RaffleCore_Logger::log( 'draw_executed', 'raffle', $raffle_id, $result->buyer_name );
        RaffleCore_Email_Service::notify_admin_draw( $raffle, $result );
        RaffleCore_Webhook_Service::fire( 'draw.executed', array(
            'raffle_id'     => $raffle_id,
            'raffle_title'  => $raffle->title,
            'winner_name'   => $result->buyer_name,
            'winner_email'  => $result->winner_email,
            'ticket_number' => $result->ticket_number,
        ) );

        wp_send_json_success( array(
            'winner_name'   => $result->buyer_name,
            'winner_email'  => $result->winner_email,
            'ticket_number' => str_pad( $result->ticket_number, $total_digits, '0', STR_PAD_LEFT ),
        ) );
    }
}
