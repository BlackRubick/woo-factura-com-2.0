<?php
/**
 * Clase de debug y logging para WooCommerce Factura.com
 * Maneja logs, debugging y diagnósticos del plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WooFacturaComDebug')) {
    
    class WooFacturaComDebug {
        
        private $log_table;
        private $logger;
        
        public function __construct() {
            global $wpdb;
            $this->log_table = $wpdb->prefix . 'woo_factura_com_logs';
            
            if (function_exists('wc_get_logger')) {
                $this->logger = wc_get_logger();
            }
            
            $this->init_hooks();
        }
        
        /**
         * Inicializar hooks de debug
         */
        private function init_hooks() {
            // Solo si el modo debug está activo
            if (get_option('woo_factura_com_debug_mode') !== 'yes') {
                return;
            }
            
            // Hooks para debug
            add_action('admin_bar_menu', array($this, 'add_debug_admin_bar'), 999);
            add_action('wp_ajax_woo_factura_com_debug_info', array($this, 'ajax_debug_info'));
            add_action('wp_ajax_woo_factura_com_clear_logs', array($this, 'ajax_clear_logs'));
            add_action('wp_ajax_woo_factura_com_export_logs', array($this, 'ajax_export_logs'));
            add_action('wp_ajax_woo_factura_com_test_email', array($this, 'ajax_test_email'));
            
            // Debug de requests HTTP
            add_action('http_api_debug', array($this, 'log_http_requests'), 10, 5);
            
            // Debug de errores PHP
            add_action('wp_fatal_error_handler_enabled', array($this, 'enable_error_handler'));
            
            // Debug footer
            add_action('wp_footer', array($this, 'debug_footer'));
            add_action('admin_footer', array($this, 'debug_footer'));
        }
        
        /**
         * Agregar información debug al admin bar
         */
        public function add_debug_admin_bar($wp_admin_bar) {
            if (!current_user_can('manage_options')) {
                return;
            }
            
            $stats = $this->get_debug_stats();
            
            $wp_admin_bar->add_node(array(
                'id' => 'woo-factura-com-debug',
                'title' => sprintf(
                    '🧾 Factura.com Debug (L:%d E:%d)',
                    $stats['logs_today'],
                    $stats['errors_today']
                ),
                'href' => admin_url('admin.php?page=woo-factura-com-logs')
            ));
            
            $wp_admin_bar->add_node(array(
                'parent' => 'woo-factura-com-debug',
                'id' => 'woo-factura-com-debug-logs',
                'title' => __('Ver Logs', 'woo-factura-com'),
                'href' => admin_url('admin.php?page=woo-factura-com-logs')
            ));
            
            $wp_admin_bar->add_node(array(
                'parent' => 'woo-factura-com-debug',
                'id' => 'woo-factura-com-debug-clear',
                'title' => __('Limpiar Logs', 'woo-factura-com'),
                'href' => '#',
                'meta' => array(
                    'onclick' => 'wooFacturaComClearLogs(); return false;'
                )
            ));
            
            $wp_admin_bar->add_node(array(
                'parent' => 'woo-factura-com-debug',
                'id' => 'woo-factura-com-debug-info',
                'title' => __('Info del Sistema', 'woo-factura-com'),
                'href' => '#',
                'meta' => array(
                    'onclick' => 'wooFacturaComShowDebugInfo(); return false;'
                )
            ));
            
            $wp_admin_bar->add_node(array(
                'parent' => 'woo-factura-com-debug',
                'id' => 'woo-factura-com-debug-test-email',
                'title' => __('Probar Email', 'woo-factura-com'),
                'href' => '#',
                'meta' => array(
                    'onclick' => 'wooFacturaComTestEmail(); return false;'
                )
            ));
        }
        
        /**
         * Footer con información de debug
         */
        public function debug_footer() {
            if (!current_user_can('manage_options')) {
                return;
            }
            
            $memory_usage = $this->format_bytes(memory_get_peak_usage(true));
            $execution_time = timer_stop(0, 3);
            $queries = get_num_queries();
            ?>
            <script>
            function wooFacturaComClearLogs() {
                if (confirm('¿Estás seguro de limpiar todos los logs?')) {
                    jQuery.post(ajaxurl, {
                        action: 'woo_factura_com_clear_logs',
                        nonce: '<?php echo wp_create_nonce('woo_factura_com_debug'); ?>'
                    }, function(response) {
                        alert(response.data.message);
                        location.reload();
                    });
                }
            }
            
            function wooFacturaComShowDebugInfo() {
                jQuery.post(ajaxurl, {
                    action: 'woo_factura_com_debug_info',
                    nonce: '<?php echo wp_create_nonce('woo_factura_com_debug'); ?>'
                }, function(response) {
                    var info = response.data;
                    var content = 'INFORMACIÓN DEL SISTEMA\n\n';
                    content += 'Plugin: ' + info.plugin_version + '\n';
                    content += 'WordPress: ' + info.wp_version + '\n';
                    content += 'WooCommerce: ' + info.wc_version + '\n';
                    content += 'PHP: ' + info.php_version + '\n';
                    content += 'Memoria: ' + info.memory_limit + '\n';
                    content += 'Servidor: ' + info.server_software + '\n';
                    content += 'MySQL: ' + info.mysql_version + '\n';
                    content += 'Modo Debug: ' + info.debug_mode + '\n';
                    content += 'Modo Demo: ' + info.demo_mode + '\n';
                    content += 'Entorno: ' + info.environment + '\n';
                    alert(content);
                });
            }
            
            function wooFacturaComTestEmail() {
                var email = prompt('Ingresa el email de prueba:', '<?php echo get_option('admin_email'); ?>');
                if (email) {
                    jQuery.post(ajaxurl, {
                        action: 'woo_factura_com_test_email',
                        email: email,
                        nonce: '<?php echo wp_create_nonce('woo_factura_com_debug'); ?>'
                    }, function(response) {
                        alert(response.data.message);
                    });
                }
            }
            </script>
            
            <!-- WooCommerce Factura.com Debug Info -->
            <div style="position: fixed; bottom: 0; right: 0; background: rgba(0,0,0,0.8); color: white; padding: 5px 10px; font-size: 11px; z-index: 9999; font-family: monospace;">
                🧾 FC: <?php echo $memory_usage; ?> | <?php echo $execution_time; ?>s | <?php echo $queries; ?>q
            </div>
            <?php
        }
        
        /**
         * Obtener estadísticas de debug
         */
        private function get_debug_stats() {
            global $wpdb;
            
            $today = current_time('Y-m-d');
            
            $logs_today = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->log_table} WHERE DATE(created_at) = %s",
                $today
            ));
            
            $errors_today = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->log_table} WHERE DATE(created_at) = %s AND status IN ('error', 'critical')",
                $today
            ));
            
            return array(
                'logs_today' => intval($logs_today),
                'errors_today' => intval($errors_today)
            );
        }
        
        /**
         * AJAX: Obtener información de debug
         */
        public function ajax_debug_info() {
            check_ajax_referer('woo_factura_com_debug', 'nonce');
            
            if (!current_user_can('manage_options')) {
                wp_die(__('No tienes permisos.', 'woo-factura-com'));
            }
            
            $info = array(
                'plugin_version' => WOO_FACTURA_COM_VERSION,
                'wp_version' => get_bloginfo('version'),
                'wc_version' => defined('WC_VERSION') ? WC_VERSION : 'N/A',
                'php_version' => PHP_VERSION,
                'memory_limit' => ini_get('memory_limit'),
                'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                'mysql_version' => $this->get_mysql_version(),
                'debug_mode' => get_option('woo_factura_com_debug_mode') === 'yes' ? 'Activo' : 'Inactivo',
                'demo_mode' => get_option('woo_factura_com_demo_mode') === 'yes' ? 'Activo' : 'Inactivo',
                'environment' => get_option('woo_factura_com_sandbox_mode') === 'yes' ? 'Sandbox' : 'Producción',
                'api_configured' => $this->is_api_configured(),
                'tables_exist' => $this->check_tables_exist(),
                'writable_dirs' => $this->check_writable_directories(),
                'php_extensions' => $this->check_php_extensions(),
                'active_plugins' => $this->get_active_plugins_count(),
                'multisite' => is_multisite() ? 'Sí' : 'No'
            );
            
            wp_send_json_success($info);
        }
        
        /**
         * AJAX: Limpiar logs
         */
        public function ajax_clear_logs() {
            check_ajax_referer('woo_factura_com_debug', 'nonce');
            
            if (!current_user_can('manage_options')) {
                wp_die(__('No tienes permisos.', 'woo-factura-com'));
            }
            
            global $wpdb;
            
            $deleted = $wpdb->query("TRUNCATE TABLE {$this->log_table}");
            
            wp_send_json_success(array(
                'message' => sprintf(__('Se eliminaron todos los logs. Registros eliminados: %d', 'woo-factura-com'), $deleted)
            ));
        }
        
        /**
         * AJAX: Exportar logs
         */
        public function ajax_export_logs() {
            check_ajax_referer('woo_factura_com_debug', 'nonce');
            
            if (!current_user_can('manage_options')) {
                wp_die(__('No tienes permisos.', 'woo-factura-com'));
            }
            
            global $wpdb;
            
            $logs = $wpdb->get_results(
                "SELECT * FROM {$this->log_table} ORDER BY created_at DESC LIMIT 1000"
            );
            
            $filename = 'factura-com-logs-' . current_time('Y-m-d-H-i-s') . '.csv';
            
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            
            $output = fopen('php://output', 'w');
            
            // Headers CSV
            fputcsv($output, array(
                'ID',
                'Order ID',
                'Action',
                'Status',
                'Message',
                'Data',
                'Created At'
            ));
            
            // Datos
            foreach ($logs as $log) {
                fputcsv($output, array(
                    $log->id,
                    $log->order_id,
                    $log->action,
                    $log->status,
                    $log->message,
                    $log->data,
                    $log->created_at
                ));
            }
            
            fclose($output);
            exit;
        }
        
        /**
         * AJAX: Probar email
         */
        public function ajax_test_email() {
            check_ajax_referer('woo_factura_com_debug', 'nonce');
            
            if (!current_user_can('manage_options')) {
                wp_die(__('No tienes permisos.', 'woo-factura-com'));
            }
            
            $email = sanitize_email($_POST['email']);
            
            if (!is_email($email)) {
                wp_send_json_error(array('message' => __('Email inválido.', 'woo-factura-com')));
            }
            
            $subject = __('Email de prueba - WooCommerce Factura.com', 'woo-factura-com');
            $message = sprintf(
                __('Este es un email de prueba del plugin WooCommerce Factura.com.\n\nFecha: %s\nSitio: %s\nUsuario: %s', 'woo-factura-com'),
                current_time('Y-m-d H:i:s'),
                get_bloginfo('name'),
                wp_get_current_user()->display_name
            );
            
            $sent = wp_mail($email, $subject, $message);
            
            if ($sent) {
                wp_send_json_success(array('message' => __('Email enviado exitosamente.', 'woo-factura-com')));
            } else {
                wp_send_json_error(array('message' => __('Error enviando email.', 'woo-factura-com')));
            }
        }
        
        /**
         * Log de requests HTTP
         */
        public function log_http_requests($response, $context, $transport, $request_args, $url) {
            // Solo loggear requests a Factura.com
            if (strpos($url, 'factura.com') === false) {
                return;
            }
            
            $this->log('HTTP Request', 'debug', array(
                'url' => $url,
                'method' => $request_args['method'] ?? 'GET',
                'response_code' => wp_remote_retrieve_response_code($response),
                'response_message' => wp_remote_retrieve_response_message($response),
                'request_args' => $this->sanitize_request_args($request_args)
            ));
        }
        
        /**
         * Sanitizar argumentos de request para logging
         */
        private function sanitize_request_args($args) {
            // Ocultar información sensible
            if (isset($args['headers'])) {
                foreach ($args['headers'] as $key => $value) {
                    if (stripos($key, 'api') !== false || stripos($key, 'key') !== false || stripos($key, 'secret') !== false) {
                        $args['headers'][$key] = str_repeat('*', strlen($value));
                    }
                }
            }
            
            if (isset($args['body'])) {
                $body = is_string($args['body']) ? json_decode($args['body'], true) : $args['body'];
                if (is_array($body)) {
                    // Ocultar campos sensibles
                    $sensitive_fields = array('api_key', 'secret_key', 'password', 'token');
                    foreach ($sensitive_fields as $field) {
                        if (isset($body[$field])) {
                            $body[$field] = str_repeat('*', strlen($body[$field]));
                        }
                    }
                    $args['body'] = wp_json_encode($body);
                }
            }
            
            return $args;
        }
        
        /**
         * Método principal de logging
         */
        public function log($message, $level = 'info', $context = array(), $order_id = 0) {
            // Log en WooCommerce
            if ($this->logger) {
                $this->logger->log($level, $message, array_merge($context, array('source' => 'woo-factura-com')));
            }
            
            // Log en nuestra tabla
            $this->log_to_database($message, $level, $context, $order_id);
            
            // Log crítico también en error_log
            if (in_array($level, array('error', 'critical', 'alert', 'emergency'))) {
                error_log('WooFacturaCom [' . strtoupper($level) . ']: ' . $message . ' | Context: ' . wp_json_encode($context));
            }
        }
        
        /**
         * Log en base de datos
         */
        private function log_to_database($message, $level, $context, $order_id) {
            global $wpdb;
            
            // Determinar acción desde el contexto
            $action = 'general';
            if (isset($context['action'])) {
                $action = $context['action'];
            } elseif (isset($context['source'])) {
                $action = $context['source'];
            }
            
            $wpdb->insert(
                $this->log_table,
                array(
                    'order_id' => intval($order_id),
                    'action' => sanitize_text_field($action),
                    'status' => sanitize_text_field($level),
                    'message' => wp_kses_post($message),
                    'data' => wp_json_encode($context, JSON_UNESCAPED_UNICODE),
                    'created_at' => current_time('mysql')
                ),
                array('%d', '%s', '%s', '%s', '%s', '%s')
            );
        }
        
        /**
         * Obtener versión de MySQL
         */
        private function get_mysql_version() {
            global $wpdb;
            return $wpdb->get_var('SELECT VERSION()');
        }
        
        /**
         * Verificar si la API está configurada
         */
        private function is_api_configured() {
            $api_key = get_option('woo_factura_com_api_key');
            $secret_key = get_option('woo_factura_com_secret_key');
            $serie_id = get_option('woo_factura_com_serie_id');
            
            return !empty($api_key) && !empty($secret_key) && !empty($serie_id);
        }
        
        /**
         * Verificar que las tablas existan
         */
        private function check_tables_exist() {
            global $wpdb;
            
            $required_tables = array(
                $wpdb->prefix . 'woo_factura_com_logs',
                $wpdb->prefix . 'woo_factura_com_configs',
                $wpdb->prefix . 'woo_factura_com_stats'
            );
            
            $existing_tables = array();
            foreach ($required_tables as $table) {
                if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
                    $existing_tables[] = basename($table);
                }
            }
            
            return implode(', ', $existing_tables);
        }
        
        /**
         * Verificar directorios escribibles
         */
        private function check_writable_directories() {
            $dirs = array(
                'wp-content' => is_writable(WP_CONTENT_DIR),
                'uploads' => wp_is_writable(wp_upload_dir()['basedir']),
                'plugins' => is_writable(WP_PLUGIN_DIR)
            );
            
            $writable = array_filter($dirs);
            return implode(', ', array_keys($writable));
        }
        
        /**
         * Verificar extensiones PHP
         */
        private function check_php_extensions() {
            $required = array('curl', 'json', 'mbstring', 'openssl');
            $loaded = array_filter($required, 'extension_loaded');
            
            return implode(', ', $loaded);
        }
        
        /**
         * Obtener cantidad de plugins activos
         */
        private function get_active_plugins_count() {
            return count(get_option('active_plugins', array()));
        }
        
        /**
         * Formatear bytes
         */
        private function format_bytes($size, $precision = 2) {
            $units = array('B', 'KB', 'MB', 'GB', 'TB');
            
            for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
                $size /= 1024;
            }
            
            return round($size, $precision) . ' ' . $units[$i];
        }
        
        /**
         * Habilitar handler de errores
         */
        public function enable_error_handler() {
            return true;
        }
        
        /**
         * Obtener logs recientes
         */
        public function get_recent_logs($limit = 50, $level = null) {
            global $wpdb;
            
            $where = '';
            if ($level) {
                $where = $wpdb->prepare(' WHERE status = %s', $level);
            }
            
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$this->log_table} $where ORDER BY created_at DESC LIMIT %d",
                $limit
            ));
        }
        
        /**
         * Obtener estadísticas de logs
         */
        public function get_log_statistics($days = 7) {
            global $wpdb;
            
            $since = date('Y-m-d H:i:s', strtotime("-$days days"));
            
            $stats = $wpdb->get_row($wpdb->prepare(
                "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as errors,
                    SUM(CASE WHEN status = 'warning' THEN 1 ELSE 0 END) as warnings,
                    SUM(CASE WHEN status = 'info' THEN 1 ELSE 0 END) as info,
                    SUM(CASE WHEN status = 'debug' THEN 1 ELSE 0 END) as debug
                FROM {$this->log_table} 
                WHERE created_at >= %s",
                $since
            ), ARRAY_A);
            
            return $stats;
        }
        
        /**
         * Crear reporte de diagnóstico
         */
        public function create_diagnostic_report() {
            $report = array(
                'timestamp' => current_time('c'),
                'site_info' => array(
                    'site_url' => get_site_url(),
                    'home_url' => get_home_url(),
                    'wp_version' => get_bloginfo('version'),
                    'wc_version' => defined('WC_VERSION') ? WC_VERSION : 'N/A',
                    'plugin_version' => WOO_FACTURA_COM_VERSION,
                    'php_version' => PHP_VERSION,
                    'mysql_version' => $this->get_mysql_version(),
                    'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                    'multisite' => is_multisite()
                ),
                'plugin_config' => array(
                    'demo_mode' => get_option('woo_factura_com_demo_mode'),
                    'sandbox_mode' => get_option('woo_factura_com_sandbox_mode'),
                    'debug_mode' => get_option('woo_factura_com_debug_mode'),
                    'api_configured' => $this->is_api_configured(),
                    'auto_generate' => get_option('woo_factura_com_auto_generate'),
                    'send_email' => get_option('woo_factura_com_send_email'),
                    'setup_completed' => get_option('woo_factura_com_setup_completed')
                ),
                'system_status' => array(
                    'tables_exist' => $this->check_tables_exist(),
                    'writable_dirs' => $this->check_writable_directories(),
                    'php_extensions' => $this->check_php_extensions(),
                    'memory_limit' => ini_get('memory_limit'),
                    'max_execution_time' => ini_get('max_execution_time'),
                    'active_plugins' => $this->get_active_plugins_count()
                ),
                'recent_logs' => $this->get_recent_logs(10),
                'log_stats' => $this->get_log_statistics()
            );
            
            return $report;
        }
    }
}

// Funciones globales de logging
if (!function_exists('woo_factura_com_debug_log')) {
    function woo_factura_com_debug_log($message, $level = 'debug', $context = array(), $order_id = 0) {
        if (get_option('woo_factura_com_debug_mode') === 'yes') {
            static $debug_instance = null;
            
            if (is_null($debug_instance)) {
                $debug_instance = new WooFacturaComDebug();
            }
            
            $debug_instance->log($message, $level, $context, $order_id);
        }
    }
}