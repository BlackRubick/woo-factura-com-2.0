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

