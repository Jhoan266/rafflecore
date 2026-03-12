<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div class="wrap rc-wrap">
    <div class="rc-dash-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
        <h1 class="rc-title" style="margin: 0;">📊 <?php esc_html_e( 'RaffleCore — Dashboard Analítico', 'rafflecore' ); ?></h1>
        
        <div class="rc-dash-actions" style="display: flex; gap: 10px; align-items: center;">
            <select id="rc-raffle-filter" class="rc-form-control rc-select" style="min-width: 250px;">
                <option value="0"><?php esc_html_e( 'Resumen Global (Todas las rifas)', 'rafflecore' ); ?></option>
                <?php
                global $wpdb;
                $raffles = $wpdb->get_results( "SELECT id, title FROM {$wpdb->prefix}rc_raffles WHERE status != 'deleted' ORDER BY created_at DESC" );
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
            🏷️ <?php esc_html_e( 'Precio Promedio', 'rafflecore' ); ?>: $<span>—</span>
        </div>
        <div class="rc-kpi-pill" id="kpi-month-trend">
            <span class="rc-trend-arrow" id="kpi-trend-icon">📈</span> <?php esc_html_e( 'Este Mes', 'rafflecore' ); ?>: $<span>—</span>
        </div>
    </div>

    <!-- Charts Row 1: Revenue + Tickets -->
    <div class="rc-dashboard-row rc-row-equal">
        <div class="rc-panel">
            <h2>📊 <?php esc_html_e( 'Ingresos por Rifa', 'rafflecore' ); ?></h2>
            <div class="rc-chart-container">
                <canvas id="chart-revenue-raffle"></canvas>
            </div>
        </div>
        <div class="rc-panel">
            <h2>🎫 <?php esc_html_e( 'Boletos Vendidos por Rifa', 'rafflecore' ); ?></h2>
            <div class="rc-chart-container">
                <canvas id="chart-tickets-raffle"></canvas>
            </div>
        </div>
    </div>

    <!-- Charts Row 2: Net Profit + Sales Trend -->
    <div class="rc-dashboard-row rc-row-equal">
        <div class="rc-panel">
            <h2>💎 <?php esc_html_e( 'Ganancia Neta por Rifa', 'rafflecore' ); ?></h2>
            <p class="rc-chart-subtitle"><?php esc_html_e( 'Ingresos menos el valor del premio', 'rafflecore' ); ?></p>
            <div class="rc-chart-container">
                <canvas id="chart-net-profit"></canvas>
            </div>
        </div>
        <div class="rc-panel">
            <h2>📈 <?php esc_html_e( 'Tendencia de Ventas', 'rafflecore' ); ?></h2>
            <div class="rc-chart-toolbar">
                <button class="rc-chip rc-chip-active" data-period="daily"><?php esc_html_e( 'Diario', 'rafflecore' ); ?></button>
                <button class="rc-chip" data-period="monthly"><?php esc_html_e( 'Mensual', 'rafflecore' ); ?></button>
                <button class="rc-chip" data-period="annual"><?php esc_html_e( 'Anual', 'rafflecore' ); ?></button>
            </div>
            <div class="rc-chart-container">
                <canvas id="chart-sales-trend"></canvas>
            </div>
        </div>
    </div>

    <!-- Row 3: Top Buyers + Recent Transactions -->
    <div class="rc-dashboard-row rc-row-equal">
        <div class="rc-panel">
            <h2>🏆 <?php esc_html_e( 'Top 10 Compradores', 'rafflecore' ); ?></h2>
            <table class="rc-table" id="table-top-buyers">
                <thead>
                    <tr>
                        <th>#</th>
                        <th><?php esc_html_e( 'Nombre', 'rafflecore' ); ?></th>
                        <th><?php esc_html_e( 'Compras', 'rafflecore' ); ?></th>
                        <th><?php esc_html_e( 'Boletos', 'rafflecore' ); ?></th>
                        <th><?php esc_html_e( 'Gastado', 'rafflecore' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td colspan="5" class="rc-empty">⏳ <?php esc_html_e( 'Cargando...', 'rafflecore' ); ?></td></tr>
                </tbody>
            </table>
        </div>
        <div class="rc-panel">
            <h2>🧾 <?php esc_html_e( 'Últimas Transacciones', 'rafflecore' ); ?></h2>
            <table class="rc-table" id="table-recent-txns">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Rifa', 'rafflecore' ); ?></th>
                        <th><?php esc_html_e( 'Comprador', 'rafflecore' ); ?></th>
                        <th><?php esc_html_e( 'Boletos', 'rafflecore' ); ?></th>
                        <th><?php esc_html_e( 'Total', 'rafflecore' ); ?></th>
                        <th><?php esc_html_e( 'Estado', 'rafflecore' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td colspan="5" class="rc-empty">⏳ <?php esc_html_e( 'Cargando...', 'rafflecore' ); ?></td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <!-- Charts Row 3: Revenue vs Prize + Package Popularity -->
    <div class="rc-dashboard-row rc-row-equal">
        <div class="rc-panel">
            <h2>💎 <?php esc_html_e( 'Distribución Financiera', 'rafflecore' ); ?></h2>
            <p class="rc-chart-subtitle"><?php esc_html_e( 'Ingresos destinados a premios vs ganancia neta', 'rafflecore' ); ?></p>
            <div class="rc-chart-container" style="height:280px;">
                <canvas id="chart-revenue-vs-prize"></canvas>
            </div>
        </div>
        <div class="rc-panel">
            <h2>📦 <?php esc_html_e( 'Popularidad de Paquetes', 'rafflecore' ); ?></h2>
            <p class="rc-chart-subtitle"><?php esc_html_e( 'Qué paquetes de boletos se venden más', 'rafflecore' ); ?></p>
            <div class="rc-chart-container" style="height:280px;">
                <canvas id="chart-package-popularity"></canvas>
            </div>
        </div>
    </div>

    <!-- Charts Row 4: Cumulative Revenue (full width) -->
    <div class="rc-panel">
        <h2>💰 <?php esc_html_e( 'Ingresos Acumulados', 'rafflecore' ); ?></h2>
        <p class="rc-chart-subtitle"><?php esc_html_e( 'Crecimiento total del negocio en el tiempo', 'rafflecore' ); ?></p>
        <div class="rc-chart-container">
            <canvas id="chart-cumulative-revenue"></canvas>
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
