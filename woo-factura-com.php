<?php
/**
 * Plugin Name: WooCommerce Factura.com
 * Plugin URI: https://github.com/BlackRubick/woo-factura-com
 * Description: Plugin para generar CFDIs automáticamente con Factura.com en WooCommerce
 * Version: 1.0.0
 * Author: CESAR.G.A
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: woo-factura-com
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 4.0
 * WC tested up to: 8.5
 */

if (!defined('ABSPATH')) {
    exit;
}

// Verificar si WooCommerce está activo
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>';
        echo esc_html__('WooCommerce Factura.com requiere que WooCommerce esté instalado y activado.', 'woo-factura-com');
        echo '</p></div>';
    });
    return;
}
 
define('WOO_FACTURA_COM_VERSION', '1.0.0');
define('WOO_FACTURA_COM_PLUGIN_FILE', __FILE__);
define('WOO_FACTURA_COM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WOO_FACTURA_COM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WOO_FACTURA_COM_PLUGIN_BASENAME', plugin_basename(__FILE__));

require_once WOO_FACTURA_COM_PLUGIN_DIR . 'includes/class-woo-factura-com.php';

add_action('plugins_loaded', 'woo_factura_com_init');
function woo_factura_com_init() {
    WooFacturaCom::get_instance();
}

register_activation_hook(__FILE__, function() {
    require_once WOO_FACTURA_COM_PLUGIN_DIR . 'includes/class-install.php';
    WooFacturaComInstaller::activate();
});

register_deactivation_hook(__FILE__, function() {
    require_once WOO_FACTURA_COM_PLUGIN_DIR . 'includes/class-install.php';
    WooFacturaComInstaller::deactivate();
});

add_action('before_woocommerce_init', function() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );
    }
});

add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links) {
    $settings_link = '<a href="' . esc_url(admin_url('admin.php?page=woo-factura-com')) . '">' . 
                    esc_html__('Configuración', 'woo-factura-com') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
});

add_filter('plugin_row_meta', function($links, $file) {
    if ($file === plugin_basename(__FILE__)) {
        $links[] = '<a href="https://factura.com" target="_blank">' . 
                  esc_html__('Factura.com', 'woo-factura-com') . '</a>';
        $links[] = '<a href="https://github.com/tu-usuario/woo-factura-com/issues" target="_blank">' . 
                  esc_html__('Soporte', 'woo-factura-com') . '</a>';
    }
    return $links;
}, 10, 2);


add_action('admin_notices', function() {
    if (!current_user_can('manage_options')) return;
    
    $demo_mode = get_option('woo_factura_com_demo_mode');
    $cfdi_manager_exists = class_exists('WooFacturaComRealCFDIManager');
    
    echo '<div class="notice notice-info" style="border-left: 4px solid #667eea;">';
    echo '<p><strong>🧾 ' . esc_html__('Factura.com Status', 'woo-factura-com') . ':</strong></p>';
    echo '<ul style="margin: 10px 0 10px 20px;">';
    echo '<li>' . esc_html__('Demo Mode', 'woo-factura-com') . ': ' . 
         ($demo_mode === 'yes' ? '<span style="color:green;"> ' . esc_html__('Activo', 'woo-factura-com') . '</span>' : 
                                '<span style="color:red;"> ' . esc_html__('Inactivo', 'woo-factura-com') . '</span>') . '</li>';
    echo '<li>' . esc_html__('CFDI Manager', 'woo-factura-com') . ': ' . 
         ($cfdi_manager_exists ? '<span style="color:green;"> ' . esc_html__('Cargado', 'woo-factura-com') . '</span>' : 
                                '<span style="color:red;"> ' . esc_html__('No encontrado', 'woo-factura-com') . '</span>') . '</li>';
    echo '<li>WooCommerce: ' . 
         (class_exists('WooCommerce') ? '<span style="color:green;"> ' . esc_html__('Activo', 'woo-factura-com') . '</span>' : 
                                      '<span style="color:red;"> ' . esc_html__('Inactivo', 'woo-factura-com') . '</span>') . '</li>';
    echo '</ul>';
    
    if ($demo_mode !== 'yes') {
        echo '<p><button type="button" class="button button-primary" onclick="wooFacturaActivarDemo()">🚀 ' . 
             esc_html__('Activar Modo Demo', 'woo-factura-com') . '</button></p>';
        
        echo '<script>
        function wooFacturaActivarDemo() {
            if (confirm("' . esc_js(__('¿Activar modo demo?', 'woo-factura-com')) . '")) {
                fetch("' . esc_url(admin_url('admin-ajax.php')) . '", {
                    method: "POST",
                    headers: {"Content-Type": "application/x-www-form-urlencoded"},
                    body: "action=activar_demo_factura&nonce=' . wp_create_nonce('activar_demo') . '"
                }).then(response => response.json()).then(data => {
                    if (data.success) {
                        alert("' . esc_js(__('Modo demo activado', 'woo-factura-com')) . '");
                        location.reload();
                    } else {
                        alert("' . esc_js(__('Error:', 'woo-factura-com')) . ' " + data.data);
                    }
                }).catch(err => {
                    alert("' . esc_js(__('Error de conexión', 'woo-factura-com')) . '");
                });
            }
        }
        </script>';
    }
    echo '</div>';
});

