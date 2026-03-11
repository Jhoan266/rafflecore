<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div class="wrap rc-wrap">
    <h1 class="rc-title"><?php esc_html_e( '📋 Registro de Actividad', 'rafflecore' ); ?></h1>

    <table class="rc-table" role="table" aria-label="<?php esc_attr_e( 'Registro de actividad', 'rafflecore' ); ?>">
        <thead>
            <tr>
                <th scope="col"><?php esc_html_e( 'Fecha', 'rafflecore' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Usuario', 'rafflecore' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Acción', 'rafflecore' ); ?></th>
                <th scope="col"><?php esc_html_e( 'Tipo', 'rafflecore' ); ?></th>
                <th scope="col">ID</th>
                <th scope="col"><?php esc_html_e( 'Detalles', 'rafflecore' ); ?></th>
                <th scope="col">IP</th>
            </tr>
        </thead>
        <tbody>
            <?php if ( empty( $logs ) ) : ?>
                <tr><td colspan="7" class="rc-empty"><?php esc_html_e( 'No hay registros de actividad.', 'rafflecore' ); ?></td></tr>
            <?php else : ?>
                <?php foreach ( $logs as $log ) : ?>
                    <tr>
                        <td><?php echo esc_html( date_i18n( 'd/m/Y H:i', strtotime( $log->created_at ) ) ); ?></td>
                        <td><?php echo esc_html( $log->user_name ?: __( 'Sistema', 'rafflecore' ) ); ?></td>
                        <td><span class="rc-badge"><?php echo esc_html( RaffleCore_Logger::action_label( $log->action ) ); ?></span></td>
                        <td><?php echo esc_html( $log->object_type ); ?></td>
                        <td><?php echo esc_html( $log->object_id ); ?></td>
                        <td><?php echo esc_html( $log->details ); ?></td>
                        <td><code><?php echo esc_html( $log->ip_address ); ?></code></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ( $pages > 1 ) : ?>
        <div class="rc-pagination" role="navigation" aria-label="<?php esc_attr_e( 'Paginación', 'rafflecore' ); ?>">
            <?php for ( $i = 1; $i <= $pages; $i++ ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=rc-activity-log&paged=' . $i ) ); ?>"
                   class="rc-btn rc-btn-sm <?php echo $i === $page ? 'rc-btn-primary' : 'rc-btn-secondary'; ?>"
                   <?php echo $i === $page ? 'aria-current="page"' : ''; ?>>
                    <?php echo esc_html( $i ); ?>
                </a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
</div>
