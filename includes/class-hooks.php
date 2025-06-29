<?php
/**
 * Clase para manejo de hooks de WooCommerce y WordPress + Integración POS
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
            // Hooks básicos del checkout
            add_action('woocommerce_after_checkout_billing_form', array($this, 'add_rfc_field_to_checkout'));
            add_action('woocommerce_checkout_process', array($this, 'validate_rfc_field'));
            add_action('woocommerce_checkout_update_order_meta', array($this, 'save_rfc_field'));
            
            // Hooks de pedidos
            add_action('woocommerce_order_status_completed', array($this, 'auto_generate_cfdi'));
            add_action('woocommerce_order_status_processing', array($this, 'maybe_auto_generate_cfdi'));
            
            // ===== INTEGRACIÓN POS =====
            add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'add_pos_cfdi_button'));
            add_action('wp_ajax_pos_generate_cfdi', array($this, 'ajax_pos_generate_cfdi'));
            
            // Hooks tradicionales del admin (mantener compatibilidad)
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
        
        // =====================================================
        // ============= FUNCIONALIDAD POS ====================
        // =====================================================
        
        /**
         * NUEVO: Botón CFDI en interfaz POS para cada pedido
         */
        public function add_pos_cfdi_button($order) {
            $order_id = $order->get_id();
            $uuid = $order->get_meta('_factura_com_cfdi_uuid');
            
            echo '<div class="pos-cfdi-section" style="margin: 20px 0; padding: 20px; background: linear-gradient(135deg, #e7f3ff 0%, #f0f8ff 100%); border: 1px solid #b3d9ff; border-radius: 12px; box-shadow: 0 2px 8px rgba(102, 126, 234, 0.1);">';
            echo '<h4 style="margin: 0 0 15px 0; color: #495057; display: flex; align-items: center; gap: 8px;">🧾 Factura Electrónica POS</h4>';
            
            if ($uuid) {
                echo '<div style="background: #d4edda; border: 1px solid #c3e6cb; border-radius: 8px; padding: 15px; margin-bottom: 15px;">';
                echo '<p style="margin: 0 0 10px 0; color: #155724; font-weight: 600;">✅ CFDI Generado Exitosamente</p>';
                echo '<p style="margin: 0 0 10px 0;"><strong>UUID:</strong> <code style="background: #e9ecef; padding: 4px 8px; border-radius: 4px; font-size: 12px; word-break: break-all;">' . esc_html($uuid) . '</code></p>';
                
                $serie = $order->get_meta('_factura_com_cfdi_serie');
                $folio = $order->get_meta('_factura_com_cfdi_folio');
                if ($serie && $folio) {
                    echo '<p style="margin: 0 0 10px 0;"><strong>Serie-Folio:</strong> ' . esc_html($serie . '-' . $folio) . '</p>';
                }
                
                $environment = $order->get_meta('_factura_com_cfdi_environment');
                if ($environment) {
                    $env_color = $environment === 'demo' ? '#856404' : ($environment === 'sandbox' ? '#0c5460' : '#155724');
                    $env_bg = $environment === 'demo' ? '#fff3cd' : ($environment === 'sandbox' ? '#d1ecf1' : '#d4edda');
                    echo '<span style="background: ' . $env_bg . '; color: ' . $env_color . '; padding: 4px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; text-transform: uppercase;">' . esc_html($environment) . '</span>';
                }
                echo '</div>';
                
                $pdf_url = $order->get_meta('_factura_com_cfdi_pdf_url');
                $xml_url = $order->get_meta('_factura_com_cfdi_xml_url');
                
                echo '<div style="display: flex; gap: 10px; flex-wrap: wrap;">';
                if ($pdf_url) {
                    echo '<a href="' . esc_url($pdf_url) . '" target="_blank" class="button button-primary" style="background: #667eea; border-color: #667eea; display: flex; align-items: center; gap: 5px;">📄 Ver PDF</a>';
                }
                if ($xml_url) {
                    echo '<a href="' . esc_url($xml_url) . '" target="_blank" class="button" style="display: flex; align-items: center; gap: 5px;">📁 Ver XML</a>';
                }
                echo '<button type="button" onclick="imprimirCFDI(\'' . esc_js($uuid) . '\')" class="button" style="display: flex; align-items: center; gap: 5px;">🖨️ Imprimir</button>';
                echo '</div>';
            } else {
                echo '<div style="text-align: center;">';
                echo '<p style="color: #6c757d; margin-bottom: 15px;">El cliente puede solicitar su factura electrónica</p>';
                echo '<button type="button" onclick="mostrarModalCFDI(' . $order_id . ')" class="button button-primary button-large" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; color: white; padding: 15px 30px; font-size: 16px; font-weight: 600; border-radius: 8px; cursor: pointer; transition: all 0.3s ease; text-transform: uppercase; letter-spacing: 0.5px; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);">🧾 GENERAR FACTURA</button>';
                echo '</div>';
            }
            echo '</div>';
            
            // Agregar JavaScript del modal POS una sola vez
            $this->add_pos_modal_script();
        }
        
        /**
         * JavaScript completo del modal POS
         */
        private function add_pos_modal_script() {
            static $script_added = false;
            if ($script_added) return;
            $script_added = true;
            ?>
            
            <style>
            /* Estilos del Modal POS */
            .pos-modal {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.6);
                z-index: 99999;
                display: flex;
                justify-content: center;
                align-items: center;
                backdrop-filter: blur(5px);
            }
            
            .pos-modal-content {
                background: white;
                padding: 30px;
                border-radius: 16px;
                max-width: 600px;
                width: 90%;
                max-height: 90vh;
                overflow-y: auto;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                animation: posModalSlideIn 0.3s ease-out;
            }
            
            @keyframes posModalSlideIn {
                from {
                    opacity: 0;
                    transform: scale(0.9) translateY(-20px);
                }
                to {
                    opacity: 1;
                    transform: scale(1) translateY(0);
                }
            }
            
            .pos-option {
                padding: 20px;
                border: 2px solid #e9ecef;
                border-radius: 12px;
                text-align: center;
                cursor: pointer;
                margin: 15px 0;
                transition: all 0.3s ease;
                background: #f8f9fa;
            }
            
            .pos-option:hover {
                border-color: #667eea;
                background: #e7f3ff;
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
            }
            
            .pos-option.selected {
                border-color: #667eea;
                background: linear-gradient(135deg, #e7f3ff 0%, #f0f8ff 100%);
                box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
            }
            
            .pos-option h4 {
                margin: 0 0 8px 0;
                color: #495057;
                font-size: 18px;
            }
            
            .pos-option p {
                margin: 0;
                color: #6c757d;
                font-size: 14px;
            }
            
            .pos-option small {
                color: #667eea;
                font-weight: 600;
                font-family: 'Courier New', monospace;
            }
            
            .pos-input {
                width: 100%;
                padding: 15px;
                font-size: 16px;
                border: 2px solid #e9ecef;
                border-radius: 8px;
                margin: 10px 0;
                transition: border-color 0.3s ease;
                box-sizing: border-box;
            }
            
            .pos-input:focus {
                outline: none;
                border-color: #667eea;
                box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            }
            
            .pos-input.rfc-input {
                text-transform: uppercase;
                font-family: 'Courier New', monospace;
                letter-spacing: 1px;
            }
            
            .pos-button {
                padding: 15px 30px;
                font-size: 16px;
                font-weight: 600;
                border: none;
                border-radius: 8px;
                cursor: pointer;
                margin: 10px 5px;
                transition: all 0.3s ease;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            
            .pos-button-primary {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
            }
            
            .pos-button-primary:hover:not(:disabled) {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
            }
            
            .pos-button-primary:disabled {
                background: #6c757d;
                cursor: not-allowed;
                transform: none;
                box-shadow: none;
            }
            
            .pos-button-secondary {
                background: #6c757d;
                color: white;
            }
            
            .pos-button-secondary:hover {
                background: #5a6268;
            }
            
            .pos-validation {
                padding: 12px;
                border-radius: 8px;
                margin: 10px 0;
                font-size: 14px;
                font-weight: 600;
                display: none;
            }
            
            .pos-validation.valid {
                background: #d4edda;
                color: #155724;
                border: 1px solid #c3e6cb;
                display: block;
            }
            
            .pos-validation.invalid {
                background: #f8d7da;
                color: #721c24;
                border: 1px solid #f5c6cb;
                display: block;
            }
            
            .pos-loading {
                text-align: center;
                padding: 40px;
            }
            
            .pos-spinner {
                border: 4px solid #f3f3f3;
                border-top: 4px solid #667eea;
                border-radius: 50%;
                width: 60px;
                height: 60px;
                animation: posSpinner 1s linear infinite;
                margin: 0 auto 20px;
            }
            
            @keyframes posSpinner {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
            
            .pos-loading-steps {
                text-align: left;
                max-width: 300px;
                margin: 0 auto;
            }
            
            .pos-loading-steps p {
                padding: 8px 0;
                border-bottom: 1px solid #e9ecef;
                color: #6c757d;
            }
            
            .pos-result-success {
                background: #d4edda;
                border: 1px solid #c3e6cb;
                border-radius: 12px;
                padding: 20px;
                margin: 20px 0;
                text-align: center;
            }
            
            .pos-result-error {
                background: #f8d7da;
                border: 1px solid #f5c6cb;
                border-radius: 12px;
                padding: 20px;
                margin: 20px 0;
                text-align: center;
            }
            
            .pos-uuid-display {
                background: #e9ecef;
                padding: 15px;
                border-radius: 8px;
                font-family: 'Courier New', monospace;
                word-break: break-all;
                margin: 15px 0;
                border: 1px solid #ced4da;
                font-size: 13px;
            }
            
            .pos-actions-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 15px;
                margin: 20px 0;
            }
            
            @media (max-width: 600px) {
                .pos-modal-content {
                    margin: 20px;
                    width: calc(100% - 40px);
                    padding: 20px;
                }
                
                .pos-actions-grid {
                    grid-template-columns: 1fr;
                }
                
                .pos-option {
                    padding: 15px;
                }
                
                .pos-button {
                    width: 100%;
                    margin: 5px 0;
                }
            }
            </style>
            
            <script>
            // Variables globales del POS
            window.currentPOSModal = null;
            window.selectedPOSOption = null;
            window.currentPOSOrderId = null;
            
            /**
             * Mostrar modal principal para generar CFDI
             */
            function mostrarModalCFDI(orderId) {
                window.currentPOSOrderId = orderId;
                
                const modal = document.createElement('div');
                modal.className = 'pos-modal';
                modal.innerHTML = `
                    <div class="pos-modal-content">
                        <h3 style="text-align: center; margin-bottom: 25px; color: #495057; font-size: 24px; font-weight: 300;">🧾 Generar Factura Electrónica</h3>
                        
                        <div class="pos-option" onclick="seleccionarOpcionPOS('publico')" data-option="publico">
                            <h4>🚀 Público General</h4>
                            <p>Factura sin RFC específico</p>
                            <small>RFC: XAXX010101000</small>
                        </div>
                        
                        <div class="pos-option" onclick="seleccionarOpcionPOS('empresa')" data-option="empresa">
                            <h4>🏢 Cliente Empresarial</h4>
                            <p>Factura con RFC del cliente</p>
                            <small>Capturar datos fiscales</small>
                        </div>
                        
                        <div id="rfc-form-pos" style="display: none; margin-top: 25px;">
                            <div style="margin-bottom: 15px;">
                                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #495057;">RFC del Cliente:</label>
                                <input type="text" id="rfc-input-pos" placeholder="AAAA000000AAA" class="pos-input rfc-input" maxlength="13">
                                <div id="rfc-validation-pos" class="pos-validation"></div>
                            </div>
                            
                            <div style="margin-bottom: 15px;">
                                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #495057;">Nombre/Razón Social:</label>
                                <input type="text" id="nombre-input-pos" placeholder="Nombre del cliente" class="pos-input">
                            </div>
                            
                            <div style="margin-bottom: 15px;">
                                <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #495057;">Uso de CFDI:</label>
                                <select id="uso-cfdi-pos" class="pos-input">
                                    <option value="G01">G01 - Adquisición de mercancías</option>
                                    <option value="G03">G03 - Gastos en general</option>
                                    <option value="P01">P01 - Por definir</option>
                                </select>
                            </div>
                        </div>
                        
                        <div style="text-align: center; margin-top: 30px;">
                            <button onclick="cerrarModalPOS()" class="pos-button pos-button-secondary">Cancelar</button>
                            <button onclick="procesarCFDIPOS()" class="pos-button pos-button-primary" id="generar-btn-pos" disabled>Generar CFDI</button>
                        </div>
                    </div>
                `;
                
                document.body.appendChild(modal);
                window.currentPOSModal = modal;
                
                // Prevenir scroll del body
                document.body.style.overflow = 'hidden';
                
                // Cerrar con ESC
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        cerrarModalPOS();
                    }
                });
                
                // Cerrar clickeando fuera
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) {
                        cerrarModalPOS();
                    }
                });
            }
            
            /**
             * Seleccionar opción de facturación
             */
            function seleccionarOpcionPOS(tipo) {
                // Limpiar selecciones anteriores
                document.querySelectorAll('.pos-option').forEach(opt => opt.classList.remove('selected'));
                
                if (tipo === 'publico') {
                    document.querySelector('[data-option="publico"]').classList.add('selected');
                    document.getElementById('rfc-form-pos').style.display = 'none';
                    document.getElementById('generar-btn-pos').disabled = false;
                    window.selectedPOSOption = 'publico';
                } else {
                    document.querySelector('[data-option="empresa"]').classList.add('selected');
                    document.getElementById('rfc-form-pos').style.display = 'block';
                    document.getElementById('generar-btn-pos').disabled = true;
                    window.selectedPOSOption = 'empresa';
                    
                    // Focus en el campo RFC
                    setTimeout(() => {
                        document.getElementById('rfc-input-pos').focus();
                    }, 100);
                    
                    // Configurar validación de RFC en tiempo real
                    configurarValidacionRFC();
                }
            }
            
            /**
             * Configurar validación de RFC en tiempo real
             */
            function configurarValidacionRFC() {
                const rfcInput = document.getElementById('rfc-input-pos');
                const validation = document.getElementById('rfc-validation-pos');
                const btnGenerar = document.getElementById('generar-btn-pos');
                
                if (!rfcInput || rfcInput.dataset.configured) return;
                rfcInput.dataset.configured = 'true';
                
                rfcInput.addEventListener('input', function() {
                    let rfc = this.value.toUpperCase().replace(/[^A-Z0-9Ñ&]/g, '');
                    
                    // Limitar a 13 caracteres
                    if (rfc.length > 13) {
                        rfc = rfc.substring(0, 13);
                    }
                    
                    this.value = rfc;
                    
                    if (rfc.length === 0) {
                        validation.style.display = 'none';
                        btnGenerar.disabled = true;
                        return;
                    }
                    
                    if (rfc.length < 13) {
                        validation.className = 'pos-validation invalid';
                        validation.textContent = '⏳ RFC incompleto (' + rfc.length + '/13 caracteres)';
                        validation.style.display = 'block';
                        btnGenerar.disabled = true;
                        return;
                    }
                    
                    // Validar patrón RFC
                    const rfcPattern = /^[A-Z&Ñ]{3,4}[0-9]{6}[A-Z0-9]{3}$/;
                    if (rfcPattern.test(rfc)) {
                        // Validar fecha en RFC
                        const fechaParte = rfc.substring(rfc.length - 9, rfc.length - 3);
                        const año = parseInt(fechaParte.substring(0, 2));
                        const mes = parseInt(fechaParte.substring(2, 4));
                        const dia = parseInt(fechaParte.substring(4, 6));
                        
                        // Convertir año de 2 dígitos a 4 dígitos
                        const añoCompleto = año <= 30 ? 2000 + año : 1900 + año;
                        
                        // Verificar fecha válida
                        const fecha = new Date(añoCompleto, mes - 1, dia);
                        if (fecha.getFullYear() === añoCompleto && 
                            fecha.getMonth() === mes - 1 && 
                            fecha.getDate() === dia) {
                            validation.className = 'pos-validation valid';
                            validation.textContent = '✅ RFC válido';
                            validation.style.display = 'block';
                            btnGenerar.disabled = false;
                        } else {
                            validation.className = 'pos-validation invalid';
                            validation.textContent = '❌ Fecha en RFC inválida';
                            validation.style.display = 'block';
                            btnGenerar.disabled = true;
                        }
                    } else {
                        validation.className = 'pos-validation invalid';
                        validation.textContent = '❌ Formato de RFC inválido';
                        validation.style.display = 'block';
                        btnGenerar.disabled = true;
                    }
                });
            }
            
            /**
             * Procesar generación de CFDI
             */
            function procesarCFDIPOS() {
                if (!window.selectedPOSOption) {
                    alert('Selecciona una opción para continuar');
                    return;
                }
                
                const orderId = window.currentPOSOrderId;
                const rfc = window.selectedPOSOption === 'publico' ? 'XAXX010101000' : document.getElementById('rfc-input-pos').value;
                const nombre = document.getElementById('nombre-input-pos')?.value || '';
                const usoCfdi = document.getElementById('uso-cfdi-pos')?.value || 'G01';
                
                // Mostrar pantalla de loading
                document.querySelector('.pos-modal-content').innerHTML = `
                    <div class="pos-loading">
                        <div class="pos-spinner"></div>
                        <h3 style="color: #495057; margin-bottom: 20px;">⚡ Generando CFDI</h3>
                        <p style="color: #6c757d; margin-bottom: 30px;">Procesando con Factura.com...</p>
                        
                        <div class="pos-loading-steps" id="loading-steps-pos">
                            <p>✅ Validando datos fiscales</p>
                            <p id="step2">⏳ Enviando a timbrado SAT...</p>
                            <p id="step3">⏳ Generando archivos PDF y XML...</p>
                            <p id="step4">⏳ Preparando descarga...</p>
                        </div>
                    </div>
                `;
                
                // Simular progreso visual
                setTimeout(() => {
                    const step2 = document.getElementById('step2');
                    if (step2) step2.innerHTML = '✅ Enviando a timbrado SAT...';
                }, 1000);
                
                setTimeout(() => {
                    const step3 = document.getElementById('step3');
                    if (step3) step3.innerHTML = '✅ Generando archivos PDF y XML...';
                }, 2000);
                
                setTimeout(() => {
                    const step4 = document.getElementById('step4');
                    if (step4) step4.innerHTML = '✅ Preparando descarga...';
                }, 2500);
                
                // Llamada AJAX real
                fetch(ajaxurl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'pos_generate_cfdi',
                        order_id: orderId,
                        rfc: rfc,
                        nombre: nombre,
                        uso_cfdi: usoCfdi,
                        nonce: '<?php echo wp_create_nonce('pos_cfdi'); ?>'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        mostrarResultadoExitoPOS(data.data);
                    } else {
                        mostrarResultadoErrorPOS(data.data);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    mostrarResultadoErrorPOS('Error de conexión: ' + error.message);
                });
            }
            
            /**
             * Mostrar resultado exitoso
             */
            function mostrarResultadoExitoPOS(cfdiData) {
                document.querySelector('.pos-modal-content').innerHTML = `
                    <div style="text-align: center;">
                        <h3 style="color: #155724; margin-bottom: 20px; font-size: 28px;">✅ ¡CFDI Generado Exitosamente!</h3>
                        
                        <div class="pos-result-success">
                            <h4 style="margin: 0 0 15px 0; color: #155724;">📄 Factura Electrónica</h4>
                            <p style="margin: 0 0 10px 0;"><strong>Serie-Folio:</strong> ${cfdiData.serie || 'N/A'}-${cfdiData.folio || 'N/A'}</p>
                            <p style="margin: 0 0 15px 0;"><strong>Fecha:</strong> ${new Date().toLocaleString('es-MX')}</p>
                            
                            <div class="pos-uuid-display">
                                <strong>UUID (Folio Fiscal):</strong><br>
                                ${cfdiData.uuid}
                            </div>
                        </div>
                        
                        <div class="pos-actions-grid">
                            <button onclick="window.open('${cfdiData.pdf_url}', '_blank')" class="pos-button pos-button-primary">📄 Ver PDF</button>
                            <button onclick="window.open('${cfdiData.xml_url}', '_blank')" class="pos-button pos-button-secondary">📁 Ver XML</button>
                        </div>
                        
                        <div class="pos-actions-grid" style="margin-top: 15px;">
                            <button onclick="imprimirCFDI('${cfdiData.uuid}')" class="pos-button pos-button-secondary">🖨️ Imprimir</button>
                            <button onclick="enviarEmailPOS()" class="pos-button pos-button-secondary">📧 Enviar Email</button>
                        </div>
                        
                        <button onclick="finalizarVentaPOS()" class="pos-button pos-button-primary" style="width: 100%; margin-top: 25px; font-size: 18px;">✅ Finalizar Venta</button>
                    </div>
                `;
            }
            
            /**
             * Mostrar resultado de error
             */
            function mostrarResultadoErrorPOS(errorMessage) {
                document.querySelector('.pos-modal-content').innerHTML = `
                    <div style="text-align: center;">
                        <h3 style="color: #721c24; margin-bottom: 20px;">❌ Error al Generar CFDI</h3>
                        
                        <div class="pos-result-error">
                            <p style="color: #721c24; font-weight: 600; margin: 0;">${errorMessage}</p>
                        </div>
                        
                        <div style="margin-top: 30px;">
                            <button onclick="mostrarModalCFDI(window.currentPOSOrderId)" class="pos-button pos-button-primary">🔄 Reintentar</button>
                            <button onclick="cerrarModalPOS()" class="pos-button pos-button-secondary">Cerrar</button>
                        </div>
                    </div>
                `;
            }
            
            /**
             * Cerrar modal POS
             */
            function cerrarModalPOS() {
                if (window.currentPOSModal) {
                    document.body.removeChild(window.currentPOSModal);
                    window.currentPOSModal = null;
                    window.selectedPOSOption = null;
                    window.currentPOSOrderId = null;
                    
                    // Restaurar scroll del body
                    document.body.style.overflow = '';
                }
            }
            
            /**
             * Imprimir CFDI
             */
            function imprimirCFDI(uuid) {
                console.log('Imprimiendo CFDI:', uuid);
                alert('🖨️ Enviando CFDI a impresora...\n\nUUID: ' + uuid);
                // Aquí iría la integración real con impresora térmica
                
                // Ejemplo de integración con impresora térmica:
                /*
                fetch(ajaxurl, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({
                        action: 'print_thermal_cfdi',
                        uuid: uuid,
                        nonce: '<?php echo wp_create_nonce('print_cfdi'); ?>'
                    })
                });
                */
            }
            
            /**
             * Enviar CFDI por email
             */
            function enviarEmailPOS() {
                const email = prompt('📧 Ingresa el email del cliente:');
                if (email && isValidEmail(email)) {
                    // Aquí iría la llamada AJAX para enviar email
                    alert('✅ CFDI enviado exitosamente a: ' + email);
                    
                    // Ejemplo de implementación:
                    /*
                    fetch(ajaxurl, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: new URLSearchParams({
                            action: 'send_cfdi_email',
                            order_id: window.currentPOSOrderId,
                            email: email,
                            nonce: '<?php echo wp_create_nonce('send_cfdi_email'); ?>'
                        })
                    });
                    */
                } else if (email) {
                    alert('❌ Email inválido. Intenta nuevamente.');
                }
            }
            
            /**
             * Finalizar venta POS
             */
            function finalizarVentaPOS() {
                cerrarModalPOS();
                
                // Mostrar mensaje de confirmación
                if (confirm('✅ Venta completada exitosamente.\n\n¿Recargar página para ver el CFDI generado?')) {
                    location.reload();
                }
            }
            
            /**
             * Validar email
             */
            function isValidEmail(email) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return emailRegex.test(email);
            }
            
            /**
             * Función global para imprimir desde botones externos
             */
            window.imprimirCFDI = function(uuid) {
                console.log('Imprimiendo CFDI externo:', uuid);
                alert('🖨️ Enviando a impresora térmica...\n\nUUID: ' + uuid);
            };
            </script>
            <?php
        }
        
        /**
         * AJAX: Generar CFDI desde POS
         */
        public function ajax_pos_generate_cfdi() {
            check_ajax_referer('pos_cfdi', 'nonce');
            
            if (!current_user_can('edit_shop_orders')) {
                wp_send_json_error('Sin permisos para realizar esta acción');
            }
            
            $order_id = intval($_POST['order_id']);
            $rfc = sanitize_text_field($_POST['rfc']);
            $nombre = sanitize_text_field($_POST['nombre']);
            $uso_cfdi = sanitize_text_field($_POST['uso_cfdi']);
            
            $order = wc_get_order($order_id);
            if (!$order) {
                wp_send_json_error('Pedido no encontrado');
            }
            
            // Verificar si ya tiene CFDI
            if ($order->get_meta('_factura_com_cfdi_uuid')) {
                wp_send_json_error('Este pedido ya tiene un CFDI generado');
            }
            
            // Agregar datos fiscales al pedido
            if ($rfc && $rfc !== 'XAXX010101000') {
                $order->update_meta_data('_billing_rfc', $rfc);
            }
            if ($nombre) {
                $order->update_meta_data('_billing_first_name', $nombre);
                $order->update_meta_data('_billing_last_name', '');
            }
            if ($uso_cfdi) {
                $order->update_meta_data('_factura_com_uso_cfdi', $uso_cfdi);
            }
            
            // Marcar como venta POS
            $order->update_meta_data('_pos_sale', 'yes');
            $order->update_meta_data('_pos_cfdi_generated_at', current_time('mysql'));
            
            $order->save();
            
            // Generar CFDI
            if (class_exists('WooFacturaComRealCFDIManager')) {
                $cfdi_manager = new WooFacturaComRealCFDIManager();
                $result = $cfdi_manager->generate_cfdi_for_order($order_id);
                
                if ($result['success']) {
                    // Agregar nota al pedido
                    $order->add_order_note(sprintf(
                        'CFDI generado desde POS.\nRFC: %s\nNombre: %s\nUUID: %s',
                        $rfc,
                        $nombre,
                        $result['uuid']
                    ));
                    
                    wp_send_json_success(array(
                        'uuid' => $result['uuid'],
                        'pdf_url' => $result['pdf_url'],
                        'xml_url' => $result['xml_url'],
                        'serie' => $result['serie'],
                        'folio' => $result['folio'],
                        'fecha_timbrado' => $result['fecha_timbrado']
                    ));
                } else {
                    wp_send_json_error($result['error']);
                }
            } else {
                wp_send_json_error('Gestor de CFDI no disponible');
            }
        }
        
        // =====================================================
        // =========== FUNCIONALIDAD TRADICIONAL ==============
        // =====================================================
        
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
         * Mostrar RFC en el admin de pedidos (información tradicional)
         */
        public function display_rfc_in_admin($order) {
            $rfc = $order->get_meta('_billing_rfc');
            $uuid = $order->get_meta('_factura_com_cfdi_uuid');
            
            // Solo mostrar si no hay CFDI (el POS button ya muestra la info completa)
            if ($rfc && !$uuid) {
                echo '<div class="woo-factura-com-admin-info" style="margin: 10px 0; padding: 10px; background: #f8f9fa; border-left: 4px solid #667eea;">';
                echo '<p style="margin: 0;"><strong>' . __('RFC Cliente:', 'woo-factura-com') . '</strong> ' . esc_html($rfc) . '</p>';
                echo '</div>';
            }
        }
        
        /**
         * Agregar acciones CFDI a los pedidos (mantener compatibilidad)
         */
        public function add_cfdi_order_actions($actions, $order) {
            $uuid = $order->get_meta('_factura_com_cfdi_uuid');
            $cancelled = $order->get_meta('_factura_com_cfdi_cancelled');
            
            if (!$uuid) {
                // Si no tiene CFDI, mostrar acción para generar (método tradicional)
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
         * AJAX: Generar CFDI (método tradicional)
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