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

// Evitar acceso directo
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

// Definir constantes
if (!defined('WOO_FACTURA_COM_VERSION')) {
    define('WOO_FACTURA_COM_VERSION', '1.0.0');
}
if (!defined('WOO_FACTURA_COM_PLUGIN_FILE')) {
    define('WOO_FACTURA_COM_PLUGIN_FILE', __FILE__);
}
if (!defined('WOO_FACTURA_COM_PLUGIN_DIR')) {
    define('WOO_FACTURA_COM_PLUGIN_DIR', plugin_dir_path(__FILE__));
}
if (!defined('WOO_FACTURA_COM_PLUGIN_URL')) {
    define('WOO_FACTURA_COM_PLUGIN_URL', plugin_dir_url(__FILE__));
}
if (!defined('WOO_FACTURA_COM_PLUGIN_BASENAME')) {
    define('WOO_FACTURA_COM_PLUGIN_BASENAME', plugin_basename(__FILE__));
}

// Cargar clase principal solo si existe
$main_class_file = WOO_FACTURA_COM_PLUGIN_DIR . 'includes/class-woo-factura-com.php';
if (file_exists($main_class_file)) {
    require_once $main_class_file;
} else {
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>';
        echo esc_html__('Error: No se pudo cargar la clase principal del plugin WooCommerce Factura.com', 'woo-factura-com');
        echo '</p></div>';
    });
    return;
}

// Inicializar plugin cuando WordPress esté listo
add_action('plugins_loaded', 'woo_factura_com_init');
function woo_factura_com_init() {
    if (class_exists('WooFacturaCom')) {
        WooFacturaCom::get_instance();
    }
}

// Hooks de activación/desactivación
register_activation_hook(__FILE__, 'woo_factura_com_activate');
function woo_factura_com_activate() {
    $installer_file = WOO_FACTURA_COM_PLUGIN_DIR . 'includes/class-install.php';
    if (file_exists($installer_file)) {
        require_once $installer_file;
        if (class_exists('WooFacturaComInstaller')) {
            WooFacturaComInstaller::activate();
        }
    }
}

register_deactivation_hook(__FILE__, 'woo_factura_com_deactivate');
function woo_factura_com_deactivate() {
    $installer_file = WOO_FACTURA_COM_PLUGIN_DIR . 'includes/class-install.php';
    if (file_exists($installer_file)) {
        require_once $installer_file;
        if (class_exists('WooFacturaComInstaller')) {
            WooFacturaComInstaller::deactivate();
        }
    }
}

// Declarar compatibilidad con HPOS
add_action('before_woocommerce_init', function() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );
    }
});

// Enlaces en la página de plugins
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
        $links[] = '<a href="https://github.com/BlackRubick/woo-factura-com/issues" target="_blank">' . 
                  esc_html__('Soporte', 'woo-factura-com') . '</a>';
    }
    return $links;
}, 10, 2);

// =====================================================
// FUNCIONES DEMO Y DEBUG (Solo si está habilitado)
// =====================================================

// Status notice en admin
add_action('admin_notices', 'woo_factura_com_status_notice');
function woo_factura_com_status_notice() {
    if (!current_user_can('manage_options')) return;
    
    $demo_mode = get_option('woo_factura_com_demo_mode');
    $cfdi_manager_exists = class_exists('WooFacturaComRealCFDIManager');
    
    echo '<div class="notice notice-info" style="border-left: 4px solid #667eea;">';
    echo '<p><strong>🧾 ' . esc_html__('Factura.com Status', 'woo-factura-com') . ':</strong></p>';
    echo '<ul style="margin: 10px 0 10px 20px;">';
    echo '<li>' . esc_html__('Demo Mode', 'woo-factura-com') . ': ' . 
         ($demo_mode === 'yes' ? '<span style="color:green;">✅ ' . esc_html__('Activo', 'woo-factura-com') . '</span>' : 
                                '<span style="color:red;">❌ ' . esc_html__('Inactivo', 'woo-factura-com') . '</span>') . '</li>';
    echo '<li>' . esc_html__('CFDI Manager', 'woo-factura-com') . ': ' . 
         ($cfdi_manager_exists ? '<span style="color:green;">✅ ' . esc_html__('Cargado', 'woo-factura-com') . '</span>' : 
                                '<span style="color:red;">❌ ' . esc_html__('No encontrado', 'woo-factura-com') . '</span>') . '</li>';
    echo '<li>WooCommerce: ' . 
         (class_exists('WooCommerce') ? '<span style="color:green;">✅ ' . esc_html__('Activo', 'woo-factura-com') . '</span>' : 
                                      '<span style="color:red;">❌ ' . esc_html__('Inactivo', 'woo-factura-com') . '</span>') . '</li>';
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
}

// AJAX para activar demo
add_action('wp_ajax_activar_demo_factura', 'woo_factura_com_ajax_activar_demo');
function woo_factura_com_ajax_activar_demo() {
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
}

// Funciones de utilidad (solo definir si no existen)
if (!function_exists('woo_factura_generate_uuid')) {
    function woo_factura_generate_uuid() {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
}

if (!function_exists('woo_factura_debug_log')) {
    function woo_factura_debug_log($message, $data = null) {
        if (WP_DEBUG && WP_DEBUG_LOG) {
            $log_message = '[WooFacturaCom] ' . $message;
            if ($data !== null) {
                $log_message .= ' | Data: ' . print_r($data, true);
            }
            error_log($log_message);
        }
    }
}

// Debug info en footer (solo si está habilitado el debug)
if (defined('WP_DEBUG') && WP_DEBUG) {
    add_action('wp_footer', 'woo_factura_com_debug_footer');
    function woo_factura_com_debug_footer() {
        if (current_user_can('manage_options') && isset($_GET['woo_factura_debug'])) {
            echo '<!-- WooFactura.com Debug Info -->';
            echo '<script>console.log("WooFactura.com Debug:", ' . json_encode(array(
                'version' => WOO_FACTURA_COM_VERSION,
                'demo_mode' => get_option('woo_factura_com_demo_mode'),
                'plugin_dir' => WOO_FACTURA_COM_PLUGIN_DIR,
                'wc_active' => class_exists('WooCommerce')
            )) . ');</script>';
        }
    }
}

// Log de debug en admin_init
add_action('admin_init', 'woo_factura_com_debug_init');
function woo_factura_com_debug_init() {
    if (current_user_can('manage_options') && WP_DEBUG) {
        error_log('=== Factura.com Debug Init ===');
        error_log('Demo mode: ' . get_option('woo_factura_com_demo_mode', 'NOT_SET'));
        error_log('CFDI Manager: ' . (class_exists('WooFacturaComRealCFDIManager') ? 'EXISTS' : 'MISSING'));
        error_log('WooCommerce: ' . (class_exists('WooCommerce') ? 'EXISTS' : 'MISSING'));
        error_log('================================');
    }
}