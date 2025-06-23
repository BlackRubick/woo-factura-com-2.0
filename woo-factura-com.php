<?php
/**
 * Plugin Name: WooCommerce Factura.com
 * Plugin URI: https://github.com/tu-usuario/woo-factura-com
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
        echo __('WooCommerce Factura.com requiere que WooCommerce esté instalado y activado.', 'woo-factura-com');
        echo '</p></div>';
    });
    return;
}

// Definir constantes del plugin
define('WOO_FACTURA_COM_VERSION', '1.0.0');
define('WOO_FACTURA_COM_PLUGIN_FILE', __FILE__);
define('WOO_FACTURA_COM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WOO_FACTURA_COM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WOO_FACTURA_COM_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Cargar archivo principal del plugin
require_once WOO_FACTURA_COM_PLUGIN_DIR . 'includes/class-woo-factura-com.php';

// Inicializar el plugin
function woo_factura_com_init() {
    WooFacturaCom::get_instance();
}
add_action('plugins_loaded', 'woo_factura_com_init');

// Hook de activación
register_activation_hook(__FILE__, function() {
    require_once WOO_FACTURA_COM_PLUGIN_DIR . 'includes/class-install.php';
    WooFacturaComInstaller::activate();
});

// Hook de desactivación
register_deactivation_hook(__FILE__, function() {
    require_once WOO_FACTURA_COM_PLUGIN_DIR . 'includes/class-install.php';
    WooFacturaComInstaller::deactivate();
});

// Declarar compatibilidad con WooCommerce HPOS
add_action('before_woocommerce_init', function() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );
    }
});

// Agregar enlaces en la página de plugins
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=woo-factura-com') . '">' . __('Configuración', 'woo-factura-com') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
});

// Agregar enlaces meta en la página de plugins
add_filter('plugin_row_meta', function($links, $file) {
    if ($file === plugin_basename(__FILE__)) {
        $links[] = '<a href="https://factura.com" target="_blank">' . __('Factura.com', 'woo-factura-com') . '</a>';
        $links[] = '<a href="https://github.com/tu-usuario/woo-factura-com/issues" target="_blank">' . __('Soporte', 'woo-factura-com') . '</a>';
    }
    return $links;
}, 10, 2);

// ============== DEBUG PARA DESARROLLO - AGREGAR AL FINAL ==============

// 1. Debug básico en admin
add_action('admin_notices', function() {
    if (!current_user_can('manage_options')) return;
    
    $demo_mode = get_option('woo_factura_com_demo_mode');
    $cfdi_manager_exists = class_exists('WooFacturaComRealCFDIManager');
    
    echo '<div class="notice notice-info" style="border-left: 4px solid #667eea;">';
    echo '<p><strong>🧾 Factura.com Status:</strong></p>';
    echo '<ul style="margin: 10px 0 10px 20px;">';
    echo '<li>Demo Mode: ' . ($demo_mode === 'yes' ? '<span style="color:green;">✅ Activo</span>' : '<span style="color:red;">❌ Inactivo</span>') . '</li>';
    echo '<li>CFDI Manager: ' . ($cfdi_manager_exists ? '<span style="color:green;">✅ Cargado</span>' : '<span style="color:red;">❌ No encontrado</span>') . '</li>';
    echo '<li>WooCommerce: ' . (class_exists('WooCommerce') ? '<span style="color:green;">✅ Activo</span>' : '<span style="color:red;">❌ Inactivo</span>') . '</li>';
    echo '</ul>';
    
    if ($demo_mode !== 'yes') {
        echo '<p><button type="button" class="button button-primary" onclick="activarDemo()">🚀 Activar Modo Demo</button></p>';
        echo '<script>
        function activarDemo() {
            if (confirm("¿Activar modo demo?")) {
                fetch("' . admin_url('admin-ajax.php') . '", {
                    method: "POST",
                    headers: {"Content-Type": "application/x-www-form-urlencoded"},
                    body: "action=activar_demo_factura&nonce=' . wp_create_nonce('activar_demo') . '"
                }).then(() => {
                    alert("Modo demo activado");
                    location.reload();
                });
            }
        }
        </script>';
    }
    echo '</div>';
});

// 2. Handler para activar demo
add_action('wp_ajax_activar_demo_factura', function() {
    check_ajax_referer('activar_demo', 'nonce');
    
    if (current_user_can('manage_options')) {
        update_option('woo_factura_com_demo_mode', 'yes');
        update_option('woo_factura_com_add_rfc_field', 'yes');
        update_option('woo_factura_com_setup_completed', true);
        update_option('woo_factura_com_sandbox_mode', 'yes');
        update_option('woo_factura_com_lugar_expedicion', '44100');
        update_option('woo_factura_com_uso_cfdi', 'G01');
        update_option('woo_factura_com_forma_pago', '99');
        update_option('woo_factura_com_metodo_pago', 'PUE');
        
        wp_send_json_success('Demo activado');
    }
});

// 3. Botón en pedidos
add_action('woocommerce_admin_order_data_after_billing_address', function($order) {
    if (!current_user_can('manage_woocommerce') || !$order) return;
    
    $order_id = $order->get_id();
    $uuid = $order->get_meta('_factura_com_cfdi_uuid');
    
    echo '<div style="margin: 15px 0; padding: 15px; background: #e7f3ff; border: 1px solid #b3d9ff; border-radius: 4px;">';
    echo '<h4 style="margin: 0 0 10px 0;">🧾 Factura.com Debug</h4>';
    
    if ($uuid) {
        echo '<p style="color: green;">✅ CFDI generado: <code>' . esc_html($uuid) . '</code></p>';
    } else {
        echo '<p>Sin CFDI. <button type="button" class="button button-primary" onclick="generarCFDI(' . $order_id . ')">Generar CFDI Demo</button></p>';
        echo '<div id="resultado-' . $order_id . '"></div>';
    }
    echo '</div>';
    
    if (!$uuid) {
        echo '<script>
        function generarCFDI(id) {
            if (!confirm("¿Generar CFDI demo para pedido #" + id + "?")) return;
            
            document.getElementById("resultado-" + id).innerHTML = "<p style=\"color:blue;\">⏳ Generando...</p>";
            
            fetch("' . admin_url('admin-ajax.php') . '", {
                method: "POST",
                headers: {"Content-Type": "application/x-www-form-urlencoded"},
                body: "action=generar_cfdi_test&order_id=" + id + "&nonce=' . wp_create_nonce('generar_cfdi') . '"
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById("resultado-" + id).innerHTML = "<p style=\"color:green;\">✅ " + data.data + "</p>";
                    setTimeout(() => location.reload(), 2000);
                } else {
                    document.getElementById("resultado-" + id).innerHTML = "<p style=\"color:red;\">❌ " + data.data + "</p>";
                }
            });
        }
        </script>';
    }
});

// 4. Handler para generar CFDI
add_action('wp_ajax_generar_cfdi_test', function() {
    check_ajax_referer('generar_cfdi', 'nonce');
    
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error('Sin permisos');
    }
    
    $order_id = intval($_POST['order_id']);
    $order = wc_get_order($order_id);
    
    if (!$order) {
        wp_send_json_error('Pedido no encontrado');
    }
    
    // Activar demo si no está activo
    if (get_option('woo_factura_com_demo_mode') !== 'yes') {
        update_option('woo_factura_com_demo_mode', 'yes');
    }
    
    // Verificar clase
    if (!class_exists('WooFacturaComRealCFDIManager')) {
        wp_send_json_error('Clase WooFacturaComRealCFDIManager no encontrada');
    }
    
    try {
        $cfdi_manager = new WooFacturaComRealCFDIManager();
        $result = $cfdi_manager->generate_cfdi_for_order($order_id);
        
        if ($result && $result['success']) {
            wp_send_json_success('CFDI demo generado! UUID: ' . $result['uuid']);
        } else {
            wp_send_json_error($result['error'] ?? 'Error desconocido');
        }
    } catch (Exception $e) {
        wp_send_json_error('Excepción: ' . $e->getMessage());
    }
});

// 5. Log de debug
add_action('admin_init', function() {
    error_log('=== Factura.com Debug ===');
    error_log('Demo mode: ' . get_option('woo_factura_com_demo_mode', 'NOT_SET'));
    error_log('CFDI Manager: ' . (class_exists('WooFacturaComRealCFDIManager') ? 'EXISTS' : 'MISSING'));
    error_log('WooCommerce: ' . (class_exists('WooCommerce') ? 'EXISTS' : 'MISSING'));
});

// ============== FIN DEBUG ==============