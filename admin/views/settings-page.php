<?php
/**
 * Vista de la página de configuración principal
 */

if (!defined('ABSPATH')) {
    exit;
}

// Procesar formulario si se envió
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['woo_factura_com_settings_nonce'])) {
    if (wp_verify_nonce($_POST['woo_factura_com_settings_nonce'], 'woo_factura_com_save_settings')) {
        
        // Guardar configuraciones
        $settings = array(
            'woo_factura_com_demo_mode' => sanitize_text_field($_POST['demo_mode'] ?? 'yes'),
            'woo_factura_com_sandbox_mode' => sanitize_text_field($_POST['sandbox_mode'] ?? 'yes'),
            'woo_factura_com_debug_mode' => sanitize_text_field($_POST['debug_mode'] ?? 'no'),
            'woo_factura_com_api_key' => sanitize_text_field($_POST['api_key'] ?? ''),
            'woo_factura_com_secret_key' => sanitize_text_field($_POST['secret_key'] ?? ''),
            'woo_factura_com_serie_id' => sanitize_text_field($_POST['serie_id'] ?? ''),
            'woo_factura_com_auto_generate' => sanitize_text_field($_POST['auto_generate'] ?? 'no'),
            'woo_factura_com_send_email' => sanitize_text_field($_POST['send_email'] ?? 'yes'),
            'woo_factura_com_require_rfc' => sanitize_text_field($_POST['require_rfc'] ?? 'no'),
            'woo_factura_com_uso_cfdi' => sanitize_text_field($_POST['uso_cfdi'] ?? 'G01'),
            'woo_factura_com_forma_pago' => sanitize_text_field($_POST['forma_pago'] ?? '01'),
            'woo_factura_com_metodo_pago' => sanitize_text_field($_POST['metodo_pago'] ?? 'PUE'),
            'woo_factura_com_lugar_expedicion' => sanitize_text_field($_POST['lugar_expedicion'] ?? ''),
            'woo_factura_com_clave_prod_serv' => sanitize_text_field($_POST['clave_prod_serv'] ?? '81112101'),
            'woo_factura_com_clave_unidad' => sanitize_text_field($_POST['clave_unidad'] ?? 'E48'),
            'woo_factura_com_unidad' => sanitize_text_field($_POST['unidad'] ?? 'Unidad de servicio'),
            'woo_factura_com_tasa_iva' => sanitize_text_field($_POST['tasa_iva'] ?? '0.16'),
            'woo_factura_com_objeto_impuesto' => sanitize_text_field($_POST['objeto_impuesto'] ?? '02'),
            'woo_factura_com_tipo_cambio' => sanitize_text_field($_POST['tipo_cambio'] ?? '20.00'),
            'woo_factura_com_timeout_api' => intval($_POST['timeout_api'] ?? 60),
            'woo_factura_com_retention_days' => intval($_POST['retention_days'] ?? 30),
            'woo_factura_com_remove_data_on_uninstall' => sanitize_text_field($_POST['remove_data_on_uninstall'] ?? 'no')
        );
        
        foreach ($settings as $key => $value) {
            update_option($key, $value);
        }
        
        echo '<div class="notice notice-success is-dismissible"><p>' . __('Configuración guardada exitosamente.', 'woo-factura-com') . '</p></div>';
    }
}

// Obtener configuraciones actuales
$demo_mode = get_option('woo_factura_com_demo_mode', 'yes');
$sandbox_mode = get_option('woo_factura_com_sandbox_mode', 'yes');
$debug_mode = get_option('woo_factura_com_debug_mode', 'no');
$api_key = get_option('woo_factura_com_api_key', '');
$secret_key = get_option('woo_factura_com_secret_key', '');
$serie_id = get_option('woo_factura_com_serie_id', '');
$auto_generate = get_option('woo_factura_com_auto_generate', 'no');
$send_email = get_option('woo_factura_com_send_email', 'yes');
$require_rfc = get_option('woo_factura_com_require_rfc', 'no');