add_action('wp_ajax_activar_demo_factura', function() {
    check_ajax_referer('activar_demo', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(__('Sin permisos', 'woo-factura-com'));
    }
    
    $demo_options = array(
        'woo_factura_com_demo_mode' => 'yes',
        'woo_factura_com_add_rfc_field' => 'yes',
        'woo_factura_com_setup_completed' => true,
        'woo_factura_com_sandbox_mode' => 'yes',
        'woo_factura_com_lugar_expedicion' => '44100',
        'woo_factura_com_uso_cfdi' => 'G01',
        'woo_factura_com_forma_pago' => '99',
        'woo_factura_com_metodo_pago' => 'PUE'
    );
    
    foreach ($demo_options as $option => $value) {
        update_option($option, $value);
    }
    
    wp_send_json_success(__('Demo activado correctamente', 'woo-factura-com'));
});

add_action('woocommerce_admin_order_data_after_billing_address', function($order) {
    if (!current_user_can('manage_woocommerce') || !$order) return;
    
    $order_id = $order->get_id();
    $uuid = $order->get_meta('_factura_com_cfdi_uuid');
    
    echo '<div style="margin: 15px 0; padding: 15px; background: #e7f3ff; border: 1px solid #b3d9ff; border-radius: 4px;">';
    echo '<h4 style="margin: 0 0 10px 0;"> ' . esc_html__('Factura.com Debug', 'woo-factura-com') . '</h4>';
    
    if ($uuid) {
        echo '<p style="color: green;"> ' . esc_html__('CFDI generado', 'woo-factura-com') . ': <code>' . esc_html($uuid) . '</code></p>';
        
        $pdf_url = $order->get_meta('_factura_com_cfdi_pdf_url');
        $xml_url = $order->get_meta('_factura_com_cfdi_xml_url');
        
        if ($pdf_url) {
            echo '<a href="' . esc_url($pdf_url) . '" target="_blank" class="button button-small"> ' . esc_html__('Ver CFDI', 'woo-factura-com') . '</a> ';
        }
        if ($xml_url) {
            echo '<a href="' . esc_url($xml_url) . '" target="_blank" class="button button-small"> XML</a> ';
        }
        echo '<button type="button" class="button button-small" onclick="wooFacturaLimpiarCFDI(' . intval($order_id) . ')">🗑️ ' . 
             esc_html__('Limpiar', 'woo-factura-com') . '</button>';
    } else {
        echo '<p>' . esc_html__('Sin CFDI', 'woo-factura-com') . '. ';
        echo '<button type="button" class="button button-primary" onclick="wooFacturaGenerarCFDI(' . intval($order_id) . ')">' . 
             esc_html__('Generar CFDI Demo', 'woo-factura-com') . '</button></p>';
    }
    
    echo '<div id="woo-factura-resultado-' . intval($order_id) . '"></div>';
    echo '</div>';
    
    echo '<script>
    function wooFacturaGenerarCFDI(orderId) {
        if (!confirm("' . esc_js(__('¿Generar CFDI demo para pedido #', 'woo-factura-com')) . '" + orderId + "?")) return;
        
        const resultDiv = document.getElementById("woo-factura-resultado-" + orderId);
        resultDiv.innerHTML = "<p style=\"color:blue;\">⏳ ' . esc_js(__('Generando...', 'woo-factura-com')) . '</p>";
        
        fetch("' . esc_url(admin_url('admin-ajax.php')) . '", {
            method: "POST",
            headers: {"Content-Type": "application/x-www-form-urlencoded"},
            body: "action=generar_cfdi_demo_con_archivos&order_id=" + orderId + "&nonce=' . wp_create_nonce('generar_cfdi_demo') . '"
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                resultDiv.innerHTML = "<p style=\"color:green;\"> " + data.data + "</p>";
                setTimeout(() => location.reload(), 2000);
            } else {
                resultDiv.innerHTML = "<p style=\"color:red;\"> " + data.data + "</p>";
            }
        })
        .catch(err => {
            resultDiv.innerHTML = "<p style=\"color:red;\"> ' . esc_js(__('Error de conexión', 'woo-factura-com')) . '</p>";
        });
    }
    
    function wooFacturaLimpiarCFDI(orderId) {
        if (!confirm("' . esc_js(__('¿Limpiar CFDI del pedido #', 'woo-factura-com')) . '" + orderId + "?")) return;
        
        fetch("' . esc_url(admin_url('admin-ajax.php')) . '", {
            method: "POST",
            headers: {"Content-Type": "application/x-www-form-urlencoded"},
            body: "action=limpiar_cfdi_pedido&order_id=" + orderId + "&nonce=' . wp_create_nonce('limpiar_cfdi') . '"
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert("' . esc_js(__('CFDI eliminado', 'woo-factura-com')) . '");
                location.reload();
            } else {
                alert("' . esc_js(__('Error:', 'woo-factura-com')) . ' " + data.data);
            }
        });
    }
    </script>';
});

