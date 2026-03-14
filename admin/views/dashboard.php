<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div class="wrap rc-wrap">
    <div class="rc-dash-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
        <h1 class="rc-title" style="margin: 0;">📊 <?php esc_html_e( 'RaffleCore — Dashboard Analítico', 'rafflecore' ); ?></h1>
        
        <div class="rc-dash-actions" style="display: flex; gap: 10px; align-items: center;">
            <select id="rc-raffle-filter" class="rc-form-control rc-select" style="min-width: 250px;">
                <option value="0"><?php esc_html_e( 'Resumen Global (Todas las rifas)', 'rafflecore' ); ?></option>
                <?php
                global $wpdb;
                $raffles = $wpdb->get_results(
                    "SELECT DISTINCT r.id, r.title FROM {$wpdb->prefix}rc_raffles r
                     WHERE r.status != 'deleted'
                        OR r.id IN (SELECT DISTINCT raffle_id FROM {$wpdb->prefix}rc_purchases)
                     ORDER BY r.created_at DESC"
                );
                foreach ( $raffles as $r ) {
                    echo '<option value="' . esc_attr( $r->id ) . '">' . esc_html( $r->title ) . '</option>';
                }
                ?>
            </select>
            <button id="rc-refresh-dashboard" class="rc-btn rc-btn-secondary rc-btn-sm">
                🔄 <?php esc_html_e( 'Actualizar', 'rafflecore' ); ?>
            </button>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="rc-stats-grid rc-stats-grid-5">
        <div class="rc-stat-card rc-card-blue">
            <div class="rc-stat-icon">💰</div>
            <div class="rc-stat-body">
                <span class="rc-stat-number" id="kpi-revenue">—</span>
                <span class="rc-stat-label"><?php esc_html_e( 'Ingresos Totales', 'rafflecore' ); ?></span>
            </div>
        </div>
        <div class="rc-stat-card rc-card-green">
            <div class="rc-stat-icon">📈</div>
            <div class="rc-stat-body">
                <span class="rc-stat-number" id="kpi-net-profit">—</span>
                <span class="rc-stat-label"><?php esc_html_e( 'Ganancia Neta', 'rafflecore' ); ?></span>
            </div>
        </div>
        <div class="rc-stat-card rc-card-purple">
            <div class="rc-stat-icon">🎫</div>
            <div class="rc-stat-body">
                <span class="rc-stat-number" id="kpi-tickets">—</span>
                <span class="rc-stat-label"><?php esc_html_e( 'Boletos Vendidos', 'rafflecore' ); ?></span>
            </div>
        </div>
        <div class="rc-stat-card rc-card-teal">
            <div class="rc-stat-icon">👥</div>
            <div class="rc-stat-body">
                <span class="rc-stat-number" id="kpi-buyers">—</span>
                <span class="rc-stat-label"><?php esc_html_e( 'Compradores Únicos', 'rafflecore' ); ?></span>
            </div>
        </div>
        <div class="rc-stat-card rc-card-orange">
            <div class="rc-stat-icon">📊</div>
            <div class="rc-stat-body">
                <span class="rc-stat-number" id="kpi-sell-rate">—</span>
                <span class="rc-stat-label"><?php esc_html_e( 'Tasa de Venta', 'rafflecore' ); ?></span>
            </div>
        </div>
    </div>

    <!-- Secondary KPIs -->
    <div class="rc-kpi-secondary">
        <div class="rc-kpi-pill" id="kpi-active-raffles">
            🎯 <span>—</span> <?php esc_html_e( 'Rifas Activas', 'rafflecore' ); ?>
        </div>
        <div class="rc-kpi-pill" id="kpi-total-raffles">
            📋 <span>—</span> <?php esc_html_e( 'Total Rifas', 'rafflecore' ); ?>
        </div>
        <div class="rc-kpi-pill" id="kpi-avg-price">
            🏷️ <?php esc_html_e( 'Precio Promedio', 'rafflecore' ); ?>: <span>—</span>
        </div>
        <div class="rc-kpi-pill" id="kpi-month-trend">
            <span class="rc-trend-arrow" id="kpi-trend-icon">📈</span> <?php esc_html_e( 'Este Mes', 'rafflecore' ); ?>: <span>—</span>
        </div>
    </div>

    <!-- Asymmetric Bento Row 1: 2fr 1fr -->
    <div class="rc-bento-row rc-bento-70-30" style="margin-bottom: 20px;">
        <!-- Col 1: Main Trend (Always Show) -->
        <div class="rc-panel rc-panel-flex">
            <h2>📈 <?php esc_html_e( 'Desempeño y Crecimiento', 'rafflecore' ); ?></h2>
            <p class="rc-chart-subtitle"><?php esc_html_e( 'Evolución de ventas en el tiempo', 'rafflecore' ); ?></p>
            <div class="rc-chart-toolbar">
                <button class="rc-chip" data-period="today"><?php esc_html_e( 'Hoy', 'rafflecore' ); ?></button>
                <button class="rc-chip rc-chip-active" data-period="daily"><?php esc_html_e( 'Diario', 'rafflecore' ); ?></button>
                <button class="rc-chip" data-period="weekly"><?php esc_html_e( 'Semanal', 'rafflecore' ); ?></button>
                <button class="rc-chip" data-period="monthly"><?php esc_html_e( 'Mensual', 'rafflecore' ); ?></button>
                <button class="rc-chip" data-period="annual"><?php esc_html_e( 'Anual', 'rafflecore' ); ?></button>
            </div>
            <div class="rc-chart-container" style="flex: 1; min-height: 350px;">
                <canvas id="chart-main-trend"></canvas>
            </div>
        </div>

        <!-- Col 2: Dynamic Contextual Panel -->
        <div class="rc-bento-col-stack">
            <!-- Global View Ranking Panel (Hidden when filtering single raffle) -->
            <div class="rc-panel rc-panel-flex rc-comparative-panel" style="height: 100%; margin-bottom: 0;">
                <h2>👑 <?php esc_html_e( 'Compradores Frecuentes', 'rafflecore' ); ?></h2>
                <div style="flex: 1; overflow-y: auto;">
                    <table class="rc-table" id="table-top-buyers">
                        <thead>
                            <tr>
                                <th style="width: 40px;">#</th>
                                <th><?php esc_html_e( 'Cliente', 'rafflecore' ); ?></th>
                                <th><?php esc_html_e( 'Compras', 'rafflecore' ); ?></th>
                                <th><?php esc_html_e( 'Boletos', 'rafflecore' ); ?></th>
                                <th><?php esc_html_e( 'Gasto Total', 'rafflecore' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Loaded via AJAX -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Single View Progress Panel (Only visible for one raffle) -->
            <div class="rc-panel rc-panel-flex rc-single-panel" style="height: 100%; margin-bottom: 0; display: none;">
                <h2>🎯 <?php esc_html_e( 'Progreso de Venta', 'rafflecore' ); ?></h2>
                <p class="rc-chart-subtitle"><?php esc_html_e( 'Boletos vs Disponibles', 'rafflecore' ); ?></p>
                <div class="rc-chart-container" style="flex: 1; min-height: 300px; display: flex; align-items: center; justify-content: center;">
                     <canvas id="chart-raffle-progress"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Asymmetric Bento Row 2: 1fr 2fr -->
    <div class="rc-bento-row rc-bento-30-70" style="margin-bottom: 20px;">
        <!-- Col 1: Rentabilidad -->
        <div class="rc-panel rc-panel-flex" style="text-align: center;">
            <h2 style="text-align: left;">💎 <?php esc_html_e( 'Rentabilidad', 'rafflecore' ); ?></h2>
            <p class="rc-chart-subtitle" style="text-align: left;"><?php esc_html_e( 'Costo premio vs Ganancia', 'rafflecore' ); ?></p>
            <div class="rc-chart-container" style="flex: 1; min-height: 280px; max-width: 100%; margin: 0 auto;">
                <canvas id="chart-revenue-vs-prize"></canvas>
            </div>
        </div>
        
        <!-- Col 2: Preferencias Paquetes -->
        <div class="rc-panel rc-panel-flex">
            <h2>📦 <?php esc_html_e( 'Preferencias de Paquetes', 'rafflecore' ); ?></h2>
            <p class="rc-chart-subtitle"><?php esc_html_e( 'Qué cantidades compran más los usuarios', 'rafflecore' ); ?></p>
            <div class="rc-chart-container" style="flex: 1; min-height: 280px;">
                <canvas id="chart-package-popularity"></canvas>
            </div>
        </div>
    </div>

    <!-- Row: Top Buyers + Recent Transactions -->
    <div class="rc-panel">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h2 style="margin: 0;">📝 <?php esc_html_e( 'Últimas Transacciones', 'rafflecore' ); ?></h2>
            <div style="display: flex; gap: 10px; align-items: center;">
                <input type="text" id="rc-txn-search" class="rc-form-control" style="width: 250px;" placeholder="<?php esc_html_e( 'Buscar por nombre, correo, teléfono...', 'rafflecore' ); ?>">
                <select id="rc-txn-status" class="rc-form-control rc-select" style="width: 150px;">
                    <option value=""><?php esc_html_e( 'Todos los estados', 'rafflecore' ); ?></option>
                    <option value="completed"><?php esc_html_e( 'Completado', 'rafflecore' ); ?></option>
                    <option value="processing"><?php esc_html_e( 'Procesando', 'rafflecore' ); ?></option>
                    <option value="on-hold"><?php esc_html_e( 'En espera', 'rafflecore' ); ?></option>
                    <option value="cancelled"><?php esc_html_e( 'Cancelado', 'rafflecore' ); ?></option>
                    <option value="failed"><?php esc_html_e( 'Fallido', 'rafflecore' ); ?></option>
                </select>
                <button id="rc-export-txns" class="rc-btn rc-btn-secondary">
                    ⬇️ <?php esc_html_e( 'Exportar', 'rafflecore' ); ?>
                </button>
            </div>
        </div>
        <div style="overflow-x: auto;">
            <table class="rc-table" id="table-recent-txns">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Rifa', 'rafflecore' ); ?></th>
                        <th><?php esc_html_e( 'Comprador', 'rafflecore' ); ?></th>
                        <th><?php esc_html_e( 'Boletos', 'rafflecore' ); ?></th>
                        <th><?php esc_html_e( 'Pagado', 'rafflecore' ); ?></th>
                        <th><?php esc_html_e( 'Estado', 'rafflecore' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Loaded via AJAX -->
                </tbody>
            </table>
        </div>
        <div id="rc-txn-pagination" style="display: flex; justify-content: flex-end; align-items: center; gap: 10px; margin-top: 16px;">
            <!-- Loaded via AJAX -->
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="rc-panel">
        <h2>⚡ <?php esc_html_e( 'Acciones Rápidas', 'rafflecore' ); ?></h2>
        <div class="rc-quick-actions rc-quick-actions-row">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=rc-new' ) ); ?>" class="rc-btn rc-btn-primary">
                ➕ <?php esc_html_e( 'Crear Nueva Rifa', 'rafflecore' ); ?>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=rc-raffles' ) ); ?>" class="rc-btn rc-btn-secondary">
                📋 <?php esc_html_e( 'Ver Rifas Activas', 'rafflecore' ); ?>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=rc-buyers' ) ); ?>" class="rc-btn rc-btn-secondary">
                👥 <?php esc_html_e( 'Ver Compradores', 'rafflecore' ); ?>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=rc-coupons' ) ); ?>" class="rc-btn rc-btn-secondary">
                🎟️ <?php esc_html_e( 'Cupones', 'rafflecore' ); ?>
            </a>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=rc-activity-log' ) ); ?>" class="rc-btn rc-btn-secondary">
                📋 <?php esc_html_e( 'Actividad', 'rafflecore' ); ?>
            </a>
        </div>
    </div>
</div>
