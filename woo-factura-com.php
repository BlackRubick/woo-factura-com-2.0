<?php
/**
 * Plugin Name: WooCommerce Factura.com
 * Plugin URI: https://github.com/tu-usuario/woo-factura-com
 * Description: Integración completa con Factura.com para generar CFDIs automáticamente en WooCommerce. Incluye validación de RFC, generación automática de facturas, cancelación de CFDIs y más.
 * Version: 1.0.0
 * Author: Cesar.G.A
 * Author URI: https://tu-sitio.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: woo-factura-com
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 4.0
 * WC tested up to: 8.5
 * Network: false
 */

// Prevenir acceso directo
if (!defined('ABSPATH')) {
    exit;
}

// Constantes del plugin
define('WOO_FACTURA_COM_VERSION', '1.0.0');
define('WOO_FACTURA_COM_PLUGIN_FILE', __FILE__);
define('WOO_FACTURA_COM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WOO_FACTURA_COM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WOO_FACTURA_COM_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Clase principal del plugin
 */
class WooFacturaComMain {
    
    private static $instance = null;
    
    /**
     * Obtener instancia singleton
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Inicializar plugin
     */
    public function init() {
        // Verificar dependencias
        if (!$this->check_dependencies()) {
            return;
        }
        
        // Cargar traducciones
        load_plugin_textdomain('woo-factura-com', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Incluir archivos principales
        $this->include_files();
        
        // Inicializar componentes
        $this->init_components();
        
        // Hooks principales
        $this->init_hooks();
    }
    
    /**
     * Verificar dependencias
     */
    private function check_dependencies() {
        // Verificar WooCommerce
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>';
                echo '<strong>' . __('WooCommerce Factura.com:', 'woo-factura-com') . '</strong> ';
                echo __('Este plugin requiere WooCommerce para funcionar.', 'woo-factura-com');
                echo '</p></div>';
            });
            return false;
        }
        
        // Verificar versión de PHP
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-error"><p>';
                echo '<strong>' . __('WooCommerce Factura.com:', 'woo-factura-com') . '</strong> ';
                echo sprintf(__('Este plugin requiere PHP 7.4 o superior. Tu versión: %s', 'woo-factura-com'), PHP_VERSION);
                echo '</p></div>';
            });
            return false;
        }
        
        // Verificar extensiones PHP requeridas
        $required_extensions = array('curl', 'json', 'mbstring');
        foreach ($required_extensions as $extension) {
            if (!extension_loaded($extension)) {
                add_action('admin_notices', function() use ($extension) {
                    echo '<div class="notice notice-error"><p>';
                    echo '<strong>' . __('WooCommerce Factura.com:', 'woo-factura-com') . '</strong> ';
                    echo sprintf(__('Extensión de PHP requerida no encontrada: %s', 'woo-factura-com'), $extension);
                    echo '</p></div>';
                });
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Incluir archivos necesarios
     */
    private function include_files() {
        // Clases principales
        require_once WOO_FACTURA_COM_PLUGIN_DIR . 'includes/class-install.php';
        require_once WOO_FACTURA_COM_PLUGIN_DIR . 'includes/class-hooks.php';
        require_once WOO_FACTURA_COM_PLUGIN_DIR . 'includes/class-utilities.php';
        require_once WOO_FACTURA_COM_PLUGIN_DIR . 'includes/class-debug.php';
        
        // APIs y gestores
        require_once WOO_FACTURA_COM_PLUGIN_DIR . 'includes/class-real-api-client.php';
        require_once WOO_FACTURA_COM_PLUGIN_DIR . 'includes/class-real-cfdi-manager.php';
        
        // Setup wizard
        if (is_admin()) {
            require_once WOO_FACTURA_COM_PLUGIN_DIR . 'includes/class-setup-wizard.php';
        }
    }
    
    /**
     * Inicializar componentes
     */
    private function init_components() {
        // Inicializar hooks
        new WooFacturaComHooks();
        
        // Inicializar debug si está activo
        if (get_option('woo_factura_com_debug_mode') === 'yes') {
            new WooFacturaComDebug();
        }
    }
    
    /**
     * Inicializar hooks principales
     */
    private function init_hooks() {
        // Aviso de bienvenida
        if (!get_option('woo_factura_com_setup_completed') && !get_option('woo_factura_com_hide_welcome')) {
            add_action('admin_notices', array($this, 'welcome_notice'));
        }
        
        // Links en la página de plugins
        add_filter('plugin_action_links_' . WOO_FACTURA_COM_PLUGIN_BASENAME, array($this, 'plugin_action_links'));
        add_filter('plugin_row_meta', array($this, 'plugin_row_meta'), 10, 2);
    }
    
    /**
     * Aviso de bienvenida
     */
    public function welcome_notice() {
        if (current_user_can('manage_options')) {
            ?>
            <div class="notice notice-success is-dismissible" data-dismissible="woo-factura-com-welcome">
                <h3><?php _e('¡Bienvenido a WooCommerce Factura.com!', 'woo-factura-com'); ?></h3>
                <p><?php _e('Para comenzar a generar CFDIs, ejecuta el asistente de configuración.', 'woo-factura-com'); ?></p>
                <p>
                    <a href="<?php echo admin_url('admin.php?page=woo-factura-com-setup'); ?>" class="button button-primary">
                        <?php _e('Ejecutar configuración', 'woo-factura-com'); ?>
                    </a>
                    <a href="#" class="button dismiss-welcome" onclick="wooFacturaComDismissWelcome(); return false;">
                        <?php _e('Ocultar aviso', 'woo-factura-com'); ?>
                    </a>
                </p>
            </div>
            <script>
            function wooFacturaComDismissWelcome() {
                jQuery.post(ajaxurl, {
                    action: 'woo_factura_com_dismiss_welcome',
                    nonce: '<?php echo wp_create_nonce('dismiss_welcome'); ?>'
                });
                jQuery('[data-dismissible="woo-factura-com-welcome"]').fadeOut();
            }
            
            // Manejar AJAX para ocultar aviso
            jQuery(document).ready(function($) {
                $('body').on('click', '[data-dismissible="woo-factura-com-welcome"] .notice-dismiss', function() {
                    wooFacturaComDismissWelcome();
                });
            });
            </script>
            <?php
        }
    }
    
    /**
     * Links de acción en página de plugins
     */
    public function plugin_action_links($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=woo-factura-com') . '">' . __('Configuración', 'woo-factura-com') . '</a>';
        array_unshift($links, $settings_link);
        
        if (!get_option('woo_factura_com_setup_completed')) {
            $setup_link = '<a href="' . admin_url('admin.php?page=woo-factura-com-setup') . '" style="color: #d54e21; font-weight: bold;">' . __('Configurar', 'woo-factura-com') . '</a>';
            array_unshift($links, $setup_link);
        }
        
        return $links;
    }
    
    /**
     * Meta links en página de plugins
     */
    public function plugin_row_meta($links, $file) {
        if (WOO_FACTURA_COM_PLUGIN_BASENAME === $file) {
            $row_meta = array(
                'docs' => '<a href="https://github.com/tu-usuario/woo-factura-com/wiki" target="_blank">' . __('Documentación', 'woo-factura-com') . '</a>',
                'support' => '<a href="mailto:soporte@factura.com" target="_blank">' . __('Soporte', 'woo-factura-com') . '</a>',
                'factura' => '<a href="https://factura.com" target="_blank">' . __('Factura.com', 'woo-factura-com') . '</a>'
            );
            
            return array_merge($links, $row_meta);
        }
        
        return $links;
    }
    
    /**
     * Activación del plugin
     */
    public function activate() {
        if (!$this->check_dependencies()) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(__('No se puede activar el plugin debido a dependencias faltantes.', 'woo-factura-com'));
        }
        
        // Ejecutar instalación
        WooFacturaComInstaller::install();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Desactivación del plugin
     */
    public function deactivate() {
        // Limpiar cron jobs si los hay
        wp_clear_scheduled_hook('woo_factura_com_cleanup_logs');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
}

// AJAX para ocultar aviso de bienvenida
add_action('wp_ajax_woo_factura_com_dismiss_welcome', function() {
    check_ajax_referer('dismiss_welcome', 'nonce');
    update_option('woo_factura_com_hide_welcome', true);
    wp_die();
});

// Inicializar plugin
WooFacturaComMain::getInstance();