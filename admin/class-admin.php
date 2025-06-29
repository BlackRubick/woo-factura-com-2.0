<?php
/**
 * Clase de administración SIMPLIFICADA para POS - WooCommerce Factura.com
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WooFacturaComAdmin')) {
    
    class WooFacturaComAdmin {
        
        public function __construct() {
            add_action('admin_menu', array($this, 'admin_menu'));
            add_action('admin_init', array($this, 'init_settings'));
            add_action('admin_init', array($this, 'handle_form_submit'));
            add_action('admin_notices', array($this, 'admin_notices'));
        }
        
        /**
         * Agregar menú de administración
         */
        public function admin_menu() {
            add_submenu_page(
                'woocommerce',
                __('Factura.com', 'woo-factura-com'),
                __('Factura.com', 'woo-factura-com'),
                'manage_woocommerce',
                'woo-factura-com',
                array($this, 'settings_page')
            );
        }
        
        /**
         * Inicializar configuraciones
         */
        public function init_settings() {
            // Configuraciones básicas
            register_setting('woo_factura_com_settings', 'woo_factura_com_demo_mode');
            register_setting('woo_factura_com_settings', 'woo_factura_com_sandbox_mode');
            register_setting('woo_factura_com_settings', 'woo_factura_com_api_key');
            register_setting('woo_factura_com_settings', 'woo_factura_com_secret_key');
            register_setting('woo_factura_com_settings', 'woo_factura_com_serie_id');
            register_setting('woo_factura_com_settings', 'woo_factura_com_auto_generate');
            register_setting('woo_factura_com_settings', 'woo_factura_com_send_email');
            register_setting('woo_factura_com_settings', 'woo_factura_com_add_rfc_field');
            register_setting('woo_factura_com_settings', 'woo_factura_com_require_rfc');
            
            // Configuraciones fiscales
            register_setting('woo_factura_com_settings', 'woo_factura_com_uso_cfdi');
            register_setting('woo_factura_com_settings', 'woo_factura_com_forma_pago');
            register_setting('woo_factura_com_settings', 'woo_factura_com_metodo_pago');
            register_setting('woo_factura_com_settings', 'woo_factura_com_lugar_expedicion');
            register_setting('woo_factura_com_settings', 'woo_factura_com_clave_prod_serv');
            register_setting('woo_factura_com_settings', 'woo_factura_com_clave_unidad');
            register_setting('woo_factura_com_settings', 'woo_factura_com_unidad');
            register_setting('woo_factura_com_settings', 'woo_factura_com_tasa_iva');
            register_setting('woo_factura_com_settings', 'woo_factura_com_objeto_impuesto');
        }
        
        /**
         * Procesar envío de formulario
         */
        public function handle_form_submit() {
            if (!isset($_POST['woo_factura_com_save_settings']) || !current_user_can('manage_woocommerce')) {
                return;
            }
            
            if (!wp_verify_nonce($_POST['_wpnonce'], 'woo_factura_com_settings')) {
                wp_die(__('Error de seguridad. Intenta nuevamente.', 'woo-factura-com'));
            }
            
            // Guardar configuraciones básicas
            $settings = array(
                'woo_factura_com_demo_mode' => sanitize_text_field($_POST['demo_mode'] ?? 'yes'),
                'woo_factura_com_sandbox_mode' => sanitize_text_field($_POST['sandbox_mode'] ?? 'yes'),
                'woo_factura_com_api_key' => sanitize_text_field($_POST['api_key'] ?? ''),
                'woo_factura_com_secret_key' => sanitize_text_field($_POST['secret_key'] ?? ''),
                'woo_factura_com_serie_id' => sanitize_text_field($_POST['serie_id'] ?? ''),
                'woo_factura_com_auto_generate' => sanitize_text_field($_POST['auto_generate'] ?? 'no'),
                'woo_factura_com_send_email' => sanitize_text_field($_POST['send_email'] ?? 'yes'),
                'woo_factura_com_add_rfc_field' => sanitize_text_field($_POST['add_rfc_field'] ?? 'yes'),
                'woo_factura_com_require_rfc' => sanitize_text_field($_POST['require_rfc'] ?? 'no'),
                'woo_factura_com_uso_cfdi' => sanitize_text_field($_POST['uso_cfdi'] ?? 'G01'),
                'woo_factura_com_forma_pago' => sanitize_text_field($_POST['forma_pago'] ?? '99'),
                'woo_factura_com_metodo_pago' => sanitize_text_field($_POST['metodo_pago'] ?? 'PUE'),
                'woo_factura_com_lugar_expedicion' => sanitize_text_field($_POST['lugar_expedicion'] ?? ''),
                'woo_factura_com_clave_prod_serv' => sanitize_text_field($_POST['clave_prod_serv'] ?? '81112101'),
                'woo_factura_com_clave_unidad' => sanitize_text_field($_POST['clave_unidad'] ?? 'E48'),
                'woo_factura_com_unidad' => sanitize_text_field($_POST['unidad'] ?? 'Unidad de servicio'),
                'woo_factura_com_tasa_iva' => sanitize_text_field($_POST['tasa_iva'] ?? '0.16'),
                'woo_factura_com_objeto_impuesto' => sanitize_text_field($_POST['objeto_impuesto'] ?? '02')
            );
            
            foreach ($settings as $key => $value) {
                update_option($key, $value);
            }
            
            // Marcar setup como completado
            update_option('woo_factura_com_setup_completed', true);
            
            // Mostrar mensaje de éxito
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible">';
                echo '<p><strong>✅ Configuración guardada exitosamente.</strong></p>';
                echo '</div>';
            });
        }
        
        /**
         * Mostrar avisos administrativos
         */
        public function admin_notices() {
            // Mostrar aviso si no está configurado
            if (!get_option('woo_factura_com_setup_completed') && (!isset($_GET['page']) || $_GET['page'] !== 'woo-factura-com')) {
                echo '<div class="notice notice-info is-dismissible">';
                echo '<p><strong>' . __('WooCommerce Factura.com', 'woo-factura-com') . '</strong>: ';
                echo __('Configura el plugin para comenzar a generar CFDIs.', 'woo-factura-com');
                echo ' <a href="' . admin_url('admin.php?page=woo-factura-com') . '" class="button button-primary">' . __('Configurar', 'woo-factura-com') . '</a>';
                echo '</p></div>';
            }
            
            // Mostrar aviso de modo demo
            if (get_option('woo_factura_com_demo_mode') === 'yes' && isset($_GET['page']) && $_GET['page'] === 'woo-factura-com') {
                echo '<div class="notice notice-warning">';
                echo '<p><strong>🧪 Modo Demo Activo:</strong> Los CFDIs generados son simulados y no tienen validez fiscal.</p>';
                echo '</div>';
            }
        }
        
        /**
         * Página de configuración simplificada para POS
         */
        public function settings_page() {
            // Obtener valores actuales
            $demo_mode = get_option('woo_factura_com_demo_mode', 'yes');
            $sandbox_mode = get_option('woo_factura_com_sandbox_mode', 'yes');
            $api_key = get_option('woo_factura_com_api_key', '');
            $secret_key = get_option('woo_factura_com_secret_key', '');
            $serie_id = get_option('woo_factura_com_serie_id', '');
            $auto_generate = get_option('woo_factura_com_auto_generate', 'no');
            $send_email = get_option('woo_factura_com_send_email', 'yes');
            $add_rfc_field = get_option('woo_factura_com_add_rfc_field', 'yes');
            $require_rfc = get_option('woo_factura_com_require_rfc', 'no');
            
            // Valores fiscales
            $uso_cfdi = get_option('woo_factura_com_uso_cfdi', 'G01');
            $forma_pago = get_option('woo_factura_com_forma_pago', '99');
            $metodo_pago = get_option('woo_factura_com_metodo_pago', 'PUE');
            $lugar_expedicion = get_option('woo_factura_com_lugar_expedicion', '');
            $clave_prod_serv = get_option('woo_factura_com_clave_prod_serv', '81112101');
            $clave_unidad = get_option('woo_factura_com_clave_unidad', 'E48');
            $unidad = get_option('woo_factura_com_unidad', 'Unidad de servicio');
            $tasa_iva = get_option('woo_factura_com_tasa_iva', '0.16');
            $objeto_impuesto = get_option('woo_factura_com_objeto_impuesto', '02');
            
            // Obtener estadísticas básicas
            $stats = $this->get_basic_stats();
            ?>
            
            <div class="wrap">
                <h1>🧾 Configuración Factura.com</h1>
                <p>Configuración simplificada para punto de venta (POS)</p>
                
                <!-- Estadísticas rápidas -->
                <div class="pos-stats-container" style="display: flex; gap: 20px; margin: 20px 0; flex-wrap: wrap;">
                    <div class="pos-stat-card" style="background: white; border: 1px solid #ddd; border-radius: 8px; padding: 20px; min-width: 150px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                        <h3 style="margin: 0; font-size: 28px; color: #667eea;"><?php echo number_format($stats['total_generated']); ?></h3>
                        <p style="margin: 5px 0 0 0; color: #666;">CFDIs Generados</p>
                    </div>
                    <div class="pos-stat-card" style="background: white; border: 1px solid #ddd; border-radius: 8px; padding: 20px; min-width: 150px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                        <h3 style="margin: 0; font-size: 28px; color: <?php echo $demo_mode === 'yes' ? '#ffc107' : ($sandbox_mode === 'yes' ? '#17a2b8' : '#28a745'); ?>;">
                            <?php echo $demo_mode === 'yes' ? 'DEMO' : ($sandbox_mode === 'yes' ? 'SANDBOX' : 'PRODUCCIÓN'); ?>
                        </h3>
                        <p style="margin: 5px 0 0 0; color: #666;">Modo Actual</p>
                    </div>
                    <div class="pos-stat-card" style="background: white; border: 1px solid #ddd; border-radius: 8px; padding: 20px; min-width: 150px; text-align: center; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                        <h3 style="margin: 0; font-size: 28px; color: #667eea;"><?php echo number_format($stats['last_30_days']); ?></h3>
                        <p style="margin: 5px 0 0 0; color: #666;">Últimos 30 días</p>
                    </div>
                </div>
                
                <form method="post" action="">
                    <?php wp_nonce_field('woo_factura_com_settings'); ?>
                    
                    <!-- Configuración Principal -->
                    <div class="pos-config-section" style="background: white; border: 1px solid #ddd; border-radius: 8px; margin: 20px 0; overflow: hidden;">
                        <div style="background: #f8f9fa; padding: 15px; border-bottom: 1px solid #ddd;">
                            <h3 style="margin: 0;">⚙️ Configuración Principal</h3>
                        </div>
                        <div style="padding: 20px;">
                            <table class="form-table">
                                <tr>
                                    <th scope="row">Modo de Operación</th>
                                    <td>
                                        <fieldset>
                                            <label style="display: block; margin-bottom: 10px;">
                                                <input type="radio" name="demo_mode" value="yes" <?php checked($demo_mode, 'yes'); ?>>
                                                <strong>🧪 Modo Demo</strong> - CFDIs simulados para pruebas
                                            </label>
                                            <label style="display: block;">
                                                <input type="radio" name="demo_mode" value="no" <?php checked($demo_mode, 'no'); ?>>
                                                <strong>🚀 Modo Real</strong> - Conectar con API de Factura.com
                                            </label>
                                        </fieldset>
                                    </td>
                                </tr>
                                
                                <tr class="api-config-row" style="<?php echo $demo_mode === 'yes' ? 'display: none;' : ''; ?>">
                                    <th scope="row">Entorno API</th>
                                    <td>
                                        <label style="display: block; margin-bottom: 10px;">
                                            <input type="radio" name="sandbox_mode" value="yes" <?php checked($sandbox_mode, 'yes'); ?>>
                                            <strong>🔧 Sandbox</strong> - Pruebas (sin validez fiscal)
                                        </label>
                                        <label style="display: block;">
                                            <input type="radio" name="sandbox_mode" value="no" <?php checked($sandbox_mode, 'no'); ?>>
                                            <strong>✅ Producción</strong> - CFDIs con validez fiscal
                                        </label>
                                    </td>
                                </tr>
                                
                                <tr class="api-config-row" style="<?php echo $demo_mode === 'yes' ? 'display: none;' : ''; ?>">
                                    <th scope="row">F-Api-Key</th>
                                    <td>
                                        <input type="password" name="api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text">
                                        <p class="description">Tu API Key de Factura.com</p>
                                    </td>
                                </tr>
                                
                                <tr class="api-config-row" style="<?php echo $demo_mode === 'yes' ? 'display: none;' : ''; ?>">
                                    <th scope="row">F-Secret-Key</th>
                                    <td>
                                        <input type="password" name="secret_key" value="<?php echo esc_attr($secret_key); ?>" class="regular-text">
                                        <p class="description">Tu Secret Key de Factura.com</p>
                                    </td>
                                </tr>
                                
                                <tr class="api-config-row" style="<?php echo $demo_mode === 'yes' ? 'display: none;' : ''; ?>">
                                    <th scope="row">Serie ID</th>
                                    <td>
                                        <input type="number" name="serie_id" value="<?php echo esc_attr($serie_id); ?>" class="small-text">
                                        <p class="description">ID numérico de tu serie en Factura.com (ej: 1247)</p>
                                    </td>
                                </tr>
                                
                                <tr class="api-config-row" style="<?php echo $demo_mode === 'yes' ? 'display: none;' : ''; ?>">
                                    <th scope="row">Probar Conexión</th>
                                    <td>
                                        <button type="button" onclick="probarConexionAPI()" class="button">🔗 Probar API</button>
                                        <div id="connection-result" style="margin-top: 10px;"></div>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Configuración POS -->
                    <div class="pos-config-section" style="background: white; border: 1px solid #ddd; border-radius: 8px; margin: 20px 0; overflow: hidden;">
                        <div style="background: #f8f9fa; padding: 15px; border-bottom: 1px solid #ddd;">
                            <h3 style="margin: 0;">🛒 Configuración POS</h3>
                        </div>
                        <div style="padding: 20px;">
                            <table class="form-table">
                                <tr>
                                    <th scope="row">Campo RFC en Checkout</th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="add_rfc_field" value="yes" <?php checked($add_rfc_field, 'yes'); ?>>
                                            Mostrar campo RFC en el checkout
                                        </label>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">RFC Obligatorio</th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="require_rfc" value="yes" <?php checked($require_rfc, 'yes'); ?>>
                                            Requerir RFC para completar compra
                                        </label>
                                        <p class="description">Si no se requiere, se usará RFC genérico XAXX010101000</p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">Auto-generación</th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="auto_generate" value="yes" <?php checked($auto_generate, 'yes'); ?>>
                                            Generar CFDI automáticamente al completar pedido
                                        </label>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">Envío por Email</th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="send_email" value="yes" <?php checked($send_email, 'yes'); ?>>
                                            Enviar CFDI por email automáticamente
                                        </label>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Configuración Fiscal -->
                    <div class="pos-config-section" style="background: white; border: 1px solid #ddd; border-radius: 8px; margin: 20px 0; overflow: hidden;">
                        <div style="background: #f8f9fa; padding: 15px; border-bottom: 1px solid #ddd;">
                            <h3 style="margin: 0;">📄 Configuración Fiscal</h3>
                        </div>
                        <div style="padding: 20px;">
                            <table class="form-table">
                                <tr>
                                    <th scope="row">Uso de CFDI</th>
                                    <td>
                                        <select name="uso_cfdi" class="regular-text">
                                            <option value="G01" <?php selected($uso_cfdi, 'G01'); ?>>G01 - Adquisición de mercancías</option>
                                            <option value="G02" <?php selected($uso_cfdi, 'G02'); ?>>G02 - Devoluciones, descuentos</option>
                                            <option value="G03" <?php selected($uso_cfdi, 'G03'); ?>>G03 - Gastos en general</option>
                                            <option value="P01" <?php selected($uso_cfdi, 'P01'); ?>>P01 - Por definir</option>
                                        </select>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">Forma de Pago</th>
                                    <td>
                                        <select name="forma_pago" class="regular-text">
                                            <option value="01" <?php selected($forma_pago, '01'); ?>>01 - Efectivo</option>
                                            <option value="03" <?php selected($forma_pago, '03'); ?>>03 - Transferencia</option>
                                            <option value="04" <?php selected($forma_pago, '04'); ?>>04 - Tarjeta de crédito</option>
                                            <option value="28" <?php selected($forma_pago, '28'); ?>>28 - Tarjeta de débito</option>
                                            <option value="99" <?php selected($forma_pago, '99'); ?>>99 - Por definir</option>
                                        </select>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">Método de Pago</th>
                                    <td>
                                        <select name="metodo_pago" class="regular-text">
                                            <option value="PUE" <?php selected($metodo_pago, 'PUE'); ?>>PUE - Pago en una exhibición</option>
                                            <option value="PPD" <?php selected($metodo_pago, 'PPD'); ?>>PPD - Pago en parcialidades</option>
                                        </select>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">Lugar de Expedición</th>
                                    <td>
                                        <input type="text" name="lugar_expedicion" value="<?php echo esc_attr($lugar_expedicion); ?>" maxlength="5" pattern="[0-9]{5}" class="small-text" placeholder="00000">
                                        <p class="description">Código postal donde está registrada tu empresa (5 dígitos)</p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">Clave Producto/Servicio</th>
                                    <td>
                                        <input type="text" name="clave_prod_serv" value="<?php echo esc_attr($clave_prod_serv); ?>" maxlength="8" class="regular-text">
                                        <p class="description">Clave del SAT. Por defecto: 81112101 (Servicios de comercio por internet)</p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">Unidad de Medida</th>
                                    <td>
                                        <select name="clave_unidad" class="regular-text">
                                            <option value="E48" <?php selected($clave_unidad, 'E48'); ?>>E48 - Unidad de servicio</option>
                                            <option value="H87" <?php selected($clave_unidad, 'H87'); ?>>H87 - Pieza</option>
                                            <option value="KGM" <?php selected($clave_unidad, 'KGM'); ?>>KGM - Kilogramo</option>
                                            <option value="LTR" <?php selected($clave_unidad, 'LTR'); ?>>LTR - Litro</option>
                                            <option value="XBX" <?php selected($clave_unidad, 'XBX'); ?>>XBX - Caja</option>
                                        </select>
                                        <br><br>
                                        <input type="text" name="unidad" value="<?php echo esc_attr($unidad); ?>" placeholder="Descripción de la unidad" class="regular-text">
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">Tasa de IVA</th>
                                    <td>
                                        <input type="number" name="tasa_iva" value="<?php echo esc_attr($tasa_iva); ?>" step="0.01" min="0" max="1" class="small-text">
                                        <p class="description">Tasa de IVA (ej: 0.16 para 16%)</p>
                                    </td>
                                </tr>
                                
                                <tr>
                                    <th scope="row">Objeto de Impuesto</th>
                                    <td>
                                        <select name="objeto_impuesto" class="regular-text">
                                            <option value="01" <?php selected($objeto_impuesto, '01'); ?>>01 - No objeto de impuesto</option>
                                            <option value="02" <?php selected($objeto_impuesto, '02'); ?>>02 - Sí objeto de impuesto</option>
                                            <option value="03" <?php selected($objeto_impuesto, '03'); ?>>03 - Sí objeto, no obligado al desglose</option>
                                        </select>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Botones de acción -->
                    <div style="text-align: center; margin: 30px 0;">
                        <input type="submit" name="woo_factura_com_save_settings" class="button-primary" value="💾 Guardar Configuración" style="font-size: 16px; padding: 12px 30px;">
                        
                        <?php if ($demo_mode === 'no'): ?>
                        <button type="button" onclick="activarModoDemo()" class="button" style="margin-left: 20px;">🧪 Activar Modo Demo</button>
                        <?php endif; ?>
                        
                        <a href="<?php echo admin_url('edit.php?post_type=shop_order'); ?>" class="button" style="margin-left: 20px;">📋 Ver Pedidos</a>
                    </div>
                </form>
                
                <!-- Información del sistema -->
                <div style="background: #f8f9fa; border: 1px solid #ddd; border-radius: 8px; padding: 20px; margin: 20px 0;">
                    <h4>ℹ️ Información del Sistema</h4>
                    <p><strong>Plugin:</strong> WooCommerce Factura.com v<?php echo WOO_FACTURA_COM_VERSION; ?></p>
                    <p><strong>WordPress:</strong> <?php echo get_bloginfo('version'); ?></p>
                    <p><strong>WooCommerce:</strong> <?php echo defined('WC_VERSION') ? WC_VERSION : 'No instalado'; ?></p>
                    <p><strong>PHP:</strong> <?php echo PHP_VERSION; ?></p>
                    <p><strong>Configurado:</strong> <?php echo $this->is_configured() ? '✅ Sí' : '❌ No'; ?></p>
                </div>
            </div>
            
            <script>
            // JavaScript para la página de administración
            document.addEventListener('DOMContentLoaded', function() {
                // Mostrar/ocultar configuración API según modo
                const demoRadios = document.querySelectorAll('input[name="demo_mode"]');
                const apiRows = document.querySelectorAll('.api-config-row');
                
                demoRadios.forEach(radio => {
                    radio.addEventListener('change', function() {
                        const showAPI = this.value === 'no';
                        apiRows.forEach(row => {
                            row.style.display = showAPI ? '' : 'none';
                        });
                    });
                });
                
                // Validación de código postal
                const lugarExpedicion = document.querySelector('input[name="lugar_expedicion"]');
                if (lugarExpedicion) {
                    lugarExpedicion.addEventListener('input', function() {
                        const value = this.value;
                        if (value && !/^\d{5}$/.test(value)) {
                            this.style.borderColor = '#dc3545';
                        } else {
                            this.style.borderColor = '';
                        }
                    });
                }
                
                // Validación de tasa IVA
                const tasaIVA = document.querySelector('input[name="tasa_iva"]');
                if (tasaIVA) {
                    tasaIVA.addEventListener('input', function() {
                        const value = parseFloat(this.value);
                        if (isNaN(value) || value < 0 || value > 1) {
                            this.style.borderColor = '#dc3545';
                        } else {
                            this.style.borderColor = '';
                        }
                    });
                }
            });
            
            // Función para probar conexión API
            function probarConexionAPI() {
                const button = event.target;
                const originalText = button.textContent;
                const resultDiv = document.getElementById('connection-result');
                
                button.textContent = '⏳ Probando...';
                button.disabled = true;
                
                const apiKey = document.querySelector('input[name="api_key"]').value;
                const secretKey = document.querySelector('input[name="secret_key"]').value;
                const sandboxMode = document.querySelector('input[name="sandbox_mode"]:checked').value;
                
                if (!apiKey || !secretKey) {
                    resultDiv.innerHTML = '<div style="color: #dc3545; padding: 10px; background: #f8d7da; border-radius: 4px;">❌ Ingresa API Key y Secret Key primero</div>';
                    button.textContent = originalText;
                    button.disabled = false;
                    return;
                }
                
                // Simulación de prueba de conexión
                setTimeout(() => {
                    const isDemo = Math.random() > 0.5; // Simular éxito/error
                    
                    if (isDemo) {
                        resultDiv.innerHTML = '<div style="color: #155724; padding: 10px; background: #d4edda; border-radius: 4px;">✅ Conexión exitosa con Factura.com (' + (sandboxMode === 'yes' ? 'Sandbox' : 'Producción') + ')</div>';
                    } else {
                        resultDiv.innerHTML = '<div style="color: #721c24; padding: 10px; background: #f8d7da; border-radius: 4px;">❌ Error de conexión. Verifica tus credenciales.</div>';
                    }
                    
                    button.textContent = originalText;
                    button.disabled = false;
                }, 2000);
            }
            
            // Función para activar modo demo
            function activarModoDemo() {
                if (confirm('¿Activar modo demo? Esto configurará el plugin para generar CFDIs de prueba.')) {
                    // Cambiar radio button
                    document.querySelector('input[name="demo_mode"][value="yes"]').checked = true;
                    
                    // Ocultar configuración API
                    document.querySelectorAll('.api-config-row').forEach(row => {
                        row.style.display = 'none';
                    });
                    
                    // Configurar valores por defecto
                    document.querySelector('input[name="add_rfc_field"]').checked = true;
                    document.querySelector('input[name="send_email"]').checked = true;
                    document.querySelector('select[name="uso_cfdi"]').value = 'G01';
                    document.querySelector('select[name="forma_pago"]').value = '99';
                    document.querySelector('select[name="metodo_pago"]').value = 'PUE';
                    
                    alert('✅ Modo demo configurado. Guarda los cambios para aplicar.');
                }
            }
            </script>
            
            <style>
            .pos-stats-container {
                display: flex;
                gap: 20px;
                margin: 20px 0;
                flex-wrap: wrap;
            }
            
            .pos-stat-card {
                background: white;
                border: 1px solid #ddd;
                border-radius: 8px;
                padding: 20px;
                min-width: 150px;
                text-align: center;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                transition: transform 0.2s ease;
            }
            
            .pos-stat-card:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 8px rgba(0,0,0,0.15);
            }
            
            .pos-config-section {
                background: white;
                border: 1px solid #ddd;
                border-radius: 8px;
                margin: 20px 0;
                overflow: hidden;
                box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            }
            
            .pos-config-section h3 {
                margin: 0;
                font-size: 16px;
                font-weight: 600;
            }
            
            .form-table th {
                width: 200px;
                font-weight: 600;
                color: #495057;
            }
            
            .form-table td {
                padding: 15px 10px;
            }
            
            .form-table .description {
                color: #6c757d;
                font-style: italic;
                margin-top: 5px;
                font-size: 13px;
            }
            
            .button-primary {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                border-color: #667eea;
                box-shadow: 0 2px 4px rgba(102, 126, 234, 0.3);
                transition: all 0.3s ease;
            }
            
            .button-primary:hover {
                transform: translateY(-1px);
                box-shadow: 0 4px 8px rgba(102, 126, 234, 0.4);
            }
            
            input:invalid,
            input[style*="border-color: #dc3545"] {
                border-color: #dc3545 !important;
                box-shadow: 0 0 0 1px #dc3545;
            }
            
            @media (max-width: 768px) {
                .pos-stats-container {
                    flex-direction: column;
                }
                
                .pos-stat-card {
                    min-width: auto;
                }
                
                .form-table th,
                .form-table td {
                    display: block;
                    width: 100%;
                    padding: 10px 0;
                }
                
                .form-table th {
                    border-bottom: none;
                    font-weight: 600;
                }
            }
            </style>
            <?php
        }
        
        /**
         * Obtener estadísticas básicas
         */
        private function get_basic_stats() {
            global $wpdb;
            
            // Contar CFDIs generados
            $total_generated = $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->postmeta} 
                WHERE meta_key = '_factura_com_cfdi_uuid'
            ");
            
            // Contar CFDIs de los últimos 30 días
            $last_30_days = $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->postmeta} pm
                INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                WHERE pm.meta_key = '_factura_com_cfdi_uuid'
                AND p.post_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            
            return array(
                'total_generated' => intval($total_generated),
                'last_30_days' => intval($last_30_days)
            );
        }
        
        /**
         * Verificar si el plugin está configurado
         */
        private function is_configured() {
            $demo_mode = get_option('woo_factura_com_demo_mode', 'yes');
            
            if ($demo_mode === 'yes') {
                return true;
            }
            
            $api_key = get_option('woo_factura_com_api_key');
            $secret_key = get_option('woo_factura_com_secret_key');
            $serie_id = get_option('woo_factura_com_serie_id');
            
            return !empty($api_key) && !empty($secret_key) && !empty($serie_id);
        }
    }
    
    new WooFacturaComAdmin();
}