<?php
/**
 * API REST para integración con POS externos
 * Archivo: includes/class-pos-api.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class WooFacturaComPOSAPI {
    
    public function __construct() {
        add_action('rest_api_init', array($this, 'register_routes'));
    }
    
    /**
     * Registrar rutas de la API
     */
    public function register_routes() {
        register_rest_route('woo-factura-com/v1', '/cfdi/create', array(
            'methods' => 'POST',
            'callback' => array($this, 'create_cfdi_from_pos'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'order_data' => array(
                    'required' => true,
                    'type' => 'object'
                ),
                'customer_data' => array(
                    'required' => true,
                    'type' => 'object'
                )
            )
        ));
        
        register_rest_route('woo-factura-com/v1', '/cfdi/status/(?P<order_id>\d+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_cfdi_status'),
            'permission_callback' => array($this, 'check_permissions')
        ));
        
        register_rest_route('woo-factura-com/v1', '/cfdi/print/(?P<uuid>[a-fA-F0-9-]+)', array(
            'methods' => 'POST',
            'callback' => array($this, 'print_cfdi'),
            'permission_callback' => array($this, 'check_permissions')
        ));
    }
    
    /**
     * Crear CFDI desde POS externo
     */
    public function create_cfdi_from_pos($request) {
        $order_data = $request->get_param('order_data');
        $customer_data = $request->get_param('customer_data');
        
        try {
            // 1. Crear pedido en WooCommerce
            $order_id = $this->create_wc_order($order_data, $customer_data);
            
            if (!$order_id) {
                return new WP_Error('order_creation_failed', 'No se pudo crear el pedido', array('status' => 400));
            }
            
            // 2. Generar CFDI
            if (class_exists('WooFacturaComRealCFDIManager')) {
                $cfdi_manager = new WooFacturaComRealCFDIManager();
                $result = $cfdi_manager->generate_cfdi_for_order($order_id);
                
                if ($result['success']) {
                    return rest_ensure_response(array(
                        'success' => true,
                        'order_id' => $order_id,
                        'cfdi' => array(
                            'uuid' => $result['uuid'],
                            'pdf_url' => $result['pdf_url'],
                            'xml_url' => $result['xml_url'],
                            'serie' => $result['serie'],
                            'folio' => $result['folio']
                        ),
                        'message' => 'CFDI generado exitosamente'
                    ));
                } else {
                    return new WP_Error('cfdi_generation_failed', $result['error'], array('status' => 500));
                }
            } else {
                return new WP_Error('cfdi_manager_missing', 'Gestor de CFDI no disponible', array('status' => 500));
            }
            
        } catch (Exception $e) {
            return new WP_Error('server_error', 'Error interno: ' . $e->getMessage(), array('status' => 500));
        }
    }
    
    /**
     * Obtener estado del CFDI
     */
    public function get_cfdi_status($request) {
        $order_id = $request->get_param('order_id');
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return new WP_Error('order_not_found', 'Pedido no encontrado', array('status' => 404));
        }
        
        $uuid = $order->get_meta('_factura_com_cfdi_uuid');
        
        if ($uuid) {
            return rest_ensure_response(array(
                'has_cfdi' => true,
                'uuid' => $uuid,
                'pdf_url' => $order->get_meta('_factura_com_cfdi_pdf_url'),
                'xml_url' => $order->get_meta('_factura_com_cfdi_xml_url'),
                'serie' => $order->get_meta('_factura_com_cfdi_serie'),
                'folio' => $order->get_meta('_factura_com_cfdi_folio'),
                'generated_at' => $order->get_meta('_factura_com_cfdi_generated_at')
            ));
        } else {
            return rest_ensure_response(array(
                'has_cfdi' => false,
                'message' => 'Pedido sin CFDI'
            ));
        }
    }
    
    /**
     * Enviar CFDI a impresora
     */
    public function print_cfdi($request) {
        $uuid = $request->get_param('uuid');
        
        // Buscar pedido por UUID
        global $wpdb;
        $order_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_factura_com_cfdi_uuid' AND meta_value = %s",
            $uuid
        ));
        
        if (!$order_id) {
            return new WP_Error('cfdi_not_found', 'CFDI no encontrado', array('status' => 404));
        }
        
        $order = wc_get_order($order_id);
        $pdf_url = $order->get_meta('_factura_com_cfdi_pdf_url');
        
        // Aquí puedes integrar con tu sistema de impresión
        // Por ejemplo, enviar a una cola de impresión o API de impresora
        
        return rest_ensure_response(array(
            'success' => true,
            'uuid' => $uuid,
            'pdf_url' => $pdf_url,
            'message' => 'CFDI enviado a impresión'
        ));
    }
    
    /**
     * Crear pedido en WooCommerce desde datos del POS
     */
    private function create_wc_order($order_data, $customer_data) {
        $order = wc_create_order();
        
        // Datos del cliente
        $order->set_billing_first_name($customer_data['first_name'] ?? '');
        $order->set_billing_last_name($customer_data['last_name'] ?? '');
        $order->set_billing_email($customer_data['email'] ?? '');
        $order->set_billing_phone($customer_data['phone'] ?? '');
        
        // RFC si se proporciona
        if (!empty($customer_data['rfc'])) {
            $order->update_meta_data('_billing_rfc', strtoupper($customer_data['rfc']));
        }
        
        // Agregar productos
        foreach ($order_data['items'] as $item) {
            // Si el producto existe en WooCommerce, usarlo
            if (!empty($item['product_id']) && wc_get_product($item['product_id'])) {
                $product = wc_get_product($item['product_id']);
                $order->add_product($product, $item['quantity']);
            } else {
                // Crear producto simple sobre la marcha
                $product_id = $this->create_simple_product($item);
                if ($product_id) {
                    $product = wc_get_product($product_id);
                    $order->add_product($product, $item['quantity']);
                }
            }
        }
        
        // Calcular totales
        $order->calculate_totals();
        
        // Marcar como procesado desde POS
        $order->update_meta_data('_created_via_pos_api', 'yes');
        $order->update_meta_data('_pos_timestamp', current_time('mysql'));
        
        // Cambiar estado a completado (o el que prefieras)
        $order->update_status('completed', 'Pedido creado desde POS');
        
        $order->save();
        
        return $order->get_id();
    }
    
    /**
     * Crear producto simple dinámicamente
     */
    private function create_simple_product($item_data) {
        $product = new WC_Product_Simple();
        
        $product->set_name($item_data['name']);
        $product->set_regular_price($item_data['price']);
        $product->set_sku($item_data['sku'] ?? '');
        $product->set_manage_stock(false);
        $product->set_status('publish');
        $product->set_catalog_visibility('hidden'); // Oculto del catálogo
        
        // Metadatos para identificar productos creados desde POS
        $product->update_meta_data('_created_via_pos', 'yes');
        $product->update_meta_data('_pos_timestamp', current_time('mysql'));
        
        return $product->save();
    }
    
    /**
     * Verificar permisos
     */
    public function check_permissions() {
        // Verificar autenticación básica o token de API
        $headers = getallheaders();
        $auth_header = $headers['Authorization'] ?? '';
        
        if (empty($auth_header)) {
            return false;
        }
        
        // Verificar token simple (puedes usar JWT o algo más robusto)
        $expected_token = get_option('woo_factura_com_pos_api_token');
        $provided_token = str_replace('Bearer ', '', $auth_header);
        
        return hash_equals($expected_token, $provided_token);
    }
}

// Inicializar API
new WooFacturaComPOSAPI();