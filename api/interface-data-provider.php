<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Data Provider Interface — Contrato SaaS-ready.
 *
 * Cualquier proveedor de datos (local BD, API remota, mock para tests)
 * debe implementar esta interfaz. Esto permite cambiar el backend
 * sin modificar el resto del plugin.
 */
interface RaffleCore_Data_Provider {

    // ─── RAFFLES ───────────────────────────────────────────────

    /**
     * @param int $id
     * @return object|null
     */
    public function get_raffle( $id );

    /**
     * @return array
     */
    public function get_active_raffles();

    /**
     * @param array $args
     * @return array
     */
    public function get_all_raffles( $args = array() );

    /**
     * @param array $data
     * @return int|WP_Error
     */
    public function create_raffle( $data );

    /**
     * @param int   $id
     * @param array $data
     * @return bool|WP_Error
     */
    public function update_raffle( $id, $data );

    /**
     * @param int $id
     * @return bool|WP_Error
     */
    public function delete_raffle( $id );

    // ─── PURCHASES ─────────────────────────────────────────────

    /**
     * @param array $data
     * @return int|WP_Error
     */
    public function create_purchase( $data );

    /**
     * @param int $id
     * @return object|null
     */
    public function get_purchase( $id );

    /**
     * @param int $raffle_id
     * @return array
     */
    public function get_purchases_by_raffle( $raffle_id );

    /**
     * @param int   $id
     * @param array $data
     * @return bool|WP_Error
     */
    public function update_purchase( $id, $data );

    /**
     * @param array $args
     * @return array
     */
    public function get_all_buyers( $args = array() );

    // ─── TICKETS ───────────────────────────────────────────────

    /**
     * @param int    $raffle_id
     * @param int    $purchase_id
     * @param int    $quantity
     * @param string $buyer_email
     * @return array|WP_Error
     */
    public function generate_tickets( $raffle_id, $purchase_id, $quantity, $buyer_email, $specific_numbers = array() );

    /**
     * @param int $purchase_id
     * @return array
     */
    public function get_tickets_by_purchase( $purchase_id );

    /**
     * @param int $raffle_id
     * @return array
     */
    public function get_tickets_by_raffle( $raffle_id );

    /**
     * @param int $raffle_id
     * @return array
     */
    public function get_used_numbers( $raffle_id );

    // ─── DRAW ───────────────────────────────────────────────────

    /**
     * @param int $raffle_id
     * @return object|WP_Error
     */
    public function draw_winner( $raffle_id );

    // ─── STATS ──────────────────────────────────────────────────

    /**
     * @return array
     */
    public function get_dashboard_stats();
}
