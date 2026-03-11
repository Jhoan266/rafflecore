<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap rc-wrap">
    <h1 class="rc-title">⚙️ Configuración — RaffleCore</h1>

    <?php if ( isset( $_GET['msg'] ) && $_GET['msg'] === 'saved' ) : ?>
        <div class="notice notice-success is-dismissible"><p>Configuración guardada.</p></div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rc-form">
        <input type="hidden" name="action" value="rc_admin_form">
        <?php wp_nonce_field( 'rc_save_settings', 'rc_nonce' ); ?>
        <input type="hidden" name="rc_save_settings" value="1">

        <div class="rc-panel">
            <h2>🌐 Modo de Operación</h2>
            <p class="rc-help">En modo <strong>local</strong>, RaffleCore usa la base de datos de WordPress directamente. En modo <strong>API</strong>, se conecta a un servicio externo (SaaS).</p>

            <div class="rc-form-grid">
                <div class="rc-form-group">
                    <label for="rc_mode">Modo</label>
                    <select id="rc_mode" name="rc_mode" class="rc-select">
                        <option value="local" <?php selected( get_option( 'rafflecore_mode', 'local' ), 'local' ); ?>>Local (Base de datos WordPress)</option>
                        <option value="api" <?php selected( get_option( 'rafflecore_mode', 'local' ), 'api' ); ?>>API externa (SaaS)</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="rc-panel" id="rc-api-settings">
            <h2>🔑 Configuración API</h2>
            <p class="rc-help">Estos campos solo se usan en modo API. Conecta RaffleCore a tu servidor SaaS externo.</p>

            <div class="rc-form-grid">
                <div class="rc-form-group rc-col-full">
                    <label for="rc_api_url">URL del API</label>
                    <input type="url" id="rc_api_url" name="rc_api_url"
                           value="<?php echo esc_url( get_option( 'rafflecore_api_url', '' ) ); ?>"
                           placeholder="https://api.tuservicio.com/v1">
                </div>
                <div class="rc-form-group rc-col-full">
                    <label for="rc_api_key">API Key</label>
                    <input type="password" id="rc_api_key" name="rc_api_key"
                           value="<?php echo esc_attr( get_option( 'rafflecore_api_key', '' ) ); ?>"
                           placeholder="sk-xxxxxxxxxxxxxxxxxxxxxxxx">
                </div>
            </div>
        </div>

        <div class="rc-panel">
            <h2>📊 Información del Sistema</h2>
            <table class="rc-info-table">
                <tr><th>Versión del Plugin</th><td><?php echo esc_html( RAFFLECORE_VERSION ); ?></td></tr>
                <tr><th>Modo Actual</th><td><span class="rc-badge rc-badge-active"><?php echo esc_html( ucfirst( get_option( 'rafflecore_mode', 'local' ) ) ); ?></span></td></tr>
                <tr><th>WooCommerce</th><td><?php echo class_exists( 'WooCommerce' ) ? '<span class="rc-badge rc-badge-completed">Activo</span>' : '<span class="rc-badge rc-badge-paused">No instalado</span>'; ?></td></tr>
                <tr><th>PHP</th><td><?php echo esc_html( phpversion() ); ?></td></tr>
                <tr><th>WordPress</th><td><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td></tr>
            </table>
        </div>

        <div class="rc-form-actions">
            <button type="submit" class="rc-btn rc-btn-primary rc-btn-lg">💾 Guardar Configuración</button>
        </div>
    </form>
</div>
