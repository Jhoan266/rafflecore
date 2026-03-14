<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div class="wrap rc-wrap">
    <h1 class="rc-title"><?php esc_html_e( '🔗 Webhooks', 'rafflecore' ); ?></h1>

    <?php if ( isset( $_GET['msg'] ) ) : ?>
        <div class="rc-alert rc-alert-success" role="alert">
            <?php
            $msgs = array(
                'webhook_saved'   => __( 'Webhook guardado correctamente.', 'rafflecore' ),
                'webhook_deleted' => __( 'Webhook eliminado.', 'rafflecore' ),
            );
            $key = sanitize_text_field( wp_unslash( $_GET['msg'] ) );
            echo esc_html( isset( $msgs[ $key ] ) ? $msgs[ $key ] : '' );
            ?>
        </div>
    <?php endif; ?>

    <!-- Create Webhook Form -->
    <div class="rc-card" style="margin-bottom:24px;">
        <h2 class="rc-card-title"><?php esc_html_e( 'Registrar Nuevo Webhook', 'rafflecore' ); ?></h2>
        <form method="post" action="" class="rc-form-grid" aria-label="<?php esc_attr_e( 'Formulario de webhook', 'rafflecore' ); ?>">
            <?php wp_nonce_field( 'rc_save_webhook', 'rc_nonce' ); ?>

            <div class="rc-form-row">
                <label for="webhook_event"><?php esc_html_e( 'Evento', 'rafflecore' ); ?></label>
                <select id="webhook_event" name="webhook_event" required aria-required="true">
                    <?php foreach ( RaffleCore_Webhook_Service::get_events() as $key => $label ) : ?>
                        <option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="rc-form-row">
                <label for="webhook_url"><?php esc_html_e( 'URL de destino', 'rafflecore' ); ?></label>
                <input type="url" id="webhook_url" name="webhook_url" required placeholder="https://example.com/webhook" aria-required="true">
            </div>

            <div class="rc-form-actions">
                <button type="submit" class="rc-btn rc-btn-primary"><?php esc_html_e( 'Crear Webhook', 'rafflecore' ); ?></button>
            </div>
        </form>
    </div>

    <!-- Webhooks List -->
    <div class="rc-card">
        <h2 class="rc-card-title"><?php esc_html_e( 'Webhooks Registrados', 'rafflecore' ); ?></h2>
        <table class="rc-table" role="table" aria-label="<?php esc_attr_e( 'Lista de webhooks', 'rafflecore' ); ?>">
            <thead>
                <tr>
                    <th scope="col"><?php esc_html_e( 'Evento', 'rafflecore' ); ?></th>
                    <th scope="col">URL</th>
                    <th scope="col"><?php esc_html_e( 'Secreto', 'rafflecore' ); ?></th>
                    <th scope="col"><?php esc_html_e( 'Estado', 'rafflecore' ); ?></th>
                    <th scope="col"><?php esc_html_e( 'Creado', 'rafflecore' ); ?></th>
                    <th scope="col"><?php esc_html_e( 'Acciones', 'rafflecore' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $webhooks ) ) : ?>
                    <tr><td colspan="6" class="rc-empty"><?php esc_html_e( 'No hay webhooks registrados.', 'rafflecore' ); ?></td></tr>
                <?php else : ?>
                    <?php foreach ( $webhooks as $wh ) : ?>
                        <tr>
                            <td>
                                <?php
                                $events = RaffleCore_Webhook_Service::get_events();
                                echo esc_html( isset( $events[ $wh->event ] ) ? $events[ $wh->event ] : $wh->event );
                                ?>
                            </td>
                            <td><code style="font-size:12px;"><?php echo esc_html( $wh->url ); ?></code></td>
                            <td><code style="font-size:11px;"><?php echo esc_html( substr( $wh->secret, 0, 8 ) . '...' ); ?></code></td>
                            <td>
                                <span class="rc-badge rc-badge-<?php echo $wh->status === 'active' ? 'success' : 'secondary'; ?>">
                                    <?php echo esc_html( $wh->status === 'active' ? __( 'Activo', 'rafflecore' ) : __( 'Inactivo', 'rafflecore' ) ); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html( date_i18n( 'd/m/Y H:i', strtotime( $wh->created_at ) ) ); ?></td>
                            <td>
                                <form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=rc-webhooks' ) ); ?>" style="display:inline;" onsubmit="return confirm('<?php esc_attr_e( '¿Eliminar este webhook?', 'rafflecore' ); ?>')">
                                    <?php wp_nonce_field( 'rc_delete_webhook' ); ?>
                                    <input type="hidden" name="rc_action" value="delete_webhook">
                                    <input type="hidden" name="id" value="<?php echo intval( $wh->id ); ?>">
                                    <button type="submit" class="rc-btn rc-btn-sm rc-btn-danger"
                                       aria-label="<?php echo esc_attr( sprintf( __( 'Eliminar webhook %s', 'rafflecore' ), $wh->url ) ); ?>">
                                        <?php esc_html_e( 'Eliminar', 'rafflecore' ); ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
