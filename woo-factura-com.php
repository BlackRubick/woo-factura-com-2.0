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
    return;
}

// Definir constantes
define('WOO_FACTURA_COM_VERSION', '1.0.0');
define('WOO_FACTURA_COM_PLUGIN_FILE', __FILE__);
define('WOO_FACTURA_COM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WOO_FACTURA_COM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WOO_FACTURA_COM_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Cargar clase principal
$main_class_file = WOO_FACTURA_COM_PLUGIN_DIR . 'includes/class-woo-factura-com.php';
if (file_exists($main_class_file)) {
    require_once $main_class_file;
}

// Cargar API POS
$pos_api_file = WOO_FACTURA_COM_PLUGIN_DIR . 'includes/class-pos-api.php';
if (file_exists($pos_api_file)) {
    require_once $pos_api_file;
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
    // Generar token API
    if (!get_option('woo_factura_com_pos_api_token')) {
        $token = bin2hex(random_bytes(16)); // Token más corto para evitar problemas
        update_option('woo_factura_com_pos_api_token', $token);
    }
    
    // Cargar instalador
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

// Funciones de utilidad básicas
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