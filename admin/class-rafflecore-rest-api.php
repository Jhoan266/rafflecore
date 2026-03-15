<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * RaffleCore REST API — Endpoints públicos bajo /wp-json/rafflecore/v1/
 */
class RaffleCore_REST_API {

    const API_NAMESPACE = 'rafflecore/v1';

    public function register_routes() {
        // GET /raffles
        register_rest_route( self::API_NAMESPACE, '/raffles', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_raffles' ),
            'permission_callback' => '__return_true',
        ) );

        // GET /raffles/{id}
        register_rest_route( self::API_NAMESPACE, '/raffles/(?P<id>\d+)', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_raffle' ),
            'permission_callback' => '__return_true',
            'args'                => array(
                'id' => array( 'validate_callback' => function( $param ) { return is_numeric( $param ); } ),
            ),
        ) );

        // GET /raffles/{id}/tickets
        register_rest_route( self::API_NAMESPACE, '/raffles/(?P<id>\d+)/tickets', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_raffle_tickets' ),
            'permission_callback' => array( $this, 'admin_permission' ),
            'args'                => array(
                'id' => array( 'validate_callback' => function( $param ) { return is_numeric( $param ); } ),
            ),
        ) );

        // GET /stats
        register_rest_route( self::API_NAMESPACE, '/stats', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_stats' ),
            'permission_callback' => array( $this, 'admin_permission' ),
        ) );

        // POST /lookup-tickets (público, por email)
        register_rest_route( self::API_NAMESPACE, '/lookup-tickets', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'lookup_tickets' ),
            'permission_callback' => '__return_true',
            'args'                => array(
                'email' => array(
                    'required'          => true,
                    'validate_callback' => function( $param ) { return is_email( $param ); },
                    'sanitize_callback' => 'sanitize_email',
                ),
            ),
        ) );

        // GET /coupons (admin)
        register_rest_route( self::API_NAMESPACE, '/coupons', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'get_coupons' ),
            'permission_callback' => array( $this, 'admin_permission' ),
        ) );
    }

    public function admin_permission() {
        return current_user_can( 'manage_options' );
    }

    public function get_raffles( $request ) {
        $raffles = RaffleCore_Raffle_Model::get_all( array(
            'per_page' => 50,
            'status'   => 'active',
        ) );

        $data = array();
        foreach ( $raffles as $r ) {
            $data[] = array(
                'id'            => (int) $r->id,
                'title'         => $r->title,
                'prize_value'   => (float) $r->prize_value,
                'total_tickets' => (int) $r->total_tickets,
                'sold_tickets'  => (int) $r->sold_tickets,
                'ticket_price'  => (float) $r->ticket_price,
                'draw_date'     => $r->draw_date,
                'status'        => $r->status,
                'progress'      => RaffleCore_Raffle_Service::get_progress( $r ),
            );
        }

        return rest_ensure_response( $data );
    }

    public function get_raffle( $request ) {
        $raffle = RaffleCore_Raffle_Model::find( (int) $request['id'] );

        if ( ! $raffle ) {
            return new WP_Error( 'not_found', __( 'Rifa no encontrada.', 'rafflecore' ), array( 'status' => 404 ) );
        }

        return rest_ensure_response( array(
            'id'            => (int) $raffle->id,
            'title'         => $raffle->title,
            'description'   => $raffle->description,
            'prize_value'   => (float) $raffle->prize_value,
            'prize_image'   => $raffle->prize_image,
            'total_tickets' => (int) $raffle->total_tickets,
            'sold_tickets'  => (int) $raffle->sold_tickets,
            'ticket_price'  => (float) $raffle->ticket_price,
            'packages'      => json_decode( $raffle->packages, true ) ?: array(),
            'draw_date'     => $raffle->draw_date,
            'status'        => $raffle->status,
            'progress'      => RaffleCore_Raffle_Service::get_progress( $raffle ),
        ) );
    }

    public function get_raffle_tickets( $request ) {
        $tickets = RaffleCore_Ticket_Model::get_by_raffle( (int) $request['id'] );
        return rest_ensure_response( $tickets );
    }

    public function get_stats( $request ) {
        $api = new RaffleCore_API_Service();
        return rest_ensure_response( $api->get_dashboard_stats() );
    }

    public function lookup_tickets( $request ) {
        // Rate limiting: 10 requests per minute per IP
        $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
        $transient_key = 'rc_lookup_' . md5( $ip );
        $attempts = (int) get_transient( $transient_key );
        if ( $attempts >= 10 ) {
            return new WP_Error( 'rate_limited', __( 'Demasiadas solicitudes. Intenta en un minuto.', 'rafflecore' ), array( 'status' => 429 ) );
        }
        set_transient( $transient_key, $attempts + 1, MINUTE_IN_SECONDS );

        $email = $request->get_param( 'email' );

        global $wpdb;
        $p = $wpdb->prefix;

        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT t.ticket_number, r.title as raffle_title, r.id as raffle_id,
                    r.draw_date, r.status as raffle_status, r.total_tickets, r.ticket_digits,
                    p.purchase_date, p.quantity, p.amount_paid
             FROM {$p}rc_tickets t
             JOIN {$p}rc_purchases p ON t.purchase_id = p.id
             JOIN {$p}rc_raffles r ON t.raffle_id = r.id
             WHERE t.buyer_email = %s AND p.status = 'completed'
             ORDER BY r.id, t.ticket_number ASC",
            $email
        ) );

        if ( empty( $results ) ) {
            return rest_ensure_response( array() );
        }

        // Agrupar por rifa
        $grouped = array();
        foreach ( $results as $row ) {
            $rid = (int) $row->raffle_id;
            $digits = isset($row->ticket_digits) ? (int)$row->ticket_digits : null;
            if ( ! isset( $grouped[ $rid ] ) ) {
                $grouped[ $rid ] = array(
                    'raffle'  => $row->raffle_title,
                    'tickets' => array(),
                );
            }
            $grouped[ $rid ]['tickets'][] = RaffleCore_Ticket_Service::format_numbers([$row->ticket_number], ['digits'=>$digits])[0];
        }

        return rest_ensure_response( array_values( $grouped ) );
    }

    public function get_coupons( $request ) {
        $coupons = RaffleCore_Coupon_Model::get_all();
        return rest_ensure_response( $coupons );
    }
}
