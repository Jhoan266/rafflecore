<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Local Data Provider — Operaciones contra la BD de WordPress.
 *
 * Implementación por defecto. Delega a los modelos/servicios existentes.
 */
class RaffleCore_Local_Provider implements RaffleCore_Data_Provider {

    // ─── RAFFLES ───────────────────────────────────────────────

    public function get_raffle( $id ) {
        return RaffleCore_Raffle_Model::find( $id );
    }

    public function get_active_raffles() {
        return RaffleCore_Raffle_Model::get_by_status( 'active' );
    }

    public function get_all_raffles( $args = array() ) {
        return RaffleCore_Raffle_Model::get_all( $args );
    }

    public function create_raffle( $data ) {
        return RaffleCore_Raffle_Model::create( $data );
    }

    public function update_raffle( $id, $data ) {
        return RaffleCore_Raffle_Model::update( $id, $data );
    }

    public function delete_raffle( $id ) {
        return RaffleCore_Raffle_Model::delete( $id );
    }

    // ─── PURCHASES ─────────────────────────────────────────────

    public function create_purchase( $data ) {
        return RaffleCore_Purchase_Model::create( $data );
    }

    public function get_purchase( $id ) {
        return RaffleCore_Purchase_Model::find( $id );
    }

    public function get_purchases_by_raffle( $raffle_id ) {
        return RaffleCore_Purchase_Model::get_by_raffle( $raffle_id );
    }

    public function update_purchase( $id, $data ) {
        return RaffleCore_Purchase_Model::update( $id, $data );
    }

    public function get_all_buyers( $args = array() ) {
        return RaffleCore_Purchase_Model::get_all_buyers( $args );
    }

    // ─── TICKETS ───────────────────────────────────────────────

    public function generate_tickets( $raffle_id, $purchase_id, $quantity, $buyer_email ) {
        return RaffleCore_Ticket_Service::generate( $raffle_id, $purchase_id, $quantity, $buyer_email );
    }

    public function get_tickets_by_purchase( $purchase_id ) {
        return RaffleCore_Ticket_Model::get_by_purchase( $purchase_id );
    }

    public function get_tickets_by_raffle( $raffle_id ) {
        return RaffleCore_Ticket_Model::get_by_raffle( $raffle_id );
    }

    // ─── DRAW ───────────────────────────────────────────────────

    public function draw_winner( $raffle_id ) {
        return RaffleCore_Draw_Service::execute_draw( $raffle_id );
    }

    // ─── STATS ──────────────────────────────────────────────────

    public function get_dashboard_stats() {
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
}
