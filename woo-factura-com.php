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

// Verificar WooCommerce
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

// Cargar clase principal
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

// ✅ CARGAR API POS (NUEVO)
$pos_api_file = WOO_FACTURA_COM_PLUGIN_DIR . 'includes/class-pos-api.php';
if (file_exists($pos_api_file)) {
    require_once $pos_api_file;
}

// Inicializar plugin
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
    
    // ✅ GENERAR TOKEN API AL ACTIVAR (NUEVO)
    if (!get_option('woo_factura_com_pos_api_token')) {
        $token = bin2hex(random_bytes(32));
        update_option('woo_factura_com_pos_api_token', $token);
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
    
    // ✅ ENLACE A DOCUMENTACIÓN API (NUEVO)
    $api_link = '<a href="' . esc_url(admin_url('admin.php?page=woo-factura-com-api')) . '" style="color: #0073aa;">' . 
               esc_html__('API POS', 'woo-factura-com') . '</a>';
    
    array_unshift($links, $settings_link, $api_link);
    return $links;
});

// ✅ PÁGINA DE DOCUMENTACIÓN API EN ADMIN (NUEVO)
add_action('admin_menu', 'woo_factura_com_add_api_page');
function woo_factura_com_add_api_page() {
    add_submenu_page(
        'woocommerce',
        __('API POS - Factura.com', 'woo-factura-com'),
        __('API POS', 'woo-factura-com'),
        'manage_woocommerce',
        'woo-factura-com-api',
        'woo_factura_com_api_page'
    );
}

function woo_factura_com_api_page() {
    $api_token = get_option('woo_factura_com_pos_api_token');
    $site_url = get_site_url();
    ?>
    <div class="wrap">
        <h1>🔌 API POS - WooCommerce Factura.com</h1>
        <p>Documentación para integrar tu POS con el sistema de CFDIs.</p>
        
        <div style="background: #f0f8ff; border: 1px solid #b3d9ff; padding: 20px; border-radius: 8px; margin: 20px 0;">
            <h2>🔑 Credenciales de API</h2>
            <p><strong>Base URL:</strong> <code><?php echo esc_html($site_url); ?>/wp-json/woo-factura-com/v1</code></p>
            <p><strong>Token:</strong> <code><?php echo esc_html($api_token); ?></code></p>
            <p><em>Usa este token en el header: <code>Authorization: Bearer <?php echo esc_html($api_token); ?></code></em></p>
            
            <button type="button" onclick="regenerarToken()" class="button">🔄 Regenerar Token</button>
        </div>
        
        <div style="background: white; border: 1px solid #ddd; padding: 20px; border-radius: 8px; margin: 20px 0;">
            <h2>📋 Endpoints Disponibles</h2>
            
            <h3>1. Crear CFDI</h3>
            <p><code>POST /cfdi/create</code></p>
            <pre style="background: #f5f5f5; padding: 10px; overflow-x: auto;">
curl -X POST "<?php echo esc_html($site_url); ?>/wp-json/woo-factura-com/v1/cfdi/create" \
  -H "Authorization: Bearer <?php echo esc_html($api_token); ?>" \
  -H "Content-Type: application/json" \
  -d '{
    "order_data": {
      "items": [
        {
          "name": "Producto A",
          "price": 100.00,
          "quantity": 2,
          "sku": "PROD-001"
        }
      ]
    },
    "customer_data": {
      "first_name": "Juan",
      "last_name": "Pérez",
      "email": "juan@ejemplo.com",
      "rfc": "PERJ800101AAA"
    }
  }'</pre>
            
            <h3>2. Verificar Estado de CFDI</h3>
            <p><code>GET /cfdi/status/{order_id}</code></p>
            <pre style="background: #f5f5f5; padding: 10px; overflow-x: auto;">
curl "<?php echo esc_html($site_url); ?>/wp-json/woo-factura-com/v1/cfdi/status/123" \
  -H "Authorization: Bearer <?php echo esc_html($api_token); ?>"</pre>
            
            <h3>3. Imprimir CFDI</h3>
            <p><code>POST /cfdi/print/{uuid}</code></p>
            <pre style="background: #f5f5f5; padding: 10px; overflow-x: auto;">
