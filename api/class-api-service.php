<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * API Service — Fachada SaaS-ready con patrón Provider.
 *
 * TODOS los módulos acceden a datos a través de esta clase.
 * Internamente delega a un RaffleCore_Data_Provider según RAFFLECORE_MODE:
 *   - 'local' → RaffleCore_Local_Provider  (BD WordPress)
 *   - 'api'   → RaffleCore_Remote_Provider (HTTP a API externa)
 *
 * Para migrar a SaaS: cambiar RAFFLECORE_MODE a 'api' y configurar URL/Key.
 * Para tests: inyectar un mock provider vía set_provider().
 */
class RaffleCore_API_Service {

    /**
     * @var RaffleCore_Data_Provider
     */
    private $provider;

    public function __construct() {
        if ( RAFFLECORE_MODE === 'api' ) {
            $this->provider = new RaffleCore_Remote_Provider();
        } else {
            $this->provider = new RaffleCore_Local_Provider();
        }
    }

    /**
     * Permite inyectar un provider personalizado (útil para tests o extensiones).
     */
    public function set_provider( RaffleCore_Data_Provider $provider ) {
        $this->provider = $provider;
    }

    /**
     * @return RaffleCore_Data_Provider
     */
    public function get_provider() {
        return $this->provider;
    }

    // ─── RAFFLES ───────────────────────────────────────────────

    public function get_raffle( $id ) {
        return $this->provider->get_raffle( $id );
    }

    public function get_active_raffles() {
        return $this->provider->get_active_raffles();
    }

    public function get_all_raffles( $args = array() ) {
        return $this->provider->get_all_raffles( $args );
    }

    public function create_raffle( $data ) {
        return $this->provider->create_raffle( $data );
    }

    public function update_raffle( $id, $data ) {
        return $this->provider->update_raffle( $id, $data );
    }

    public function delete_raffle( $id ) {
        return $this->provider->delete_raffle( $id );
    }

    // ─── PURCHASES ─────────────────────────────────────────────

    public function create_purchase( $data ) {
        return $this->provider->create_purchase( $data );
    }

    public function get_purchase( $id ) {
        return $this->provider->get_purchase( $id );
    }

    public function get_purchases_by_raffle( $raffle_id ) {
        return $this->provider->get_purchases_by_raffle( $raffle_id );
    }

    public function update_purchase( $id, $data ) {
        return $this->provider->update_purchase( $id, $data );
    }

    public function get_all_buyers( $args = array() ) {
        return $this->provider->get_all_buyers( $args );
    }

    // ─── TICKETS ───────────────────────────────────────────────

    public function generate_tickets( $raffle_id, $purchase_id, $quantity, $buyer_email ) {
        return $this->provider->generate_tickets( $raffle_id, $purchase_id, $quantity, $buyer_email );
    }

    public function get_tickets_by_purchase( $purchase_id ) {
        return $this->provider->get_tickets_by_purchase( $purchase_id );
    }

    public function get_tickets_by_raffle( $raffle_id ) {
        return $this->provider->get_tickets_by_raffle( $raffle_id );
    }

    // ─── DRAW ───────────────────────────────────────────────────

    public function draw_winner( $raffle_id ) {
        return $this->provider->draw_winner( $raffle_id );
    }

    // ─── EMAIL ──────────────────────────────────────────────────

    public function send_purchase_email( $purchase_id, $raffle, $tickets ) {
        return RaffleCore_Email_Service::send_purchase_confirmation( $purchase_id, $raffle, $tickets );
    }

    // ─── STATS ──────────────────────────────────────────────────

    public function get_dashboard_stats() {
        return $this->provider->get_dashboard_stats();
    }
}
