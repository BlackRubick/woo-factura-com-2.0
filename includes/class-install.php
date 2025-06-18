<?php
/**
 * Clase para manejo de instalación/desinstalación del plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WooFacturaComInstaller')) {
    
    class WooFacturaComInstaller {
        
        /**
         * Activación del plugin
         */
        public static function activate() {
            // Crear tablas de base de datos
            self::create_tables();
            
            // Establecer opciones por defecto
            self::set_default_options();
            
            // Crear página de configuración si no existe
            self::create_admin_page();
            
            // Verificar permisos y dependencias
            self::check_requirements();
            
            // Programar tareas cron si es necesario
            self::schedule_cron_jobs();
            
            // Log de activación
            error_log('WooCommerce Factura.com: Plugin activado correctamente');
        }
        
        /**
         * Desactivación del plugin
         */
        public static function deactivate() {
            // Limpiar tareas cron
            self::clear_cron_jobs();
            
            // Log de desactivación
            error_log('WooCommerce Factura.com: Plugin desactivado');
        }
        
        /**
         * Desinstalación del plugin
         */
        public static function uninstall() {
            // Eliminar opciones si el usuario lo desea
            if (get_option('woo_factura_com_remove_data_on_uninstall') === 'yes') {
                self::remove_plugin_data();
            }
            
            // Log de desinstalación
            error_log('WooCommerce Factura.com: Plugin desinstalado');
        }
        
        /**
         * Crear tablas de base de datos
         */
        private static function create_tables() {
            global $wpdb;
            
            $charset_collate = $wpdb->get_charset_collate();
            
            // Tabla de logs
            $table_logs = $wpdb->prefix . 'woo_factura_com_logs';
            $sql_logs = "CREATE TABLE $table_logs (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                order_id bigint(20) DEFAULT NULL,
                action varchar(100) NOT NULL,
                status varchar(50) NOT NULL,
                message text,
                data longtext,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY order_id (order_id),
                KEY status (status),
                KEY created_at (created_at)
            ) $charset_collate;";
            
            // Tabla de configuraciones
            $table_configs = $wpdb->prefix . 'woo_factura_com_configs';
            $sql_configs = "CREATE TABLE $table_configs (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                config_key varchar(100) NOT NULL UNIQUE,
                config_value longtext,
                config_type varchar(50) DEFAULT 'string',
                autoload varchar(10) DEFAULT 'yes',
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY config_key (config_key)
            ) $charset_collate;";
            
            // Tabla de estadísticas
            $table_stats = $wpdb->prefix . 'woo_factura_com_stats';
            $sql_stats = "CREATE TABLE $table_stats (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                stat_date date NOT NULL,
                cfdis_generated int(11) DEFAULT 0,
                cfdis_cancelled int(11) DEFAULT 0,
                total_amount decimal(15,2) DEFAULT 0,
                api_calls int(11) DEFAULT 0,
                errors int(11) DEFAULT 0,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY stat_date (stat_date)
            ) $charset_collate;";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql_logs);
            dbDelta($sql_configs);
            dbDelta($sql_stats);
            
            // Actualizar versión de BD
            update_option('woo_factura_com_db_version', '1.0.0');
        }
        
        /**
         * Establecer opciones por defecto
         */
        private static function set_default_options() {
            $default_options = array(
                'woo_factura_com_version' => WOO_FACTURA_COM_VERSION,
                'woo_factura_com_demo_mode' => 'yes',
                'woo_factura_com_sandbox_mode' => 'yes',
                'woo_factura_com_debug_mode' => 'no',
                'woo_factura_com_auto_generate' => 'no',
                'woo_factura_com_send_email' => 'yes',
                'woo_factura_com_add_rfc_field' => 'yes',
                'woo_factura_com_require_rfc' => 'no',
                'woo_factura_com_uso_cfdi' => 'G01',
                'woo_factura_com_forma_pago' => '99',
                'woo_factura_com_metodo_pago' => 'PUE',
                'woo_factura_com_clave_prod_serv' => '81112101',
                'woo_factura_com_clave_unidad' => 'E48',
                'woo_factura_com_unidad' => 'Unidad de servicio',
                'woo_factura_com_objeto_impuesto' => '02',
                'woo_factura_com_tasa_iva' => '0.16',
                'woo_factura_com_lugar_expedicion' => '',
                'woo_factura_com_tipo_cambio' => '20.00',
                'woo_factura_com_setup_completed' => false,
                'woo_factura_com_installed_at' => current_time('mysql')
            );
            
            foreach ($default_options as $option => $value) {
                if (get_option($option) === false) {
                    add_option($option, $value);
                }
            }
        }
        
        /**
         * Crear página de administración
         */
        private static function create_admin_page() {
            // Verificar si existe la página de configuración
            $page = get_page_by_path('woo-factura-com-config');
            
            if (!$page) {
                wp_insert_post(array(
                    'post_title' => 'Configuración WooCommerce Factura.com',
                    'post_name' => 'woo-factura-com-config',
                    'post_content' => '[woo_factura_com_config]',
                    'post_status' => 'private',
                    'post_type' => 'page'
                ));
            }
        }
        
        /**
         * Verificar requisitos del sistema
         */
        private static function check_requirements() {
            $requirements_met = true;
            $errors = array();
            
            // Verificar versión de PHP
            if (version_compare(PHP_VERSION, '7.4', '<')) {
                $requirements_met = false;
                $errors[] = 'Se requiere PHP 7.4 o superior. Versión actual: ' . PHP_VERSION;
            }
            
            // Verificar WooCommerce
            if (!class_exists('WooCommerce')) {
                $requirements_met = false;
                $errors[] = 'WooCommerce debe estar instalado y activado';
            }
            
            // Verificar extensiones PHP
            $required_extensions = array('curl', 'json', 'mbstring');
            foreach ($required_extensions as $extension) {
                if (!extension_loaded($extension)) {
                    $requirements_met = false;
                    $errors[] = "Extensión PHP requerida: $extension";
                }
            }
            
            // Verificar permisos de escritura
            if (!is_writable(WP_CONTENT_DIR)) {
                $errors[] = 'Directorio wp-content no tiene permisos de escritura';
            }
            
            if (!$requirements_met) {
                update_option('woo_factura_com_requirements_errors', $errors);
                add_action('admin_notices', function() {
                    $errors = get_option('woo_factura_com_requirements_errors', array());
                    if (!empty($errors)) {
                        echo '<div class="notice notice-error"><p>';
                        echo '<strong>WooCommerce Factura.com:</strong> No se cumplen los requisitos mínimos:<br>';
                        echo implode('<br>', $errors);
                        echo '</p></div>';
                    }
                });
            } else {
                delete_option('woo_factura_com_requirements_errors');
            }
        }
        
        /**
         * Programar tareas cron
         */
        private static function schedule_cron_jobs() {
            // Limpiar logs antiguos diariamente
            if (!wp_next_scheduled('woo_factura_com_cleanup_logs')) {
                wp_schedule_event(time(), 'daily', 'woo_factura_com_cleanup_logs');
            }
            
            // Actualizar estadísticas diariamente
            if (!wp_next_scheduled('woo_factura_com_update_stats')) {
                wp_schedule_event(time(), 'daily', 'woo_factura_com_update_stats');
            }
        }
        
        /**
         * Limpiar tareas cron
         */
        private static function clear_cron_jobs() {
            wp_clear_scheduled_hook('woo_factura_com_cleanup_logs');
            wp_clear_scheduled_hook('woo_factura_com_update_stats');
        }
        
        /**
         * Remover datos del plugin
         */
        private static function remove_plugin_data() {
            global $wpdb;
            
            // Eliminar tablas
            $tables = array(
                $wpdb->prefix . 'woo_factura_com_logs',
                $wpdb->prefix . 'woo_factura_com_configs',
                $wpdb->prefix . 'woo_factura_com_stats'
            );
            
            foreach ($tables as $table) {
                $wpdb->query("DROP TABLE IF EXISTS $table");
            }
            
            // Eliminar opciones
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE 'woo_factura_com_%'");
            
            // Eliminar meta de pedidos
            $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_factura_com_%'");
            
            // Eliminar páginas creadas
            $page = get_page_by_path('woo-factura-com-config');
            if ($page) {
                wp_delete_post($page->ID, true);
            }
        }
        
        /**
         * Actualizar base de datos si es necesario
         */
        public static function maybe_update_db() {
            $current_db_version = get_option('woo_factura_com_db_version', '0');
            
            if (version_compare($current_db_version, '1.0.0', '<')) {
                self::create_tables();
                update_option('woo_factura_com_db_version', '1.0.0');
            }
        }
    }
}

