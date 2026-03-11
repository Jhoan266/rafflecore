<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div class="wrap rc-wrap">
    <h1 class="rc-title"><?php esc_html_e( '🎟️ Cupones de Descuento', 'rafflecore' ); ?></h1>

    <?php if ( isset( $_GET['msg'] ) ) : ?>
        <div class="rc-alert rc-alert-success" role="alert">
            <?php
            $msgs = array(
                'coupon_saved'   => __( 'Cupón guardado correctamente.', 'rafflecore' ),
                'coupon_deleted' => __( 'Cupón eliminado.', 'rafflecore' ),
            );
            $key = sanitize_text_field( wp_unslash( $_GET['msg'] ) );
            echo esc_html( isset( $msgs[ $key ] ) ? $msgs[ $key ] : '' );
            ?>
        </div>
    <?php endif; ?>

    <!-- Create Coupon Form -->
    <div class="rc-card" style="margin-bottom:24px;">
        <h2 class="rc-card-title"><?php esc_html_e( 'Crear Nuevo Cupón', 'rafflecore' ); ?></h2>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rc-form-grid" aria-label="<?php esc_attr_e( 'Formulario de cupón', 'rafflecore' ); ?>">
            <?php wp_nonce_field( 'rc_save_coupon', 'rc_nonce' ); ?>
            <input type="hidden" name="action" value="rc_save_coupon">

            <div class="rc-form-row">
                <label for="coupon_code"><?php esc_html_e( 'Código', 'rafflecore' ); ?></label>
                <input type="text" id="coupon_code" name="coupon_code" required placeholder="DESCUENTO20" style="text-transform:uppercase" aria-required="true">
            </div>

            <div class="rc-form-row">
                <label for="discount_type"><?php esc_html_e( 'Tipo de descuento', 'rafflecore' ); ?></label>
                <select id="discount_type" name="discount_type">
                    <option value="percentage"><?php esc_html_e( 'Porcentaje (%)', 'rafflecore' ); ?></option>
                    <option value="fixed"><?php esc_html_e( 'Monto fijo ($)', 'rafflecore' ); ?></option>
                </select>
            </div>

            <div class="rc-form-row">
                <label for="discount_value"><?php esc_html_e( 'Valor', 'rafflecore' ); ?></label>
                <input type="number" id="discount_value" name="discount_value" step="0.01" min="0.01" required aria-required="true">
            </div>

            <div class="rc-form-row">
                <label for="max_uses"><?php esc_html_e( 'Usos máximos', 'rafflecore' ); ?></label>
                <input type="number" id="max_uses" name="max_uses" min="0" value="0" placeholder="0 = ilimitado">
                <small><?php esc_html_e( '0 = uso ilimitado', 'rafflecore' ); ?></small>
            </div>

            <div class="rc-form-row">
                <label for="raffle_id"><?php esc_html_e( 'Rifa específica (opcional)', 'rafflecore' ); ?></label>
                <select id="raffle_id" name="raffle_id">
                    <option value="0"><?php esc_html_e( 'Todas las rifas', 'rafflecore' ); ?></option>
                    <?php foreach ( $all_raffles as $r ) : ?>
                        <option value="<?php echo esc_attr( $r->id ); ?>"><?php echo esc_html( $r->title ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="rc-form-row">
                <label for="min_tickets"><?php esc_html_e( 'Mínimo de boletos', 'rafflecore' ); ?></label>
                <input type="number" id="min_tickets" name="min_tickets" min="0" value="0">
            </div>

            <div class="rc-form-row">
                <label for="expires_at"><?php esc_html_e( 'Fecha de expiración', 'rafflecore' ); ?></label>
                <input type="datetime-local" id="expires_at" name="expires_at">
            </div>

            <div class="rc-form-actions">
                <button type="submit" class="rc-btn rc-btn-primary"><?php esc_html_e( 'Crear Cupón', 'rafflecore' ); ?></button>
            </div>
        </form>
    </div>

    <!-- Coupons List -->
    <div class="rc-card">
        <h2 class="rc-card-title"><?php esc_html_e( 'Cupones Existentes', 'rafflecore' ); ?></h2>
        <table class="rc-table" role="table" aria-label="<?php esc_attr_e( 'Lista de cupones', 'rafflecore' ); ?>">
            <thead>
                <tr>
                    <th scope="col"><?php esc_html_e( 'Código', 'rafflecore' ); ?></th>
                    <th scope="col"><?php esc_html_e( 'Descuento', 'rafflecore' ); ?></th>
                    <th scope="col"><?php esc_html_e( 'Usos', 'rafflecore' ); ?></th>
                    <th scope="col"><?php esc_html_e( 'Rifa', 'rafflecore' ); ?></th>
                    <th scope="col"><?php esc_html_e( 'Expira', 'rafflecore' ); ?></th>
                    <th scope="col"><?php esc_html_e( 'Estado', 'rafflecore' ); ?></th>
                    <th scope="col"><?php esc_html_e( 'Acciones', 'rafflecore' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if ( empty( $coupons ) ) : ?>
                    <tr><td colspan="7" class="rc-empty"><?php esc_html_e( 'No hay cupones creados.', 'rafflecore' ); ?></td></tr>
                <?php else : ?>
                    <?php foreach ( $coupons as $coupon ) : ?>
                        <tr>
                            <td><code style="font-size:14px;font-weight:700;"><?php echo esc_html( $coupon->code ); ?></code></td>
                            <td>
                                <?php
                                if ( $coupon->discount_type === 'percentage' ) {
                                    echo esc_html( $coupon->discount_value . '%' );
                                } else {
                                    echo '$' . esc_html( number_format( $coupon->discount_value, 2 ) );
                                }
                                ?>
                            </td>
                            <td>
                                <?php echo esc_html( $coupon->used_count ); ?> / <?php echo $coupon->max_uses ? esc_html( $coupon->max_uses ) : '∞'; ?>
                            </td>
                            <td>
                                <?php
                                if ( $coupon->raffle_id ) {
                                    $r = RaffleCore_Raffle_Model::find( $coupon->raffle_id );
                                    echo $r ? esc_html( $r->title ) : '#' . esc_html( $coupon->raffle_id );
                                } else {
                                    esc_html_e( 'Todas', 'rafflecore' );
                                }
                                ?>
                            </td>
                            <td><?php echo $coupon->expires_at ? esc_html( date_i18n( 'd/m/Y H:i', strtotime( $coupon->expires_at ) ) ) : '—'; ?></td>
                            <td>
                                <span class="rc-badge rc-badge-<?php echo $coupon->status === 'active' ? 'success' : 'secondary'; ?>">
                                    <?php echo esc_html( $coupon->status === 'active' ? __( 'Activo', 'rafflecore' ) : __( 'Inactivo', 'rafflecore' ) ); ?>
                                </span>
                            </td>
                            <td>
                                <form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=rc-coupons' ) ); ?>" style="display:inline;" onsubmit="return confirm('<?php esc_attr_e( '¿Eliminar este cupón?', 'rafflecore' ); ?>')">
                                    <?php wp_nonce_field( 'rc_delete_coupon' ); ?>
                                    <input type="hidden" name="rc_action" value="delete_coupon">
                                    <input type="hidden" name="id" value="<?php echo intval( $coupon->id ); ?>">
                                    <button type="submit" class="rc-btn rc-btn-sm rc-btn-danger"
                                       aria-label="<?php echo esc_attr( sprintf( __( 'Eliminar cupón %s', 'rafflecore' ), $coupon->code ) ); ?>">
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
