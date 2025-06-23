<?php
/**
 * Asistente de configuración inicial CORREGIDO para WooCommerce Factura.com
 * 
 * REEMPLAZA COMPLETAMENTE el archivo includes/class-setup-wizard.php
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WooFacturaComSetupWizard')) {
    
    class WooFacturaComSetupWizard {
        
        private $steps = array();
        private $current_step = '';
        
        public function __construct() {
            add_action('admin_menu', array($this, 'admin_menus'));
            add_action('admin_init', array($this, 'setup_wizard'));
            add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
            
            $this->init_steps();
        }
        
        /**
         * Inicializar pasos del wizard
         */
        private function init_steps() {
            $this->steps = array(
                'welcome' => array(
                    'name' => __('Bienvenida', 'woo-factura-com'),
                    'view' => array($this, 'step_welcome'),
                    'handler' => array($this, 'save_welcome') // FIX: Agregar handler
                ),
                'environment' => array(
                    'name' => __('Entorno', 'woo-factura-com'),
                    'view' => array($this, 'step_environment'),
                    'handler' => array($this, 'save_environment')
                ),
                'credentials' => array(
                    'name' => __('Credenciales', 'woo-factura-com'),
                    'view' => array($this, 'step_credentials'),
                    'handler' => array($this, 'save_credentials')
                ),
                'fiscal' => array(
                    'name' => __('Configuración Fiscal', 'woo-factura-com'),
                    'view' => array($this, 'step_fiscal'),
                    'handler' => array($this, 'save_fiscal')
                ),
                'test' => array(
                    'name' => __('Prueba', 'woo-factura-com'),
                    'view' => array($this, 'step_test'),
                    'handler' => array($this, 'test_configuration')
                ),
                'complete' => array(
                    'name' => __('Completado', 'woo-factura-com'),
                    'view' => array($this, 'step_complete'),
                    'handler' => ''
                )
            );
        }
        
        /**
         * Agregar menús de administración
         */
        public function admin_menus() {
            if ($this->should_show_setup_wizard()) {
                $page_hook = add_dashboard_page(
                    __('Configurar Factura.com', 'woo-factura-com'),
                    __('Configurar Factura.com', 'woo-factura-com'),
                    'manage_options',
                    'woo-factura-com-setup',
                    array($this, 'setup_wizard')
                );
                
                // DEBUG
                error_log('WooFacturaCom Setup: Página de setup agregada con hook: ' . $page_hook);
            }
        }
        
        /**
         * Verificar si debe mostrar el wizard
         */
        private function should_show_setup_wizard() {
            $setup_completed = get_option('woo_factura_com_setup_completed', false);
            $hide_wizard = get_option('woo_factura_com_hide_setup_wizard', false);
            
            $should_show = !$setup_completed && !$hide_wizard;
            
            error_log('WooFacturaCom Setup Debug:');
            error_log('- Setup completed: ' . ($setup_completed ? 'true' : 'false'));
            error_log('- Hide wizard: ' . ($hide_wizard ? 'true' : 'false'));
            error_log('- Should show wizard: ' . ($should_show ? 'true' : 'false'));
            
            return $should_show;
        }
        
        /**
         * Enqueue scripts para el wizard
         */
        public function enqueue_scripts($hook) {
            if ($hook === 'dashboard_page_woo-factura-com-setup') {
                wp_enqueue_style(
                    'woo-factura-com-setup',
                    WOO_FACTURA_COM_PLUGIN_URL . 'assets/css/setup.css',
                    array(),
                    WOO_FACTURA_COM_VERSION
                );
                
                wp_enqueue_script(
                    'woo-factura-com-setup',
                    WOO_FACTURA_COM_PLUGIN_URL . 'assets/js/setup.js',
                    array('jquery'),
                    WOO_FACTURA_COM_VERSION,
                    true
                );
            }
        }
        
        /**
         * Controlador principal del wizard - MEJORADO
         */
        public function setup_wizard() {
            // Solo procesar en la página correcta
            if (!isset($_GET['page']) || $_GET['page'] !== 'woo-factura-com-setup') {
                return;
            }
            
            $this->current_step = isset($_GET['step']) ? sanitize_key($_GET['step']) : 'welcome';
            
            // DEBUG: Verificar si se está procesando POST
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                error_log('WooFacturaCom Setup: Procesando POST para step: ' . $this->current_step);
                error_log('POST data: ' . print_r($_POST, true));
                
                if (isset($this->steps[$this->current_step]['handler'])) {
                    $handler = $this->steps[$this->current_step]['handler'];
                    if (is_callable($handler)) {
                        error_log('WooFacturaCom Setup: Ejecutando handler para step: ' . $this->current_step);
                        call_user_func($handler);
                    } else {
                        error_log('WooFacturaCom Setup: Handler no callable para step: ' . $this->current_step);
                    }
                } else {
                    error_log('WooFacturaCom Setup: No hay handler para step: ' . $this->current_step);
                    // Si no hay handler específico, ir al siguiente paso
                    $this->redirect_to_next_step();
                }
            }
            
            $this->render_wizard();
        }
        
        /**
         * Renderizar el wizard
         */
        private function render_wizard() {
            ?>
            <!DOCTYPE html>
            <html <?php language_attributes(); ?>>
            <head>
                <meta name="viewport" content="width=device-width" />
                <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
                <title><?php _e('Configuración de Factura.com', 'woo-factura-com'); ?></title>
                <?php wp_print_head_scripts(); ?>
                <?php wp_print_styles(); ?>
                <style>
                    body { background: #f1f1f1; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
                    .woo-factura-com-setup { max-width: 800px; margin: 50px auto; background: white; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
                    .setup-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 8px 8px 0 0; }
                    .setup-header h1 { margin: 0; font-size: 28px; font-weight: 300; }
                    .setup-progress { display: flex; justify-content: space-between; padding: 20px 0; margin: 0 30px; border-bottom: 1px solid #eee; }
                    .progress-step { flex: 1; text-align: center; position: relative; }
                    .progress-step.active { color: #667eea; font-weight: 600; }
                    .progress-step.completed { color: #28a745; }
                    .setup-content { padding: 40px; min-height: 400px; }
                    .setup-actions { padding: 20px 40px; border-top: 1px solid #eee; text-align: right; }
                    .button { padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; }
                    .button-primary { background: #667eea; color: white; }
                    .button-secondary { background: #f8f9fa; color: #495057; border: 1px solid #dee2e6; margin-right: 10px; }
                    .form-group { margin-bottom: 20px; }
                    .form-group label { display: block; margin-bottom: 8px; font-weight: 600; }
                    .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px; }
                    .form-group .description { font-size: 13px; color: #666; margin-top: 5px; }
                    .alert { padding: 15px; margin: 20px 0; border-radius: 4px; }
                    .alert-success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
                    .alert-warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; }
                    .alert-error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
                    .card { background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 6px; padding: 20px; margin: 20px 0; }
                    .card h3 { margin-top: 0; color: #495057; }
                    .feature-list { list-style: none; padding: 0; }
                    .feature-list li { padding: 8px 0; }
                    .feature-list li:before { content: "✅"; margin-right: 10px; }
                    .loading { opacity: 0.6; pointer-events: none; }
                </style>
            </head>
            <body>
                <div class="woo-factura-com-setup">
                    <div class="setup-header">
                        <h1>🧾 <?php _e('Configuración de Factura.com', 'woo-factura-com'); ?></h1>
                        <p><?php _e('Te guiaremos paso a paso para configurar tu integración con Factura.com', 'woo-factura-com'); ?></p>
                    </div>
                    
                    <div class="setup-progress">
                        <?php $this->render_progress(); ?>
                    </div>
                    
                    <div class="setup-content">
                        <?php $this->render_current_step(); ?>
                    </div>
                    
                    <div class="setup-actions">
                        <?php $this->render_actions(); ?>
                    </div>
                </div>
                
                <script>
                jQuery(document).ready(function($) {
                    console.log('WooFacturaCom Setup Wizard cargado - Step:', '<?php echo $this->current_step; ?>');
                    
                    // Debug para formularios
                    $('form').on('submit', function(e) {
                        console.log('Enviando formulario:', this);
                        console.log('Action:', $(this).attr('action'));
                        console.log('Method:', $(this).attr('method'));
                        
                        var formData = $(this).serialize();
                        console.log('Form data:', formData);
                        
                        // Verificar que tenemos los campos necesarios
                        if (!formData.includes('_wpnonce')) {
                            console.error('¡Falta nonce de seguridad!');
                            alert('Error: Falta token de seguridad. Recarga la página.');
                            e.preventDefault();
                            return false;
                        }
                        
                        // Mostrar loading
                        $(this).find('button[type="submit"]').prop('disabled', true).text('Procesando...');
                        $('.woo-factura-com-setup').addClass('loading');
                    });
                    
                    // Debug para botones
                    $('button[type="submit"]').on('click', function() {
                        console.log('Botón presionado:', $(this).attr('name'), '=', $(this).val());
                    });
                });
                </script>
                
                <?php wp_print_footer_scripts(); ?>
            </body>
            </html>
            <?php
        }
        
        /**
         * Renderizar barra de progreso
         */
        private function render_progress() {
            $step_keys = array_keys($this->steps);
            $current_index = array_search($this->current_step, $step_keys);
            
            foreach ($this->steps as $key => $step) {
                $index = array_search($key, $step_keys);
                $class = '';
                
                if ($index < $current_index) {
                    $class = 'completed';
                } elseif ($key === $this->current_step) {
                    $class = 'active';
                }
                
                echo '<div class="progress-step ' . $class . '">' . esc_html($step['name']) . '</div>';
            }
        }
        
        /**
         * Renderizar paso actual
         */
        private function render_current_step() {
            if (isset($this->steps[$this->current_step]['view'])) {
                call_user_func($this->steps[$this->current_step]['view']);
            }
        }
        
        /**
         * Renderizar botones de acción - MEJORADO
         */
        private function render_actions() {
            $step_keys = array_keys($this->steps);
            $current_index = array_search($this->current_step, $step_keys);
            
            // Botón anterior
            if ($current_index > 0) {
                $prev_step = $step_keys[$current_index - 1];
                echo '<a href="' . esc_url($this->get_step_url($prev_step)) . '" class="button button-secondary">' . __('Anterior', 'woo-factura-com') . '</a>';
            }
            
            // Botón siguiente/continuar
            if ($this->current_step === 'complete') {
                echo '<a href="' . admin_url('admin.php?page=woo-factura-com') . '" class="button button-primary">' . __('Ir a Configuración', 'woo-factura-com') . '</a>';
            } elseif ($this->current_step === 'test') {
                echo '<button type="submit" class="button button-primary" name="test_config" value="1">' . __('Probar Configuración', 'woo-factura-com') . '</button>';
            } else {
                // FIX: Especificar name y value para todos los botones de continuar
                echo '<button type="submit" class="button button-primary" name="continue" value="1">' . __('Continuar', 'woo-factura-com') . '</button>';
            }
            
            // Botón saltar wizard
            if (!in_array($this->current_step, array('complete'))) {
                echo '<a href="' . esc_url($this->get_step_url('complete')) . '" style="margin-left: 20px; color: #666;">' . __('Saltar configuración', 'woo-factura-com') . '</a>';
            }
        }
        
        /**
         * Obtener URL del paso
         */
        private function get_step_url($step) {
            return admin_url('admin.php?page=woo-factura-com-setup&step=' . $step);
        }
        
        /**
         * Obtener siguiente paso
         */
        private function get_next_step() {
            $step_keys = array_keys($this->steps);
            $current_index = array_search($this->current_step, $step_keys);
            
            if ($current_index !== false && $current_index < count($step_keys) - 1) {
                return $step_keys[$current_index + 1];
            }
            
            return 'complete';
        }
        
        /**
         * Redirigir al siguiente paso - MEJORADO
         */
        private function redirect_to_next_step() {
            $next_step = $this->get_next_step();
            $redirect_url = $this->get_step_url($next_step);
            
            error_log('WooFacturaCom Setup: Redirigiendo de ' . $this->current_step . ' a ' . $next_step);
            error_log('WooFacturaCom Setup: URL de redirección: ' . $redirect_url);
            
            wp_redirect($redirect_url);
            exit;
        }
        
        // ============== PASOS DEL WIZARD ==============
        
        /**
         * Paso 1: Bienvenida - CORREGIDO
         */
        public function step_welcome() {
            ?>
            <h2><?php _e('¡Bienvenido a Factura.com!', 'woo-factura-com'); ?></h2>
            
            <p><?php _e('Este asistente te ayudará a configurar la integración con Factura.com para generar CFDIs automáticamente en tu tienda WooCommerce.', 'woo-factura-com'); ?></p>
            
            <div class="card">
                <h3><?php _e('¿Qué puedes hacer con este plugin?', 'woo-factura-com'); ?></h3>
                <ul class="feature-list">
                    <li><?php _e('Generar CFDIs automáticamente cuando se completa un pedido', 'woo-factura-com'); ?></li>
                    <li><?php _e('Validar RFC de clientes en tiempo real', 'woo-factura-com'); ?></li>
                    <li><?php _e('Enviar facturas por email automáticamente', 'woo-factura-com'); ?></li>
                    <li><?php _e('Cancelar y regenerar CFDIs desde el admin', 'woo-factura-com'); ?></li>
                    <li><?php _e('Configuración completa según catálogos del SAT', 'woo-factura-com'); ?></li>
                    <li><?php _e('Modo demo para pruebas sin consumir API', 'woo-factura-com'); ?></li>
                </ul>
            </div>
            
            <div class="alert alert-warning">
                <h4><?php _e('Antes de comenzar', 'woo-factura-com'); ?></h4>
                <p><?php _e('Asegúrate de tener:', 'woo-factura-com'); ?></p>
                <ul>
                    <li><?php _e('Una cuenta en Factura.com (o sandbox para pruebas)', 'woo-factura-com'); ?></li>
                    <li><?php _e('Tus credenciales API (F-Api-Key y F-Secret-Key)', 'woo-factura-com'); ?></li>
                    <li><?php _e('Una serie configurada en tu cuenta de Factura.com', 'woo-factura-com'); ?></li>
                </ul>
            </div>
            
            <!-- FIX: Agregar action explícito y campo hidden para continuar -->
            <form method="post" action="<?php echo esc_url($this->get_step_url($this->current_step)); ?>">
                <?php wp_nonce_field('woo_factura_com_setup'); ?>
                <input type="hidden" name="continue" value="1">
            </form>
            <?php
        }
        
        /**
         * Paso 2: Seleccionar entorno
         */
        public function step_environment() {
            $demo_mode = get_option('woo_factura_com_demo_mode', 'yes') === 'yes';
            $sandbox_mode = get_option('woo_factura_com_sandbox_mode', 'yes') === 'yes';
            ?>
            <h2><?php _e('Selecciona tu entorno de trabajo', 'woo-factura-com'); ?></h2>
            
            <form method="post" action="<?php echo esc_url($this->get_step_url($this->current_step)); ?>">
                <?php wp_nonce_field('woo_factura_com_setup'); ?>
                
                <div class="form-group">
                    <label>
                        <input type="radio" name="environment" value="demo" <?php checked($demo_mode && $sandbox_mode); ?>>
                        <strong><?php _e('🧪 Modo Demo', 'woo-factura-com'); ?></strong>
                    </label>
                    <div class="description">
                        <?php _e('Ideal para empezar. Genera CFDIs simulados sin usar la API real. Perfecto para conocer el plugin.', 'woo-factura-com'); ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="radio" name="environment" value="sandbox" <?php checked(!$demo_mode && $sandbox_mode); ?>>
                        <strong><?php _e('🔧 Sandbox (Pruebas)', 'woo-factura-com'); ?></strong>
                    </label>
                    <div class="description">
                        <?php _e('Para pruebas con la API real de Factura.com. Genera CFDIs reales pero sin validez fiscal.', 'woo-factura-com'); ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>
                        <input type="radio" name="environment" value="production" <?php checked(!$demo_mode && !$sandbox_mode); ?>>
                        <strong><?php _e('🚀 Producción', 'woo-factura-com'); ?></strong>
                    </label>
                    <div class="description">
                        <?php _e('Para generar CFDIs con validez fiscal. Solo selecciona si ya probaste en sandbox.', 'woo-factura-com'); ?>
                    </div>
                </div>
                
                <div class="alert alert-warning">
                    <p><strong><?php _e('Recomendación:', 'woo-factura-com'); ?></strong> <?php _e('Si es tu primera vez, comienza con "Modo Demo" para familiarizarte con el plugin.', 'woo-factura-com'); ?></p>
                </div>
            </form>
            <?php
        }
        
        /**
         * Paso 3: Credenciales
         */
        public function step_credentials() {
            $demo_mode = get_option('woo_factura_com_demo_mode', 'yes') === 'yes';
            
            if ($demo_mode) {
                ?>
                <h2><?php _e('Modo Demo Activado', 'woo-factura-com'); ?></h2>
                
                <div class="alert alert-success">
                    <h4><?php _e('✅ ¡Perfecto!', 'woo-factura-com'); ?></h4>
                    <p><?php _e('Has seleccionado el modo demo. No necesitas credenciales para continuar.', 'woo-factura-com'); ?></p>
                    <p><?php _e('Podrás generar CFDIs simulados para probar todas las funcionalidades.', 'woo-factura-com'); ?></p>
                </div>
                
                <div class="card">
                    <h3><?php _e('¿Cuándo configurar credenciales reales?', 'woo-factura-com'); ?></h3>
                    <p><?php _e('Cuando estés listo para generar CFDIs reales, ve a WooCommerce → Factura.com y:', 'woo-factura-com'); ?></p>
                    <ol>
                        <li><?php _e('Desactiva el "Modo Demo"', 'woo-factura-com'); ?></li>
                        <li><?php _e('Agrega tus credenciales de Factura.com', 'woo-factura-com'); ?></li>
                        <li><?php _e('Prueba la conexión', 'woo-factura-com'); ?></li>
                    </ol>
                </div>
                
                <form method="post" action="<?php echo esc_url($this->get_step_url($this->current_step)); ?>">
                    <?php wp_nonce_field('woo_factura_com_setup'); ?>
                    <input type="hidden" name="continue" value="1">
                </form>
                <?php
            } else {
                $api_key = get_option('woo_factura_com_api_key', '');
                $secret_key = get_option('woo_factura_com_secret_key', '');
                $serie_id = get_option('woo_factura_com_serie_id', '');
                $sandbox_mode = get_option('woo_factura_com_sandbox_mode', 'yes') === 'yes';
                ?>
                <h2><?php _e('Configura tus credenciales de Factura.com', 'woo-factura-com'); ?></h2>
                
                <div class="alert alert-warning">
                    <h4><?php _e('¿Cómo obtener tus credenciales?', 'woo-factura-com'); ?></h4>
                    <ol>
                        <li><?php _e('Inicia sesión en', 'woo-factura-com'); ?> 
                            <a href="<?php echo $sandbox_mode ? 'https://sandbox.factura.com' : 'https://factura.com'; ?>" target="_blank">
                                <?php echo $sandbox_mode ? 'sandbox.factura.com' : 'factura.com'; ?>
                            </a>
                        </li>
                        <li><?php _e('Ve a: Desarrolladores → API → Datos de acceso', 'woo-factura-com'); ?></li>
                        <li><?php _e('Copia tu F-Api-Key y F-Secret-Key', 'woo-factura-com'); ?></li>
                        <li><?php _e('Ve a: Configuraciones → Series y folios para obtener el Serie ID', 'woo-factura-com'); ?></li>
                    </ol>
                </div>
                
                <form method="post" action="<?php echo esc_url($this->get_step_url($this->current_step)); ?>">
                    <?php wp_nonce_field('woo_factura_com_setup'); ?>
                    
                    <div class="form-group">
                        <label for="api_key"><?php _e('F-Api-Key', 'woo-factura-com'); ?></label>
                        <input type="password" id="api_key" name="api_key" value="<?php echo esc_attr($api_key); ?>" required>
                        <div class="description"><?php _e('Tu API Key de Factura.com', 'woo-factura-com'); ?></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="secret_key"><?php _e('F-Secret-Key', 'woo-factura-com'); ?></label>
                        <input type="password" id="secret_key" name="secret_key" value="<?php echo esc_attr($secret_key); ?>" required>
                        <div class="description"><?php _e('Tu Secret Key de Factura.com', 'woo-factura-com'); ?></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="serie_id"><?php _e('Serie ID', 'woo-factura-com'); ?></label>
                        <input type="number" id="serie_id" name="serie_id" value="<?php echo esc_attr($serie_id); ?>" required>
                        <div class="description"><?php _e('ID numérico de tu serie (ej: 1247)', 'woo-factura-com'); ?></div>
                    </div>
                </form>
                <?php
            }
        }
        
        /**
         * Paso 4: Configuración fiscal
         */
        public function step_fiscal() {
            $uso_cfdi = get_option('woo_factura_com_uso_cfdi', 'G01');
            $forma_pago = get_option('woo_factura_com_forma_pago', '01');
            $metodo_pago = get_option('woo_factura_com_metodo_pago', 'PUE');
            $lugar_expedicion = get_option('woo_factura_com_lugar_expedicion', '');
            ?>
            <h2><?php _e('Configuración fiscal básica', 'woo-factura-com'); ?></h2>
            
            <p><?php _e('Configura los valores básicos según los catálogos del SAT. Podrás cambiarlos más tarde.', 'woo-factura-com'); ?></p>
            
            <form method="post" action="<?php echo esc_url($this->get_step_url($this->current_step)); ?>">
                <?php wp_nonce_field('woo_factura_com_setup'); ?>
                
                <div class="form-group">
                    <label for="uso_cfdi"><?php _e('Uso de CFDI', 'woo-factura-com'); ?></label>
                    <select id="uso_cfdi" name="uso_cfdi" required>
                        <option value="G01" <?php selected($uso_cfdi, 'G01'); ?>>G01 - Adquisición de mercancías</option>
                        <option value="G03" <?php selected($uso_cfdi, 'G03'); ?>>G03 - Gastos en general</option>
                        <option value="P01" <?php selected($uso_cfdi, 'P01'); ?>>P01 - Por definir</option>
                    </select>
                    <div class="description"><?php _e('Uso más común que darán tus clientes al CFDI', 'woo-factura-com'); ?></div>
                </div>
                
                <div class="form-group">
                    <label for="forma_pago"><?php _e('Forma de Pago', 'woo-factura-com'); ?></label>
                    <select id="forma_pago" name="forma_pago" required>
                        <option value="01" <?php selected($forma_pago, '01'); ?>>01 - Efectivo</option>
                        <option value="03" <?php selected($forma_pago, '03'); ?>>03 - Transferencia electrónica</option>
                        <option value="04" <?php selected($forma_pago, '04'); ?>>04 - Tarjeta de crédito</option>
                        <option value="28" <?php selected($forma_pago, '28'); ?>>28 - Tarjeta de débito</option>
                    </select>
                    <div class="description"><?php _e('Forma de pago más común en tu tienda', 'woo-factura-com'); ?></div>
                </div>
                
                <div class="form-group">
                    <label for="metodo_pago"><?php _e('Método de Pago', 'woo-factura-com'); ?></label>
                    <select id="metodo_pago" name="metodo_pago" required>
                        <option value="PUE" <?php selected($metodo_pago, 'PUE'); ?>>PUE - Pago en una sola exhibición</option>
                        <option value="PPD" <?php selected($metodo_pago, 'PPD'); ?>>PPD - Pago en parcialidades</option>
                    </select>
                    <div class="description"><?php _e('Generalmente "PUE" para ventas al momento', 'woo-factura-com'); ?></div>
                </div>
                
                <div class="form-group">
                    <label for="lugar_expedicion"><?php _e('Lugar de Expedición', 'woo-factura-com'); ?></label>
                    <input type="text" id="lugar_expedicion" name="lugar_expedicion" value="<?php echo esc_attr($lugar_expedicion); ?>" maxlength="5" pattern="[0-9]{5}" required>
                    <div class="description"><?php _e('Código postal donde tienes tu empresa (5 dígitos)', 'woo-factura-com'); ?></div>
                </div>
            </form>
            <?php
        }
        
        /**
         * Paso 5: Probar configuración
         */
        public function step_test() {
            $demo_mode = get_option('woo_factura_com_demo_mode', 'yes') === 'yes';
            ?>
            <h2><?php _e('Probar configuración', 'woo-factura-com'); ?></h2>
            
            <?php if ($demo_mode): ?>
                <div class="alert alert-success">
                    <h4><?php _e('✅ Modo Demo Configurado', 'woo-factura-com'); ?></h4>
                    <p><?php _e('Tu configuración está lista. En modo demo puedes:', 'woo-factura-com'); ?></p>
                    <ul>
                        <li><?php _e('Generar CFDIs simulados desde cualquier pedido', 'woo-factura-com'); ?></li>
                        <li><?php _e('Probar el campo RFC en el checkout', 'woo-factura-com'); ?></li>
                        <li><?php _e('Ver cómo funcionan los emails automáticos', 'woo-factura-com'); ?></li>
                    </ul>
                </div>
            <?php else: ?>
                <div id="connection-test-result"></div>
                
                <div class="card">
                    <h3><?php _e('Resumen de tu configuración', 'woo-factura-com'); ?></h3>
                    <ul>
                        <li><strong><?php _e('Entorno:', 'woo-factura-com'); ?></strong> 
                            <?php echo get_option('woo_factura_com_sandbox_mode') === 'yes' ? 'Sandbox' : 'Producción'; ?>
                        </li>
                        <li><strong><?php _e('API Key:', 'woo-factura-com'); ?></strong> 
                            <?php echo str_repeat('*', 20) . substr(get_option('woo_factura_com_api_key'), -4); ?>
                        </li>
                        <li><strong><?php _e('Serie ID:', 'woo-factura-com'); ?></strong> 
                            <?php echo get_option('woo_factura_com_serie_id'); ?>
                        </li>
                        <li><strong><?php _e('Uso CFDI:', 'woo-factura-com'); ?></strong> 
                            <?php echo get_option('woo_factura_com_uso_cfdi'); ?>
                        </li>
                        <li><strong><?php _e('Lugar Expedición:', 'woo-factura-com'); ?></strong> 
                            <?php echo get_option('woo_factura_com_lugar_expedicion'); ?>
                        </li>
                    </ul>
                </div>
            <?php endif; ?>
            
            <form method="post" action="<?php echo esc_url($this->get_step_url($this->current_step)); ?>">
                <?php wp_nonce_field('woo_factura_com_setup'); ?>
                <input type="hidden" name="test_config" value="1">
            </form>
            <?php
        }
        
        /**
         * Paso 6: Completado
         */
        public function step_complete() {
            // Marcar wizard como completado
            update_option('woo_factura_com_setup_completed', true);
            ?>
            <h2><?php _e('🎉 ¡Configuración completada!', 'woo-factura-com'); ?></h2>
            
            <div class="alert alert-success">
                <h4><?php _e('Tu plugin está listo para usar', 'woo-factura-com'); ?></h4>
                <p><?php _e('La integración con Factura.com ha sido configurada exitosamente.', 'woo-factura-com'); ?></p>
            </div>
            
            <div class="card">
                <h3><?php _e('Próximos pasos recomendados', 'woo-factura-com'); ?></h3>
                <ol>
                    <li><?php _e('Ve a WooCommerce → Pedidos y prueba generar un CFDI', 'woo-factura-com'); ?></li>
                    <li><?php _e('Haz una compra de prueba para verificar el campo RFC', 'woo-factura-com'); ?></li>
                    <li><?php _e('Revisa la configuración completa en WooCommerce → Factura.com', 'woo-factura-com'); ?></li>
                    <li><?php _e('Activa la generación automática si lo deseas', 'woo-factura-com'); ?></li>
                </ol>
            </div>
            
            <div class="card">
                <h3><?php _e('¿Necesitas ayuda?', 'woo-factura-com'); ?></h3>
                <ul>
                    <li><strong><?php _e('Documentación:', 'woo-factura-com'); ?></strong> Revisa el archivo README.md del plugin</li>
                    <li><strong><?php _e('Logs:', 'woo-factura-com'); ?></strong> Activa el modo debug en la configuración</li>
                    <li><strong><?php _e('Soporte Factura.com:', 'woo-factura-com'); ?></strong> soporte@factura.com</li>
                </ul>
            </div>
            <?php
        }
        
        // ============== HANDLERS ==============
        
        /**
         * NUEVO: Handler para el paso welcome
         */
        public function save_welcome() {
            if (!wp_verify_nonce($_POST['_wpnonce'], 'woo_factura_com_setup')) {
                wp_die(__('Error de seguridad', 'woo-factura-com'));
            }
            
            if (isset($_POST['continue'])) {
                error_log('WooFacturaCom Setup: Continuando desde welcome');
                $this->redirect_to_next_step();
            }
        }
        
        /**
         * Guardar configuración de entorno
         */
        public function save_environment() {
            if (!wp_verify_nonce($_POST['_wpnonce'], 'woo_factura_com_setup')) {
                wp_die(__('Error de seguridad', 'woo-factura-com'));
            }
            
            $environment = sanitize_text_field($_POST['environment']);
            
            switch ($environment) {
                case 'demo':
                    update_option('woo_factura_com_demo_mode', 'yes');
                    update_option('woo_factura_com_sandbox_mode', 'yes');
                    break;
                case 'sandbox':
                    update_option('woo_factura_com_demo_mode', 'no');
                    update_option('woo_factura_com_sandbox_mode', 'yes');
                    break;
                case 'production':
                    update_option('woo_factura_com_demo_mode', 'no');
                    update_option('woo_factura_com_sandbox_mode', 'no');
                    break;
            }
            
            $this->redirect_to_next_step();
        }
        
        /**
         * Guardar credenciales
         */
        public function save_credentials() {
            if (!wp_verify_nonce($_POST['_wpnonce'], 'woo_factura_com_setup')) {
                wp_die(__('Error de seguridad', 'woo-factura-com'));
            }
            
            $demo_mode = get_option('woo_factura_com_demo_mode', 'yes') === 'yes';
            
            if (!$demo_mode) {
                update_option('woo_factura_com_api_key', sanitize_text_field($_POST['api_key']));
                update_option('woo_factura_com_secret_key', sanitize_text_field($_POST['secret_key']));
                update_option('woo_factura_com_serie_id', sanitize_text_field($_POST['serie_id']));
            }
            
            $this->redirect_to_next_step();
        }
        
        /**
         * Guardar configuración fiscal
         */
        public function save_fiscal() {
            if (!wp_verify_nonce($_POST['_wpnonce'], 'woo_factura_com_setup')) {
                wp_die(__('Error de seguridad', 'woo-factura-com'));
            }
            
            update_option('woo_factura_com_uso_cfdi', sanitize_text_field($_POST['uso_cfdi']));
            update_option('woo_factura_com_forma_pago', sanitize_text_field($_POST['forma_pago']));
            update_option('woo_factura_com_metodo_pago', sanitize_text_field($_POST['metodo_pago']));
            update_option('woo_factura_com_lugar_expedicion', sanitize_text_field($_POST['lugar_expedicion']));
            
            $this->redirect_to_next_step();
        }
        
        /**
         * Probar configuración
         */
        public function test_configuration() {
            if (!wp_verify_nonce($_POST['_wpnonce'], 'woo_factura_com_setup')) {
                wp_die(__('Error de seguridad', 'woo-factura-com'));
            }
            
            $demo_mode = get_option('woo_factura_com_demo_mode', 'yes') === 'yes';
            
            if ($demo_mode || isset($_POST['test_config'])) {
                // En modo demo o si se presionó el botón de prueba, continuar
                $this->redirect_to_next_step();
                return;
            }
            
            // Probar conexión real
            if (class_exists('WooFacturaComRealAPIClient')) {
                $api_client = new WooFacturaComRealAPIClient();
                $result = $api_client->test_connection();
                
                if ($result['success']) {
                    // Conexión exitosa
                    update_option('woo_factura_com_connection_tested', current_time('mysql'));
                    $this->redirect_to_next_step();
                } else {
                    // Error de conexión - mostrar error y permanecer en el paso
                    add_action('admin_notices', function() use ($result) {
                        echo '<div class="notice notice-error"><p>Error de conexión: ' . esc_html($result['message']) . '</p></div>';
                    });
                }
            } else {
                $this->redirect_to_next_step();
            }
        }
    }
    
    // Inicializar wizard
    new WooFacturaComSetupWizard();
}