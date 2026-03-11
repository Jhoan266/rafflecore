<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * API Service — Capa de abstracción SaaS-ready.
 *
 * TODOS los módulos acceden a datos a través de esta clase.
 * Implementación actual: operaciones locales contra la BD de WordPress.
 *
 * Para migrar a SaaS: reemplazar los métodos por llamadas HTTP a la API externa.
 * El resto del plugin no necesita cambiar.
 *
 * Ejemplo futuro:
 *   En vez de $wpdb->get_row(...), sería:
 *   wp_remote_get( 'https://api.rafflecore.com/v1/raffles/' . $id );
 */
class RaffleCore_API_Service {

    /**
     * @var string Modo de operación: 'local' o 'api'
     */
    private $mode;

    public function __construct() {
        $this->mode = RAFFLECORE_MODE;
    }

    // ─── RAFFLES ───────────────────────────────────────────────

    public function get_raffle( $id ) {
        if ( $this->mode === 'api' ) {
            return $this->api_call( 'GET', "/raffles/{$id}" );
        }
        return RaffleCore_Raffle_Model::find( $id );
    }

    public function get_active_raffles() {
        if ( $this->mode === 'api' ) {
            return $this->api_call( 'GET', '/raffles?status=active' );
        }
        return RaffleCore_Raffle_Model::get_by_status( 'active' );
    }

    public function get_all_raffles( $args = array() ) {
        if ( $this->mode === 'api' ) {
            return $this->api_call( 'GET', '/raffles', $args );
        }
        return RaffleCore_Raffle_Model::get_all( $args );
    }

    public function create_raffle( $data ) {
        if ( $this->mode === 'api' ) {
            return $this->api_call( 'POST', '/raffles', $data );
        }
        return RaffleCore_Raffle_Model::create( $data );
    }

    public function update_raffle( $id, $data ) {
        if ( $this->mode === 'api' ) {
            return $this->api_call( 'PUT', "/raffles/{$id}", $data );
        }
        return RaffleCore_Raffle_Model::update( $id, $data );
    }

    public function delete_raffle( $id ) {
        if ( $this->mode === 'api' ) {
            return $this->api_call( 'DELETE', "/raffles/{$id}" );
        }
        return RaffleCore_Raffle_Model::delete( $id );
    }

    // ─── PURCHASES ─────────────────────────────────────────────

    public function create_purchase( $data ) {
        if ( $this->mode === 'api' ) {
            return $this->api_call( 'POST', '/purchases', $data );
        }
        return RaffleCore_Purchase_Model::create( $data );
    }

    public function get_purchase( $id ) {
        if ( $this->mode === 'api' ) {
            return $this->api_call( 'GET', "/purchases/{$id}" );
        }
        return RaffleCore_Purchase_Model::find( $id );
    }

    public function get_purchases_by_raffle( $raffle_id ) {
        if ( $this->mode === 'api' ) {
            return $this->api_call( 'GET', "/raffles/{$raffle_id}/purchases" );
        }
        return RaffleCore_Purchase_Model::get_by_raffle( $raffle_id );
    }

    public function update_purchase( $id, $data ) {
        if ( $this->mode === 'api' ) {
            return $this->api_call( 'PUT', "/purchases/{$id}", $data );
        }
        return RaffleCore_Purchase_Model::update( $id, $data );
    }

    public function get_all_buyers( $args = array() ) {
        if ( $this->mode === 'api' ) {
            return $this->api_call( 'GET', '/buyers', $args );
        }
        return RaffleCore_Purchase_Model::get_all_buyers( $args );
    }

    // ─── TICKETS ───────────────────────────────────────────────

    public function generate_tickets( $raffle_id, $purchase_id, $quantity, $buyer_email ) {
        if ( $this->mode === 'api' ) {
            return $this->api_call( 'POST', "/raffles/{$raffle_id}/tickets", compact( 'purchase_id', 'quantity', 'buyer_email' ) );
        }
        return RaffleCore_Ticket_Service::generate( $raffle_id, $purchase_id, $quantity, $buyer_email );
    }

    public function get_tickets_by_purchase( $purchase_id ) {
        if ( $this->mode === 'api' ) {
            return $this->api_call( 'GET', "/purchases/{$purchase_id}/tickets" );
        }
        return RaffleCore_Ticket_Model::get_by_purchase( $purchase_id );
    }

    public function get_tickets_by_raffle( $raffle_id ) {
        if ( $this->mode === 'api' ) {
            return $this->api_call( 'GET', "/raffles/{$raffle_id}/tickets" );
        }
        return RaffleCore_Ticket_Model::get_by_raffle( $raffle_id );
    }

    // ─── DRAW ───────────────────────────────────────────────────

    public function draw_winner( $raffle_id ) {
        if ( $this->mode === 'api' ) {
            return $this->api_call( 'POST', "/raffles/{$raffle_id}/draw" );
        }
        return RaffleCore_Draw_Service::execute_draw( $raffle_id );
    }

    // ─── EMAIL ──────────────────────────────────────────────────

    public function send_purchase_email( $purchase_id, $raffle, $tickets ) {
        // Los emails siempre se envían localmente desde WordPress
        return RaffleCore_Email_Service::send_purchase_confirmation( $purchase_id, $raffle, $tickets );
    }

    // ─── STATS ──────────────────────────────────────────────────

    public function get_dashboard_stats() {
        if ( $this->mode === 'api' ) {
            return $this->api_call( 'GET', '/stats/dashboard' );
        }
        return $this->local_dashboard_stats();
    }

    private function local_dashboard_stats() {
        global $wpdb;
        $p = $wpdb->prefix;

        return array(
            'total_raffles'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}rc_raffles" ),
            'active_raffles'  => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}rc_raffles WHERE status = 'active'" ),
            'total_tickets'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}rc_tickets" ),
            'total_revenue'   => (float) $wpdb->get_var( "SELECT COALESCE(SUM(amount_paid), 0) FROM {$p}rc_purchases WHERE status = 'completed'" ),
            'total_buyers'    => (int) $wpdb->get_var( "SELECT COUNT(DISTINCT buyer_email) FROM {$p}rc_purchases WHERE status = 'completed'" ),
            'total_purchases' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}rc_purchases WHERE status = 'completed'" ),
            'recent_purchases' => $wpdb->get_results(
                "SELECT p.*, r.title as raffle_title
                 FROM {$p}rc_purchases p
                 JOIN {$p}rc_raffles r ON p.raffle_id = r.id
                 WHERE p.status = 'completed'
                 ORDER BY p.purchase_date DESC
                 LIMIT 10"
            ),
        );
    }

    // ─── API CLIENT (para futuro SaaS) ─────────────────────────

    /**
     * Placeholder para llamadas HTTP a la API externa.
     * Se implementará cuando el sistema migre a SaaS.
     */
    private function api_call( $method, $endpoint, $data = array() ) {
        $api_url = get_option( 'rafflecore_api_url', '' );
        $api_key = get_option( 'rafflecore_api_key', '' );

        if ( empty( $api_url ) || empty( $api_key ) ) {
            return new WP_Error( 'api_not_configured', 'La API externa no está configurada.' );
        }

        $url  = rtrim( $api_url, '/' ) . '/v1' . $endpoint;
        $args = array(
            'method'  => $method,
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
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
            $message = isset( $decoded->message ) ? $decoded->message : 'Error de API';
            return new WP_Error( 'api_error', $message );
        }

        return isset( $decoded->data ) ? $decoded->data : $decoded;
    }
}
