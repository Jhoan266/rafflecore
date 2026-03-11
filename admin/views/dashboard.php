<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap rc-wrap">
    <h1 class="rc-title">🎰 RaffleCore — Dashboard</h1>

    <div class="rc-stats-grid">
        <div class="rc-stat-card rc-card-blue">
            <div class="rc-stat-icon">🎟️</div>
            <div class="rc-stat-body">
                <span class="rc-stat-number"><?php echo intval( $stats['total_raffles'] ?? 0 ); ?></span>
                <span class="rc-stat-label">Rifas Totales</span>
            </div>
        </div>
        <div class="rc-stat-card rc-card-green">
            <div class="rc-stat-icon">✅</div>
            <div class="rc-stat-body">
                <span class="rc-stat-number"><?php echo intval( $stats['active_raffles'] ?? 0 ); ?></span>
                <span class="rc-stat-label">Rifas Activas</span>
            </div>
        </div>
        <div class="rc-stat-card rc-card-purple">
            <div class="rc-stat-icon">🎫</div>
            <div class="rc-stat-body">
                <span class="rc-stat-number"><?php echo number_format_i18n( $stats['total_tickets'] ?? 0 ); ?></span>
                <span class="rc-stat-label">Boletos Vendidos</span>
            </div>
        </div>
        <div class="rc-stat-card rc-card-orange">
            <div class="rc-stat-icon">💰</div>
            <div class="rc-stat-body">
                <span class="rc-stat-number">$<?php echo number_format_i18n( $stats['total_revenue'] ?? 0 ); ?></span>
                <span class="rc-stat-label">Ingresos Totales</span>
            </div>
        </div>
        <div class="rc-stat-card rc-card-teal">
            <div class="rc-stat-icon">👥</div>
            <div class="rc-stat-body">
                <span class="rc-stat-number"><?php echo intval( $stats['total_buyers'] ?? 0 ); ?></span>
                <span class="rc-stat-label">Compradores</span>
            </div>
        </div>
        <div class="rc-stat-card rc-card-red">
            <div class="rc-stat-icon">🛒</div>
            <div class="rc-stat-body">
                <span class="rc-stat-number"><?php echo intval( $stats['total_purchases'] ?? 0 ); ?></span>
                <span class="rc-stat-label">Compras Totales</span>
            </div>
        </div>
    </div>

    <div class="rc-dashboard-row">
        <div class="rc-panel rc-panel-wide">
            <h2>📋 Compras Recientes</h2>
            <?php if ( ! empty( $stats['recent_purchases'] ) ) : ?>
            <table class="rc-table">
                <thead>
                    <tr>
                        <th>Comprador</th>
                        <th>Email</th>
                        <th>Boletos</th>
                        <th>Estado</th>
                        <th>Fecha</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $stats['recent_purchases'] as $p ) : ?>
                    <tr>
                        <td><strong><?php echo esc_html( $p->buyer_name ); ?></strong></td>
                        <td><?php echo esc_html( $p->buyer_email ); ?></td>
                        <td><?php echo intval( $p->quantity ); ?></td>
                        <td>
                            <span class="rc-badge rc-badge-<?php echo esc_attr( $p->status ); ?>">
                                <?php echo esc_html( ucfirst( $p->status ) ); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html( date_i18n( 'd/m/Y H:i', strtotime( $p->purchase_date ) ) ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else : ?>
            <p class="rc-empty">No hay compras recientes.</p>
            <?php endif; ?>
        </div>

        <div class="rc-panel rc-panel-narrow">
            <h2>⚡ Acciones Rápidas</h2>
            <div class="rc-quick-actions">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=rc-new' ) ); ?>" class="rc-btn rc-btn-primary">
                    ➕ Crear Nueva Rifa
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=rc-raffles' ) ); ?>" class="rc-btn rc-btn-secondary">
                    📋 Ver Rifas Activas
                </a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=rc-buyers' ) ); ?>" class="rc-btn rc-btn-secondary">
                    👥 Ver Compradores
                </a>
            </div>
        </div>
    </div>
</div>