// Obtener estadísticas básicas
if (function_exists('woo_factura_com_get_stats')) {
    $stats = woo_factura_com_get_stats();
} else {
    $stats = array(
        'total_generated' => 0,
        'total_cancelled' => 0,
        'demo_generated' => 0,
        'last_30_days' => 0
    );
}
?>

<div class="wrap woo-factura-com-settings">
    <h1><?php _e('Configuración de Factura.com', 'woo-factura-com'); ?></h1>
    
    <!-- Estadísticas rápidas -->
    <div class="woo-factura-com-stats">
        <div class="woo-factura-com-stat-box">
            <h3><?php echo number_format($stats['total_generated']); ?></h3>
            <p><?php _e('CFDIs Generados', 'woo-factura-com'); ?></p>
        </div>
        <div class="woo-factura-com-stat-box">
            <h3><?php echo number_format($stats['total_cancelled']); ?></h3>
            <p><?php _e('CFDIs Cancelados', 'woo-factura-com'); ?></p>
        </div>
        <div class="woo-factura-com-stat-box">
            <h3><?php echo number_format($stats['last_30_days']); ?></h3>
            <p><?php _e('Últimos 30 días', 'woo-factura-com'); ?></p>
        </div>
        <div class="woo-factura-com-stat-box">
            <h3 class="api-status <?php echo $demo_mode === 'yes' ? 'demo' : ($sandbox_mode === 'yes' ? 'sandbox' : 'production'); ?>">
                <?php echo $demo_mode === 'yes' ? __('Demo', 'woo-factura-com') : ($sandbox_mode === 'yes' ? __('Sandbox', 'woo-factura-com') : __('Producción', 'woo-factura-com')); ?>
            </h3>
            <p><?php _e('Modo Actual', 'woo-factura-com'); ?></p>
        </div>
    </div>
    
    <!-- Avisos importantes -->
    <?php if ($demo_mode === 'yes'): ?>
    <div class="woo-factura-com-notice warning">
        <h4><?php _e('Modo Demo Activo', 'woo-factura-com'); ?></h4>
        <p><?php _e('Los CFDIs generados son simulados y no tienen validez fiscal. Configura las credenciales de Factura.com para generar CFDIs reales.', 'woo-factura-com'); ?></p>
    </div>
    <?php elseif ($sandbox_mode === 'yes'): ?>
    <div class="woo-factura-com-notice">
        <h4><?php _e('Modo Sandbox Activo', 'woo-factura-com'); ?></h4>
        <p><?php _e('Estás usando el entorno de pruebas de Factura.com. Los CFDIs son reales pero no tienen validez fiscal.', 'woo-factura-com'); ?></p>
    </div>
    <?php endif; ?>
    
    <!-- Tabs de navegación -->
    <nav class="nav-tab-wrapper">
        <a href="#general" class="nav-tab nav-tab-active"><?php _e('General', 'woo-factura-com'); ?></a>
        <a href="#fiscal" class="nav-tab"><?php _e('Configuración Fiscal', 'woo-factura-com'); ?></a>
        <a href="#advanced" class="nav-tab"><?php _e('Avanzado', 'woo-factura-com'); ?></a>
        <a href="#tools" class="nav-tab"><?php _e('Herramientas', 'woo-factura-com'); ?></a>
    </nav>
    
    <form method="post" action="">
        <?php wp_nonce_field('woo_factura_com_save_settings', 'woo_factura_com_settings_nonce'); ?>
        
        <!-- Tab: General -->
        <div id="general" class="tab-content">
            <div class="woo-factura-com-field-group">
                <h3><?php _e('Configuración General', 'woo-factura-com'); ?></h3>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Modo de Operación', 'woo-factura-com'); ?></th>
                        <td>
                            <fieldset>
                                <label>
                                    <input type="radio" name="demo_mode" value="yes" <?php checked($demo_mode, 'yes'); ?>>
                                    <?php _e('Modo Demo', 'woo-factura-com'); ?>
                                </label>
                                <p class="description"><?php _e('Genera CFDIs simulados para pruebas. No requiere credenciales.', 'woo-factura-com'); ?></p>
                                <br>
                                <label>
                                    <input type="radio" name="demo_mode" value="no" <?php checked($demo_mode, 'no'); ?>>
                                    <?php _e('Modo Real', 'woo-factura-com'); ?>
                                </label>
                                <p class="description"><?php _e('Conecta con la API de Factura.com. Requiere credenciales válidas.', 'woo-factura-com'); ?></p>
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr class="api-credentials-section" style="<?php echo $demo_mode === 'yes' ? 'display: none;' : ''; ?>">
                        <th scope="row"><?php _e('Entorno API', 'woo-factura-com'); ?></th>
                        <td>
                            <label>
                                <input type="radio" name="sandbox_mode" value="yes" <?php checked($sandbox_mode, 'yes'); ?>>
                                <?php _e('Sandbox (Pruebas)', 'woo-factura-com'); ?>
                            </label><br>
                            <label>
                                <input type="radio" name="sandbox_mode" value="no" <?php checked($sandbox_mode, 'no'); ?>>
                                <?php _e('Producción', 'woo-factura-com'); ?>
                            </label>
                            <p class="description"><?php _e('Sandbox para pruebas, Producción para CFDIs con validez fiscal.', 'woo-factura-com'); ?></p>
                        </td>
                    </tr>
                    
                    <tr class="api-credentials-section" style="<?php echo $demo_mode === 'yes' ? 'display: none;' : ''; ?>">
                        <th scope="row"><label for="api_key"><?php _e('F-Api-Key', 'woo-factura-com'); ?></label></th>
                        <td>
                            <input type="password" id="api_key" name="api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text">
                            <p class="description"><?php _e('Tu API Key de Factura.com. Obténla desde tu panel de Factura.com.', 'woo-factura-com'); ?></p>
                        </td>
                    </tr>
                    
                    <tr class="api-credentials-section" style="<?php echo $demo_mode === 'yes' ? 'display: none;' : ''; ?>">
                        <th scope="row"><label for="secret_key"><?php _e('F-Secret-Key', 'woo-factura-com'); ?></label></th>
                        <td>
                            <input type="password" id="secret_key" name="secret_key" value="<?php echo esc_attr($secret_key); ?>" class="regular-text">
                            <p class="description"><?php _e('Tu Secret Key de Factura.com. Manténla segura.', 'woo-factura-com'); ?></p>
                        </td>
                    </tr>
                    
                    <tr class="api-credentials-section" style="<?php echo $demo_mode === 'yes' ? 'display: none;' : ''; ?>">
                        <th scope="row"><label for="serie_id"><?php _e('Serie ID', 'woo-factura-com'); ?></label></th>
                        <td>
                            <input type="number" id="serie_id" name="serie_id" value="<?php echo esc_attr($serie_id); ?>" class="small-text">
                            <p class="description"><?php _e('ID numérico de tu serie configurada en Factura.com (ej: 1247).', 'woo-factura-com'); ?></p>
                        </td>
                    </tr>
                    
                    <tr class="api-credentials-section" style="<?php echo $demo_mode === 'yes' ? 'display: none;' : ''; ?>">
                        <th scope="row"></th>
                        <td>
                            <button type="button" id="test-api-connection" class="button" data-nonce="<?php echo wp_create_nonce('test_api_connection'); ?>">
                                <?php _e('Probar Conexión API', 'woo-factura-com'); ?>
                            </button>
                            <div id="connection-test-result"></div>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Generación Automática', 'woo-factura-com'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="auto_generate" value="yes" <?php checked($auto_generate, 'yes'); ?>>
                                <?php _e('Generar CFDI automáticamente cuando un pedido se complete', 'woo-factura-com'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Envío de Email', 'woo-factura-com'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="send_email" value="yes" <?php checked($send_email, 'yes'); ?>>
                                <?php _e('Enviar CFDI por email al cliente automáticamente', 'woo-factura-com'); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('RFC Obligatorio', 'woo-factura-com'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="require_rfc" value="yes" <?php checked($require_rfc, 'yes'); ?>>
                                <?php _e('Requerir RFC en el checkout', 'woo-factura-com'); ?>
                            </label>
                            <p class="description"><?php _e('Si no se requiere, se usará el RFC genérico XAXX010101000.', 'woo-factura-com'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <!-- Tab: Configuración Fiscal -->
        <div id="fiscal" class="tab-content" style="display: none;">
            <div class="woo-factura-com-field-group">
                <h3><?php _e('Configuración Fiscal', 'woo-factura-com'); ?></h3>
                <p><?php _e('Configura los valores según los catálogos oficiales del SAT.', 'woo-factura-com'); ?></p>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="uso_cfdi"><?php _e('Uso de CFDI', 'woo-factura-com'); ?></label></th>
                        <td>
                            <select id="uso_cfdi" name="uso_cfdi">
                                <?php foreach (WooFacturaComUtilities::get_uso_cfdi_options() as $key => $label): ?>
                                    <option value="<?php echo esc_attr($key); ?>" <?php selected(get_option('woo_factura_com_uso_cfdi', 'G01'), $key); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php _e('Uso más común que darán tus clientes al CFDI.', 'woo-factura-com'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="forma_pago"><?php _e('Forma de Pago', 'woo-factura-com'); ?></label></th>
                        <td>
                            <select id="forma_pago" name="forma_pago">
                                <?php foreach (WooFacturaComUtilities::get_forma_pago_options() as $key => $label): ?>
                                    <option value="<?php echo esc_attr($key); ?>" <?php selected(get_option('woo_factura_com_forma_pago', '01'), $key); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="metodo_pago"><?php _e('Método de Pago', 'woo-factura-com'); ?></label></th>
                        <td>
                            <select id="metodo_pago" name="metodo_pago">
                                <?php foreach (WooFacturaComUtilities::get_metodo_pago_options() as $key => $label): ?>
                                    <option value="<?php echo esc_attr($key); ?>" <?php selected(get_option('woo_factura_com_metodo_pago', 'PUE'), $key); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="lugar_expedicion"><?php _e('Lugar de Expedición', 'woo-factura-com'); ?></label></th>
                        <td>
                            <input type="text" id="lugar_expedicion" name="lugar_expedicion" value="<?php echo esc_attr(get_option('woo_factura_com_lugar_expedicion', '')); ?>" maxlength="5" pattern="[0-9]{5}" class="small-text">
                            <p class="description"><?php _e('Código postal donde tienes registrada tu empresa (5 dígitos).', 'woo-factura-com'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="clave_prod_serv"><?php _e('Clave Producto/Servicio', 'woo-factura-com'); ?></label></th>
                        <td>
                            <input type="text" id="clave_prod_serv" name="clave_prod_serv" value="<?php echo esc_attr(get_option('woo_factura_com_clave_prod_serv', '81112101')); ?>" maxlength="8" class="regular-text">
                            <p class="description"><?php _e('Clave del SAT para tus productos/servicios. Por defecto: 81112101 (Servicios de comercio por internet).', 'woo-factura-com'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="clave_unidad"><?php _e('Unidad de Medida', 'woo-factura-com'); ?></label></th>
                        <td>
                            <select id="clave_unidad" name="clave_unidad">
                                <?php foreach (WooFacturaComUtilities::get_unidad_medida_options() as $key => $label): ?>
                                    <option value="<?php echo esc_attr($key); ?>" <?php selected(get_option('woo_factura_com_clave_unidad', 'E48'), $key); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <br>
                            <input type="text" name="unidad" value="<?php echo esc_attr(get_option('woo_factura_com_unidad', 'Unidad de servicio')); ?>" placeholder="<?php _e('Descripción de la unidad', 'woo-factura-com'); ?>" class="regular-text">
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="tasa_iva"><?php _e('Tasa de IVA', 'woo-factura-com'); ?></label></th>
                        <td>
                            <input type="number" id="tasa_iva" name="tasa_iva" value="<?php echo esc_attr(get_option('woo_factura_com_tasa_iva', '0.16')); ?>" step="0.01" min="0" max="1" class="small-text">
                            <p class="description"><?php _e('Tasa de IVA a aplicar (ej: 0.16 para 16%).', 'woo-factura-com'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="objeto_impuesto"><?php _e('Objeto de Impuesto', 'woo-factura-com'); ?></label></th>
                        <td>
                            <select id="objeto_impuesto" name="objeto_impuesto">
                                <?php foreach (WooFacturaComUtilities::get_objeto_impuesto_options() as $key => $label): ?>
                                    <option value="<?php echo esc_attr($key); ?>" <?php selected(get_option('woo_factura_com_objeto_impuesto', '02'), $key); ?>><?php echo esc_html($label); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <!-- Tab: Configuración Avanzada -->
        <div id="advanced" class="tab-content" style="display: none;">
            <div class="woo-factura-com-field-group">
                <h3><?php _e('Configuración Avanzada', 'woo-factura-com'); ?></h3>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Modo Debug', 'woo-factura-com'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="debug_mode" value="yes" <?php checked($debug_mode, 'yes'); ?>>
                                <?php _e('Activar logging detallado para debugging', 'woo-factura-com'); ?>
                            </label>
                            <p class="description"><?php _e('Solo activar si necesitas diagnosticar problemas. Puede afectar el rendimiento.', 'woo-factura-com'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="tipo_cambio"><?php _e('Tipo de Cambio', 'woo-factura-com'); ?></label></th>
                        <td>
                            <input type="number" id="tipo_cambio" name="tipo_cambio" value="<?php echo esc_attr(get_option('woo_factura_com_tipo_cambio', '20.00')); ?>" step="0.01" min="1" class="small-text">
                            <p class="description"><?php _e('Tipo de cambio para monedas extranjeras (solo si vendes en USD, EUR, etc.).', 'woo-factura-com'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="timeout_api"><?php _e('Timeout API', 'woo-factura-com'); ?></label></th>
                        <td>
                            <input type="number" id="timeout_api" name="timeout_api" value="<?php echo esc_attr(get_option('woo_factura_com_timeout_api', '60')); ?>" min="30" max="300" class="small-text">
                            <p class="description"><?php _e('Tiempo límite en segundos para peticiones API (30-300).', 'woo-factura-com'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label for="retention_days"><?php _e('Retención de Logs', 'woo-factura-com'); ?></label></th>
                        <td>
                            <input type="number" id="retention_days" name="retention_days" value="<?php echo esc_attr(get_option('woo_factura_com_retention_days', '30')); ?>" min="7" max="365" class="small-text">
                            <p class="description"><?php _e('Días que se conservan los logs antes de eliminarlos automáticamente.', 'woo-factura-com'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Eliminar Datos', 'woo-factura-com'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="remove_data_on_uninstall" value="yes" <?php checked(get_option('woo_factura_com_remove_data_on_uninstall', 'no'), 'yes'); ?>>
                                <?php _e('Eliminar todos los datos al desinstalar el plugin', 'woo-factura-com'); ?>
                            </label>
                            <p class="description" style="color: #d63384;"><?php _e('⚠️ CUIDADO: Esto eliminará permanentemente todos los CFDIs, logs y configuraciones.', 'woo-factura-com'); ?></p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <!-- Tab: Herramientas -->
        <div id="tools" class="tab-content" style="display: none;">
            <div class="woo-factura-com-field-group">
                <h3><?php _e('Herramientas de Diagnóstico', 'woo-factura-com'); ?></h3>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Información del Sistema', 'woo-factura-com'); ?></th>
                        <td>
                            <p><?php _e('Ver información técnica del servidor y configuración.', 'woo-factura-com'); ?></p>
                            <button type="button" id="show-system-info" class="button"><?php _e('Ver Información', 'woo-factura-com'); ?></button>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Probar Email', 'woo-factura-com'); ?></th>
                        <td>
                            <p><?php _e('Enviar un email de prueba para verificar la configuración.', 'woo-factura-com'); ?></p>
                            <button type="button" id="test-email" class="button"><?php _e('Probar Email', 'woo-factura-com'); ?></button>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Limpiar Logs', 'woo-factura-com'); ?></th>
                        <td>
                            <p><?php _e('Eliminar todos los logs del plugin para liberar espacio.', 'woo-factura-com'); ?></p>
                            <button type="button" id="clear-logs" class="button" data-nonce="<?php echo wp_create_nonce('clear_logs'); ?>"><?php _e('Limpiar Logs', 'woo-factura-com'); ?></button>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Exportar Configuración', 'woo-factura-com'); ?></th>
                        <td>
                            <p><?php _e('Descargar un backup de tu configuración actual.', 'woo-factura-com'); ?></p>
                            <button type="button" id="export-config" class="button" data-nonce="<?php echo wp_create_nonce('export_config'); ?>"><?php _e('Exportar', 'woo-factura-com'); ?></button>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Reparar Instalación', 'woo-factura-com'); ?></th>
                        <td>
                            <p><?php _e('Verificar y reparar tablas y configuraciones del plugin.', 'woo-factura-com'); ?></p>
                            <button type="button" id="repair-installation" class="button"><?php _e('Reparar', 'woo-factura-com'); ?></button>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Reiniciar Configuración', 'woo-factura-com'); ?></th>
                        <td>
                            <p><?php _e('Ejecutar el asistente de configuración nuevamente.', 'woo-factura-com'); ?></p>
                            <a href="<?php echo admin_url('admin.php?page=woo-factura-com-setup'); ?>" class="button"><?php _e('Reiniciar Setup', 'woo-factura-com'); ?></a>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        
        <p class="submit">
            <input type="submit" name="submit" id="submit" class="button-primary" value="<?php _e('Guardar Configuración', 'woo-factura-com'); ?>">
        </p>
    </form>
</div>

<!-- Scripts específicos de la página -->
<script>
jQuery(document).ready(function($) {
    // Tabs
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        
        // Actualizar tabs
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        // Mostrar contenido
        const target = $(this).attr('href');
        $('.tab-content').hide();
        $(target).show();
        
        // Actualizar URL
        if (history.pushState) {
            const newUrl = window.location.pathname + window.location.search + target;
            history.pushState(null, null, newUrl);
        }
    });
    
    // Mostrar tab desde URL
    if (window.location.hash) {
        const targetTab = $('.nav-tab[href="' + window.location.hash + '"]');
        if (targetTab.length) {
            targetTab.click();
        }
    }
    
    // Mostrar/ocultar credenciales según modo
    $('input[name="demo_mode"]').on('change', function() {
        const showCredentials = $(this).val() === 'no' && $(this).is(':checked');
        $('.api-credentials-section').toggle(showCredentials);
    });
    
    // Probar conexión API
    $('#test-api-connection').on('click', function() {
        const $button = $(this);
        const originalText = $button.text();
        
        $button.text('<?php _e('Probando...', 'woo-factura-com'); ?>').prop('disabled', true);
        
        $.post(ajaxurl, {
            action: 'woo_factura_com_test_connection',
            nonce: $button.data('nonce'),
            api_key: $('#api_key').val(),
            secret_key: $('#secret_key').val(),
            sandbox_mode: $('input[name="sandbox_mode"]:checked').val()
        })
        .done(function(response) {
            $('#connection-test-result').html(
                '<div class="notice notice-' + (response.success ? 'success' : 'error') + ' inline">' +
                '<p>' + response.data.message + '</p>' +
                '</div>'
            );
        })
        .fail(function() {
            $('#connection-test-result').html(
                '<div class="notice notice-error inline">' +
                '<p>Error de conexión con el servidor</p>' +
                '</div>'
            );
        })
        .always(function() {
            $button.text(originalText).prop('disabled', false);
        });
    });
    
    // Mostrar información del sistema
    $('#show-system-info').on('click', function() {
        const info = `
INFORMACIÓN DEL SISTEMA

Plugin: <?php echo WOO_FACTURA_COM_VERSION; ?>
WordPress: <?php echo get_bloginfo('version'); ?>
WooCommerce: <?php echo defined('WC_VERSION') ? WC_VERSION : 'N/A'; ?>
PHP: <?php echo PHP_VERSION; ?>
Servidor: <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?>
Modo Demo: <?php echo $demo_mode === 'yes' ? 'Activo' : 'Inactivo'; ?>
Modo Sandbox: <?php echo $sandbox_mode === 'yes' ? 'Activo' : 'Inactivo'; ?>
Debug: <?php echo $debug_mode === 'yes' ? 'Activo' : 'Inactivo'; ?>
        `.trim();
        
        alert(info);
    });
    
    // Probar email
    $('#test-email').on('click', function() {
        const email = prompt('<?php _e('Ingresa el email de prueba:', 'woo-factura-com'); ?>', '<?php echo get_option('admin_email'); ?>');
        
        if (email) {
            $.post(ajaxurl, {
                action: 'woo_factura_com_test_email',
                email: email,
                nonce: '<?php echo wp_create_nonce('test_email'); ?>'
            })
            .done(function(response) {
                alert(response.data.message);
            });
        }
    });
    
    // Limpiar logs
    $('#clear-logs').on('click', function() {
        if (confirm('<?php _e('¿Estás seguro de eliminar todos los logs?', 'woo-factura-com'); ?>')) {
            const $button = $(this);
            
            $.post(ajaxurl, {
                action: 'woo_factura_com_clear_logs',
                nonce: $button.data('nonce')
            })
            .done(function(response) {
                alert(response.data.message);
            });
        }
    });
    
    // Validaciones del formulario
    $('#lugar_expedicion').on('input', function() {
        const value = $(this).val();
        if (value && !/^\d{5}$/.test(value)) {
            $(this).css('border-color', '#dc3545');
        } else {
            $(this).css('border-color', '');
        }
    });
    
    $('#tasa_iva').on('input', function() {
        const value = parseFloat($(this).val());
        if (isNaN(value) || value < 0 || value > 1) {
            $(this).css('border-color', '#dc3545');
        } else {
            $(this).css('border-color', '');
        }
    });
});
</script>

<style>
/* Estilos específicos para la página de configuración */
.woo-factura-com-settings .form-table th {
    width: 200px;
    padding: 20px 10px 20px 0;
    font-weight: 600;
}

.woo-factura-com-settings .form-table td {
    padding: 15px 10px;
}

.woo-factura-com-settings .description {
    color: #666;
    font-style: italic;
    margin-top: 8px;
    font-size: 13px;
}

.woo-factura-com-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin: 20px 0;
}

.woo-factura-com-stat-box {
    background: white;
    border: 1px solid #ddd;
    border-radius: 6px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.woo-factura-com-stat-box h3 {
    margin: 0 0 10px 0;
    font-size: 32px;
    font-weight: 700;
    color: #667eea;
}

.woo-factura-com-stat-box p {
    margin: 0;
    color: #666;
    font-weight: 500;
}

.woo-factura-com-notice {
    background: white;
    border: 1px solid #ddd;
    border-left: 4px solid #667eea;
    border-radius: 6px;
    padding: 15px 20px;
    margin: 20px 0;
}

.woo-factura-com-notice.warning {
    border-left-color: #ffc107;
    background: #fff3cd;
}

.woo-factura-com-notice h4 {
    margin: 0 0 10px 0;
    color: #495057;
}

.woo-factura-com-field-group {
    background: white;
    border: 1px solid #ddd;
    border-radius: 6px;
    margin: 20px 0;
    overflow: hidden;
}

.woo-factura-com-field-group h3 {
    margin: 0;
    padding: 15px 20px;
    background: #f8f9fa;
    border-bottom: 1px solid #ddd;
    color: #495057;
    font-size: 16px;
    font-weight: 600;
}

.nav-tab-wrapper {
    margin: 20px 0 0 0;
    border-bottom: 1px solid #ddd;
}

.tab-content {
    margin-top: 0;
}

#connection-test-result {
    margin-top: 10px;
}

.notice.inline {
    margin: 5px 0;
    padding: 8px 12px;
}

@media (max-width: 768px) {
    .woo-factura-com-stats {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .woo-factura-com-settings .form-table th,
    .woo-factura-com-settings .form-table td {
        display: block;
        width: 100%;
        padding: 10px 0;
    }
    
    .woo-factura-com-settings .form-table th {
        border-bottom: none;
        font-weight: 600;
    }
}

@media (max-width: 480px) {
    .woo-factura-com-stats {
        grid-template-columns: 1fr;
    }
}
</style>