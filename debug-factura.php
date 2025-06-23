<?php
/**
 * ARCHIVO DEBUG PARA WooCommerce Factura.com
 * 
 * Guardar como: debug-factura.php en la carpeta raíz del plugin
 * Luego incluir en woo-factura-com.php con:
 * require_once WOO_FACTURA_COM_PLUGIN_DIR . 'debug-factura.php';
 */

if (!defined('ABSPATH')) {
    exit;
}

// Solo cargar en admin y si es usuario admin
if (!is_admin() || !current_user_can('manage_options')) {
    return;
}

// ============== DEBUG DE CONFIGURACIÓN ==============
add_action('admin_init', function() {
    error_log('=== WooFacturaCom Debug Report ===');
    error_log('Demo mode: ' . get_option('woo_factura_com_demo_mode', 'NOT_SET'));
    error_log('Setup completed: ' . get_option('woo_factura_com_setup_completed', 'NOT_SET'));
    error_log('Auto generate: ' . get_option('woo_factura_com_auto_generate', 'NOT_SET'));
    error_log('RFC field: ' . get_option('woo_factura_com_add_rfc_field', 'NOT_SET'));
    error_log('Send email: ' . get_option('woo_factura_com_send_email', 'NOT_SET'));
    error_log('Uso CFDI: ' . get_option('woo_factura_com_uso_cfdi', 'NOT_SET'));
    error_log('Lugar expedición: ' . get_option('woo_factura_com_lugar_expedicion', 'NOT_SET'));
    error_log('Classes loaded:');
    error_log('- WooFacturaCom: ' . (class_exists('WooFacturaCom') ? 'YES' : 'NO'));
    error_log('- WooFacturaComHooks: ' . (class_exists('WooFacturaComHooks') ? 'YES' : 'NO'));
    error_log('- WooFacturaComRealCFDIManager: ' . (class_exists('WooFacturaComRealCFDIManager') ? 'YES' : 'NO'));
    error_log('- WooFacturaComRealAPIClient: ' . (class_exists('WooFacturaComRealAPIClient') ? 'YES' : 'NO'));
    error_log('WooCommerce active: ' . (class_exists('WooCommerce') ? 'YES' : 'NO'));
    error_log('================================');
});

