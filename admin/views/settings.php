<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap rc-wrap">
    <h1 class="rc-title">⚙️ <?php esc_html_e( 'Configuración — RaffleCore', 'rafflecore' ); ?></h1>

    <?php if ( isset( $_GET['msg'] ) && $_GET['msg'] === 'saved' ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Configuración guardada.', 'rafflecore' ); ?></p></div>
    <?php endif; ?>

    <form method="post" action="" class="rc-form">
        <?php wp_nonce_field( 'rc_save_settings', 'rc_nonce' ); ?>
        <input type="hidden" name="rc_save_settings" value="1">

        <div class="rc-panel">
            <h2>🌐 <?php esc_html_e( 'Modo de Operación', 'rafflecore' ); ?></h2>
            <p class="rc-help"><?php echo wp_kses( __( 'En modo <strong>local</strong>, RaffleCore usa la base de datos de WordPress directamente. En modo <strong>API</strong>, se conecta a un servicio externo (SaaS).', 'rafflecore' ), array( 'strong' => array() ) ); ?></p>

            <div class="rc-form-grid">
                <div class="rc-form-group">
                    <label for="rc_mode"><?php esc_html_e( 'Modo', 'rafflecore' ); ?></label>
                    <select id="rc_mode" name="rc_mode" class="rc-select">
                        <option value="local" <?php selected( get_option( 'rafflecore_mode', 'local' ), 'local' ); ?>><?php esc_html_e( 'Local (Base de datos WordPress)', 'rafflecore' ); ?></option>
                        <option value="api" <?php selected( get_option( 'rafflecore_mode', 'local' ), 'api' ); ?>><?php esc_html_e( 'API externa (SaaS)', 'rafflecore' ); ?></option>
                    </select>
                </div>
            </div>
        </div>

        <div class="rc-panel" id="rc-api-settings">
            <h2>🔑 <?php esc_html_e( 'Configuración API', 'rafflecore' ); ?></h2>
            <p class="rc-help"><?php esc_html_e( 'Estos campos solo se usan en modo API. Conecta RaffleCore a tu servidor SaaS externo.', 'rafflecore' ); ?></p>

            <div class="rc-form-grid">
                <div class="rc-form-group rc-col-full">
                    <label for="rc_api_url"><?php esc_html_e( 'URL del API', 'rafflecore' ); ?></label>
                    <input type="url" id="rc_api_url" name="rc_api_url"
                           value="<?php echo esc_url( get_option( 'rafflecore_api_url', '' ) ); ?>"
                           placeholder="https://api.tuservicio.com/v1">
                </div>
                <div class="rc-form-group rc-col-full">
                    <label for="rc_api_key"><?php esc_html_e( 'API Key', 'rafflecore' ); ?></label>
                    <input type="password" id="rc_api_key" name="rc_api_key"
                           value="<?php echo esc_attr( get_option( 'rafflecore_api_key', '' ) ); ?>"
                           placeholder="sk-xxxxxxxxxxxxxxxxxxxxxxxx">
                </div>
            </div>
        </div>

        <div class="rc-panel">
            <h2>🎨 <?php esc_html_e( 'Tema de Visualización', 'rafflecore' ); ?></h2>
            <p class="rc-help"><?php esc_html_e( 'Elige el diseño que verán los visitantes en la página pública de cada rifa.', 'rafflecore' ); ?></p>
            <div class="rc-form-grid">
                <div class="rc-form-group">
                    <label for="rc_display_theme"><?php esc_html_e( 'Tema de escritorio', 'rafflecore' ); ?></label>
                    <select id="rc_display_theme" name="rc_display_theme" class="rc-select">
                        <option value="theme1" <?php selected( get_option( 'rafflecore_display_theme', 'theme1' ), 'theme1' ); ?>><?php esc_html_e( 'Tema 1 — Layout 2 columnas (predeterminado)', 'rafflecore' ); ?></option>
                        <option value="theme2" <?php selected( get_option( 'rafflecore_display_theme', 'theme1' ), 'theme2' ); ?>><?php esc_html_e( 'Tema 2 — Layout columna simple', 'rafflecore' ); ?></option>
                    </select>
                </div>
            </div>
        </div>

        <div class="rc-panel">
            <h2>🌍 <?php esc_html_e( 'Moneda y Conversión', 'rafflecore' ); ?></h2>
            <p class="rc-help"><?php esc_html_e( 'Configura la moneda base de tu tienda y la moneda de visualización en el dashboard. Si son diferentes, el panel convertirá los valores automáticamente usando métricas interbancarias actualizadas.', 'rafflecore' ); ?></p>

            <div class="rc-form-grid">
                <div class="rc-form-group">
                    <label for="rc_base_currency"><?php esc_html_e( 'Moneda Base (Tienda/Datos)', 'rafflecore' ); ?></label>
                    <?php $b_cur = get_option( 'rafflecore_base_currency', 'COP' ); ?>
                    <select id="rc_base_currency" name="rc_base_currency" class="rc-select">
                        <option value="COP" <?php selected( $b_cur, 'COP' ); ?>>🇨🇴 COP — <?php esc_html_e( 'Peso Colombiano', 'rafflecore' ); ?></option>
                        <option value="USD" <?php selected( $b_cur, 'USD' ); ?>>🇺🇸 USD — <?php esc_html_e( 'Dólar Estadounidense', 'rafflecore' ); ?></option>
                        <option value="EUR" <?php selected( $b_cur, 'EUR' ); ?>>🇪🇺 EUR — Euro</option>
                        <option value="MXN" <?php selected( $b_cur, 'MXN' ); ?>>🇲🇽 MXN — <?php esc_html_e( 'Peso Mexicano', 'rafflecore' ); ?></option>
                        <option value="ARS" <?php selected( $b_cur, 'ARS' ); ?>>🇦🇷 ARS — <?php esc_html_e( 'Peso Argentino', 'rafflecore' ); ?></option>
                        <option value="BRL" <?php selected( $b_cur, 'BRL' ); ?>>🇧🇷 BRL — <?php esc_html_e( 'Real Brasileño', 'rafflecore' ); ?></option>
                        <option value="PEN" <?php selected( $b_cur, 'PEN' ); ?>>🇵🇪 PEN — <?php esc_html_e( 'Sol Peruano', 'rafflecore' ); ?></option>
                        <option value="CLP" <?php selected( $b_cur, 'CLP' ); ?>>🇨🇱 CLP — <?php esc_html_e( 'Peso Chileno', 'rafflecore' ); ?></option>
                        <option value="VES" <?php selected( $b_cur, 'VES' ); ?>>🇻🇪 VES — <?php esc_html_e( 'Bolívar Venezolano', 'rafflecore' ); ?></option>
                    </select>
                    <small class="rc-help"><?php esc_html_e('La moneda en la que se registran originalmente las compras.', 'rafflecore'); ?></small>
                </div>
                
                <div class="rc-form-group">
                    <label for="rc_currency"><?php esc_html_e( 'Moneda de Visualización (Dashboard)', 'rafflecore' ); ?></label>
                    <?php $cur = get_option( 'rafflecore_currency', 'COP' ); ?>
                    <select id="rc_currency" name="rc_currency" class="rc-select">
                        <option value="COP" <?php selected( $cur, 'COP' ); ?>>🇨🇴 COP — <?php esc_html_e( 'Peso Colombiano', 'rafflecore' ); ?></option>
                        <option value="USD" <?php selected( $cur, 'USD' ); ?>>🇺🇸 USD — <?php esc_html_e( 'Dólar Estadounidense', 'rafflecore' ); ?></option>
                        <option value="EUR" <?php selected( $cur, 'EUR' ); ?>>🇪🇺 EUR — Euro</option>
                        <option value="MXN" <?php selected( $cur, 'MXN' ); ?>>🇲🇽 MXN — <?php esc_html_e( 'Peso Mexicano', 'rafflecore' ); ?></option>
                        <option value="ARS" <?php selected( $cur, 'ARS' ); ?>>🇦🇷 ARS — <?php esc_html_e( 'Peso Argentino', 'rafflecore' ); ?></option>
                        <option value="BRL" <?php selected( $cur, 'BRL' ); ?>>🇧🇷 BRL — <?php esc_html_e( 'Real Brasileño', 'rafflecore' ); ?></option>
                        <option value="PEN" <?php selected( $cur, 'PEN' ); ?>>🇵🇪 PEN — <?php esc_html_e( 'Sol Peruano', 'rafflecore' ); ?></option>
                        <option value="CLP" <?php selected( $cur, 'CLP' ); ?>>🇨🇱 CLP — <?php esc_html_e( 'Peso Chileno', 'rafflecore' ); ?></option>
                        <option value="VES" <?php selected( $cur, 'VES' ); ?>>🇻🇪 VES — <?php esc_html_e( 'Bolívar Venezolano', 'rafflecore' ); ?></option>
                    </select>
                    <small class="rc-help"><?php esc_html_e('A esta moneda se convertirán los valores en las estadísticas y reportes.', 'rafflecore'); ?></small>
                </div>
            </div>
        </div>

        <div class="rc-panel">
            <h2>�📊 <?php esc_html_e( 'Información del Sistema', 'rafflecore' ); ?></h2>
            <table class="rc-info-table">
                <tr><th><?php esc_html_e( 'Versión del Plugin', 'rafflecore' ); ?></th><td><?php echo esc_html( RAFFLECORE_VERSION ); ?></td></tr>
                <tr><th><?php esc_html_e( 'Modo Actual', 'rafflecore' ); ?></th><td><span class="rc-badge rc-badge-active"><?php echo esc_html( ucfirst( get_option( 'rafflecore_mode', 'local' ) ) ); ?></span></td></tr>
                <tr><th>WooCommerce</th><td><?php echo class_exists( 'WooCommerce' ) ? '<span class="rc-badge rc-badge-completed">' . esc_html__( 'Activo', 'rafflecore' ) . '</span>' : '<span class="rc-badge rc-badge-paused">' . esc_html__( 'No instalado', 'rafflecore' ) . '</span>'; ?></td></tr>
                <tr><th>PHP</th><td><?php echo esc_html( phpversion() ); ?></td></tr>
                <tr><th>WordPress</th><td><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td></tr>
            </table>
        </div>

        <div class="rc-form-actions">
            <button type="submit" class="rc-btn rc-btn-primary rc-btn-lg">💾 <?php esc_html_e( 'Guardar Configuración', 'rafflecore' ); ?></button>
        </div>
    </form>
</div>
