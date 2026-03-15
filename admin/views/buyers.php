<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap rc-wrap">
    <h1 class="rc-title">👥 <?php esc_html_e( 'Compradores', 'rafflecore' ); ?>
        <button type="button" class="rc-btn rc-btn-secondary rc-btn-sm" onclick="rcExport('buyers')" style="margin-left:12px;">📥 <?php esc_html_e( 'Exportar CSV', 'rafflecore' ); ?></button>
    </h1>

    <div class="rc-filters">
        <form method="get" class="rc-filter-form">
            <input type="hidden" name="page" value="rc-buyers">
            <div class="rc-filter-group">
                <input type="text" name="s" placeholder="<?php esc_attr_e( 'Buscar por nombre o email...', 'rafflecore' ); ?>"
                       value="<?php echo esc_attr( $search ); ?>" class="rc-input">
                <select name="raffle_id" class="rc-select">
                    <option value=""><?php esc_html_e( 'Todas las rifas', 'rafflecore' ); ?></option>
                    <?php if ( ! empty( $all_raffles ) ) : ?>
                        <?php foreach ( $all_raffles as $r ) : ?>
                            <option value="<?php echo intval( $r->id ); ?>" <?php selected( $filter_raffle, $r->id ); ?>>
                                <?php echo esc_html( $r->name ); ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
                <button type="submit" class="rc-btn rc-btn-primary rc-btn-sm">🔍 <?php esc_html_e( 'Buscar', 'rafflecore' ); ?></button>
                <?php if ( $search || $filter_raffle ) : ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=rc-buyers' ) ); ?>" class="rc-btn rc-btn-secondary rc-btn-sm">??? <?php esc_html_e( 'Limpiar', 'rafflecore' ); ?></a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <table class="rc-table" role="table" aria-label="<?php esc_attr_e( 'Lista de compradores', 'rafflecore' ); ?>">
        <thead>
            <tr>
                <th>ID</th>
                <th><?php esc_html_e( 'Comprador', 'rafflecore' ); ?></th>
                <th><?php esc_html_e( 'Email', 'rafflecore' ); ?></th>
                <th><?php esc_html_e( 'Rifa', 'rafflecore' ); ?></th>
                <th><?php esc_html_e( 'Boletos', 'rafflecore' ); ?></th>
                <th><?php esc_html_e( 'Estado', 'rafflecore' ); ?></th>
                <th><?php esc_html_e( 'Fecha', 'rafflecore' ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if ( empty( $buyers ) ) : ?>
                <tr><td colspan="8" class="rc-empty"><?php esc_html_e( 'No se encontraron compradores.', 'rafflecore' ); ?></td></tr>
            <?php else : ?>
                <?php foreach ( $buyers as $b ) : ?>
                <tr>
                    <td>#<?php echo intval( $b->id ); ?></td>
                    <td><strong><?php echo esc_html( $b->buyer_name ); ?></strong></td>
                    <td><?php echo esc_html( $b->buyer_email ); ?></td>
                    <td>
                        <?php if ( ! empty( $b->raffle_title ) ) : ?>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=rc-raffles&action=view&id=' . $b->raffle_id ) ); ?>">
                                <?php echo esc_html( $b->raffle_title ); ?>
                            </a>
                        <?php else : ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td><?php echo intval( $b->quantity ); ?></td>
                    <td>
                        <span class="rc-badge rc-badge-<?php echo esc_attr( $b->status ); ?>">
                            <?php echo esc_html( ucfirst( $b->status ) ); ?>
                        </span>
                    </td>
                    <td><?php echo esc_html( date_i18n( 'd/m/Y', strtotime( $b->purchase_date ) ) ); ?></td>
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
