<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap rc-wrap">
    <h1 class="rc-title">📋 <?php esc_html_e( 'Rifas', 'rafflecore' ); ?>
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=rc-new' ) ); ?>" class="rc-btn rc-btn-primary rc-btn-sm">➕ <?php esc_html_e( 'Crear Nueva', 'rafflecore' ); ?></a>
    </h1>

    <?php if ( isset( $_GET['msg'] ) ) : ?>
        <div class="notice notice-success is-dismissible">
            <p>
            <?php
            $msg_key = sanitize_text_field( wp_unslash( $_GET['msg'] ) );
            $messages = array(
                'saved'   => __( 'Rifa guardada correctamente.', 'rafflecore' ),
                'deleted' => __( 'Rifa eliminada correctamente.', 'rafflecore' ),
            );
            echo esc_html( isset( $messages[ $msg_key ] ) ? $messages[ $msg_key ] : '' );
            ?>
            </p>
        </div>
    <?php endif; ?>

    <table class="rc-table" role="table" aria-label="<?php esc_attr_e( 'Lista de rifas', 'rafflecore' ); ?>">
        <thead>
            <tr>
                <th>ID</th>
                <th><?php esc_html_e( 'Imagen', 'rafflecore' ); ?></th>
                <th><?php esc_html_e( 'Nombre', 'rafflecore' ); ?></th>
                <th><?php esc_html_e( 'Precio Unit.', 'rafflecore' ); ?></th>
                <th><?php esc_html_e( 'Total Boletos', 'rafflecore' ); ?></th>
                <th><?php esc_html_e( 'Progreso', 'rafflecore' ); ?></th>
                <th><?php esc_html_e( 'Estado', 'rafflecore' ); ?></th>
                <th><?php esc_html_e( 'Fecha Sorteo', 'rafflecore' ); ?></th>
                <th><?php esc_html_e( 'Acciones', 'rafflecore' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( empty( $raffles ) ) : ?>
                <tr><td colspan="9" class="rc-empty"><?php esc_html_e( 'No hay rifas creadas aún.', 'rafflecore' ); ?></td></tr>
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
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=rc-raffles&action=view&id=' . $r->id ) ); ?>" class="rc-btn rc-btn-sm rc-btn-info" title="<?php esc_attr_e( 'Ver detalles', 'rafflecore' ); ?>">👁️</a>
                        <a href="<?php echo esc_url( admin_url( 'admin.php?page=rc-raffles&action=edit&id=' . $r->id ) ); ?>" class="rc-btn rc-btn-sm rc-btn-warning" title="<?php esc_attr_e( 'Editar', 'rafflecore' ); ?>">✏️</a>
                        <form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=rc-raffles' ) ); ?>" style="display:inline;" onsubmit="return confirm('<?php echo esc_js( __( '¿Eliminar esta rifa y todos sus datos?', 'rafflecore' ) ); ?>')">
                            <?php wp_nonce_field( 'rc_delete_raffle' ); ?>
                            <input type="hidden" name="rc_action" value="delete">
                            <input type="hidden" name="id" value="<?php echo intval( $r->id ); ?>">
                            <button type="submit" class="rc-btn rc-btn-sm rc-btn-danger" title="<?php esc_attr_e( 'Eliminar', 'rafflecore' ); ?>">🗑️</button>
                        </form>
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