curl -X POST "<?php echo esc_html($site_url); ?>/wp-json/woo-factura-com/v1/cfdi/print/550e8400-e29b-41d4-a716-446655440000" \
  -H "Authorization: Bearer <?php echo esc_html($api_token); ?>"</pre>
        </div>
        
        <div style="background: #f9f9f9; border: 1px solid #ddd; padding: 20px; border-radius: 8px; margin: 20px 0;">
            <h2>🧪 Probar API</h2>
            <p>Usa estos botones para probar la API directamente:</p>
            
            <button type="button" onclick="probarCrearCFDI()" class="button button-primary">🧾 Probar Crear CFDI</button>
            <button type="button" onclick="probarVerificarCFDI()" class="button">🔍 Verificar Último CFDI</button>
            
            <div id="api-test-result" style="margin-top: 15px; padding: 10px; background: white; border: 1px solid #ddd; border-radius: 4px; display: none;">
                <h4>Resultado:</h4>
                <pre id="api-result-content"></pre>
            </div>
        </div>
    </div>
    
    <script>
    function regenerarToken() {
        if (confirm('¿Regenerar token de API? Esto invalidará el token actual.')) {
            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=regenerar_api_token&nonce=<?php echo wp_create_nonce('regenerar_token'); ?>'
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('Token regenerado: ' + data.data.token);
                    location.reload();
                } else {
                    alert('Error: ' + data.data);
                }
            });
        }
    }
    
    function probarCrearCFDI() {
        const testData = {
            order_data: {
                items: [
                    {
                        name: "Producto de Prueba API",
                        price: 100.00,
                        quantity: 1,
                        sku: "TEST-API-001"
                    }
                ]
            },
            customer_data: {
                first_name: "Cliente",
                last_name: "Prueba API",
                email: "test@api.com",
                rfc: "XAXX010101000"
            }
        };
        
        fetch('<?php echo esc_url($site_url); ?>/wp-json/woo-factura-com/v1/cfdi/create', {
            method: 'POST',
            headers: {
                'Authorization': 'Bearer <?php echo esc_js($api_token); ?>',
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(testData)
        })
        .then(r => r.json())
        .then(data => {
            document.getElementById('api-test-result').style.display = 'block';
            document.getElementById('api-result-content').textContent = JSON.stringify(data, null, 2);
        })
        .catch(err => {
            document.getElementById('api-test-result').style.display = 'block';
            document.getElementById('api-result-content').textContent = 'Error: ' + err.message;
        });
    }
    
    function probarVerificarCFDI() {
        // Obtener el último pedido para probar
        fetch('<?php echo admin_url('admin-ajax.php'); ?>?action=get_last_order_id', {
            method: 'GET'
        })
        .then(r => r.json())
        .then(orderData => {
            if (orderData.success && orderData.data.order_id) {
                return fetch(`<?php echo esc_url($site_url); ?>/wp-json/woo-factura-com/v1/cfdi/status/${orderData.data.order_id}`, {
                    headers: {
                        'Authorization': 'Bearer <?php echo esc_js($api_token); ?>'
                    }
                });
            } else {
                throw new Error('No hay pedidos para verificar');
            }
        })
        .then(r => r.json())
        .then(data => {
            document.getElementById('api-test-result').style.display = 'block';
            document.getElementById('api-result-content').textContent = JSON.stringify(data, null, 2);
        })
        .catch(err => {
            document.getElementById('api-test-result').style.display = 'block';
            document.getElementById('api-result-content').textContent = 'Error: ' + err.message;
        });
    }
    </script>
    <?php
}

// AJAX para regenerar token
add_action('wp_ajax_regenerar_api_token', function() {
    check_ajax_referer('regenerar_token', 'nonce');
    
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error('Sin permisos');
    }
    
    $new_token = bin2hex(random_bytes(32));
    update_option('woo_factura_com_pos_api_token', $new_token);
    
    wp_send_json_success(['token' => $new_token]);
});

// AJAX para obtener último pedido (para pruebas)
add_action('wp_ajax_get_last_order_id', function() {
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error('Sin permisos');
    }
    
    $orders = wc_get_orders(['limit' => 1]);
    if (!empty($orders)) {
        wp_send_json_success(['order_id' => $orders[0]->get_id()]);
    } else {
        wp_send_json_error('No hay pedidos');
    }
});

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