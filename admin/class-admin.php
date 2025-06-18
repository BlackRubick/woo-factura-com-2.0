<?php
/**
 * Clase para el panel de administración
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WooFacturaComAdmin')) {
    
    class WooFacturaComAdmin {
        
        public function __construct() {
            add_action('admin_menu', array($this, 'admin_menu'));
            add_action('admin_init', array($this, 'init_settings'));
            add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
            add_action('admin_notices', array($this, 'admin_notices'));
        }
        
        /**
         * Agregar menús de administración
         */
        public function admin_menu() {
            // Menú principal bajo WooCommerce
            add_submenu_page(
                'woocommerce',
                __('Factura.com', 'woo-factura-com'),
                __('Factura.com', 'woo-factura-com'),
                'manage_woocommerce',
                'woo-factura-com',
                array($this, 'admin_page')
            );
            
            // Submenú de estadísticas
            add_submenu_page(
                'woocommerce',
                __('Estadísticas CFDI', 'woo-factura-com'),
                __('Estadísticas CFDI', 'woo-factura-com'),
                'manage_woocommerce',
                'woo-factura-com-stats',
                array($this, 'stats_page')
            );
            
            // Submenú de logs
            add_submenu_page(
                'woocommerce',
                __('Logs Factura.com', 'woo-factura-com'),
                __('Logs Factura.com', 'woo-factura-com'),
                'manage_woocommerce',
                'woo-factura-com-logs',
                array($this, 'logs_page')
            );
        }
        
        /**
         * Inicializar configuraciones
         */
        public function init_settings() {
            register_setting('woo_factura_com_settings', 'woo_factura_com_demo_mode');
            register_setting('woo_factura_com_settings', 'woo_factura_com_sandbox_mode');
            register_setting('woo_factura_com_settings', 'woo_factura_com_debug_mode');
            register_setting('woo_factura_com_settings', 'woo_factura_com_api_key');
            register_setting('woo_factura_com_settings', 'woo_factura_com_secret_key');
            register_setting('woo_factura_com_settings', 'woo_factura_com_serie_id');
            register_setting('woo_factura_com_settings', 'woo_factura_com_auto_generate');
            register_setting('woo_factura_com_settings', 'woo_factura_com_send_email');
            register_setting('woo_factura_com_settings', 'woo_factura_com_uso_cfdi');
            register_setting('woo_factura_com_settings', 'woo_factura_com_forma_pago');
            register_setting('woo_factura_com_settings', 'woo_factura_com_metodo_pago');
            register_setting('woo_factura_com_settings', 'woo_factura_com_lugar_expedicion');
        }
        
        /**
         * Cargar scripts de administración
         */
        public function admin_scripts($hook) {
            if (strpos($hook, 'woo-factura-com') !== false) {
                wp_enqueue_style('woo-factura-com-admin', WOO_FACTURA_COM_PLUGIN_URL . 'assets/css/admin.css', array(), WOO_FACTURA_COM_VERSION);
                wp_enqueue_script('woo-factura-com-admin', WOO_FACTURA_COM_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), WOO_FACTURA_COM_VERSION, true);
            }
        }
        
        /**
         * Mostrar notificaciones administrativas
         */
        public function admin_notices() {
            // Mostrar notice si el setup no está completado
            if (!get_option('woo_factura_com_setup_completed') && !isset($_GET['page']) || $_GET['page'] !== 'woo-factura-com-setup') {
                echo '<div class="notice notice-info is-dismissible">';
                echo '<p><strong>' . __('WooCommerce Factura.com', 'woo-factura-com') . '</strong>: ';
                echo __('¡Bienvenido! Completa la configuración inicial para comenzar a generar CFDIs.', 'woo-factura-com');
                echo ' <a href="' . admin_url('admin.php?page=woo-factura-com-setup') . '" class="button button-primary">' . __('Configurar ahora', 'woo-factura-com') . '</a>';
                echo '</p></div>';
            }
        }
        
        /**
         * Página principal de administración
         */
        public function admin_page() {
            include WOO_FACTURA_COM_PLUGIN_DIR . 'admin/views/settings-page.php';
        }
        
        /**
         * Página de estadísticas
         */
        public function stats_page() {
            include WOO_FACTURA_COM_PLUGIN_DIR . 'admin/views/stats-page.php';
        }
        
        /**
         * Página de logs
         */
        public function logs_page() {
            echo '<div class="wrap">';
            echo '<h1>' . __('Logs de Factura.com', 'woo-factura-com') . '</h1>';
            echo '<p>' . __('Aquí aparecerán los logs del plugin cuando esté en modo debug.', 'woo-factura-com') . '</p>';
            echo '</div>';
        }
    }
    
    new WooFacturaComAdmin();
}