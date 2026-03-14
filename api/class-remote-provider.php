<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Remote Data Provider — Skeleton para futuro SaaS.
 *
 * Cuando se migre a SaaS, implementar cada método con llamadas HTTP
 * al API externo usando $this->api_call().
 */
class RaffleCore_Remote_Provider implements RaffleCore_Data_Provider {

    private $api_url;
    private $api_key;

    public function __construct() {
        $this->api_url = get_option( 'rafflecore_api_url', '' );
        $this->api_key = get_option( 'rafflecore_api_key', '' );
    }

    // ─── RAFFLES ───────────────────────────────────────────────

    public function get_raffle( $id ) {
        return $this->api_call( 'GET', "/raffles/{$id}" );
    }

    public function get_active_raffles() {
        return $this->api_call( 'GET', '/raffles?status=active' );
    }

    public function get_all_raffles( $args = array() ) {
        return $this->api_call( 'GET', '/raffles', $args );
    }

    public function create_raffle( $data ) {
        return $this->api_call( 'POST', '/raffles', $data );
    }

    public function update_raffle( $id, $data ) {
        return $this->api_call( 'PUT', "/raffles/{$id}", $data );
    }

    public function delete_raffle( $id ) {
        return $this->api_call( 'DELETE', "/raffles/{$id}" );
    }

    // ─── PURCHASES ─────────────────────────────────────────────

    public function create_purchase( $data ) {
        return $this->api_call( 'POST', '/purchases', $data );
    }

    public function get_purchase( $id ) {
        return $this->api_call( 'GET', "/purchases/{$id}" );
    }

    public function get_purchases_by_raffle( $raffle_id ) {
        return $this->api_call( 'GET', "/raffles/{$raffle_id}/purchases" );
    }

    public function update_purchase( $id, $data ) {
        return $this->api_call( 'PUT', "/purchases/{$id}", $data );
    }

    public function get_all_buyers( $args = array() ) {
        return $this->api_call( 'GET', '/buyers', $args );
    }

    // ─── TICKETS ───────────────────────────────────────────────

    public function generate_tickets( $raffle_id, $purchase_id, $quantity, $buyer_email, $specific_numbers = array() ) {
        return $this->api_call( 'POST', "/raffles/{$raffle_id}/tickets", compact( 'purchase_id', 'quantity', 'buyer_email', 'specific_numbers' ) );
    }

    public function get_tickets_by_purchase( $purchase_id ) {
        return $this->api_call( 'GET', "/purchases/{$purchase_id}/tickets" );
    }

    public function get_tickets_by_raffle( $raffle_id ) {
        return $this->api_call( 'GET', "/raffles/{$raffle_id}/tickets" );
    }

    public function get_used_numbers( $raffle_id ) {
        return $this->api_call( 'GET', "/raffles/{$raffle_id}/used-numbers" );
    }

    // ─── DRAW ───────────────────────────────────────────────────

    public function draw_winner( $raffle_id ) {
        return $this->api_call( 'POST', "/raffles/{$raffle_id}/draw" );
    }

    // ─── STATS ──────────────────────────────────────────────────

    public function get_dashboard_stats() {
        return $this->api_call( 'GET', '/stats/dashboard' );
    }

    // ─── HTTP Client ────────────────────────────────────────────

    private function api_call( $method, $endpoint, $data = array() ) {
        if ( empty( $this->api_url ) || empty( $this->api_key ) ) {
            return new WP_Error( 'api_not_configured', __( 'La API externa no está configurada.', 'rafflecore' ) );
        }

        $url  = rtrim( $this->api_url, '/' ) . '/v1' . $endpoint;
        $args = array(
            'method'  => $method,
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ),
            'timeout' => 30,
        );

        if ( in_array( $method, array( 'POST', 'PUT' ), true ) && ! empty( $data ) ) {
            $args['body'] = wp_json_encode( $data );
        } elseif ( $method === 'GET' && ! empty( $data ) ) {
            $url = add_query_arg( $data, $url );
        }

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = wp_remote_retrieve_body( $response );
        $code = wp_remote_retrieve_response_code( $response );

        $decoded = json_decode( $body );

        if ( $code >= 400 ) {
            $message = isset( $decoded->message ) ? $decoded->message : __( 'Error de API', 'rafflecore' );
            return new WP_Error( 'api_error', $message );
        }

        return isset( $decoded->data ) ? $decoded->data : $decoded;
    }
}