// ============== BOTÓN MANUAL EN PEDIDOS ==============
add_action('woocommerce_admin_order_data_after_billing_address', function($order) {
    if (!current_user_can('manage_woocommerce')) return;
    
    $order_id = $order->get_id();
    $uuid = $order->get_meta('_factura_com_cfdi_uuid');
    
    echo '<div style="margin: 15px 0; padding: 15px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 6px;">';
    echo '<h4 style="margin: 0 0 10px 0; color: #495057;">🧾 WooCommerce Factura.com</h4>';
    
    if ($uuid) {
        echo '<div style="color: #28a745; margin-bottom: 10px;">✅ CFDI ya generado</div>';
        echo '<p><strong>UUID:</strong> <code style="background: #e9ecef; padding: 2px 6px; border-radius: 3px;">' . esc_html($uuid) . '</code></p>';
        
        $pdf_url = $order->get_meta('_factura_com_cfdi_pdf_url');
        $xml_url = $order->get_meta('_factura_com_cfdi_xml_url');
        
        if ($pdf_url) {
            echo '<a href="' . esc_url($pdf_url) . '" target="_blank" class="button button-small" style="margin-right: 10px;">📄 Ver PDF</a>';
        }
        if ($xml_url) {
            echo '<a href="' . esc_url($xml_url) . '" target="_blank" class="button button-small">📁 Ver XML</a>';
        }
        
        echo '<br><br><button type="button" class="button button-secondary" onclick="regenerateCFDI(' . $order_id . ')">🔄 Regenerar CFDI</button>';
    } else {
        echo '<div style="color: #856404; margin-bottom: 10px;">⚠️ Sin CFDI generado</div>';
        echo '<p style="margin-bottom: 15px;">Genera un CFDI demo para probar la funcionalidad:</p>';
        echo '<button type="button" class="button button-primary" onclick="generateCFDIDemo(' . $order_id . ')">🚀 Generar CFDI Demo</button>';
    }
    
    echo '<div id="cfdi-result-' . $order_id . '" style="margin-top: 15px;"></div>';
    echo '</div>';
    
    // JavaScript para las acciones
    ?>
    <script>
    function generateCFDIDemo(orderId) {
        if (!confirm("¿Generar CFDI demo para este pedido?\n\nEsto creará un CFDI simulado para pruebas.")) return;
        
        var resultDiv = document.getElementById("cfdi-result-" + orderId);
        resultDiv.innerHTML = "<div style='padding: 10px; background: #cce5ff; border-radius: 4px;'>⏳ Generando CFDI demo... Por favor espera.</div>";
        
        var data = {
            action: "generate_cfdi_demo_manual",
            order_id: orderId,
            nonce: "<?php echo wp_create_nonce('generate_cfdi_demo'); ?>"
        };
        
        jQuery.post(ajaxurl, data, function(response) {
            if (response.success) {
                resultDiv.innerHTML = "<div style='padding: 10px; background: #d4edda; color: #155724; border-radius: 4px;'>✅ " + response.data + "</div>";
                setTimeout(function() { location.reload(); }, 2000);
            } else {
                resultDiv.innerHTML = "<div style='padding: 10px; background: #f8d7da; color: #721c24; border-radius: 4px;'>❌ Error: " + response.data + "</div>";
            }
        }).fail(function() {
            resultDiv.innerHTML = "<div style='padding: 10px; background: #f8d7da; color: #721c24; border-radius: 4px;'>❌ Error de conexión</div>";
        });
    }
    
    function regenerateCFDI(orderId) {
        if (!confirm("¿Regenerar el CFDI?\n\nEsto cancelará el CFDI actual y creará uno nuevo.")) return;
        
        var resultDiv = document.getElementById("cfdi-result-" + orderId);
        resultDiv.innerHTML = "<div style='padding: 10px; background: #fff3cd; border-radius: 4px;'>⏳ Regenerando CFDI...</div>";
        
        var data = {
            action: "regenerate_cfdi_demo_manual",
            order_id: orderId,
            nonce: "<?php echo wp_create_nonce('regenerate_cfdi_demo'); ?>"
        };
        
        jQuery.post(ajaxurl, data, function(response) {
            if (response.success) {
                resultDiv.innerHTML = "<div style='padding: 10px; background: #d4edda; color: #155724; border-radius: 4px;'>✅ " + response.data + "</div>";
                setTimeout(function() { location.reload(); }, 2000);
            } else {
                resultDiv.innerHTML = "<div style='padding: 10px; background: #f8d7da; color: #721c24; border-radius: 4px;'>❌ Error: " + response.data + "</div>";
            }
        });
    }
    </script>
    <?php
});

// ============== HANDLERS AJAX ==============

// Handler para generar CFDI demo
add_action('wp_ajax_generate_cfdi_demo_manual', function() {
    check_ajax_referer('generate_cfdi_demo', 'nonce');
    
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error('Sin permisos suficientes');
    }
    
    $order_id = intval($_POST['order_id']);
    error_log('=== Manual CFDI Demo Generation ===');
    error_log('Order ID: ' . $order_id);
    error_log('User: ' . wp_get_current_user()->user_login);
    
    $order = wc_get_order($order_id);
    if (!$order) {
        error_log('ERROR: Order not found');
        wp_send_json_error('Pedido no encontrado');
    }
    
    error_log('Order data: Status=' . $order->get_status() . ', Total=' . $order->get_total());
    
    // Verificar modo demo
    $demo_mode = get_option('woo_factura_com_demo_mode', 'yes');
    error_log('Demo mode: ' . $demo_mode);
    
    if ($demo_mode !== 'yes') {
        wp_send_json_error('Plugin no está en modo demo. Ve a configuración para activarlo.');
    }
    
    // Verificar si ya tiene CFDI
    $existing_uuid = $order->get_meta('_factura_com_cfdi_uuid');
    if ($existing_uuid) {
        wp_send_json_error('Este pedido ya tiene un CFDI. UUID: ' . $existing_uuid);
    }
    
    // Intentar generar CFDI
    if (!class_exists('WooFacturaComRealCFDIManager')) {
        error_log('ERROR: WooFacturaComRealCFDIManager class not found');
        wp_send_json_error('Clase WooFacturaComRealCFDIManager no encontrada. Verifica que el plugin esté correctamente instalado.');
    }
    
    try {
        $cfdi_manager = new WooFacturaComRealCFDIManager();
        $result = $cfdi_manager->generate_cfdi_for_order($order_id);
        
        error_log('CFDI Generation Result: ' . print_r($result, true));
        
        if ($result && isset($result['success']) && $result['success']) {
            error_log('SUCCESS: CFDI generated with UUID: ' . $result['uuid']);
            wp_send_json_success('CFDI demo generado exitosamente! UUID: ' . $result['uuid']);
        } else {
            $error_msg = isset($result['error']) ? $result['error'] : 'Error desconocido en la generación';
            error_log('ERROR: CFDI generation failed: ' . $error_msg);
            wp_send_json_error($error_msg);
        }
    } catch (Exception $e) {
        error_log('EXCEPTION: ' . $e->getMessage());
        wp_send_json_error('Excepción: ' . $e->getMessage());
    }
});

