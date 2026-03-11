<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Admin Controller — Menús, assets, manejo de formularios.
 */
class RaffleCore_Admin {

    private $api;

    public function __construct( $api ) {
        $this->api = $api;
    }

    public function add_menus() {
        add_menu_page(
            'RaffleCore',
            'RaffleCore',
            'manage_options',
            'rafflecore',
            array( $this, 'page_dashboard' ),
            'dashicons-tickets-alt',
            30
        );

        add_submenu_page( 'rafflecore', 'Dashboard', 'Dashboard', 'manage_options', 'rafflecore', array( $this, 'page_dashboard' ) );
        add_submenu_page( 'rafflecore', 'Crear Rifa', 'Crear Rifa', 'manage_options', 'rc-new', array( $this, 'page_form' ) );
        add_submenu_page( 'rafflecore', 'Rifas Activas', 'Rifas Activas', 'manage_options', 'rc-raffles', array( $this, 'page_list' ) );
        add_submenu_page( 'rafflecore', 'Compradores', 'Compradores', 'manage_options', 'rc-buyers', array( $this, 'page_buyers' ) );
        add_submenu_page( 'rafflecore', 'Configuración', 'Configuración', 'manage_options', 'rc-settings', array( $this, 'page_settings' ) );
    }

    public function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'rafflecore' ) === false && strpos( $hook, 'rc-' ) === false ) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_style( 'rc-admin', RAFFLECORE_URL . 'assets/css/admin.css', array(), RAFFLECORE_VERSION );
        wp_enqueue_script( 'rc-admin', RAFFLECORE_URL . 'assets/js/admin.js', array( 'jquery' ), RAFFLECORE_VERSION, true );
        wp_localize_script( 'rc-admin', 'rcAdmin', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'rc_admin_nonce' ),
        ) );
    }

    // ─── Form Handling ──────────────────────────────────────────

    public function handle_form() {
        // Save raffle
        if ( isset( $_POST['rc_save_raffle'] ) ) {
            if ( ! current_user_can( 'manage_options' ) ) return;
            if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['rc_nonce'] ?? '' ) ), 'rc_save_raffle' ) ) {
                wp_die( 'Error de seguridad.' );
            }

            $data      = RaffleCore_Raffle_Service::prepare_data( $_POST );
            $raffle_id = isset( $_POST['raffle_id'] ) ? absint( $_POST['raffle_id'] ) : 0;

            if ( $raffle_id ) {
                $this->api->update_raffle( $raffle_id, $data );
            } else {
                $this->api->create_raffle( $data );
            }

            wp_safe_redirect( admin_url( 'admin.php?page=rc-raffles&msg=saved' ) );
            exit;
        }

        // Delete raffle
        if ( isset( $_GET['rc_action'] ) && $_GET['rc_action'] === 'delete' && isset( $_GET['id'] ) ) {
            if ( ! current_user_can( 'manage_options' ) ) return;
            if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) ), 'rc_delete_raffle' ) ) {
                wp_die( 'Error de seguridad.' );
            }

            $this->api->delete_raffle( absint( $_GET['id'] ) );
            wp_safe_redirect( admin_url( 'admin.php?page=rc-raffles&msg=deleted' ) );
            exit;
        }

        // Save settings
        if ( isset( $_POST['rc_save_settings'] ) ) {
            if ( ! current_user_can( 'manage_options' ) ) return;
            if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['rc_nonce'] ?? '' ) ), 'rc_save_settings' ) ) {
                wp_die( 'Error de seguridad.' );
            }

            update_option( 'rafflecore_mode', sanitize_text_field( wp_unslash( $_POST['rc_mode'] ?? 'local' ) ) );
            update_option( 'rafflecore_api_url', esc_url_raw( wp_unslash( $_POST['rc_api_url'] ?? '' ) ) );
            update_option( 'rafflecore_api_key', sanitize_text_field( wp_unslash( $_POST['rc_api_key'] ?? '' ) ) );

            wp_safe_redirect( admin_url( 'admin.php?page=rc-settings&msg=saved' ) );
            exit;
        }
    }

    // ─── Pages ──────────────────────────────────────────────────

    public function page_dashboard() {
        $stats = $this->api->get_dashboard_stats();
        include RAFFLECORE_PATH . 'admin/views/dashboard.php';
    }

    public function page_list() {
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'view' && isset( $_GET['id'] ) ) {
            $this->page_details();
            return;
        }
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'edit' && isset( $_GET['id'] ) ) {
            $this->page_form();
            return;
        }

        $page     = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
        $per_page = 15;
        $raffles  = $this->api->get_all_raffles( array(
            'per_page' => $per_page,
            'offset'   => ( $page - 1 ) * $per_page,
        ) );
        $total    = RaffleCore_Raffle_Model::count();
        $pages    = ceil( $total / $per_page );

        include RAFFLECORE_PATH . 'admin/views/raffle-list.php';
    }

    public function page_form() {
        $raffle = null;
        if ( isset( $_GET['id'] ) ) {
            $raffle = $this->api->get_raffle( absint( $_GET['id'] ) );
        }
        include RAFFLECORE_PATH . 'admin/views/raffle-form.php';
    }

    private function page_details() {
        $raffle_id = absint( $_GET['id'] );
        $raffle    = $this->api->get_raffle( $raffle_id );

        if ( ! $raffle ) {
            echo '<div class="wrap"><h1>Rifa no encontrada</h1></div>';
            return;
        }

        $purchases = $this->api->get_purchases_by_raffle( $raffle_id );

        $winner = null;
        if ( $raffle->winner_ticket_id ) {
            $winner = RaffleCore_Ticket_Model::find( $raffle->winner_ticket_id );
            if ( $winner ) {
                $purchase_data = RaffleCore_Purchase_Model::find( $winner->purchase_id );
                $winner->buyer_name = $purchase_data ? $purchase_data->buyer_name : '';
            }
        }

        include RAFFLECORE_PATH . 'admin/views/raffle-details.php';
    }

    public function page_buyers() {
        $page     = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
        $per_page = 20;
        $search   = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
        $filter_raffle = isset( $_GET['raffle_id'] ) ? absint( $_GET['raffle_id'] ) : 0;

        $args = array(
            'per_page' => $per_page,
            'offset'   => ( $page - 1 ) * $per_page,
        );
        if ( $search ) $args['search'] = $search;
        if ( $filter_raffle ) $args['raffle_id'] = $filter_raffle;

        $buyers = $this->api->get_all_buyers( $args );
        $total  = RaffleCore_Purchase_Model::count_buyers( $args );
        $pages  = ceil( $total / $per_page );
        $all_raffles = $this->api->get_all_raffles( array( 'per_page' => 100 ) );

        include RAFFLECORE_PATH . 'admin/views/buyers.php';
    }

    public function page_settings() {
        include RAFFLECORE_PATH . 'admin/views/settings.php';
    }
}