add_action('wp_ajax_generar_cfdi_demo_con_archivos', function() {
    check_ajax_referer('generar_cfdi_demo', 'nonce');
    
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error(__('Sin permisos', 'woo-factura-com'));
    }
    
    $order_id = intval($_POST['order_id'] ?? 0);
    if (!$order_id) {
        wp_send_json_error(__('ID de pedido requerido', 'woo-factura-com'));
    }
    
    $order = wc_get_order($order_id);
    if (!$order) {
        wp_send_json_error(__('Pedido no encontrado', 'woo-factura-com'));
    }
    
    $existing_uuid = $order->get_meta('_factura_com_cfdi_uuid');
    if ($existing_uuid) {
        wp_send_json_error(sprintf(__('Ya tiene CFDI: %s', 'woo-factura-com'), $existing_uuid));
    }
    
    update_option('woo_factura_com_demo_mode', 'yes');
    
    error_log('=== GENERANDO CFDI DEMO CON ARCHIVOS REALES ===');
    error_log('Order ID: ' . $order_id);
    error_log('Order Total: ' . $order->get_total());
    
    try {
        $demo_data = woo_factura_generate_demo_data($order);
        
        $files_result = woo_factura_create_demo_files($order, $demo_data);
        
        if (!$files_result['success']) {
            wp_send_json_error($files_result['message']);
        }
        
        woo_factura_save_cfdi_metadata($order, $demo_data, $files_result['urls']);
        
        woo_factura_add_order_note($order, $demo_data, $files_result['urls']);
        
        error_log('CFDI Demo con archivos generado: ' . $demo_data['uuid']);
        error_log('PDF URL: ' . $files_result['urls']['pdf']);
        error_log('XML URL: ' . $files_result['urls']['xml']);
        
        wp_send_json_success(sprintf(__('CFDI demo generado con archivos! UUID: %s', 'woo-factura-com'), $demo_data['uuid']));
        
    } catch (Exception $e) {
        error_log('Error generando archivos demo: ' . $e->getMessage());
        wp_send_json_error(__('Error interno del servidor', 'woo-factura-com'));
    }
});

add_action('wp_dashboard_setup', function() {
    if (current_user_can('manage_options')) {
        wp_add_dashboard_widget('test_cfdi_widget', '🧪 ' . __('Test CFDI Rápido', 'woo-factura-com'), function() {
            echo '<div>';
            echo '<h4>' . esc_html__('Estado del Plugin', 'woo-factura-com') . ':</h4>';
            echo '<ul>';
            echo '<li>' . esc_html__('Demo Mode', 'woo-factura-com') . ': ' . 
                 (get_option('woo_factura_com_demo_mode') === 'yes' ? '✅ ' . esc_html__('Activo', 'woo-factura-com') : 
                                                                     '❌ ' . esc_html__('Inactivo', 'woo-factura-com')) . '</li>';
            echo '<li>' . esc_html__('Classes', 'woo-factura-com') . ': ' . 
                 (class_exists('WooFacturaComRealCFDIManager') ? '✅ OK' : '❌ Error') . '</li>';
            echo '</ul>';
            
            if (function_exists('wc_get_orders')) {
                $orders = wc_get_orders(array('limit' => 1));
                if (!empty($orders)) {
                    $order = $orders[0];
                    echo '<h4>' . esc_html__('Test Rápido', 'woo-factura-com') . ':</h4>';
                    echo '<p>' . sprintf(
                        esc_html__('Pedido #%s - Total: %s', 'woo-factura-com'),
                        $order->get_order_number(),
                        $order->get_formatted_order_total()
                    ) . '</p>';
                    echo '<button type="button" class="button-primary" onclick="wooFacturaTestRapido(' . intval($order->get_id()) . ')">' .
                         '🚀 ' . esc_html__('Generar CFDI Demo', 'woo-factura-com') . '</button>';
                    echo '<div id="test-rapido-result"></div>';
                    
                    echo '<script>
                    function wooFacturaTestRapido(orderId) {
                        document.getElementById("test-rapido-result").innerHTML = "⏳ ' . esc_js(__('Generando...', 'woo-factura-com')) . '";
                        
                        fetch("' . esc_url(admin_url('admin-ajax.php')) . '", {
                            method: "POST",
                            headers: {"Content-Type": "application/x-www-form-urlencoded"},
                            body: "action=generar_cfdi_demo_con_archivos&order_id=" + orderId + "&nonce=' . wp_create_nonce('generar_cfdi_demo') . '"
                        })
                        .then(r => r.json())
                        .then(data => {
                            if (data.success) {
                                document.getElementById("test-rapido-result").innerHTML = "<p style=\"color:green;\">✅ " + data.data + "</p>";
                            } else {
                                document.getElementById("test-rapido-result").innerHTML = "<p style=\"color:red;\">❌ " + data.data + "</p>";
                            }
                        });
                    }
                    </script>';
                } else {
                    echo '<p style="color: orange;">' . esc_html__('No hay pedidos para probar', 'woo-factura-com') . '.</p>';
                    echo '<p><a href="' . esc_url(admin_url('post-new.php?post_type=shop_order')) . '" class="button">' . 
                         esc_html__('Crear Pedido', 'woo-factura-com') . '</a></p>';
                }
            }
            echo '</div>';
        });
    }
});

add_action('admin_init', function() {
    if (current_user_can('manage_options')) {
        error_log('=== Factura.com Debug ===');
        error_log('Demo mode: ' . get_option('woo_factura_com_demo_mode', 'NOT_SET'));
        error_log('CFDI Manager: ' . (class_exists('WooFacturaComRealCFDIManager') ? 'EXISTS' : 'MISSING'));
        error_log('WooCommerce: ' . (class_exists('WooCommerce') ? 'EXISTS' : 'MISSING'));
        error_log('========================');
    }
});

