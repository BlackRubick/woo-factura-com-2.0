<?php
/**
 * Clase principal del plugin WooCommerce Factura.com
 * Orquesta la carga de todos los componentes del plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WooFacturaCom')) {
    
    final class WooFacturaCom {
        
        /**
         * Instancia única del plugin
         */
        private static $instance = null;
        
        /**
         * Versión del plugin
         */
        public $version = '1.0.0';
        
        /**
         * Instancias de las clases del plugin
         */
        public $admin = null;
        public $hooks = null;
        public $setup_wizard = null;
        public $debug = null;
        
        /**
         * Obtener instancia única
         */
        public static function get_instance() {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }
        
        /**
         * Constructor privado para singleton
         */
        private function __construct() {
            $this->init();
        }
        
        /**
         * Prevenir clonación
         */
        private function __clone() {}
        
        /**
         * Prevenir deserialización
         */
        private function __wakeup() {}
        
        /**
         * Inicializar el plugin
         */
        private function init() {
            // Verificar dependencias
            if (!$this->check_dependencies()) {
                return;
            }
            
            // Definir hooks tempranos
            add_action('init', array($this, 'load_textdomain'));
            add_action('init', array($this, 'init_components'), 5);
            add_action('admin_init', array($this, 'check_version'));
            
            // Hook de inicialización
            do_action('woo_factura_com_loaded');
        }
        
        /**
         * Verificar dependencias del plugin
         */
        private function check_dependencies() {
            // Verificar WooCommerce
            if (!class_exists('WooCommerce')) {
                add_action('admin_notices', array($this, 'missing_woocommerce_notice'));
                return false;
            }
            
            // Verificar versión de PHP
            if (version_compare(PHP_VERSION, '7.4', '<')) {
                add_action('admin_notices', array($this, 'php_version_notice'));
                return false;
            }
            
            // Verificar extensiones de PHP
            $required_extensions = array('curl', 'json', 'mbstring');
            foreach ($required_extensions as $extension) {
                if (!extension_loaded($extension)) {
                    add_action('admin_notices', function() use ($extension) {
                        echo '<div class="notice notice-error"><p>';
                        printf(__('WooCommerce Factura.com: Extensión de PHP requerida no encontrada: %s', 'woo-factura-com'), $extension);
                        echo '</p></div>';
                    });
                    return false;
                }
            }
            
            return true;
        }
        
        /**
         * Cargar archivos de idioma
         */
        public function load_textdomain() {
            load_plugin_textdomain(
                'woo-factura-com',
                false,
                dirname(plugin_basename(WOO_FACTURA_COM_PLUGIN_FILE)) . '/languages/'
            );
        }
        
        /**
         * Inicializar componentes del plugin
         */
        public function init_components() {
            // Cargar archivos necesarios
            $this->include_files();
            
            // Inicializar componentes principales
            $this->init_admin();
            $this->init_hooks();
            $this->init_debug();
            $this->init_setup_wizard();
            
            // Mostrar avisos de configuración
            $this->maybe_show_notices();
        }
        
        /**
         * Incluir archivos del plugin
         */
        private function include_files() {
            $includes = array(
                'includes/class-utilities.php',
                'includes/class-debug.php',
                'includes/class-hooks.php',
                'includes/class-install.php',
                'includes/class-real-api-client.php',
                'includes/class-real-cfdi-manager.php',
                'includes/class-setup-wizard.php',
                'admin/class-admin.php'
            );
            
            foreach ($includes as $file) {
                $file_path = WOO_FACTURA_COM_PLUGIN_DIR . $file;
                if (file_exists($file_path)) {
                    require_once $file_path;
                } else {
                    error_log('WooCommerce Factura.com: No se pudo cargar el archivo: ' . $file);
                }
            }
        }
        
        /**
         * Inicializar administración
         */
        private function init_admin() {
            if (is_admin() && class_exists('WooFacturaComAdmin')) {
                $this->admin = new WooFacturaComAdmin();
            }
        }
        
        /**
         * Inicializar hooks
         */
        private function init_hooks() {
            if (class_exists('WooFacturaComHooks')) {
                $this->hooks = new WooFacturaComHooks();
            }
        }
        
        /**
         * Inicializar debug
         */
        private function init_debug() {
            if (class_exists('WooFacturaComDebug')) {
                $this->debug = new WooFacturaComDebug();
            }
        }
        
        /**
         * Inicializar setup wizard
         */
        private function init_setup_wizard() {
            if (is_admin() && class_exists('WooFacturaComSetupWizard')) {
                $this->setup_wizard = new WooFacturaComSetupWizard();
            }
        }
        
        /**
         * Verificar versión y ejecutar actualizaciones si es necesario
         */
        public function check_version() {
            $current_version = get_option('woo_factura_com_version', '0.0.0');
            
            if (version_compare($current_version, WOO_FACTURA_COM_VERSION, '<')) {
                $this->update_plugin($current_version);
                update_option('woo_factura_com_version', WOO_FACTURA_COM_VERSION);
            }
        }
        
        /**
         * Actualizar plugin
         */
        private function update_plugin($from_version) {
            // Actualizar base de datos si es necesario
            if (class_exists('WooFacturaComInstaller')) {
                WooFacturaComInstaller::maybe_update_db();
            }
            
            // Log de actualización
            error_log("WooCommerce Factura.com actualizado de $from_version a " . WOO_FACTURA_COM_VERSION);
        }
        
        /**
         * Mostrar avisos de configuración si es necesario
         */
        private function maybe_show_notices() {
            if (!is_admin()) {
                return;
            }
            
            // Aviso de bienvenida
            if (!get_option('woo_factura_com_setup_completed') && !get_transient('woo_factura_com_welcome_dismissed')) {
                add_action('admin_notices', array($this, 'welcome_notice'));
            }
            
            // Aviso de modo demo
            if (get_option('woo_factura_com_demo_mode', 'yes') === 'yes' && !get_transient('woo_factura_com_demo_notice_dismissed')) {
                add_action('admin_notices', array($this, 'demo_mode_notice'));
            }
            
            // Aviso de configuración incompleta
            if (!$this->is_configured() && get_option('woo_factura_com_demo_mode', 'yes') === 'no') {
                add_action('admin_notices', array($this, 'configuration_notice'));
            }
        }
        
        /**
         * Verificar si el plugin está configurado
         */
        private function is_configured() {
            $demo_mode = get_option('woo_factura_com_demo_mode', 'yes') === 'yes';
            
            if ($demo_mode) {
                return true;
            }
            
            $api_key = get_option('woo_factura_com_api_key');
            $secret_key = get_option('woo_factura_com_secret_key');
            $serie_id = get_option('woo_factura_com_serie_id');
            
            return !empty($api_key) && !empty($secret_key) && !empty($serie_id);
        }
        
        /**
         * Aviso de WooCommerce faltante
         */
        public function missing_woocommerce_notice() {
            echo '<div class="notice notice-error"><p>';
            echo __('WooCommerce Factura.com:', 'woo-factura-com') . ' ';
            echo __('Este plugin requiere WooCommerce para funcionar.', 'woo-factura-com');
            echo '</p></div>';
        }
        
        /**
         * Aviso de versión de PHP
         */
        public function php_version_notice() {
            echo '<div class="notice notice-error"><p>';
            printf(
                __('WooCommerce Factura.com: Este plugin requiere PHP 7.4 o superior. Tu versión: %s', 'woo-factura-com'),
                PHP_VERSION
            );
            echo '</p></div>';
        }
        
        /**
         * Aviso de bienvenida
         */
        public function welcome_notice() {
            if (isset($_GET['woo_factura_com_dismiss_welcome'])) {
                set_transient('woo_factura_com_welcome_dismissed', true, WEEK_IN_SECONDS);
                return;
            }
            
            echo '<div class="notice notice-info is-dismissible">';
            echo '<p><strong>' . __('¡Bienvenido a WooCommerce Factura.com!', 'woo-factura-com') . '</strong><br>';
            echo __('Para comenzar a generar CFDIs, ejecuta el asistente de configuración.', 'woo-factura-com') . '</p>';
            echo '<p>';
            echo '<a href="' . admin_url('admin.php?page=woo-factura-com-setup') . '" class="button-primary">';
            echo __('Ejecutar configuración', 'woo-factura-com');
            echo '</a> ';
            echo '<a href="' . add_query_arg('woo_factura_com_dismiss_welcome', '1') . '" class="button-secondary">';
            echo __('Ocultar aviso', 'woo-factura-com');
            echo '</a>';
            echo '</p></div>';
        }
        
        /**
         * Aviso de modo demo
         */
        public function demo_mode_notice() {
            if (isset($_GET['woo_factura_com_dismiss_demo'])) {
                set_transient('woo_factura_com_demo_notice_dismissed', true, MONTH_IN_SECONDS);
                return;
            }
            
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p><strong>' . __('WooCommerce Factura.com:', 'woo-factura-com') . '</strong> ';
            echo __('Está en modo demo. Los CFDIs generados no tendrán validez fiscal.', 'woo-factura-com') . ' ';
            echo '<a href="' . admin_url('admin.php?page=woo-factura-com') . '">';
            echo __('Configurar modo real', 'woo-factura-com');
            echo '</a></p></div>';
        }
        
        /**
         * Aviso de configuración incompleta
         */
        public function configuration_notice() {
            echo '<div class="notice notice-error"><p>';
            echo __('WooCommerce Factura.com:', 'woo-factura-com') . ' ';
            echo __('El plugin no está configurado correctamente. Verifica tus credenciales.', 'woo-factura-com') . ' ';
            echo '<a href="' . admin_url('admin.php?page=woo-factura-com') . '">';
            echo __('Ir a configuración', 'woo-factura-com');
            echo '</a></p></div>';
        }
        
        /**
         * Obtener instancia del gestor de CFDIs
         */
        public function get_cfdi_manager() {
            if (class_exists('WooFacturaComRealCFDIManager')) {
                return new WooFacturaComRealCFDIManager();
            }
            return null;
        }
        
        /**
         * Obtener instancia del cliente API
         */
        public function get_api_client() {
            if (class_exists('WooFacturaComRealAPIClient')) {
                return new WooFacturaComRealAPIClient();
            }
            return null;
        }
        
        /**
         * Obtener información del plugin
         */
        public function get_plugin_info() {
            return array(
                'version' => $this->version,
                'plugin_file' => WOO_FACTURA_COM_PLUGIN_FILE,
                'plugin_dir' => WOO_FACTURA_COM_PLUGIN_DIR,
                'plugin_url' => WOO_FACTURA_COM_PLUGIN_URL,
                'is_configured' => $this->is_configured(),
                'demo_mode' => get_option('woo_factura_com_demo_mode', 'yes') === 'yes',
                'sandbox_mode' => get_option('woo_factura_com_sandbox_mode', 'yes') === 'yes',
                'debug_mode' => get_option('woo_factura_com_debug_mode', 'no') === 'yes'
            );
        }
        
        /**
         * Log de actividades del plugin
         */
        public function log($message, $level = 'info', $context = array()) {
            if (function_exists('woo_factura_com_debug_log')) {
                woo_factura_com_debug_log($message, $level, $context);
            } else {
                error_log("WooFacturaCom [$level]: $message");
            }
        }
    }
}

// Función de acceso global al plugin
if (!function_exists('woo_factura_com')) {
    function woo_factura_com() {
        return WooFacturaCom::get_instance();
    }
}

// Función para verificar si el plugin está activo
if (!function_exists('is_woo_factura_com_active')) {
    function is_woo_factura_com_active() {
        return class_exists('WooFacturaCom') && WooFacturaCom::get_instance() !== null;
    }
}