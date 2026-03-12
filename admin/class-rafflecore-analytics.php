<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * RaffleCore Analytics — Endpoints AJAX para el dashboard analítico.
 */
class RaffleCore_Analytics {

    public function __construct() {
        add_action( 'wp_ajax_rc_analytics_data', array( $this, 'handle_request' ) );
    }

    public function handle_request() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'No autorizado' );
        }

        check_ajax_referer( 'rc_analytics_nonce', 'nonce' );

        global $wpdb;
        $pfx = $wpdb->prefix;

        $type      = sanitize_text_field( wp_unslash( $_GET['type'] ?? '' ) );
        $raffle_id = absint( $_GET['raffle_id'] ?? 0 );

        switch ( $type ) {
            case 'overview':
                wp_send_json_success( $this->get_overview( $pfx, $raffle_id ) );
                break;
            case 'revenue_by_raffle':
                wp_send_json_success( $this->get_revenue_by_raffle( $pfx, $raffle_id ) );
                break;
            case 'tickets_by_raffle':
                wp_send_json_success( $this->get_tickets_by_raffle( $pfx, $raffle_id ) );
                break;
            case 'net_profit':
                wp_send_json_success( $this->get_net_profit( $pfx, $raffle_id ) );
                break;
            case 'sales_trend':
                $period = sanitize_text_field( wp_unslash( $_GET['period'] ?? 'daily' ) );
                wp_send_json_success( $this->get_sales_trend( $pfx, $period, $raffle_id ) );
                break;
            case 'top_buyers':
                wp_send_json_success( $this->get_top_buyers( $pfx, $raffle_id ) );
                break;
            case 'recent_transactions':
                wp_send_json_success( $this->get_recent_transactions( $pfx, $raffle_id ) );
                break;
            case 'revenue_vs_prize':
                wp_send_json_success( $this->get_revenue_vs_prize( $pfx, $raffle_id ) );
                break;
            case 'package_popularity':
                wp_send_json_success( $this->get_package_popularity( $pfx, $raffle_id ) );
                break;
            case 'cumulative_revenue':
                wp_send_json_success( $this->get_cumulative_revenue( $pfx, $raffle_id ) );
                break;
            default:
                wp_send_json_error( 'Tipo inválido' );
        }
    }

    private function get_overview( $pfx, $raffle_id ) {
        global $wpdb;

        $r_where   = $raffle_id > 0 ? $wpdb->prepare( "AND raffle_id = %d", $raffle_id ) : "";
        $raf_where = $raffle_id > 0 ? $wpdb->prepare( "AND id = %d", $raffle_id ) : "";

        $total_revenue = (float) $wpdb->get_var(
            "SELECT COALESCE(SUM(amount_paid), 0) FROM {$pfx}rc_purchases WHERE status IN ('completed', 'processing', 'on-hold') $r_where"
        );

        $total_tickets_sold = (int) $wpdb->get_var(
            "SELECT COALESCE(SUM(sold_tickets), 0) FROM {$pfx}rc_raffles WHERE status != 'deleted' $raf_where"
        );

        $total_tickets_available = (int) $wpdb->get_var(
            "SELECT COALESCE(SUM(total_tickets), 0) FROM {$pfx}rc_raffles WHERE status != 'deleted' $raf_where"
        );

        $active_raffles = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$pfx}rc_raffles WHERE status = 'active' $raf_where"
        );

        $total_raffles = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$pfx}rc_raffles WHERE status != 'deleted' $raf_where"
        );

        $total_prize_value = (float) $wpdb->get_var(
            "SELECT COALESCE(SUM(prize_value), 0) FROM {$pfx}rc_raffles WHERE status != 'deleted' $raf_where"
        );

        $total_buyers = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT buyer_email) FROM {$pfx}rc_purchases WHERE status IN ('completed', 'processing', 'on-hold') $r_where"
        );

        $avg_ticket_price = (float) $wpdb->get_var(
            "SELECT COALESCE(AVG(ticket_price), 0) FROM {$pfx}rc_raffles WHERE status != 'deleted' $raf_where"
        );

        $revenue_this_month = (float) $wpdb->get_var(
            "SELECT COALESCE(SUM(amount_paid), 0) FROM {$pfx}rc_purchases
             WHERE status IN ('completed', 'processing', 'on-hold') AND MONTH(purchase_date) = MONTH(CURDATE()) AND YEAR(purchase_date) = YEAR(CURDATE()) $r_where"
        );

        $revenue_last_month = (float) $wpdb->get_var(
            "SELECT COALESCE(SUM(amount_paid), 0) FROM {$pfx}rc_purchases
             WHERE status IN ('completed', 'processing', 'on-hold') AND MONTH(purchase_date) = MONTH(CURDATE() - INTERVAL 1 MONTH) AND YEAR(purchase_date) = YEAR(CURDATE() - INTERVAL 1 MONTH) $r_where"
        );

        return array(
            'total_revenue'           => $total_revenue,
            'total_tickets_sold'      => $total_tickets_sold,
            'total_tickets_available'  => $total_tickets_available,
            'active_raffles'          => $active_raffles,
            'total_raffles'           => $total_raffles,
            'total_prize_value'       => $total_prize_value,
            'net_profit'              => $total_revenue - $total_prize_value,
            'total_buyers'            => $total_buyers,
            'avg_ticket_price'        => $avg_ticket_price,
            'revenue_this_month'      => $revenue_this_month,
            'revenue_last_month'      => $revenue_last_month,
            'sell_rate'               => $total_tickets_available > 0 ? round( ( $total_tickets_sold / $total_tickets_available ) * 100, 1 ) : 0,
        );
    }

    private function get_revenue_by_raffle( $pfx, $raffle_id ) {
        global $wpdb;
        $raf_where = $raffle_id > 0 ? $wpdb->prepare( "AND r.id = %d", $raffle_id ) : "";

        return $wpdb->get_results(
            "SELECT r.id, r.title,
                    COALESCE(SUM(p.amount_paid), 0) AS revenue,
                    r.prize_value,
                    r.sold_tickets,
                    r.total_tickets
             FROM {$pfx}rc_raffles r
             LEFT JOIN {$pfx}rc_purchases p ON p.raffle_id = r.id AND p.status IN ('completed', 'processing', 'on-hold')
             WHERE r.status != 'deleted' {$raf_where}
             GROUP BY r.id
             ORDER BY revenue DESC"
        );
    }

    private function get_tickets_by_raffle( $pfx, $raffle_id ) {
        global $wpdb;
        $raf_where = $raffle_id > 0 ? $wpdb->prepare( "AND r.id = %d", $raffle_id ) : "";

        return $wpdb->get_results(
            "SELECT r.id, r.title, r.sold_tickets, r.total_tickets,
                    ROUND((r.sold_tickets / r.total_tickets) * 100, 1) AS sell_rate
             FROM {$pfx}rc_raffles r
             WHERE r.total_tickets > 0 {$raf_where}
             ORDER BY sell_rate DESC"
        );
    }

    private function get_net_profit( $pfx, $raffle_id ) {
        global $wpdb;
        $raf_where = $raffle_id > 0 ? $wpdb->prepare( "AND r.id = %d", $raffle_id ) : "";

        return $wpdb->get_results(
            "SELECT r.id, r.title, r.prize_value,
                    COALESCE(SUM(p.amount_paid), 0) AS revenue,
                    (COALESCE(SUM(p.amount_paid), 0) - r.prize_value) AS net_profit
             FROM {$pfx}rc_raffles r
             LEFT JOIN {$pfx}rc_purchases p ON p.raffle_id = r.id AND p.status IN ('completed', 'processing', 'on-hold')
             WHERE r.status != 'deleted' {$raf_where}
             GROUP BY r.id
             ORDER BY net_profit DESC"
        );
    }

    private function get_sales_trend( $pfx, $period, $raffle_id ) {
        global $wpdb;
        $r_where = $raffle_id > 0 ? $wpdb->prepare( "AND raffle_id = %d", $raffle_id ) : "";

        switch ( $period ) {
            case 'monthly':
                $sql = "SELECT DATE_FORMAT(purchase_date, '%Y-%m') AS label,
                               COUNT(*) AS transactions,
                               COALESCE(SUM(amount_paid), 0) AS revenue,
                               COALESCE(SUM(quantity), 0) AS tickets
                        FROM {$pfx}rc_purchases
                        WHERE status IN ('completed', 'processing', 'on-hold') {$r_where}
                        GROUP BY label
                        ORDER BY label ASC
                        LIMIT 24";
                break;

            case 'annual':
                $sql = "SELECT YEAR(purchase_date) AS label,
                               COUNT(*) AS transactions,
                               COALESCE(SUM(amount_paid), 0) AS revenue,
                               COALESCE(SUM(quantity), 0) AS tickets
                        FROM {$pfx}rc_purchases
                        WHERE status IN ('completed', 'processing', 'on-hold') {$r_where}
                        GROUP BY label
                        ORDER BY label ASC";
                break;

            default: // daily
                $sql = "SELECT DATE(purchase_date) AS label,
                               COUNT(*) AS transactions,
                               COALESCE(SUM(amount_paid), 0) AS revenue,
                               COALESCE(SUM(quantity), 0) AS tickets
                        FROM {$pfx}rc_purchases
                        WHERE status IN ('completed', 'processing', 'on-hold') AND purchase_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) {$r_where}
                        GROUP BY label
                        ORDER BY label ASC";
                break;
        }

        return $wpdb->get_results( $sql );
    }

    private function get_top_buyers( $pfx, $raffle_id ) {
        global $wpdb;
        $r_where = $raffle_id > 0 ? $wpdb->prepare( "AND raffle_id = %d", $raffle_id ) : "";

        return $wpdb->get_results(
            "SELECT buyer_name, buyer_email,
                    COUNT(*) AS purchases,
                    COALESCE(SUM(quantity), 0) AS total_tickets,
                    COALESCE(SUM(amount_paid), 0) AS total_spent
             FROM {$pfx}rc_purchases
             WHERE status IN ('completed', 'processing', 'on-hold') {$r_where}
             GROUP BY buyer_email
             ORDER BY total_spent DESC
             LIMIT 10"
        );
    }

    private function get_recent_transactions( $pfx, $raffle_id ) {
        global $wpdb;
        $r_where = $raffle_id > 0 ? $wpdb->prepare( "AND p.raffle_id = %d", $raffle_id ) : "";

        return $wpdb->get_results(
            "SELECT p.id, p.buyer_name, p.buyer_email, p.quantity, p.amount_paid,
                    p.status, p.purchase_date, r.title AS raffle_title
             FROM {$pfx}rc_purchases p
             JOIN {$pfx}rc_raffles r ON r.id = p.raffle_id
             WHERE 1=1 {$r_where}
             ORDER BY p.purchase_date DESC
             LIMIT 15"
        );
    }

    private function get_revenue_vs_prize( $pfx, $raffle_id ) {
        global $wpdb;
        $r_where   = $raffle_id > 0 ? $wpdb->prepare( "AND raffle_id = %d", $raffle_id ) : "";
        $raf_where = $raffle_id > 0 ? $wpdb->prepare( "AND id = %d", $raffle_id ) : "";

        $total_revenue = (float) $wpdb->get_var(
            "SELECT COALESCE(SUM(amount_paid), 0) FROM {$pfx}rc_purchases WHERE status IN ('completed', 'processing', 'on-hold') {$r_where}"
        );

        $total_prize_value = (float) $wpdb->get_var(
            "SELECT COALESCE(SUM(prize_value), 0) FROM {$pfx}rc_raffles WHERE status != 'deleted' {$raf_where}"
        );

        $net_profit = $total_revenue - $total_prize_value;

        return array(
            'total_revenue'    => $total_revenue,
            'total_prize'      => $total_prize_value,
            'net_profit'       => max( 0, $net_profit ),
            'deficit'          => $net_profit < 0 ? abs( $net_profit ) : 0,
        );
    }

    private function get_package_popularity( $pfx, $raffle_id ) {
        global $wpdb;
        $r_where = $raffle_id > 0 ? $wpdb->prepare( "AND raffle_id = %d", $raffle_id ) : "";

        return $wpdb->get_results(
            "SELECT quantity AS package_size,
                    COUNT(*) AS purchases,
                    COALESCE(SUM(amount_paid), 0) AS total_revenue
             FROM {$pfx}rc_purchases
             WHERE status IN ('completed', 'processing', 'on-hold') {$r_where}
             GROUP BY quantity
             ORDER BY purchases DESC
             LIMIT 10"
        );
    }

    private function get_cumulative_revenue( $pfx, $raffle_id ) {
        global $wpdb;
        $r_where = $raffle_id > 0 ? $wpdb->prepare( "AND p.raffle_id = %d", $raffle_id ) : "";
        $r2_where = $raffle_id > 0 ? $wpdb->prepare( "AND p2.raffle_id = %d", $raffle_id ) : "";

        return $wpdb->get_results(
            "SELECT DATE(p.purchase_date) AS date_label,
                    COALESCE(SUM(p.amount_paid), 0) AS daily_revenue,
                    (SELECT COALESCE(SUM(p2.amount_paid), 0)
                     FROM {$pfx}rc_purchases p2
                     WHERE p2.status IN ('completed', 'processing', 'on-hold') AND DATE(p2.purchase_date) <= DATE(p.purchase_date) {$r2_where}
                    ) AS cumulative
             FROM {$pfx}rc_purchases p
             WHERE p.status IN ('completed', 'processing', 'on-hold') {$r_where}
             GROUP BY date_label
             ORDER BY date_label ASC"
        );
    }
}