add_action('wp_ajax_limpiar_cfdi_pedido', function() {
    check_ajax_referer('limpiar_cfdi', 'nonce');
    
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error(__('Sin permisos', 'woo-factura-com'));
    }
    
    $order_id = intval($_POST['order_id'] ?? 0);
    if (!$order_id) {
        wp_send_json_error(__('Order ID requerido', 'woo-factura-com'));
    }
    
    $order = wc_get_order($order_id);
    if (!$order) {
        wp_send_json_error(__('Pedido no encontrado', 'woo-factura-com'));
    }
    
    $meta_keys = array(
        '_factura_com_cfdi_uuid',
        '_factura_com_cfdi_pdf_url', 
        '_factura_com_cfdi_xml_url',
        '_factura_com_cfdi_serie',
        '_factura_com_cfdi_folio',
        '_factura_com_cfdi_generated_at',
        '_factura_com_cfdi_environment',
        '_factura_com_cfdi_fecha_timbrado'
    );
    
    foreach ($meta_keys as $key) {
        $order->delete_meta_data($key);
    }
    $order->save();
    
    $order->add_order_note(__('CFDI eliminado para testing', 'woo-factura-com'));
    
    wp_send_json_success(__('CFDI eliminado del pedido', 'woo-factura-com'));
});



function woo_factura_generate_demo_data($order) {
    return array(
        'uuid' => woo_factura_generate_uuid(),
        'serie' => 'DEMO',
        'folio' => rand(1000, 9999),
        'fecha' => current_time('Y-m-d\TH:i:s'),
        'total' => $order->get_total(),
        'subtotal' => $order->get_subtotal(),
        'impuestos' => $order->get_total_tax()
    );
}



 
function woo_factura_generate_uuid() {
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}


function woo_factura_create_demo_files($order, $demo_data) {
    $upload_dir = wp_upload_dir();
    $demo_dir = $upload_dir['basedir'] . '/factura-com-demo/';
    
    if (!file_exists($demo_dir)) {
        if (!wp_mkdir_p($demo_dir)) {
            return array('success' => false, 'message' => __('No se pudo crear directorio demo', 'woo-factura-com'));
        }
        
        $htaccess_content = "Options -Indexes\n<Files ~ \"\\.(xml|txt|html)$\">\n    Header set Content-Disposition attachment\n</Files>";
        file_put_contents($demo_dir . '.htaccess', $htaccess_content);
    }
    
    if (!is_writable($demo_dir)) {
        return array('success' => false, 'message' => __('Directorio demo no escribible', 'woo-factura-com'));
    }
    
    $pdf_filename = 'cfdi-demo-' . $demo_data['uuid'] . '.html';
    $xml_filename = 'cfdi-demo-' . $demo_data['uuid'] . '.xml';
    
    $pdf_path = $demo_dir . $pdf_filename;
    $xml_path = $demo_dir . $xml_filename;
    
    $xml_content = woo_factura_generate_xml_content($order, $demo_data);
    $html_content = woo_factura_generate_html_cfdi($order, $demo_data); // Ahora es HTML puro
    
    if (file_put_contents($xml_path, $xml_content) === false) {
        return array('success' => false, 'message' => __('Error escribiendo archivo XML', 'woo-factura-com'));
    }
    
    if (file_put_contents($pdf_path, $html_content) === false) {
        unlink($xml_path);
        return array('success' => false, 'message' => __('Error escribiendo archivo HTML', 'woo-factura-com'));
    }
    
    $pdf_url = $upload_dir['baseurl'] . '/factura-com-demo/' . $pdf_filename;
    $xml_url = $upload_dir['baseurl'] . '/factura-com-demo/' . $xml_filename;
    
    return array(
        'success' => true,
        'urls' => array(
            'pdf' => $pdf_url,
            'xml' => $xml_url
        )
    );
}


