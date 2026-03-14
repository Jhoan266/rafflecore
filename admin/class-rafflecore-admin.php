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

        add_submenu_page( 'rafflecore', __( 'Dashboard', 'rafflecore' ), __( 'Dashboard', 'rafflecore' ), 'manage_options', 'rafflecore', array( $this, 'page_dashboard' ) );
        add_submenu_page( 'rafflecore', __( 'Crear Rifa', 'rafflecore' ), __( 'Crear Rifa', 'rafflecore' ), 'manage_options', 'rc-new', array( $this, 'page_form' ) );
        add_submenu_page( 'rafflecore', __( 'Rifas Activas', 'rafflecore' ), __( 'Rifas Activas', 'rafflecore' ), 'manage_options', 'rc-raffles', array( $this, 'page_list' ) );
        add_submenu_page( 'rafflecore', __( 'Compradores', 'rafflecore' ), __( 'Compradores', 'rafflecore' ), 'manage_options', 'rc-buyers', array( $this, 'page_buyers' ) );
        add_submenu_page( 'rafflecore', __( 'Cupones', 'rafflecore' ), __( 'Cupones', 'rafflecore' ), 'manage_options', 'rc-coupons', array( $this, 'page_coupons' ) );
        add_submenu_page( 'rafflecore', __( 'Actividad', 'rafflecore' ), __( 'Actividad', 'rafflecore' ), 'manage_options', 'rc-activity-log', array( $this, 'page_activity_log' ) );
        add_submenu_page( 'rafflecore', __( 'Webhooks', 'rafflecore' ), __( 'Webhooks', 'rafflecore' ), 'manage_options', 'rc-webhooks', array( $this, 'page_webhooks' ) );
        add_submenu_page( 'rafflecore', __( 'Configuración', 'rafflecore' ), __( 'Configuración', 'rafflecore' ), 'manage_options', 'rc-settings', array( $this, 'page_settings' ) );
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
            'i18n'     => array(
                'selectImage'    => __( 'Seleccionar Imagen del Premio', 'rafflecore' ),
                'useImage'       => __( 'Usar esta imagen', 'rafflecore' ),
                'noImage'        => __( 'Sin imagen', 'rafflecore' ),
                'selectFont'     => __( 'Seleccionar Archivo de Fuente', 'rafflecore' ),
                'useFont'        => __( 'Usar esta fuente', 'rafflecore' ),
                'noFont'         => __( 'Sin fuente cargada', 'rafflecore' ),
                'confirmDraw'    => __( '¿Estás seguro de realizar el sorteo? Esta acción es irreversible.', 'rafflecore' ),
                'drawing'        => __( 'Sorteando...', 'rafflecore' ),
                'drawButton'     => __( 'Realizar Sorteo', 'rafflecore' ),
                'connectionError'=> __( 'Error de conexión. Intenta de nuevo.', 'rafflecore' ),
                'enterNumber'    => __( 'Ingresa un número de boleto válido.', 'rafflecore' ),
                'enterMessage'   => __( 'Escribe un mensaje para el ganador o selecciona una plantilla.', 'rafflecore' ),
                'confirmExternal'=> __( '¿Confirmas el número ganador? Se notificará al dueño del boleto.', 'rafflecore' ),
                'searching'      => __( 'Buscando...', 'rafflecore' ),
                'searchNotify'   => __( 'Buscar y Notificar Ganador', 'rafflecore' ),
            ),
        ) );

        // Dashboard page — Chart.js + dashboard.js
        if ( $hook === 'toplevel_page_rafflecore' ) {
            wp_enqueue_script( 'chartjs', 'https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js', array(), '4.4.7', true );
            wp_enqueue_script( 'rc-dashboard', RAFFLECORE_URL . 'assets/js/dashboard.js', array( 'jquery', 'chartjs' ), RAFFLECORE_VERSION, true );
            wp_localize_script( 'rc-dashboard', 'rcDashboard', array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'rc_analytics_nonce' ),
                'currency' => get_option( 'rafflecore_currency', 'COP' ),
                'i18n'     => array(
                    'noData'       => __( 'Sin datos aún.', 'rafflecore' ),
                    'completed'    => __( 'Completado', 'rafflecore' ),
                    'pending'      => __( 'Pendiente', 'rafflecore' ),
                    'loading'      => __( 'Cargando...', 'rafflecore' ),
                    'refresh'      => __( 'Actualizar', 'rafflecore' ),
                    'revenue'      => __( 'Ingresos', 'rafflecore' ),
                    'sold'         => __( 'Vendidos', 'rafflecore' ),
                    'available'    => __( 'Disponibles', 'rafflecore' ),
                    'netProfit'    => __( 'Ganancia Neta', 'rafflecore' ),
                    'tickets'      => __( 'Boletos', 'rafflecore' ),
                    'prizes'       => __( 'Premios', 'rafflecore' ),
                    'profit'       => __( 'Ganancia', 'rafflecore' ),
                    'deficit'      => __( 'Déficit', 'rafflecore' ),
                    'packages'     => __( 'boletos', 'rafflecore' ),
                    'purchases'    => __( 'Compras', 'rafflecore' ),
                    'cumulative'   => __( 'Acumulado', 'rafflecore' ),
                    'dailyRevenue' => __( 'Ingreso Diario', 'rafflecore' ),
                ),
            ) );
        }
    }

    // ─── Form Handling ──────────────────────────────────────────

    public function handle_form() {
        // Save raffle
        if ( isset( $_POST['rc_save_raffle'] ) ) {
            if ( isset( $_GET['debug'] ) ) die( print_r( $_POST, true ) );
            RaffleCore_Logger::log( 'debug', 'system', 0, 'Form submission detected: rc_save_raffle' );
            if ( ! current_user_can( 'manage_options' ) ) {
                RaffleCore_Logger::log( 'debug', 'system', 0, 'Permission denied for user' );
                return;
            }
            if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['rc_nonce'] ?? '' ) ), 'rc_save_raffle' ) ) {
                wp_die( __( 'Error de seguridad.', 'rafflecore' ) );
            }

            $raffle_id = isset( $_POST['raffle_id'] ) ? absint( $_POST['raffle_id'] ) : 0;
            $data      = RaffleCore_Raffle_Service::prepare_data( $_POST, $raffle_id );

            error_log( "[RaffleCore] Handling form for Raffle ID: $raffle_id. Title: " . $data['title'] );

            if ( $raffle_id ) {
                RaffleCore_Logger::log( 'debug', 'system', 0, 'Updating raffle ID: ' . $raffle_id );
                $result = $this->api->update_raffle( $raffle_id, $data );
                if ( false === $result ) {
                    $msg = __( 'Error al actualizar la rifa en la base de datos.', 'rafflecore' );
                    RaffleCore_Logger::log( 'debug', 'system', $raffle_id, 'Update failed strictly: result === false' );
                    error_log( "[RaffleCore] Update failed strictly for ID $raffle_id" );
                    wp_die( $msg );
                }
                if ( is_wp_error( $result ) ) {
                    $msg = $result->get_error_message();
                    RaffleCore_Logger::log( 'debug', 'system', $raffle_id, 'Update failed (WP_Error): ' . $msg );
                    error_log( "[RaffleCore] Update failed (WP_Error) for ID $raffle_id: $msg" );
                    wp_die( $msg );
                }
                
                RaffleCore_Logger::log( 'debug', 'system', $raffle_id, 'Update successful (or no changes detected)' );
                error_log( "[RaffleCore] Update successful for ID $raffle_id" );
                RaffleCore_Logger::log( 'raffle_updated', 'raffle', $raffle_id, $data['title'] );
            } else {
                $new_id = $this->api->create_raffle( $data );
                if ( ! $new_id || is_wp_error( $new_id ) ) {
                    $msg = is_wp_error( $new_id ) ? $new_id->get_error_message() : __( 'Error desconocido al crear la rifa.', 'rafflecore' );
                    wp_die( $msg );
                }
                RaffleCore_Logger::log( 'raffle_created', 'raffle', is_numeric( $new_id ) ? $new_id : 0, $data['title'] );
                RaffleCore_Webhook_Service::fire( 'raffle.created', array(
                    'raffle_id' => is_numeric( $new_id ) ? $new_id : 0,
                    'title'     => $data['title'],
                ) );
            }

            wp_safe_redirect( admin_url( 'admin.php?page=rc-raffles&msg=saved' ) );
            exit;
        }

        // Delete raffle (POST only)
        if ( isset( $_POST['rc_action'] ) && $_POST['rc_action'] === 'delete' && isset( $_POST['id'] ) ) {
            if ( ! current_user_can( 'manage_options' ) ) return;
            if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) ), 'rc_delete_raffle' ) ) {
                wp_die( __( 'Error de seguridad.', 'rafflecore' ) );
            }

            $this->api->delete_raffle( absint( $_POST['id'] ) );
            RaffleCore_Logger::log( 'raffle_deleted', 'raffle', absint( $_POST['id'] ) );
            wp_safe_redirect( admin_url( 'admin.php?page=rc-raffles&msg=deleted' ) );
            exit;
        }

        // Save settings
        if ( isset( $_POST['rc_save_settings'] ) ) {
            if ( ! current_user_can( 'manage_options' ) ) return;
            if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['rc_nonce'] ?? '' ) ), 'rc_save_settings' ) ) {
                wp_die( __( 'Error de seguridad.', 'rafflecore' ) );
            }

            update_option( 'rafflecore_mode', sanitize_text_field( wp_unslash( $_POST['rc_mode'] ?? 'local' ) ) );
            update_option( 'rafflecore_api_url', esc_url_raw( wp_unslash( $_POST['rc_api_url'] ?? '' ) ) );
            update_option( 'rafflecore_api_key', sanitize_text_field( wp_unslash( $_POST['rc_api_key'] ?? '' ) ) );

            $allowed_currencies = array( 'COP', 'USD', 'EUR', 'MXN', 'ARS', 'BRL', 'PEN', 'CLP', 'VES' );
            
            $base_currency = sanitize_text_field( wp_unslash( $_POST['rc_base_currency'] ?? 'COP' ) );
            if ( in_array( $base_currency, $allowed_currencies, true ) ) {
                update_option( 'rafflecore_base_currency', $base_currency );
            }

            $currency = sanitize_text_field( wp_unslash( $_POST['rc_currency'] ?? 'COP' ) );
            if ( in_array( $currency, $allowed_currencies, true ) ) {
                update_option( 'rafflecore_currency', $currency );
            }

            RaffleCore_Logger::log( 'settings_updated', 'settings', 0 );

            wp_safe_redirect( admin_url( 'admin.php?page=rc-settings&msg=saved' ) );
            exit;
        }

        // Save coupon
        if ( isset( $_POST['rc_save_coupon'] ) || ( isset( $_POST['coupon_code'] ) && ! isset( $_POST['action'] ) ) ) {
            $this->handle_save_coupon();
        }

        // Delete coupon
        if ( isset( $_POST['rc_action'] ) && $_POST['rc_action'] === 'delete_coupon' ) {
            if ( ! current_user_can( 'manage_options' ) ) return;
            if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) ), 'rc_delete_coupon' ) ) {
                RaffleCore_Coupon_Model::delete( absint( $_POST['id'] ) );
                wp_safe_redirect( admin_url( 'admin.php?page=rc-coupons&msg=coupon_deleted' ) );
                exit;
            }
        }

        // Save webhook
        if ( isset( $_POST['rc_save_webhook'] ) || ( isset( $_POST['webhook_url'] ) && ! isset( $_POST['action'] ) ) ) {
            $this->handle_save_webhook();
        }

        // Delete webhook
        if ( isset( $_POST['rc_action'] ) && $_POST['rc_action'] === 'delete_webhook' ) {
            if ( ! current_user_can( 'manage_options' ) ) return;
            if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) ), 'rc_delete_webhook' ) ) {
                RaffleCore_Webhook_Service::delete( absint( $_POST['id'] ) );
                wp_safe_redirect( admin_url( 'admin.php?page=rc-webhooks&msg=webhook_deleted' ) );
                exit;
            }
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
            echo '<div class="wrap"><h1>' . esc_html__( 'Rifa no encontrada', 'rafflecore' ) . '</h1></div>';
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

    public function page_coupons() {
        $coupons = RaffleCore_Coupon_Model::get_all();
        $all_raffles = $this->api->get_all_raffles( array( 'per_page' => 100 ) );
        include RAFFLECORE_PATH . 'admin/views/coupons.php';
    }

    public function handle_save_coupon() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['rc_nonce'] ?? '' ) ), 'rc_save_coupon' ) ) {
                wp_die( __( 'Error de seguridad.', 'rafflecore' ) );
        }

        $data = RaffleCore_Coupon_Service::prepare_data( $_POST );
        RaffleCore_Coupon_Model::create( $data );

        RaffleCore_Logger::log( 'coupon_created', 'coupon', 0, $data['code'] );

        wp_safe_redirect( admin_url( 'admin.php?page=rc-coupons&msg=coupon_saved' ) );
        exit;
    }

    public function page_activity_log() {
        $page     = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
        $per_page = 30;
        $logs     = RaffleCore_Logger::get_entries( array(
            'per_page' => $per_page,
            'offset'   => ( $page - 1 ) * $per_page,
        ) );
        $total = RaffleCore_Logger::count();
        $pages = ceil( $total / $per_page );

        include RAFFLECORE_PATH . 'admin/views/activity-log.php';
    }

    public function page_webhooks() {
        global $wpdb;
        $webhooks = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}rc_webhooks ORDER BY created_at DESC" );
        include RAFFLECORE_PATH . 'admin/views/webhooks.php';
    }

    public function handle_save_webhook() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['rc_nonce'] ?? '' ) ), 'rc_save_webhook' ) ) {
            wp_die( __( 'Error de seguridad.', 'rafflecore' ) );
        }

        $event = sanitize_text_field( wp_unslash( $_POST['webhook_event'] ?? '' ) );
        $url   = esc_url_raw( wp_unslash( $_POST['webhook_url'] ?? '' ) );

        if ( $event && $url ) {
            RaffleCore_Webhook_Service::create( $event, $url );
        }

        wp_safe_redirect( admin_url( 'admin.php?page=rc-webhooks&msg=webhook_saved' ) );
        exit;
    }
}
