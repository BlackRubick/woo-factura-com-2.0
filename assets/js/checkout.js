// checkout.js - JavaScript para funcionalidades del checkout
jQuery(document).ready(function($) {
    
    // Configuración global
    const rfcConfig = {
        field: 'input[name="billing_rfc"]',
        validateOnType: true,
        debounceTime: 500,
        showIndicator: true,
        validateServer: true
    };
    
    let rfcValidationTimeout;
    let lastValidatedRFC = '';
    
    // Inicializar validación de RFC
    function initRFCValidation() {
        const $rfcField = $(rfcConfig.field);
        
        if ($rfcField.length === 0) {
            return;
        }
        
        // Agregar contenedor para indicador si no existe
        if (!$rfcField.parent().hasClass('rfc-field-container')) {
            $rfcField.wrap('<div class="rfc-field-container"></div>');
        }
        
        // Agregar indicador visual
        if (rfcConfig.showIndicator && $rfcField.siblings('.rfc-validation-indicator').length === 0) {
            $rfcField.after('<span class="rfc-validation-indicator" style="display: none;"></span>');
        }
        
        // Agregar contenedor para mensajes
        if ($rfcField.siblings('.rfc-validation-message').length === 0) {
            $rfcField.after('<div class="rfc-validation-message"></div>');
        }
        
        // Event listeners
        $rfcField.on('input', handleRFCInput);
        $rfcField.on('blur', handleRFCBlur);
        $rfcField.on('focus', handleRFCFocus);
        
        // Validar RFC inicial si ya tiene valor
        if ($rfcField.val()) {
            validateRFC($rfcField.val(), true);
        }
    }
    
    // Manejar input de RFC
    function handleRFCInput(e) {
        const $field = $(this);
        let rfc = $field.val().toUpperCase().replace(/[^A-Z0-9Ñ&]/g, '');
        
        // Limitar a 13 caracteres
        if (rfc.length > 13) {
            rfc = rfc.substring(0, 13);
        }
        
        // Actualizar campo sin disparar evento
        if ($field.val() !== rfc) {
            $field.val(rfc);
        }
        
        // Limpiar timeout anterior
        clearTimeout(rfcValidationTimeout);
        
        // Limpiar validación anterior
        clearRFCValidation($field);
        
        if (rfc.length === 0) {
            return;
        }
        
        // Validación básica inmediata
        if (rfc.length < 13) {
            showRFCMessage($field, wooFacturaCom.messages.rfc_incomplete || 'RFC incompleto', 'loading');
            updateRFCIndicator($field, '⏳', 'loading');
            return;
        }
        
        // Validación completa con debounce
        if (rfcConfig.validateOnType) {
            rfcValidationTimeout = setTimeout(function() {
                validateRFC(rfc);
            }, rfcConfig.debounceTime);
        }
    }
    
    // Manejar blur de RFC
    function handleRFCBlur(e) {
        const $field = $(this);
        const rfc = $field.val().trim();
        
        if (rfc && rfc.length === 13) {
            validateRFC(rfc, true);
        }
    }
    
    // Manejar focus de RFC
    function handleRFCFocus(e) {
        const $field = $(this);
        // Mostrar mensaje de ayuda si el campo está vacío
        if (!$field.val()) {
            showRFCMessage($field, 'Formato: 4 letras + 6 números + 3 caracteres (13 total)', 'info');
        }
    }
    
    // Validar RFC
    function validateRFC(rfc, forceValidation = false) {
        const $field = $(rfcConfig.field);
        
        if (!rfc || rfc === lastValidatedRFC && !forceValidation) {
            return;
        }
        
        // Validación local primero
        const localValidation = validateRFCLocal(rfc);
        
        if (!localValidation.valid) {
            showRFCValidation($field, false, localValidation.message);
            return;
        }
        
        // Validación en servidor si está habilitada
        if (rfcConfig.validateServer && wooFacturaCom.ajax_url) {
            validateRFCServer(rfc, $field);
        } else {
            showRFCValidation($field, true, localValidation.message);
        }
        
        lastValidatedRFC = rfc;
    }
    
    // Validación local de RFC
    function validateRFCLocal(rfc) {
        // Verificar longitud
        if (rfc.length !== 13) {
            return {
                valid: false,
                message: 'RFC debe tener 13 caracteres'
            };
        }
        
        // Verificar patrón básico
        const pattern = /^[A-Z&Ñ]{3,4}[0-9]{6}[A-Z0-9]{3}$/;
        if (!pattern.test(rfc)) {
            return {
                valid: false,
                message: 'Formato de RFC inválido'
            };
        }
        
        // Validar fecha en RFC
        const fechaParte = rfc.substring(rfc.length - 9, rfc.length - 3);
        const año = parseInt(fechaParte.substring(0, 2));
        const mes = parseInt(fechaParte.substring(2, 4));
        const dia = parseInt(fechaParte.substring(4, 6));
        
        // Convertir año de 2 dígitos a 4 dígitos
        const añoCompleto = año <= 30 ? 2000 + año : 1900 + año;
        
        // Verificar fecha válida
        const fecha = new Date(añoCompleto, mes - 1, dia);
        if (fecha.getFullYear() !== añoCompleto || 
            fecha.getMonth() !== mes - 1 || 
            fecha.getDate() !== dia) {
            return {
                valid: false,
                message: 'Fecha en RFC inválida'
            };
        }
        
        return {
            valid: true,
            message: 'RFC válido'
        };
    }
    
    // Validación en servidor
    function validateRFCServer(rfc, $field) {
        updateRFCIndicator($field, '⏳', 'loading');
        showRFCMessage($field, wooFacturaCom.messages.validating || 'Validando...', 'loading');
        
        $.ajax({
            url: wooFacturaCom.ajax_url,
            type: 'POST',
            data: {
                action: 'validate_rfc',
                rfc: rfc,
                nonce: wooFacturaCom.validate_rfc_nonce
            },
            timeout: 10000,
            success: function(response) {
                if (response && typeof response.valid !== 'undefined') {
                    const message = response.valid ? 
                        (wooFacturaCom.messages.rfc_valid || 'RFC válido') :
                        (wooFacturaCom.messages.rfc_invalid || 'RFC inválido');
                    
                    showRFCValidation($field, response.valid, message);
                } else {
                    showRFCValidation($field, false, 'Error en validación');
                }
            },
            error: function(xhr, status, error) {
                console.warn('Error validando RFC:', error);
                // En caso de error del servidor, usar validación local
                const localValidation = validateRFCLocal(rfc);
                showRFCValidation($field, localValidation.valid, localValidation.message);
            }
        });
    }
    
    // Mostrar resultado de validación
    function showRFCValidation($field, isValid, message) {
        // Limpiar clases anteriores
        $field.removeClass('rfc-valid rfc-invalid rfc-loading');
        
        // Agregar clase según resultado
        if (isValid) {
            $field.addClass('rfc-valid');
            updateRFCIndicator($field, '✓', 'valid');
            showRFCMessage($field, message, 'valid');
        } else {
            $field.addClass('rfc-invalid');
            updateRFCIndicator($field, '✗', 'invalid');
            showRFCMessage($field, message, 'invalid');
        }
    }
    
    // Actualizar indicador visual
    function updateRFCIndicator($field, icon, type) {
        if (!rfcConfig.showIndicator) return;
        
        const $indicator = $field.siblings('.rfc-validation-indicator');
        
        if ($indicator.length) {
            $indicator
                .removeClass('valid invalid loading')
                .addClass(type)
                .text(icon)
                .show();
        }
    }
    
    // Mostrar mensaje de validación
    function showRFCMessage($field, message, type) {
        const $messageContainer = $field.siblings('.rfc-validation-message');
        
        if ($messageContainer.length) {
            $messageContainer
                .removeClass('valid invalid loading info')
                .addClass(type)
                .text(message)
                .show();
            
            // Auto-ocultar mensajes informativos después de 3 segundos
            if (type === 'info') {
                setTimeout(function() {
                    $messageContainer.fadeOut();
                }, 3000);
            }
        }
    }
    
    // Limpiar validación
    function clearRFCValidation($field) {
        $field.removeClass('rfc-valid rfc-invalid rfc-loading');
        $field.siblings('.rfc-validation-indicator').hide();
        $field.siblings('.rfc-validation-message').hide();
    }
    
    // Formatear RFC mientras se escribe
    function formatRFC(value) {
        // Remover caracteres no válidos
        let cleaned = value.toUpperCase().replace(/[^A-Z0-9Ñ&]/g, '');
        
        // Limitar longitud
        if (cleaned.length > 13) {
            cleaned = cleaned.substring(0, 13);
        }
        
        return cleaned;
    }
    
    // Integración con checkout de WooCommerce
    function integrateWithWooCommerceCheckout() {
        // Ejecutar validación antes del envío del formulario
        $('form.checkout').on('checkout_place_order', function() {
            const $rfcField = $(rfcConfig.field);
            const rfc = $rfcField.val();
            
            if (rfc && rfc.length === 13) {
                const validation = validateRFCLocal(rfc);
                if (!validation.valid) {
                    // Mostrar error y prevenir envío
                    showRFCValidation($rfcField, false, validation.message);
                    
                    // Scroll hacia el campo con error
                    $('html, body').animate({
                        scrollTop: $rfcField.offset().top - 100
                    }, 500);
                    
                    return false;
                }
            }
            
            return true;
        });
        
        // Limpiar validación cuando se actualiza el checkout
        $(document.body).on('update_checkout', function() {
            const $rfcField = $(rfcConfig.field);
            if ($rfcField.length) {
                clearRFCValidation($rfcField);
            }
        });
    }
    
    // Funciones de utilidad
    function showNotification(message, type = 'info') {
        // Crear notificación temporal
        const $notification = $('<div class="woo-factura-com-notification">')
            .addClass('notification-' + type)
            .text(message)
            .css({
                position: 'fixed',
                top: '20px',
                right: '20px',
                background: type === 'error' ? '#f8d7da' : '#d4edda',
                color: type === 'error' ? '#721c24' : '#155724',
                padding: '12px 20px',
                borderRadius: '4px',
                border: '1px solid ' + (type === 'error' ? '#f5c6cb' : '#c3e6cb'),
                zIndex: 99999,
                maxWidth: '300px',
                fontSize: '14px',
                boxShadow: '0 4px 12px rgba(0,0,0,0.15)'
            });
        
        $('body').append($notification);
        
        // Auto-remover después de 5 segundos
        setTimeout(function() {
            $notification.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    // Debug helpers
    function debugLog(message, data = null) {
        if (window.console && typeof wooFacturaCom !== 'undefined' && wooFacturaCom.debug) {
            console.log('[WooFacturaCom RFC]', message, data);
        }
    }
    
    // Helpers para accesibilidad
    function announceToScreenReader(message) {
        const $announcement = $('<div>')
            .attr('aria-live', 'polite')
            .attr('aria-atomic', 'true')
            .addClass('screen-reader-text')
            .text(message);
        
        $('body').append($announcement);
        
        setTimeout(function() {
            $announcement.remove();
        }, 1000);
    }
    
    // Inicialización principal
    function init() {
        debugLog('Inicializando validación de RFC en checkout');
        
        // Inicializar validación de RFC
        initRFCValidation();
        
        // Integrar con checkout de WooCommerce
        integrateWithWooCommerceCheckout();
        
        // Re-inicializar cuando se actualiza el checkout
        $(document.body).on('updated_checkout', function() {
            debugLog('Checkout actualizado, re-inicializando RFC');
            initRFCValidation();
        });
        
        debugLog('Validación de RFC inicializada correctamente');
    }
    
    // Ejecutar inicialización
    init();
    
    // Exponer funciones para uso externo si es necesario
    window.WooFacturaComCheckout = {
        validateRFC: validateRFC,
        formatRFC: formatRFC,
        showNotification: showNotification
    };
});