function woo_factura_generate_xml_content($order, $demo_data) {
    $rfc_emisor = get_option('woo_factura_com_rfc_emisor', 'DEMO010101AAA');
    $nombre_emisor = get_option('blogname', 'Mi Empresa Demo');
    $lugar_expedicion = get_option('woo_factura_com_lugar_expedicion', '44100');
    
    $rfc_receptor = $order->get_meta('_billing_rfc') ?: 'XAXX010101000';
    $nombre_receptor = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
    if (empty($nombre_receptor)) {
        $nombre_receptor = 'Cliente Demo';
    }
    
    $total = number_format($demo_data['total'], 2, '.', '');
    $subtotal = number_format($demo_data['subtotal'], 2, '.', '');
    $impuestos = number_format($demo_data['impuestos'], 2, '.', '');
    
    $xml_content = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml_content .= '<cfdi:Comprobante xmlns:cfdi="http://www.sat.gob.mx/cfd/4" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"' . "\n";
    $xml_content .= '    xsi:schemaLocation="http://www.sat.gob.mx/cfd/4 http://www.sat.gob.mx/sitio_internet/cfd/4/cfdv40.xsd"' . "\n";
    $xml_content .= '    Version="4.0"' . "\n";
    $xml_content .= '    Serie="' . esc_attr($demo_data['serie']) . '"' . "\n";
    $xml_content .= '    Folio="' . esc_attr($demo_data['folio']) . '"' . "\n";
    $xml_content .= '    Fecha="' . esc_attr($demo_data['fecha']) . '"' . "\n";
    $xml_content .= '    Sello="DEMO_SELLO_' . substr(md5($demo_data['uuid']), 0, 20) . '"' . "\n";
    $xml_content .= '    FormaPago="99"' . "\n";
    $xml_content .= '    NoCertificado="30001000000400002330"' . "\n";
    $xml_content .= '    Certificado="DEMO_CERTIFICADO"' . "\n";
    $xml_content .= '    SubTotal="' . $subtotal . '"' . "\n";
    $xml_content .= '    Moneda="MXN"' . "\n";
    $xml_content .= '    Total="' . $total . '"' . "\n";
    $xml_content .= '    TipoDeComprobante="I"' . "\n";
    $xml_content .= '    MetodoPago="PUE"' . "\n";
    $xml_content .= '    LugarExpedicion="' . esc_attr($lugar_expedicion) . '">' . "\n";
    
    $xml_content .= '  <cfdi:Emisor Rfc="' . esc_attr($rfc_emisor) . '" Nombre="' . esc_attr($nombre_emisor) . '" RegimenFiscal="601"/>' . "\n";
    
    $xml_content .= '  <cfdi:Receptor Rfc="' . esc_attr($rfc_receptor) . '" Nombre="' . esc_attr($nombre_receptor) . '" DomicilioFiscalReceptor="' . esc_attr($lugar_expedicion) . '" RegimenFiscalReceptor="616" UsoCFDI="G01"/>' . "\n";
    
    $xml_content .= '  <cfdi:Conceptos>' . "\n";
    
    if ($order->get_items()) {
        foreach ($order->get_items() as $item) {
            $cantidad = $item->get_quantity();
            $precio = number_format($item->get_subtotal() / $cantidad, 6, '.', '');
            $importe = number_format($item->get_subtotal(), 6, '.', '');
            $iva_item = number_format($item->get_subtotal() * 0.16, 6, '.', '');
            
            $xml_content .= '    <cfdi:Concepto ClaveProdServ="81112101" NoIdentificacion="' . esc_attr($item->get_product_id()) . '"' . "\n";
            $xml_content .= '        Cantidad="' . number_format($cantidad, 6, '.', '') . '" ClaveUnidad="E48" Unidad="Unidad de servicio"' . "\n";
            $xml_content .= '        Descripcion="' . esc_attr($item->get_name()) . '"' . "\n";
            $xml_content .= '        ValorUnitario="' . $precio . '" Importe="' . $importe . '" ObjetoImp="02">' . "\n";
            $xml_content .= '      <cfdi:Impuestos>' . "\n";
            $xml_content .= '        <cfdi:Traslados>' . "\n";
            $xml_content .= '          <cfdi:Traslado Base="' . $importe . '" Impuesto="002" TipoFactor="Tasa" TasaOCuota="0.160000" Importe="' . $iva_item . '"/>' . "\n";
            $xml_content .= '        </cfdi:Traslados>' . "\n";
            $xml_content .= '      </cfdi:Impuestos>' . "\n";
            $xml_content .= '    </cfdi:Concepto>' . "\n";
        }
    } else {
        $xml_content .= '    <cfdi:Concepto ClaveProdServ="81112101" NoIdentificacion="DEMO001"' . "\n";
        $xml_content .= '        Cantidad="1.000000" ClaveUnidad="E48" Unidad="Unidad de servicio"' . "\n";
        $xml_content .= '        Descripcion="Servicio demo - Pedido #' . esc_attr($order->get_order_number()) . '"' . "\n";
        $xml_content .= '        ValorUnitario="' . $subtotal . '" Importe="' . $subtotal . '" ObjetoImp="02">' . "\n";
        $xml_content .= '      <cfdi:Impuestos>' . "\n";
        $xml_content .= '        <cfdi:Traslados>' . "\n";
        $xml_content .= '          <cfdi:Traslado Base="' . $subtotal . '" Impuesto="002" TipoFactor="Tasa" TasaOCuota="0.160000" Importe="' . $impuestos . '"/>' . "\n";
        $xml_content .= '        </cfdi:Traslados>' . "\n";
        $xml_content .= '      </cfdi:Impuestos>' . "\n";
        $xml_content .= '    </cfdi:Concepto>' . "\n";
    }
    
    $xml_content .= '  </cfdi:Conceptos>' . "\n";
    
    if ($impuestos > 0) {
        $xml_content .= '  <cfdi:Impuestos TotalImpuestosTrasladados="' . $impuestos . '">' . "\n";
        $xml_content .= '    <cfdi:Traslados>' . "\n";
        $xml_content .= '      <cfdi:Traslado Base="' . $subtotal . '" Impuesto="002" TipoFactor="Tasa" TasaOCuota="0.160000" Importe="' . $impuestos . '"/>' . "\n";
        $xml_content .= '    </cfdi:Traslados>' . "\n";
        $xml_content .= '  </cfdi:Impuestos>' . "\n";
    }
    
    $xml_content .= '  <cfdi:Complemento>' . "\n";
    $xml_content .= '    <tfd:TimbreFiscalDigital xmlns:tfd="http://www.sat.gob.mx/TimbreFiscalDigital" xsi:schemaLocation="http://www.sat.gob.mx/TimbreFiscalDigital http://www.sat.gob.mx/sitio_internet/cfd/TimbreFiscalDigital/TimbreFiscalDigitalv11.xsd"' . "\n";
    $xml_content .= '        Version="1.1"' . "\n";
    $xml_content .= '        UUID="' . esc_attr($demo_data['uuid']) . '"' . "\n";
    $xml_content .= '        FechaTimbrado="' . esc_attr($demo_data['fecha']) . '"' . "\n";
    $xml_content .= '        RfcProvCertif="DEM010101DEM"' . "\n";
    $xml_content .= '        SelloCFD="DEMO_SELLO_CFD_' . substr(md5($demo_data['uuid'] . 'cfd'), 0, 30) . '"' . "\n";
    $xml_content .= '        NoCertificadoSAT="30001000000400002330"' . "\n";
    $xml_content .= '        SelloSAT="DEMO_SELLO_SAT_' . substr(md5($demo_data['uuid'] . 'sat'), 0, 30) . '"/>' . "\n";
    $xml_content .= '  </cfdi:Complemento>' . "\n";
    
    $xml_content .= '</cfdi:Comprobante>' . "\n";
    
    return $xml_content;
}


