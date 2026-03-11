<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap rc-wrap">
    <h1 class="rc-title">📋 Rifas
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=rc-new' ) ); ?>" class="rc-btn rc-btn-primary rc-btn-sm">➕ Crear Nueva</a>
    </h1>

    <?php if ( isset( $_GET['msg'] ) ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo $_GET['msg'] === 'saved' ? 'Rifa guardada correctamente.' : 'Rifa eliminada correctamente.'; ?></p>
        </div>
    <?php endif; ?>

    <table class="rc-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Imagen</th>
                <th>Nombre</th>
                <th>Precio Unit.</th>
                <th>Total Boletos</th>
                <th>Progreso</th>
                <th>Estado</th>
                <th>Fecha Sorteo</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if ( empty( $raffles ) ) : ?>
                <tr><td colspan="9" class="rc-empty">No hay rifas creadas aún.</td></tr>
            <?php else : ?>
                <?php foreach ( $raffles as $r ) :
                    $progress = $r->total_tickets > 0 ? round( ( $r->sold_tickets / $r->total_tickets ) * 100, 1 ) : 0;
                ?>
                <tr>
                    <td>#<?php echo intval( $r->id ); ?></td>
                    <td>
                        <?php if ( $r->prize_image ) : ?>
                            <img src="<?php echo esc_url( $r->prize_image ); ?>" class="rc-thumb" alt="">
                        <?php else : ?>
                            <div class="rc-thumb rc-thumb-placeholder">🎟️</div>
                        <?php endif; ?>
                    </td>
                    <td><strong><?php echo esc_html( $r->title ); ?></strong></td>
                    <td>$<?php echo number_format_i18n( $r->ticket_price ); ?></td>
                    <td><?php echo number_format_i18n( $r->total_tickets ); ?></td>
                    <td>
                        <div class="rc-progress-bar">
                            <div class="rc-progress-fill" style="width: <?php echo esc_attr( $progress ); ?>%">
                                <?php echo esc_html( $progress ); ?>%
                            </div>
                        </div>
                        <small><?php echo number_format_i18n( $r->sold_tickets ); ?> / <?php echo number_format_i18n( $r->total_tickets ); ?></small>
                    </td>
                    <td>
                        <span class="rc-badge rc-badge-<?php echo esc_attr( $r->status ); ?>">
                            <?php echo esc_html( ucfirst( $r->status ) ); ?>
                        </span>
                    </td>
                    <td><?php echo $r->draw_date ? esc_html( date_i18n( 'd/m/Y', strtotime( $r->draw_date ) ) ) : '—'; ?></td>
                    <td class="rc-actions">
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=rc-raffles&action=view&id=' . $r->id ) ); ?>" class="rc-btn rc-btn-sm rc-btn-info" title="Ver detalles">👁️</a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=rc-raffles&action=edit&id=' . $r->id ) ); ?>" class="rc-btn rc-btn-sm rc-btn-warning" title="Editar">✏️</a>
                        <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=rc-raffles&rc_action=delete&id=' . $r->id ), 'rc_delete_raffle' ) ); ?>" class="rc-btn rc-btn-sm rc-btn-danger" title="Eliminar" onclick="return confirm('¿Eliminar esta rifa y todos sus datos?')">🗑️</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ( $pages > 1 ) : ?>
    <div class="rc-pagination">
        <?php for ( $i = 1; $i <= $pages; $i++ ) : ?>
            <?php if ( $i == $page ) : ?>
                <span class="rc-page-current"><?php echo $i; ?></span>
            <?php else : ?>
                <a href="<?php echo esc_url( add_query_arg( 'paged', $i ) ); ?>" class="rc-page-link"><?php echo $i; ?></a>
            <?php endif; ?>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>
