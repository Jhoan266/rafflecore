<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * RaffleCore Analytics — Endpoints AJAX para el dashboard analítico.
 */
class RaffleCore_Analytics {

    private $base_cur;
    private $disp_cur;

    public function __construct() {
        add_action( 'wp_ajax_rc_analytics_data', array( $this, 'handle_request' ) );
        $this->base_cur = get_option( 'rafflecore_base_currency', 'COP' );
        $this->disp_cur = get_option( 'rafflecore_currency', 'COP' );
    }

    private function convert( $amount ) {
        return RaffleCore_Currency::convert( $amount, $this->base_cur, $this->disp_cur );
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
            case 'top_raffles':
                wp_send_json_success( $this->get_revenue_by_raffle( $pfx, $raffle_id ) );
                break;
            case 'raffle_progress':
                wp_send_json_success( $this->get_raffle_progress( $pfx, $raffle_id ) );
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
        $raf_where_r = $raffle_id > 0 ? $wpdb->prepare( "AND r.id = %d", $raffle_id ) : "";

        $total_revenue = (float) $wpdb->get_var(
            "SELECT COALESCE(SUM(amount_paid), 0) FROM {$pfx}rc_purchases WHERE status IN ('completed', 'processing', 'on-hold') $r_where"
        );

        // Include all raffles that have purchases (regardless of raffle status)
        $total_tickets_sold = (int) $wpdb->get_var(
            "SELECT COALESCE(SUM(r.sold_tickets), 0)
             FROM {$pfx}rc_raffles r
             WHERE r.id IN (SELECT DISTINCT raffle_id FROM {$pfx}rc_purchases) {$raf_where}"
        );

        $total_tickets_available = (int) $wpdb->get_var(
            "SELECT COALESCE(SUM(r.total_tickets), 0)
             FROM {$pfx}rc_raffles r
             WHERE r.id IN (SELECT DISTINCT raffle_id FROM {$pfx}rc_purchases) {$raf_where}"
        );

        $active_raffles = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$pfx}rc_raffles WHERE status = 'active' $raf_where"
        );

        $total_raffles = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT r.id)
             FROM {$pfx}rc_raffles r
             WHERE (r.status != 'deleted' OR r.id IN (SELECT DISTINCT raffle_id FROM {$pfx}rc_purchases)) {$raf_where}"
        );

        // Prize value: only sum for raffles that have revenue (actual sales)
        $total_prize_value = (float) $wpdb->get_var(
            "SELECT COALESCE(SUM(r.prize_value), 0)
             FROM {$pfx}rc_raffles r
             WHERE r.id IN (
                 SELECT DISTINCT raffle_id FROM {$pfx}rc_purchases
                 WHERE status IN ('completed', 'processing', 'on-hold')
             ) {$raf_where}"
        );

        $total_buyers = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT buyer_email) FROM {$pfx}rc_purchases WHERE status IN ('completed', 'processing', 'on-hold') $r_where"
        );

        $avg_ticket_price = (float) $wpdb->get_var(
            "SELECT COALESCE(AVG(r.ticket_price), 0)
             FROM {$pfx}rc_raffles r
             WHERE r.id IN (SELECT DISTINCT raffle_id FROM {$pfx}rc_purchases) {$raf_where}"
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
            'total_revenue'           => $this->convert( $total_revenue ),
            'total_tickets_sold'      => $total_tickets_sold,
            'total_tickets_available' => $total_tickets_available,
            'active_raffles'          => $active_raffles,
            'total_raffles'           => $total_raffles,
            'total_prize_value'       => $this->convert( $total_prize_value ),
            'net_profit'              => $this->convert( $total_revenue - $total_prize_value ),
            'total_buyers'            => $total_buyers,
            'avg_ticket_price'        => $this->convert( $avg_ticket_price ),
            'revenue_this_month'      => $this->convert( $revenue_this_month ),
            'revenue_last_month'      => $this->convert( $revenue_last_month ),
            'sell_rate'               => $total_tickets_available > 0 ? round( ( $total_tickets_sold / $total_tickets_available ) * 100, 1 ) : 0,
        );
    }

    private function get_revenue_by_raffle( $pfx, $raffle_id ) {
        global $wpdb;
        $raf_where = $raffle_id > 0 ? $wpdb->prepare( "AND r.id = %d", $raffle_id ) : "";

        $results = $wpdb->get_results(
            "SELECT r.id, r.title,
                    COALESCE(SUM(p.amount_paid), 0) AS revenue,
                    r.prize_value,
                    r.sold_tickets,
                    r.total_tickets
             FROM {$pfx}rc_raffles r
             INNER JOIN {$pfx}rc_purchases p ON p.raffle_id = r.id AND p.status IN ('completed', 'processing', 'on-hold')
             WHERE 1=1 {$raf_where}
             GROUP BY r.id
             ORDER BY revenue DESC"
        );

        foreach ( $results as $row ) {
            $row->revenue     = $this->convert( $row->revenue );
            $row->prize_value = $this->convert( $row->prize_value );
        }

        return $results;
    }

    private function get_raffle_progress( $pfx, $raffle_id ) {
        global $wpdb;

        if ( ! $raffle_id ) {
            return array( 'sold' => 0, 'available' => 0, 'total' => 0 );
        }

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT sold_tickets, total_tickets
             FROM {$pfx}rc_raffles
             WHERE id = %d",
            $raffle_id
        ) );

        if ( ! $row ) {
            return array( 'sold' => 0, 'available' => 0, 'total' => 0 );
        }

        return array(
            'sold'      => (int) $row->sold_tickets,
            'total'     => (int) $row->total_tickets,
            'available' => max( 0, (int) $row->total_tickets - (int) $row->sold_tickets )
        );
    }

    private function get_net_profit( $pfx, $raffle_id ) {
        global $wpdb;
        $raf_where = $raffle_id > 0 ? $wpdb->prepare( "AND r.id = %d", $raffle_id ) : "";

        $results = $wpdb->get_results(
            "SELECT r.id, r.title, r.prize_value,
                    COALESCE(SUM(p.amount_paid), 0) AS revenue,
                    (COALESCE(SUM(p.amount_paid), 0) - r.prize_value) AS net_profit
             FROM {$pfx}rc_raffles r
             INNER JOIN {$pfx}rc_purchases p ON p.raffle_id = r.id AND p.status IN ('completed', 'processing', 'on-hold')
             WHERE 1=1 {$raf_where}
             GROUP BY r.id
             ORDER BY net_profit DESC"
        );

        foreach ( $results as $row ) {
            $row->prize_value = $this->convert( $row->prize_value );
            $row->revenue     = $this->convert( $row->revenue );
            $row->net_profit  = $this->convert( $row->net_profit );
        }

        return $results;
    }

    private function get_sales_trend( $pfx, $period, $raffle_id ) {
        global $wpdb;
        $r_where = $raffle_id > 0 ? $wpdb->prepare( "AND raffle_id = %d", $raffle_id ) : "";

        switch ( $period ) {
            case 'today':
                $sql = "SELECT DATE_FORMAT(purchase_date, '%H:00') AS label,
                               COUNT(*) AS transactions,
                               COALESCE(SUM(amount_paid), 0) AS revenue,
                               COALESCE(SUM(quantity), 0) AS tickets
                        FROM {$pfx}rc_purchases
                        WHERE status IN ('completed', 'processing', 'on-hold') 
                          AND DATE(purchase_date) = CURDATE() {$r_where}
                        GROUP BY label
                        ORDER BY label ASC";
                break;

            case 'weekly':
                $sql = "SELECT DATE_FORMAT(DATE_ADD(purchase_date, INTERVAL -WEEKDAY(purchase_date) DAY), '%Y-%m-%d') AS label,
                               COUNT(*) AS transactions,
                               COALESCE(SUM(amount_paid), 0) AS revenue,
                               COALESCE(SUM(quantity), 0) AS tickets
                        FROM {$pfx}rc_purchases
                        WHERE status IN ('completed', 'processing', 'on-hold') {$r_where}
                        GROUP BY label
                        ORDER BY label ASC";
                break;

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

        $results = $wpdb->get_results( $sql );

        foreach ( $results as $row ) {
            $row->revenue = $this->convert( $row->revenue );
        }

        return $results;
    }

    private function get_top_buyers( $pfx, $raffle_id ) {
        global $wpdb;
        $r_where = $raffle_id > 0 ? $wpdb->prepare( "AND raffle_id = %d", $raffle_id ) : "";

        $results = $wpdb->get_results(
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

        foreach ( $results as $row ) {
            $row->total_spent = $this->convert( $row->total_spent );
        }

        return $results;
    }

    private function get_recent_transactions( $pfx, $raffle_id ) {
        global $wpdb;
        $where  = "1=1";
        $params = array();

        if ( $raffle_id > 0 ) {
            $where   .= " AND p.raffle_id = %d";
            $params[] = $raffle_id;
        }

        // Search filter
        $search = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '';
        if ( $search ) {
            $like     = '%' . $wpdb->esc_like( $search ) . '%';
            $where   .= " AND (p.buyer_name LIKE %s OR p.buyer_email LIKE %s OR r.title LIKE %s)";
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        // Status filter
        $status = isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '';
        if ( $status ) {
            $where   .= " AND p.status = %s";
            $params[] = $status;
        }

        // Pagination
        $page     = isset( $_GET['txn_page'] ) ? max( 1, absint( $_GET['txn_page'] ) ) : 1;
        $per_page = 15;
        $offset   = ( $page - 1 ) * $per_page;

        $count_sql = "SELECT COUNT(*) FROM {$pfx}rc_purchases p JOIN {$pfx}rc_raffles r ON r.id = p.raffle_id WHERE {$where}";
        $total     = empty( $params )
            ? (int) $wpdb->get_var( $count_sql )
            : (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) );

        $data_sql = "SELECT p.id, p.buyer_name, p.buyer_email, p.quantity, p.amount_paid,
                            p.status, p.purchase_date, r.title AS raffle_title
                     FROM {$pfx}rc_purchases p
                     JOIN {$pfx}rc_raffles r ON r.id = p.raffle_id
                     WHERE {$where}
                     ORDER BY p.purchase_date DESC
                     LIMIT {$per_page} OFFSET {$offset}";

        $rows = empty( $params )
            ? $wpdb->get_results( $data_sql )
            : $wpdb->get_results( $wpdb->prepare( $data_sql, $params ) );

        foreach ( $rows as $row ) {
            $row->amount_paid = $this->convert( $row->amount_paid );
        }

        return array(
            'rows'     => $rows,
            'total'    => $total,
            'page'     => $page,
            'pages'    => (int) ceil( $total / $per_page ),
            'per_page' => $per_page,
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
            "SELECT COALESCE(SUM(prize_value), 0) FROM {$pfx}rc_raffles
             WHERE id IN (
                 SELECT DISTINCT raffle_id FROM {$pfx}rc_purchases
                 WHERE status IN ('completed', 'processing', 'on-hold')
             ) {$raf_where}"
        );

        $net_profit = $total_revenue - $total_prize_value;

        return array(
            'total_revenue'    => $this->convert( $total_revenue ),
            'total_prize'      => $this->convert( $total_prize_value ),
            'net_profit'       => $this->convert( max( 0, $net_profit ) ),
            'deficit'          => $this->convert( $net_profit < 0 ? abs( $net_profit ) : 0 ),
        );
    }

    private function get_package_popularity( $pfx, $raffle_id ) {
        global $wpdb;
        $r_where = $raffle_id > 0 ? $wpdb->prepare( "AND raffle_id = %d", $raffle_id ) : "";

        $results = $wpdb->get_results(
            "SELECT quantity AS package_size,
                    COUNT(*) AS purchases,
                    COALESCE(SUM(amount_paid), 0) AS total_revenue
             FROM {$pfx}rc_purchases
             WHERE status IN ('completed', 'processing', 'on-hold') {$r_where}
             GROUP BY quantity
             ORDER BY purchases DESC
             LIMIT 10"
        );

        foreach ( $results as $row ) {
            $row->total_revenue = $this->convert( $row->total_revenue );
        }

        return $results;
    }

    private function get_cumulative_revenue( $pfx, $raffle_id ) {
        global $wpdb;
        $r_where = $raffle_id > 0 ? $wpdb->prepare( "AND p.raffle_id = %d", $raffle_id ) : "";
        $r2_where = $raffle_id > 0 ? $wpdb->prepare( "AND p2.raffle_id = %d", $raffle_id ) : "";

        $results = $wpdb->get_results(
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

        foreach ( $results as $row ) {
            $row->daily_revenue = $this->convert( $row->daily_revenue );
            $row->cumulative    = $this->convert( $row->cumulative );
        }

        return $results;
    }
}