function woo_factura_generate_pdf_content($order, $demo_data) {
    $html_content = woo_factura_generate_html_cfdi($order, $demo_data);
    
    if (class_exists('TCPDF') || class_exists('mPDF') || class_exists('Dompdf\\Dompdf')) {
        return woo_factura_html_to_pdf($html_content);
    }
    
    $text_content = "=== CFDI DEMO - FACTURA.COM ===\n\n";
    $text_content .= "⚠️ DOCUMENTO DE DEMOSTRACIÓN SIN VALIDEZ FISCAL\n\n";
    $text_content .= "UUID: " . $demo_data['uuid'] . "\n";
    $text_content .= "Serie-Folio: " . $demo_data['serie'] . "-" . $demo_data['folio'] . "\n";
    $text_content .= "Fecha: " . $demo_data['fecha'] . "\n";
    $text_content .= "Pedido: #" . $order->get_order_number() . "\n";
    $text_content .= "Total: " . $order->get_formatted_order_total() . "\n";
    $text_content .= "Cliente: " . $order->get_billing_email() . "\n";
    $text_content .= "Empresa: " . get_bloginfo('name') . "\n\n";
    
    $text_content .= "EMISOR:\n";
    $text_content .= "- RFC: " . get_option('woo_factura_com_rfc_emisor', 'DEMO010101AAA') . "\n";
    $text_content .= "- Nombre: " . get_bloginfo('name') . "\n";
    $text_content .= "- Régimen: 601 - General de Ley Personas Morales\n\n";
    
    $text_content .= "RECEPTOR:\n";
    $rfc_receptor = $order->get_meta('_billing_rfc') ?: 'XAXX010101000';
    $nombre_receptor = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
    $text_content .= "- RFC: " . $rfc_receptor . "\n";
    $text_content .= "- Nombre: " . ($nombre_receptor ?: 'Cliente Demo') . "\n";
    $text_content .= "- Email: " . $order->get_billing_email() . "\n\n";
    
    $text_content .= "CONCEPTOS:\n";
    if ($order->get_items()) {
        foreach ($order->get_items() as $item) {
            $text_content .= "- " . $item->get_name() . "\n";
            $text_content .= "  Cantidad: " . $item->get_quantity() . "\n";
            $text_content .= "  Precio: $" . number_format($item->get_subtotal() / $item->get_quantity(), 2) . "\n";
            $text_content .= "  Subtotal: $" . number_format($item->get_subtotal(), 2) . "\n\n";
        }
    } else {
        $text_content .= "- Servicio demo - Pedido #" . $order->get_order_number() . "\n";
        $text_content .= "  Cantidad: 1\n";
        $text_content .= "  Subtotal: " . $order->get_subtotal() . "\n\n";
    }
    
    $text_content .= "TOTALES:\n";
    $text_content .= "Subtotal: $" . number_format($demo_data['subtotal'], 2) . "\n";
    $text_content .= "IVA (16%): $" . number_format($demo_data['impuestos'], 2) . "\n";
    $text_content .= "TOTAL: $" . number_format($demo_data['total'], 2) . "\n\n";
    
    $text_content .= "TIMBRADO:\n";
    $text_content .= "Sello CFD: DEMO_SELLO_CFD_" . substr(md5($demo_data['uuid']), 0, 20) . "...\n";
    $text_content .= "Sello SAT: DEMO_SELLO_SAT_" . substr(md5($demo_data['uuid'] . 'sat'), 0, 20) . "...\n";
    $text_content .= "Certificado SAT: 30001000000400002330\n\n";
    
    $text_content .= "NOTA IMPORTANTE:\n";
    $text_content .= "Este es un archivo de demostración. Para generar PDFs reales,\n";
    $text_content .= "instale una librería PDF como TCPDF, mPDF o DomPDF.\n\n";
    $text_content .= "Para más información visite: https://factura.com\n";
    
    return $text_content;
}