// Handler para regenerar CFDI
add_action('wp_ajax_regenerate_cfdi_demo_manual', function() {
    check_ajax_referer('regenerate_cfdi_demo', 'nonce');
    
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error('Sin permisos');
    }
    
    $order_id = intval($_POST['order_id']);
    $order = wc_get_order($order_id);
    
    if (!$order) {
        wp_send_json_error('Pedido no encontrado');
    }
    
    // Limpiar CFDI anterior
    $meta_keys = [
        '_factura_com_cfdi_uuid',
        '_factura_com_cfdi_pdf_url',
        '_factura_com_cfdi_xml_url',
        '_factura_com_cfdi_serie',
        '_factura_com_cfdi_folio',
        '_factura_com_cfdi_generated_at',
        '_factura_com_cfdi_environment'
    ];
    
    foreach ($meta_keys as $key) {
        $order->delete_meta_data($key);
    }
    $order->save();
    
    // Generar nuevo CFDI
    if (class_exists('WooFacturaComRealCFDIManager')) {
        $cfdi_manager = new WooFacturaComRealCFDIManager();
        $result = $cfdi_manager->generate_cfdi_for_order($order_id);
        
        if ($result && $result['success']) {
            wp_send_json_success('CFDI regenerado exitosamente! Nuevo UUID: ' . $result['uuid']);
        } else {
            wp_send_json_error($result['error'] ?? 'Error regenerando CFDI');
        }
    } else {
        wp_send_json_error('Clase CFDI Manager no encontrada');
    }
});

