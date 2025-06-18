<?php
/**
 * Clase para manejo de hooks de WooCommerce y WordPress
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WooFacturaComHooks')) {
    
    class WooFacturaComHooks {
        
        public function __construct() {
            $this->init_hooks();
        }
        
        /**
         * Inicializar hooks
         */
        private function init_hooks() {
            // Hooks del checkout
            add_action('woocommerce_after_checkout_billing_form', array($this, 'add_rfc_field_to_checkout'));
            add_action('woocommerce_checkout_process', array($this, 'validate_rfc_field'));
            add_action('woocommerce_checkout_update_order_meta', array($this, 'save_rfc_field'));
            
            // Hooks de pedidos
            add_action('woocommerce_order_status_completed', array($this, 'auto_generate_cfdi'));
            add_action('woocommerce_order_status_processing', array($this, 'maybe_auto_generate_cfdi'));
            
            // Hooks del admin de pedidos
            add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'display_rfc_in_admin'));
            add_filter('woocommerce_admin_order_actions', array($this, 'add_cfdi_order_actions'), 10, 2);
            add_action('wp_ajax_woo_factura_com_generate_cfdi', array($this, 'ajax_generate_cfdi'));
            add_action('wp_ajax_woo_factura_com_cancel_cfdi', array($this, 'ajax_cancel_cfdi'));
            add_action('wp_ajax_woo_factura_com_regenerate_cfdi', array($this, 'ajax_regenerate_cfdi'));
            
            // Hooks de la cuenta del cliente
            add_filter('woocommerce_my_account_my_orders_columns', array($this, 'add_cfdi_column_to_my_orders'));
            add_action('woocommerce_my_account_my_orders_column_cfdi', array($this, 'display_cfdi_links_in_my_orders'));
            
            // Hooks de emails
            add_action('woocommerce_email_after_order_table', array($this, 'add_cfdi_links_to_email'), 10, 4);
            
            // Scripts y estilos
            add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
            
            // AJAX para validación de RFC
            add_action('wp_ajax_validate_rfc', array($this, 'ajax_validate_rfc'));
            add_action('wp_ajax_nopriv_validate_rfc', array($this, 'ajax_validate_rfc'));
        }
        
        /**
         * Agregar campo RFC al checkout
         */
        public function add_rfc_field_to_checkout($checkout) {
            if (get_option('woo_factura_com_add_rfc_field') !== 'yes') {
                return;
            }
            
            echo '<div id="woo-factura-com-rfc-field">';
            
            woocommerce_form_field('billing_rfc', array(
                'type' => 'text',
                'class' => array('form-row-wide'),
                'label' => __('RFC', 'woo-factura-com'),
                'placeholder' => __('RFC para facturación (opcional)', 'woo-factura-com'),
                'required' => get_option('woo_factura_com_require_rfc') === 'yes',
                'custom_attributes' => array(
                    'pattern' => '[A-Za-z&Ññ]{3,4}[0-9]{6}[A-Za-z0-9]{3}',
                    'title' => 'Formato: AAAA000000AAA (13 caracteres)',
                    'maxlength' => '13',
                    'style' => 'text-transform: uppercase;'
                )
            ), $checkout->get_value('billing_rfc'));
            
            echo '</div>';
            
            // Agregar nota explicativa
            echo '<div class="woo-factura-com-rfc-note">';
            echo '<small>' . __('Si necesitas factura, proporciona tu RFC. Podrás solicitarla después de completar tu compra.', 'woo-factura-com') . '</small>';
            echo '</div>';
        }
        
        /**
         * Validar campo RFC en el checkout
         */
        public function validate_rfc_field() {
            if (get_option('woo_factura_com_add_rfc_field') !== 'yes') {
                return;
            }
            
            $rfc = isset($_POST['billing_rfc']) ? sanitize_text_field($_POST['billing_rfc']) : '';
            
            // Si es requerido y está vacío
            if (get_option('woo_factura_com_require_rfc') === 'yes' && empty($rfc)) {
                wc_add_notice(__('El RFC es requerido para continuar.', 'woo-factura-com'), 'error');
                return;
            }
            
            // Si se proporcionó RFC, validarlo
            if (!empty($rfc)) {
                if (!WooFacturaComUtilities::validate_rfc_complete($rfc)) {
                    wc_add_notice(__('El RFC proporcionado no tiene un formato válido.', 'woo-factura-com'), 'error');
                }
            }
        }
        
        /**
         * Guardar campo RFC en el pedido
         */
        public function save_rfc_field($order_id) {
            if (isset($_POST['billing_rfc']) && !empty($_POST['billing_rfc'])) {
                $rfc = strtoupper(sanitize_text_field($_POST['billing_rfc']));
                update_post_meta($order_id, '_billing_rfc', $rfc);
            }
        }
        
        /**
         * Generar CFDI automáticamente cuando el pedido se completa
         */
        public function auto_generate_cfdi($order_id) {
            if (get_option('woo_factura_com_auto_generate') !== 'yes') {
                return;
            }
            
            $order = wc_get_order($order_id);
            if (!$order) {
                return;
            }
            
            // Solo generar si tiene RFC
            $rfc = $order->get_meta('_billing_rfc');
            if (empty($rfc)) {
                return;
            }
            
            // No generar si ya tiene CFDI
            if ($order->get_meta('_factura_com_cfdi_uuid')) {
                return;
            }
            
            $this->generate_cfdi_for_order($order_id);
        }
        
        /**
         * Tal vez generar CFDI en estado processing (según configuración)
         */
        public function maybe_auto_generate_cfdi($order_id) {
            $auto_on_processing = get_option('woo_factura_com_auto_generate_on_processing', 'no');
            
            if ($auto_on_processing === 'yes') {
                $this->auto_generate_cfdi($order_id);
            }
        }
        
        /**
         * Mostrar RFC en el admin de pedidos
         */
        public function display_rfc_in_admin($order) {
            $rfc = $order->get_meta('_billing_rfc');
            $uuid = $order->get_meta('_factura_com_cfdi_uuid');
            
            if ($rfc || $uuid) {
                echo '<div class="woo-factura-com-admin-info">';
                
                if ($rfc) {
                    echo '<p><strong>' . __('RFC:', 'woo-factura-com') . '</strong> ' . esc_html($rfc) . '</p>';
                }
                
                if ($uuid) {
                    echo '<div class="cfdi-info">';
                    echo '<h4>' . __('Información del CFDI', 'woo-factura-com') . '</h4>';
                    echo '<p><strong>' . __('UUID:', 'woo-factura-com') . '</strong> <code>' . esc_html($uuid) . '</code></p>';
                    
                    $serie = $order->get_meta('_factura_com_cfdi_serie');
                    $folio = $order->get_meta('_factura_com_cfdi_folio');
                    if ($serie && $folio) {
                        echo '<p><strong>' . __('Serie-Folio:', 'woo-factura-com') . '</strong> ' . esc_html($serie . '-' . $folio) . '</p>';
                    }
                    
                    $generated_at = $order->get_meta('_factura_com_cfdi_generated_at');
                    if ($generated_at) {
                        echo '<p><strong>' . __('Generado:', 'woo-factura-com') . '</strong> ' . esc_html($generated_at) . '</p>';
                    }
                    
                    $environment = $order->get_meta('_factura_com_cfdi_environment');
                    if ($environment) {
                        $env_label = $environment === 'demo' ? 'Demo' : ($environment === 'sandbox' ? 'Sandbox' : 'Producción');
                        echo '<p><strong>' . __('Entorno:', 'woo-factura-com') . '</strong> ' . esc_html($env_label) . '</p>';
                    }
                    
                    // Enlaces de descarga
                    $pdf_url = $order->get_meta('_factura_com_cfdi_pdf_url');
                    $xml_url = $order->get_meta('_factura_com_cfdi_xml_url');
                    
                    if ($pdf_url || $xml_url) {
                        echo '<p><strong>' . __('Descargar:', 'woo-factura-com') . '</strong> ';
                        if ($pdf_url) {
                            echo '<a href="' . esc_url($pdf_url) . '" target="_blank" class="button button-small">PDF</a> ';
                        }
                        if ($xml_url) {
                            echo '<a href="' . esc_url($xml_url) . '" target="_blank" class="button button-small">XML</a>';
                        }
                        echo '</p>';
                    }
                    
                    // Verificar si está cancelado
                    $cancelled_at = $order->get_meta('_factura_com_cfdi_cancelled');
                    if ($cancelled_at) {
                        echo '<p style="color: #dc3232;"><strong>' . __('CFDI Cancelado:', 'woo-factura-com') . '</strong> ' . esc_html($cancelled_at) . '</p>';
                    }
                    
                    echo '</div>';
                }
                
                echo '</div>';
            }
        }
        
        /**
         * Agregar acciones CFDI a los pedidos
         */
        public function add_cfdi_order_actions($actions, $order) {
            $uuid = $order->get_meta('_factura_com_cfdi_uuid');
            $cancelled = $order->get_meta('_factura_com_cfdi_cancelled');
            
            if (!$uuid) {
                // Si no tiene CFDI, mostrar acción para generar
                $actions['generate_cfdi'] = array(
                    'url' => wp_nonce_url(admin_url('admin-ajax.php?action=woo_factura_com_generate_cfdi&order_id=' . $order->get_id()), 'generate_cfdi'),
                    'name' => __('Generar CFDI', 'woo-factura-com'),
                    'action' => 'generate_cfdi'
                );
            } else {
                if (!$cancelled) {
                    // Si tiene CFDI y no está cancelado
                    $actions['cancel_cfdi'] = array(
                        'url' => wp_nonce_url(admin_url('admin-ajax.php?action=woo_factura_com_cancel_cfdi&order_id=' . $order->get_id()), 'cancel_cfdi'),
                        'name' => __('Cancelar CFDI', 'woo-factura-com'),
                        'action' => 'cancel_cfdi'
                    );
                }
                
                $actions['regenerate_cfdi'] = array(
                    'url' => wp_nonce_url(admin_url('admin-ajax.php?action=woo_factura_com_regenerate_cfdi&order_id=' . $order->get_id()), 'regenerate_cfdi'),
                    'name' => __('Regenerar CFDI', 'woo-factura-com'),
                    'action' => 'regenerate_cfdi'
                );
            }
            
            return $actions;
        }
        
        /**
         * AJAX: Generar CFDI
         */
        public function ajax_generate_cfdi() {
            check_ajax_referer('generate_cfdi');
            
            if (!current_user_can('edit_shop_orders')) {
                wp_die(__('No tienes permisos para realizar esta acción.', 'woo-factura-com'));
            }
            
            $order_id = intval($_GET['order_id']);
            $result = $this->generate_cfdi_for_order($order_id);
            
            if ($result['success']) {
                wp_redirect(admin_url('post.php?post=' . $order_id . '&action=edit&cfdi_generated=1'));
            } else {
                wp_redirect(admin_url('post.php?post=' . $order_id . '&action=edit&cfdi_error=' . urlencode($result['error'])));
            }
            exit;
        }
        
        /**
         * AJAX: Cancelar CFDI
         */
        public function ajax_cancel_cfdi() {
            check_ajax_referer('cancel_cfdi');
            
            if (!current_user_can('edit_shop_orders')) {
                wp_die(__('No tienes permisos para realizar esta acción.', 'woo-factura-com'));
            }
            
            $order_id = intval($_GET['order_id']);
            
            if (class_exists('WooFacturaComRealCFDIManager')) {
                $cfdi_manager = new WooFacturaComRealCFDIManager();
                $result = $cfdi_manager->cancel_cfdi($order_id, '02');
                
                if ($result['success']) {
                    wp_redirect(admin_url('post.php?post=' . $order_id . '&action=edit&cfdi_cancelled=1'));
                } else {
                    wp_redirect(admin_url('post.php?post=' . $order_id . '&action=edit&cfdi_error=' . urlencode($result['error'])));
                }
            } else {
                wp_redirect(admin_url('post.php?post=' . $order_id . '&action=edit&cfdi_error=' . urlencode('CFDI Manager no disponible')));
            }
            exit;
        }
        
        /**
         * AJAX: Regenerar CFDI
         */
        public function ajax_regenerate_cfdi() {
            check_ajax_referer('regenerate_cfdi');
            
            if (!current_user_can('edit_shop_orders')) {
                wp_die(__('No tienes permisos para realizar esta acción.', 'woo-factura-com'));
            }
            
            $order_id = intval($_GET['order_id']);
            
            if (class_exists('WooFacturaComRealCFDIManager')) {
                $cfdi_manager = new WooFacturaComRealCFDIManager();
                $result = $cfdi_manager->regenerate_cfdi($order_id);
                
                if ($result['success']) {
                    wp_redirect(admin_url('post.php?post=' . $order_id . '&action=edit&cfdi_regenerated=1'));
                } else {
                    wp_redirect(admin_url('post.php?post=' . $order_id . '&action=edit&cfdi_error=' . urlencode($result['error'])));
                }
            } else {
                wp_redirect(admin_url('post.php?post=' . $order_id . '&action=edit&cfdi_error=' . urlencode('CFDI Manager no disponible')));
            }
            exit;
        }
        
        /**
         * Generar CFDI para un pedido
         */
        private function generate_cfdi_for_order($order_id) {
            if (class_exists('WooFacturaComRealCFDIManager')) {
                $cfdi_manager = new WooFacturaComRealCFDIManager();
                return $cfdi_manager->generate_cfdi_for_order($order_id);
            }
            
            return array('success' => false, 'error' => 'CFDI Manager no disponible');
        }
        
        /**
         * Agregar columna CFDI a mis pedidos
         */
        public function add_cfdi_column_to_my_orders($columns) {
            $new_columns = array();
            
            foreach ($columns as $key => $name) {
                $new_columns[$key] = $name;
                
                // Agregar después de la columna de estado
                if ($key === 'order-status') {
                    $new_columns['cfdi'] = __('CFDI', 'woo-factura-com');
                }
            }
            
            return $new_columns;
        }
        
        /**
         * Mostrar enlaces CFDI en mis pedidos
         */
        public function display_cfdi_links_in_my_orders($order) {
            $uuid = $order->get_meta('_factura_com_cfdi_uuid');
            
            if ($uuid) {
                $pdf_url = $order->get_meta('_factura_com_cfdi_pdf_url');
                $xml_url = $order->get_meta('_factura_com_cfdi_xml_url');
                
                echo '<div class="cfdi-links">';
                
                if ($pdf_url) {
                    echo '<a href="' . esc_url($pdf_url) . '" target="_blank" class="button btn-cfdi-pdf">📄 PDF</a>';
                }
                
                if ($xml_url) {
                    echo '<a href="' . esc_url($xml_url) . '" target="_blank" class="button btn-cfdi-xml">📁 XML</a>';
                }
                
                // Mostrar UUID abreviado
                echo '<small title="' . esc_attr($uuid) . '">UUID: ' . substr($uuid, 0, 8) . '...</small>';
                
                echo '</div>';
            } else {
                $rfc = $order->get_meta('_billing_rfc');
                if ($rfc) {
                    echo '<small>' . __('RFC registrado', 'woo-factura-com') . '</small><br>';
                    echo '<a href="' . esc_url(wc_get_account_endpoint_url('orders')) . '" class="button btn-request-cfdi">' . __('Solicitar', 'woo-factura-com') . '</a>';
                } else {
                    echo '<small>' . __('No disponible', 'woo-factura-com') . '</small>';
                }
            }
        }
        
        /**
         * Agregar enlaces CFDI a emails
         */
        public function add_cfdi_links_to_email($order, $sent_to_admin, $plain_text, $email) {
            // Solo en emails al cliente y si el pedido está completado
            if ($sent_to_admin || $plain_text || $order->get_status() !== 'completed') {
                return;
            }
            
            $uuid = $order->get_meta('_factura_com_cfdi_uuid');
            if (!$uuid) {
                return;
            }
            
            $pdf_url = $order->get_meta('_factura_com_cfdi_pdf_url');
            $xml_url = $order->get_meta('_factura_com_cfdi_xml_url');
            
            if ($pdf_url || $xml_url) {
                echo '<div class="cfdi-email-section" style="margin: 20px 0; padding: 15px; background: #f8f9fa; border-left: 4px solid #667eea;">';
                echo '<h3 style="margin-top: 0; color: #495057;">🧾 ' . __('Tu Factura Electrónica (CFDI)', 'woo-factura-com') . '</h3>';
                echo '<p>' . __('Tu CFDI ha sido generado exitosamente:', 'woo-factura-com') . '</p>';
                echo '<p><strong>UUID:</strong> <code>' . esc_html($uuid) . '</code></p>';
                
                if ($pdf_url) {
                    echo '<a href="' . esc_url($pdf_url) . '" style="display: inline-block; padding: 8px 16px; background: #667eea; color: white; text-decoration: none; border-radius: 4px; margin-right: 10px;">📄 Descargar PDF</a>';
                }
                
                if ($xml_url) {
                    echo '<a href="' . esc_url($xml_url) . '" style="display: inline-block; padding: 8px 16px; background: #6c757d; color: white; text-decoration: none; border-radius: 4px;">📁 Descargar XML</a>';
                }
                
                echo '</div>';
            }
        }
        
        /**
         * Cargar scripts del frontend
         */
        public function enqueue_frontend_scripts() {
            if (is_checkout() && get_option('woo_factura_com_add_rfc_field') === 'yes') {
                wp_enqueue_script(
                    'woo-factura-com-checkout',
                    WOO_FACTURA_COM_PLUGIN_URL . 'assets/js/checkout.js',
                    array('jquery'),
                    WOO_FACTURA_COM_VERSION,
                    true
                );
                
                wp_localize_script('woo-factura-com-checkout', 'wooFacturaCom', array(
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'validate_rfc_nonce' => wp_create_nonce('validate_rfc'),
                    'messages' => array(
                        'rfc_invalid' => __('RFC no válido', 'woo-factura-com'),
                        'rfc_valid' => __('RFC válido', 'woo-factura-com'),
                        'validating' => __('Validando...', 'woo-factura-com')
                    )
                ));
                
                wp_enqueue_style(
                    'woo-factura-com-checkout',
                    WOO_FACTURA_COM_PLUGIN_URL . 'assets/css/frontend.css',
                    array(),
                    WOO_FACTURA_COM_VERSION
                );
            }
        }
        
        /**
         * Cargar scripts del admin
         */
        public function enqueue_admin_scripts($hook) {
            $screen = get_current_screen();
            
            // Solo en páginas del plugin o pedidos
            if (strpos($hook, 'woo-factura-com') !== false || 
                ($screen && in_array($screen->id, array('shop_order', 'woocommerce_page_wc-orders')))) {
                
                wp_enqueue_script(
                    'woo-factura-com-admin',
                    WOO_FACTURA_COM_PLUGIN_URL . 'assets/js/admin.js',
                    array('jquery'),
                    WOO_FACTURA_COM_VERSION,
                    true
                );
                
                wp_enqueue_style(
                    'woo-factura-com-admin',
                    WOO_FACTURA_COM_PLUGIN_URL . 'assets/css/admin.css',
                    array(),
                    WOO_FACTURA_COM_VERSION
                );
            }
        }
        
        /**
         * AJAX: Validar RFC
         */
        public function ajax_validate_rfc() {
            check_ajax_referer('validate_rfc', 'nonce');
            
            $rfc = strtoupper(sanitize_text_field($_POST['rfc']));
            
            $response = array(
                'valid' => false,
                'message' => ''
            );
            
            if (empty($rfc)) {
                $response['message'] = __('RFC vacío', 'woo-factura-com');
            } elseif (WooFacturaComUtilities::validate_rfc_complete($rfc)) {
                $response['valid'] = true;
                $response['message'] = __('RFC válido', 'woo-factura-com');
            } else {
                $response['message'] = __('RFC no válido', 'woo-factura-com');
            }
            
            wp_send_json($response);
        }
    }
}