function woo_factura_generate_html_cfdi($order, $demo_data) {
    $rfc_emisor = get_option('woo_factura_com_rfc_emisor', 'DEMO010101AAA');
    $nombre_emisor = get_bloginfo('name');
    $lugar_expedicion = get_option('woo_factura_com_lugar_expedicion', '44100');
    
    $rfc_receptor = $order->get_meta('_billing_rfc') ?: 'XAXX010101000';
    $nombre_receptor = trim($order->get_billing_first_name() . ' ' . $order->get_billing_last_name());
    if (empty($nombre_receptor)) {
        $nombre_receptor = 'Cliente Demo';
    }
    
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>CFDI Demo - <?php echo esc_html($demo_data['serie'] . '-' . $demo_data['folio']); ?></title>
        <style>
            body { 
                font-family: Arial, sans-serif; 
                margin: 20px; 
                font-size: 12px;
                line-height: 1.4;
            }
            .header { 
                text-align: center; 
                margin-bottom: 20px; 
                border-bottom: 2px solid #333;
                padding-bottom: 10px;
            }
            .cfdi-data { 
                background: #f8f9fa; 
                padding: 10px; 
                border-radius: 5px; 
                margin: 15px 0;
                border: 1px solid #ddd;
            }
            .emisor, .receptor { 
                display: inline-block; 
                width: 45%; 
                vertical-align: top; 
                margin: 10px;
                border: 1px solid #ccc;
                padding: 10px;
                border-radius: 5px;
            }
            .conceptos { 
                margin: 20px 0; 
            }
            table { 
                width: 100%; 
                border-collapse: collapse; 
                margin: 10px 0;
            }
            th, td { 
                border: 1px solid #ddd; 
                padding: 6px; 
                text-align: left; 
                font-size: 11px;
            }
            th { 
                background-color: #f2f2f2; 
                font-weight: bold;
            }
            .totales { 
                text-align: right; 
                margin: 20px 0;
                background: #f0f8f0;
                padding: 15px;
                border-radius: 5px;
            }
            .demo-notice { 
                background: #fff3cd; 
                border: 2px solid #ffeaa7; 
                padding: 15px; 
                border-radius: 5px; 
                margin: 20px 0; 
                color: #856404;
                text-align: center;
                font-weight: bold;
            }
            .timbrado {
                background: #e7f3ff;
                padding: 10px;
                border-radius: 5px;
                margin: 15px 0;
                font-size: 10px;
                word-break: break-all;
            }
            h1 { font-size: 16px; margin: 5px 0; }
            h2 { font-size: 14px; margin: 5px 0; }
            h3 { font-size: 12px; margin: 8px 0; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>COMPROBANTE FISCAL DIGITAL POR INTERNET</h1>
            <h2>CFDI VERSIÓN 4.0</h2>
        </div>
        
        <div class="demo-notice">
             DOCUMENTO DE DEMOSTRACIÓN - SIN VALIDEZ FISCAL
        </div>
        
        <div class="cfdi-data">
            <strong>Serie-Folio:</strong> <?php echo esc_html($demo_data['serie'] . '-' . $demo_data['folio']); ?> | 
            <strong>UUID:</strong> <?php echo esc_html($demo_data['uuid']); ?><br>
            <strong>Fecha:</strong> <?php echo esc_html($demo_data['fecha']); ?> | 
            <strong>Pedido:</strong> #<?php echo esc_html($order->get_order_number()); ?>
        </div>
        
        <div class="emisor">
            <h3> EMISOR</h3>
            <strong>Empresa:</strong> <?php echo esc_html($nombre_emisor); ?><br>
            <strong>RFC:</strong> <?php echo esc_html($rfc_emisor); ?><br>
            <strong>Régimen Fiscal:</strong> 601 - General de Ley Personas Morales<br>
            <strong>Lugar de Expedición:</strong> <?php echo esc_html($lugar_expedicion); ?>
        </div>
        
        <div class="receptor">
            <h3> RECEPTOR</h3>
            <strong>Nombre:</strong> <?php echo esc_html($nombre_receptor); ?><br>
            <strong>RFC:</strong> <?php echo esc_html($rfc_receptor); ?><br>
            <strong>Email:</strong> <?php echo esc_html($order->get_billing_email()); ?><br>
            <strong>Uso CFDI:</strong> G01 - Adquisición de mercancías
        </div>
        
        <div class="conceptos">
            <h3> CONCEPTOS</h3>
            <table>
                <thead>
                    <tr>
                        <th>Descripción</th>
                        <th>Cantidad</th>
                        <th>Precio Unitario</th>
                        <th>Importe</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($order->get_items()): ?>
                        <?php foreach ($order->get_items() as $item): ?>
                            <tr>
                                <td><?php echo esc_html($item->get_name()); ?></td>
                                <td><?php echo esc_html($item->get_quantity()); ?></td>
                                <td>$<?php echo number_format($item->get_subtotal() / $item->get_quantity(), 2); ?></td>
                                <td>$<?php echo number_format($item->get_subtotal(), 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td>Servicio demo - Pedido #<?php echo esc_html($order->get_order_number()); ?></td>
                            <td>1</td>
                            <td>$<?php echo number_format($demo_data['subtotal'], 2); ?></td>
                            <td>$<?php echo number_format($demo_data['subtotal'], 2); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div class="totales">
            <strong>Subtotal:</strong> $<?php echo number_format($demo_data['subtotal'], 2); ?><br>
            <strong>IVA (16%):</strong> $<?php echo number_format($demo_data['impuestos'], 2); ?><br>
            <strong>TOTAL:</strong> $<?php echo number_format($demo_data['total'], 2); ?>
        </div>
        
        <div class="timbrado">
            <h3> TIMBRADO FISCAL</h3>
            <strong>Sello Digital del CFDI:</strong><br>
            DEMO_SELLO_CFD_<?php echo substr(md5($demo_data['uuid']), 0, 40); ?>...<br><br>
            
            <strong>Sello Digital del SAT:</strong><br>
            DEMO_SELLO_SAT_<?php echo substr(md5($demo_data['uuid'] . 'sat'), 0, 40); ?>...<br><br>
            
            <strong>Certificado SAT:</strong> 30001000000400002330<br>
            <strong>RFC Proveedor de Certificación:</strong> DEM010101DEM
        </div>
        
        <div style="text-align: center; margin-top: 30px; font-size: 10px; color: #666;">
            Generado por WooCommerce Factura.com | Plugin versión <?php echo WOO_FACTURA_COM_VERSION; ?><br>
            Este documento es únicamente para demostración y pruebas
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}


function woo_factura_html_to_pdf($html) {

    return $html;
}


function woo_factura_save_cfdi_metadata($order, $demo_data, $urls) {
    $metadata = array(
        '_factura_com_cfdi_uuid' => $demo_data['uuid'],
        '_factura_com_cfdi_pdf_url' => $urls['pdf'],
        '_factura_com_cfdi_xml_url' => $urls['xml'],
        '_factura_com_cfdi_serie' => $demo_data['serie'],
        '_factura_com_cfdi_folio' => $demo_data['folio'],
        '_factura_com_cfdi_generated_at' => current_time('mysql'),
        '_factura_com_cfdi_environment' => 'demo',
        '_factura_com_cfdi_fecha_timbrado' => $demo_data['fecha']
    );
    
    foreach ($metadata as $key => $value) {
        $order->update_meta_data($key, $value);
    }
    $order->save();
}


function woo_factura_add_order_note($order, $demo_data, $urls) {
    $note = sprintf(
        __(" CFDI DEMO generado con archivos funcionales\n\nUUID: %s\nSerie-Folio: %s-%s\nFecha: %s\n\n📄 PDF: %s\n📄 XML: %s\n\n⚠️ DEMO - Sin validez fiscal\nEste es un CFDI de demostración para pruebas.", 'woo-factura-com'),
        $demo_data['uuid'],
        $demo_data['serie'],
        $demo_data['folio'],
        $demo_data['fecha'],
        $urls['pdf'],
        $urls['xml']
    );
    
    $order->add_order_note($note);
}

ar archivos demo antiguos (ejecutar diariamente)
 */
add_action('wp_scheduled_delete', 'woo_factura_cleanup_demo_files');
function woo_factura_cleanup_demo_files() {
    $upload_dir = wp_upload_dir();
    $demo_dir = $upload_dir['basedir'] . '/factura-com-demo/';
    
    if (!is_dir($demo_dir)) {
        return;
    }
    
    $files = array_merge(
        glob($demo_dir . 'cfdi-demo-*.html'),
        glob($demo_dir . 'cfdi-demo-*.xml')
    );
    
    $now = time();
    $retention_days = 7; 
    
    foreach ($files as $file) {
        if (is_file($file)) {
            $file_age = $now - filemtime($file);
            if ($file_age > ($retention_days * 24 * 60 * 60)) {
                unlink($file);
                error_log('Archivo demo eliminado: ' . basename($file));
            }
        }
    }
}


add_action('init', 'woo_factura_serve_demo_files');
function woo_factura_serve_demo_files() {
    if (!isset($_GET['woo_factura_demo_file'])) {
        return;
    }
    
    $file_param = sanitize_text_field($_GET['woo_factura_demo_file']);
    $upload_dir = wp_upload_dir();
    $demo_dir = $upload_dir['basedir'] . '/factura-com-demo/';
    $file_path = $demo_dir . basename($file_param);
    
    if (!file_exists($file_path) || strpos(basename($file_param), 'cfdi-demo-') !== 0) {
        wp_die(__('Archivo no encontrado', 'woo-factura-com'), 404);
    }
    
    if (!current_user_can('manage_woocommerce')) {
        wp_die(__('Sin permisos para acceder a este archivo', 'woo-factura-com'), 403);
    }
    
    $extension = pathinfo($file_path, PATHINFO_EXTENSION);
    
    if ($extension === 'xml') {
        header('Content-Type: application/xml; charset=utf-8');
        header('Content-Disposition: inline; filename="' . basename($file_param) . '"');
        header('Cache-Control: no-cache, must-revalidate');
    } elseif ($extension === 'html') {
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: inline; filename="' . basename($file_param) . '"');
        header('Cache-Control: no-cache, must-revalidate');
    } elseif ($extension === 'txt') {
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: inline; filename="' . basename($file_param) . '"');
        header('Cache-Control: no-cache, must-revalidate');
    } else {
        wp_die(__('Tipo de archivo no soportado', 'woo-factura-com'), 400);
    }
    
    header('X-Robots-Tag: noindex, nofollow');
    header('X-Content-Type-Options: nosniff');
    
    readfile($file_path);
    exit;
}


function woo_factura_debug_log($message, $data = null) {
    if (WP_DEBUG && WP_DEBUG_LOG) {
        $log_message = '[WooFacturaCom] ' . $message;
        if ($data !== null) {
            $log_message .= ' | Data: ' . print_r($data, true);
        }
        error_log($log_message);
    }
}

if (defined('WP_DEBUG') && WP_DEBUG) {
    add_action('wp_footer', function() {
        if (current_user_can('manage_options') && isset($_GET['woo_factura_debug'])) {
            echo '<!-- WooFactura.com Debug Info -->';
            echo '<script>console.log("WooFactura.com Debug:", ' . json_encode(array(
                'version' => WOO_FACTURA_COM_VERSION,
                'demo_mode' => get_option('woo_factura_com_demo_mode'),
                'plugin_dir' => WOO_FACTURA_COM_PLUGIN_DIR,
                'wc_active' => class_exists('WooCommerce')
            )) . ');</script>';
        }
    });
}