// Hooks para tareas cron
add_action('woo_factura_com_cleanup_logs', function() {
    if (class_exists('WooFacturaComUtilities')) {
        WooFacturaComUtilities::cleanup_old_logs(30);
    }
});

add_action('woo_factura_com_update_stats', function() {
    // Actualizar estadísticas diarias
    global $wpdb;
    
    $table_stats = $wpdb->prefix . 'woo_factura_com_stats';
    $today = current_time('Y-m-d');
    
    // Contar CFDIs del día
    $cfdis_generated = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*) FROM {$wpdb->postmeta} pm
        INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
        WHERE pm.meta_key = '_factura_com_cfdi_generated_at'
        AND DATE(pm.meta_value) = %s
    ", $today));
    
    $cfdis_cancelled = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*) FROM {$wpdb->postmeta} pm
        INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
        WHERE pm.meta_key = '_factura_com_cfdi_cancelled'
        AND DATE(pm.meta_value) = %s
    ", $today));
    
    // Insertar o actualizar estadísticas
    $wpdb->replace(
        $table_stats,
        array(
            'stat_date' => $today,
            'cfdis_generated' => $cfdis_generated,
            'cfdis_cancelled' => $cfdis_cancelled
        ),
        array('%s', '%d', '%d')
    );
});