// ============== PRUEBA RÁPIDA CON URL ==============
add_action('admin_init', function() {
    if (!isset($_GET['test_cfdi_demo']) || !current_user_can('manage_options')) {
        return;
    }
    
    error_log('=== QUICK TEST CFDI DEMO ===');
    
    // Buscar pedidos
    $orders = wc_get_orders(array(
        'limit' => 1,
        'orderby' => 'date',
        'order' => 'DESC',
        'status' => array('completed', 'processing', 'on-hold')
    ));
    
    if (empty($orders)) {
        wp_die('❌ No se encontraron pedidos para probar.<br><br>
                <strong>Solución:</strong><br>
                1. Crea un pedido de prueba en WooCommerce<br>
                2. O ve a WooCommerce → Pedidos y abre cualquier pedido<br>
                3. Usa el botón "Generar CFDI Demo" que debería aparecer<br><br>
                <a href="' . admin_url('edit.php?post_type=shop_order') . '">« Ir a Pedidos</a>');
    }
    
    $order = $orders[0];
    $order_id = $order->get_id();
    
    error_log('Testing with order ID: ' . $order_id);
    
    // Limpiar CFDI anterior
    $order->delete_meta_data('_factura_com_cfdi_uuid');
    $order->save();
    
    // Verificar configuración
    $demo_mode = get_option('woo_factura_com_demo_mode', 'no');
    if ($demo_mode !== 'yes') {
        wp_die('❌ Plugin no está en modo demo.<br><br>
                <strong>Solución:</strong><br>
                Ve a WooCommerce → Factura.com y activa el "Modo Demo"<br><br>
                <a href="' . admin_url('admin.php?page=woo-factura-com') . '">« Ir a Configuración</a>');
    }
    
    // Generar CFDI
    if (class_exists('WooFacturaComRealCFDIManager')) {
        try {
            $cfdi_manager = new WooFacturaComRealCFDIManager();
            $result = $cfdi_manager->generate_cfdi_for_order($order_id);
            
            if ($result && $result['success']) {
                wp_die('✅ <strong>CFDI Demo generado exitosamente!</strong><br><br>
                        <strong>UUID:</strong> ' . $result['uuid'] . '<br>
                        <strong>Serie-Folio:</strong> ' . $result['serie'] . '-' . $result['folio'] . '<br>
                        <strong>Pedido:</strong> #' . $order->get_order_number() . '<br><br>
                        <a href="' . admin_url('post.php?post=' . $order_id . '&action=edit') . '" class="button-primary">Ver Pedido</a>
                        <a href="' . admin_url('edit.php?post_type=shop_order') . '" class="button">Ver Todos los Pedidos</a>');
            } else {
                wp_die('❌ <strong>Error generando CFDI:</strong><br><br>' . 
                       ($result['error'] ?? 'Error desconocido') . '<br><br>
                       <a href="' . admin_url('edit.php?post_type=shop_order') . '">« Volver a Pedidos</a>');
            }
        } catch (Exception $e) {
            wp_die('❌ <strong>Excepción:</strong><br><br>' . $e->getMessage() . '<br><br>
                   <a href="' . admin_url('edit.php?post_type=shop_order') . '">« Volver a Pedidos</a>');
        }
    } else {
        wp_die('❌ <strong>Clase WooFacturaComRealCFDIManager no encontrada.</strong><br><br>
               Esto indica que el plugin no se cargó correctamente.<br><br>
               <strong>Verifica:</strong><br>
               1. Que el plugin esté activado<br>
               2. Que no haya errores fatales de PHP<br>
               3. Que todos los archivos estén presentes<br><br>
               <a href="' . admin_url('plugins.php') . '">« Ver Plugins</a>');
    }
});

// ============== FIX AUTOMÁTICO DE CONFIGURACIÓN ==============
add_action('admin_init', function() {
    // Solo ejecutar una vez por sesión
    if (get_transient('woo_factura_com_debug_config_fixed')) return;
    
    error_log('Aplicando configuración básica para modo demo...');
    
    // Configuración básica garantizada
    $config = array(
        'woo_factura_com_demo_mode' => 'yes',
        'woo_factura_com_add_rfc_field' => 'yes',
        'woo_factura_com_sandbox_mode' => 'yes',
        'woo_factura_com_send_email' => 'yes',
        'woo_factura_com_uso_cfdi' => 'G01',
        'woo_factura_com_forma_pago' => '99',
        'woo_factura_com_metodo_pago' => 'PUE',
        'woo_factura_com_lugar_expedicion' => '44100',
        'woo_factura_com_clave_prod_serv' => '81112101',
        'woo_factura_com_clave_unidad' => 'E48',
        'woo_factura_com_unidad' => 'Unidad de servicio',
        'woo_factura_com_tasa_iva' => '0.16',
        'woo_factura_com_objeto_impuesto' => '02'
    );
    
    foreach ($config as $option => $value) {
        update_option($option, $value);
    }
    
    // Marcar como ejecutado por 1 hora
    set_transient('woo_factura_com_debug_config_fixed', true, HOUR_IN_SECONDS);
    
    error_log('Configuración básica aplicada correctamente');
});

// ============== INFORMACIÓN DE DEBUG EN DASHBOARD ==============
add_action('wp_dashboard_setup', function() {
    if (current_user_can('manage_options')) {
        wp_add_dashboard_widget(
            'woo_factura_com_debug_widget',
            '🧾 WooCommerce Factura.com - Debug',
            function() {
                echo '<div style="font-family: monospace; font-size: 12px;">';
                echo '<p><strong>Estado del Plugin:</strong></p>';
                echo '<ul>';
                echo '<li>Demo Mode: <code>' . get_option('woo_factura_com_demo_mode', 'NOT_SET') . '</code></li>';
                echo '<li>Setup Completed: <code>' . (get_option('woo_factura_com_setup_completed') ? 'YES' : 'NO') . '</code></li>';
                echo '<li>RFC Field: <code>' . get_option('woo_factura_com_add_rfc_field', 'NOT_SET') . '</code></li>';
                echo '<li>Auto Generate: <code>' . get_option('woo_factura_com_auto_generate', 'NOT_SET') . '</code></li>';
                echo '</ul>';
                
                $recent_orders = wc_get_orders(array('limit' => 3));
                if (!empty($recent_orders)) {
                    echo '<p><strong>Acciones Rápidas:</strong></p>';
                    echo '<a href="' . admin_url('?test_cfdi_demo=1') . '" class="button button-primary">🚀 Probar CFDI Demo</a> ';
                    echo '<a href="' . admin_url('edit.php?post_type=shop_order') . '" class="button">Ver Pedidos</a>';
                } else {
                    echo '<p style="color: orange;">⚠️ No hay pedidos para probar. <a href="' . admin_url('post-new.php?post_type=shop_order') . '">Crear pedido</a></p>';
                }
                echo '</div>';
            }
        );
    }
});
?>