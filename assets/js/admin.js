// admin.js - JavaScript para el panel de administración
jQuery(document).ready(function($) {
    
    // Confirmación para acciones CFDI
    $('.cfdi-action a').on('click', function(e) {
        const action = $(this).data('action') || this.href.match(/action=woo_factura_com_(\w+)/)?.[1];
        
        if (!action) return true;
        
        let message = '';
        switch(action) {
            case 'cancel_cfdi':
                message = '¿Estás seguro de cancelar este CFDI? Esta acción no se puede deshacer.';
                break;
            case 'regenerate_cfdi':
                message = '¿Regenerar el CFDI? El CFDI actual será cancelado y se generará uno nuevo.';
                break;
            case 'generate_cfdi':
                message = '¿Generar CFDI para este pedido?';
                break;
        }
        
        if (message && !confirm(message)) {
            e.preventDefault();
            return false;
        }
    });
    
    // Copiar UUID al clipboard
    $(document).on('click', '.copy-uuid', function(e) {
        e.preventDefault();
        
        const uuid = $(this).data('uuid');
        
        if (navigator.clipboard) {
            navigator.clipboard.writeText(uuid).then(function() {
                showNotice('UUID copiado al portapapeles', 'success');
            });
        } else {
            // Fallback para navegadores antiguos
            const textArea = document.createElement('textarea');
            textArea.value = uuid;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            showNotice('UUID copiado al portapapeles', 'success');
        }
    });
    
    // Probar conexión API
    $('#test-api-connection').on('click', function(e) {
        e.preventDefault();
        
        const $button = $(this);
        const originalText = $button.text();
        
        $button.text('Probando...').prop('disabled', true);
        
        $.post(ajaxurl, {
            action: 'woo_factura_com_test_connection',
            nonce: $button.data('nonce')
        })
        .done(function(response) {
            if (response.success) {
                showNotice('Conexión exitosa: ' + response.data.message, 'success');
            } else {
                showNotice('Error de conexión: ' + response.data.message, 'error');
            }
        })
        .fail(function() {
            showNotice('Error de conexión con el servidor', 'error');
        })
        .always(function() {
            $button.text(originalText).prop('disabled', false);
        });
    });
    
    // Validar RFC en tiempo real
    $('input[name$="_rfc"]').on('input', function() {
        const $input = $(this);
        const rfc = $input.val().toUpperCase();
        
        $input.val(rfc);
        
        if (rfc.length === 13) {
            validateRFC(rfc, $input);
        } else {
            $input.removeClass('valid invalid');
        }
    });
    
    // Función para validar RFC
    function validateRFC(rfc, $input) {
        $.post(ajaxurl, {
            action: 'validate_rfc',
            rfc: rfc,
            nonce: $('#rfc_nonce').val()
        })
        .done(function(response) {
            if (response.valid) {
                $input.addClass('valid').removeClass('invalid');
            } else {
                $input.addClass('invalid').removeClass('valid');
            }
        });
    }
    
    // Mostrar/ocultar campos según configuración
    $('input[name="woo_factura_com_demo_mode"]').on('change', function() {
        const isDemo = $(this).val() === 'yes' && $(this).is(':checked');
        
        $('.api-credentials-section').toggle(!isDemo);
        
        if (isDemo) {
            showNotice('Modo demo activado. No necesitas configurar credenciales.', 'info');
        }
    });
    
    // Exportar configuración
    $('#export-config').on('click', function(e) {
        e.preventDefault();
        
        $.post(ajaxurl, {
            action: 'woo_factura_com_export_config',
            nonce: $(this).data('nonce')
        })
        .done(function(response) {
            if (response.success) {
                downloadFile(response.data.content, 'factura-com-config.json', 'application/json');
            } else {
                showNotice('Error al exportar configuración', 'error');
            }
        });
    });
    
    // Importar configuración
    $('#import-config').on('change', function() {
        const file = this.files[0];
        if (!file) return;
        
        const reader = new FileReader();
        reader.onload = function(e) {
            try {
                const config = JSON.parse(e.target.result);
                
                if (confirm('¿Importar esta configuración? Sobrescribirá la configuración actual.')) {
                    $.post(ajaxurl, {
                        action: 'woo_factura_com_import_config',
                        config: JSON.stringify(config),
                        nonce: $('#import_nonce').val()
                    })
                    .done(function(response) {
                        if (response.success) {
                            showNotice('Configuración importada exitosamente', 'success');
                            location.reload();
                        } else {
                            showNotice('Error al importar: ' + response.data.message, 'error');
                        }
                    });
                }
            } catch (error) {
                showNotice('Archivo de configuración inválido', 'error');
            }
        };
        reader.readAsText(file);
    });
    
    // Limpiar logs
    $('#clear-logs').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm('¿Eliminar todos los logs? Esta acción no se puede deshacer.')) {
            return;
        }
        
        $.post(ajaxurl, {
            action: 'woo_factura_com_clear_logs',
            nonce: $(this).data('nonce')
        })
        .done(function(response) {
            if (response.success) {
                showNotice('Logs eliminados exitosamente', 'success');
                $('.logs-table tbody').empty();
            } else {
                showNotice('Error al eliminar logs', 'error');
            }
        });
    });
    
    // Función para mostrar notificaciones
    function showNotice(message, type = 'info') {
        const $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
        
        $('.wrap h1').after($notice);
        
        // Auto-remove después de 5 segundos
        setTimeout(function() {
            $notice.fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }
    
    // Función para descargar archivo
    function downloadFile(content, filename, contentType) {
        const blob = new Blob([content], { type: contentType });
        const url = window.URL.createObjectURL(blob);
        
        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        
        window.URL.revokeObjectURL(url);
    }
    
    // Actualizar estado de configuración en tiempo real
    $('input, select, textarea').on('change', function() {
        const $field = $(this);
        const value = $field.val();
        
        // Validaciones específicas
        if ($field.attr('name') === 'woo_factura_com_lugar_expedicion') {
            if (!/^\d{5}$/.test(value)) {
                $field.addClass('invalid');
                showNotice('El código postal debe tener 5 dígitos', 'warning');
            } else {
                $field.removeClass('invalid');
            }
        }
        
        if ($field.attr('name') === 'woo_factura_com_tasa_iva') {
            const rate = parseFloat(value);
            if (isNaN(rate) || rate < 0 || rate > 1) {
                $field.addClass('invalid');
                showNotice('La tasa de IVA debe ser un decimal entre 0 y 1', 'warning');
            } else {
                $field.removeClass('invalid');
            }
        }
    });
    
    // Tooltips para ayuda
    $('[data-tooltip]').each(function() {
        const $element = $(this);
        const tooltip = $element.data('tooltip');
        
        $element.attr('title', tooltip);
    });
    
    // Tabs en la configuración
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        
        const target = $(this).attr('href');
        
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        
        $('.tab-content').hide();
        $(target).show();
    });
    
    // Mostrar tab activo al cargar
    const activeTab = location.hash || '#general';
    $('.nav-tab[href="' + activeTab + '"]').click();
    
    // Verificar estado de la API cada 30 segundos si está en la página de configuración
    if ($('#api-status').length) {
        setInterval(function() {
            updateAPIStatus();
        }, 30000);
    }
    
    function updateAPIStatus() {
        $.post(ajaxurl, {
            action: 'woo_factura_com_api_status',
            nonce: $('#status_nonce').val()
        })
        .done(function(response) {
            if (response.success) {
                $('#api-status')
                    .removeClass('disconnected demo')
                    .addClass(response.data.status)
                    .text(response.data.message);
            }
        });
    }
});