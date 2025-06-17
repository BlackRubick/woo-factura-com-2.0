<?php
/**
 * Clase de utilidades para WooCommerce Factura.com
 * Funciones auxiliares y herramientas útiles
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('WooFacturaComUtilities')) {
    
    class WooFacturaComUtilities {
        
        /**
         * Validar RFC mexicano con algoritmo completo
         */
        public static function validate_rfc_complete($rfc) {
            $rfc = strtoupper(trim($rfc));
            
            // Verificar longitud
            if (strlen($rfc) !== 13) {
                return false;
            }
            
            // Patrón básico
            $pattern = '/^[A-Z&Ñ]{3,4}[0-9]{6}[A-Z0-9]{3}$/';
            if (!preg_match($pattern, $rfc)) {
                return false;
            }
            
            // Verificar fecha válida
            $fecha_parte = substr($rfc, -9, 6);
            $año = substr($fecha_parte, 0, 2);
            $mes = substr($fecha_parte, 2, 2);
            $dia = substr($fecha_parte, 4, 2);
            
            // Convertir año de 2 dígitos a 4 dígitos
            $año_completo = ($año <= 30) ? 2000 + $año : 1900 + $año;
            
            if (!checkdate($mes, $dia, $año_completo)) {
                return false;
            }
            
            return true;
        }
        
        /**
         * Generar RFC genérico para clientes sin RFC
         */
        public static function get_generic_rfc() {
            return 'XAXX010101000';
        }
        
        /**
         * Formatear moneda para CFDI (6 decimales)
         */
        public static function format_currency_for_cfdi($amount) {
            return number_format(floatval($amount), 6, '.', '');
        }
        
        /**
         * Limpiar descripción para CFDI (remover caracteres especiales)
         */
        public static function clean_description_for_cfdi($description) {
            // Remover HTML
            $description = strip_tags($description);
            
            // Remover caracteres especiales
            $description = preg_replace('/[^\p{L}\p{N}\s\-\.\,]/u', '', $description);
            
            // Trim y colapsar espacios múltiples
            $description = preg_replace('/\s+/', ' ', trim($description));
            
            // Limitar longitud
            if (strlen($description) > 1000) {
                $description = substr($description, 0, 997) . '...';
            }
            
            return $description;
        }
        
        /**
         * Obtener códigos de uso de CFDI
         */
        public static function get_uso_cfdi_options() {
            return array(
                'G01' => 'G01 - Adquisición de mercancías',
                'G02' => 'G02 - Devoluciones, descuentos o bonificaciones',
                'G03' => 'G03 - Gastos en general',
                'I01' => 'I01 - Construcciones',
                'I02' => 'I02 - Mobilario y equipo de oficina por inversiones',
                'I03' => 'I03 - Equipo de transporte',
                'I04' => 'I04 - Equipo de computo y accesorios',
                'I05' => 'I05 - Dados, troqueles, moldes, matrices y herramental',
                'I06' => 'I06 - Comunicaciones telefónicas',
                'I07' => 'I07 - Comunicaciones satelitales',
                'I08' => 'I08 - Otra maquinaria y equipo',
                'D01' => 'D01 - Honorarios médicos, dentales y gastos hospitalarios',
                'D02' => 'D02 - Gastos médicos por incapacidad o discapacidad',
                'D03' => 'D03 - Gastos funerales',
                'D04' => 'D04 - Donativos',
                'D05' => 'D05 - Intereses reales efectivamente pagados por créditos hipotecarios',
                'D06' => 'D06 - Aportaciones voluntarias al SAR',
                'D07' => 'D07 - Primas por seguros de gastos médicos',
                'D08' => 'D08 - Gastos de transportación escolar obligatoria',
                'D09' => 'D09 - Depósitos en cuentas para el ahorro, primas que tengan como base planes de pensiones',
                'D10' => 'D10 - Pagos por servicios educativos',
                'P01' => 'P01 - Por definir',
                'S01' => 'S01 - Sin efectos fiscales',
                'CP01' => 'CP01 - Pagos',
                'CN01' => 'CN01 - Nómina'
            );
        }
        
        /**
         * Obtener códigos de forma de pago
         */
        public static function get_forma_pago_options() {
            return array(
                '01' => '01 - Efectivo',
                '02' => '02 - Cheque nominativo',
                '03' => '03 - Transferencia electrónica de fondos',
                '04' => '04 - Tarjeta de crédito',
                '05' => '05 - Monedero electrónico',
                '06' => '06 - Dinero electrónico',
                '08' => '08 - Vales de despensa',
                '12' => '12 - Dación en pago',
                '13' => '13 - Pago por subrogación',
                '14' => '14 - Pago por consignación',
                '15' => '15 - Condonación',
                '17' => '17 - Compensación',
                '23' => '23 - Novación',
                '24' => '24 - Confusión',
                '25' => '25 - Remisión de deuda',
                '26' => '26 - Prescripción o caducidad',
                '27' => '27 - A satisfacción del acreedor',
                '28' => '28 - Tarjeta de débito',
                '29' => '29 - Tarjeta de servicios',
                '30' => '30 - Aplicación de anticipos',
                '31' => '31 - Intermediario pagos',
                '99' => '99 - Por definir'
            );
        }
        
        /**
         * Obtener códigos de método de pago
         */
        public static function get_metodo_pago_options() {
            return array(
                'PUE' => 'PUE - Pago en una sola exhibición',
                'PPD' => 'PPD - Pago en parcialidades o diferido'
            );
        }
        
        /**
         * Obtener códigos de unidades de medida más comunes
         */
        public static function get_unidad_medida_options() {
            return array(
                'E48' => 'E48 - Unidad de servicio',
                'H87' => 'H87 - Pieza',
                'KGM' => 'KGM - Kilogramo',
                'GRM' => 'GRM - Gramo',
                'MTR' => 'MTR - Metro',
                'CMT' => 'CMT - Centímetro',
                'MTK' => 'MTK - Metro cuadrado',
                'MTQ' => 'MTQ - Metro cúbico',
                'LTR' => 'LTR - Litro',
                'MLT' => 'MLT - Mililitro',
                'XBX' => 'XBX - Caja',
                'XPK' => 'XPK - Paquete',
                'XBG' => 'XBG - Bolsa',
                'XPL' => 'XPL - Pallet (tarima)',
                'SET' => 'SET - Juego',
                'PR' => 'PR - Par',
                'DZN' => 'DZN - Docena',
                'HUR' => 'HUR - Hora',
                'DAY' => 'DAY - Día',
                'WEE' => 'WEE - Semana',
                'MON' => 'MON - Mes',
                'ANN' => 'ANN - Año'
            );
        }
        
        /**
         * Obtener códigos de objeto de impuesto
         */
        public static function get_objeto_impuesto_options() {
            return array(
                '01' => '01 - No objeto de impuesto',
                '02' => '02 - Sí objeto de impuesto',
                '03' => '03 - Sí objeto de impuesto y no obligado al desglose',
                '04' => '04 - Sí objeto de impuesto y no causa impuesto'
            );
        }
        
        /**
         * Obtener motivos de cancelación
         */
        public static function get_motivos_cancelacion() {
            return array(
                '01' => '01 - Comprobante emitido con errores con relación',
                '02' => '02 - Comprobante emitido con errores sin relación',
                '03' => '03 - No se llevó a cabo la operación',
                '04' => '04 - Operación nominativa relacionada en una factura global'
            );
        }
        
        /**
         * Convertir forma de pago de WooCommerce a código SAT
         */
        public static function convert_wc_payment_method_to_sat($payment_method) {
            $conversions = array(
                'bacs' => '03',        // Transferencia
                'cheque' => '02',      // Cheque
                'cod' => '01',         // Efectivo
                'paypal' => '06',      // Dinero electrónico
                'stripe' => '04',      // Tarjeta de crédito
                'stripe_cc' => '04',   // Tarjeta de crédito
                'stripe_sepa' => '03', // Transferencia
                'mercadopago' => '04', // Tarjeta de crédito
                'oxxo' => '01',        // Efectivo
                'spei' => '03',        // Transferencia
                'card' => '04',        // Tarjeta de crédito
                'credit_card' => '04', // Tarjeta de crédito
                'debit_card' => '28',  // Tarjeta de débito
            );
            
            return $conversions[$payment_method] ?? '99'; // Por definir
        }
        
        /**
         * Obtener nombre del país por código ISO
         */
        public static function get_country_name($country_code) {
            $countries = array(
                'MX' => 'México',
                'US' => 'Estados Unidos',
                'CA' => 'Canadá',
                'ES' => 'España',
                'AR' => 'Argentina',
                'CO' => 'Colombia',
                'PE' => 'Perú',
                'CL' => 'Chile',
                'EC' => 'Ecuador',
                'VE' => 'Venezuela',
                'BR' => 'Brasil',
                'CR' => 'Costa Rica',
                'GT' => 'Guatemala',
                'SV' => 'El Salvador',
                'HN' => 'Honduras',
                'NI' => 'Nicaragua',
                'PA' => 'Panamá',
                'DO' => 'República Dominicana',
                'CU' => 'Cuba',
                'PR' => 'Puerto Rico'
            );
            
            return $countries[$country_code] ?? $country_code;
        }
        
        /**
         * Validar código postal mexicano
         */
        public static function validate_postal_code($postal_code) {
            return preg_match('/^[0-9]{5}$/', $postal_code);
        }
        
        /**
         * Generar número de orden único
         */
        public static function generate_unique_order_number($order_id) {
            return 'WC-' . $order_id . '-' . time();
        }
        
        /**
         * Formatear fecha para CFDI
         */
        public static function format_date_for_cfdi($date = null) {
            if ($date === null) {
                $date = current_time('timestamp');
            }
            
            if (is_string($date)) {
                $date = strtotime($date);
            }
            
            return date('Y-m-d\TH:i:s', $date);
        }
        
        /**
         * Obtener tipo de cambio desde API externa (ejemplo)
         */
        public static function get_exchange_rate($from = 'USD', $to = 'MXN') {
            // Esta es una función de ejemplo. En producción deberías usar
            // una API real como Banxico, XE.com, etc.
            
            $exchange_rates = array(
                'USD_MXN' => 20.50,
                'EUR_MXN' => 22.30,
                'CAD_MXN' => 15.20,
                'GBP_MXN' => 25.80
            );
            
            $key = $from . '_' . $to;
            return $exchange_rates[$key] ?? 1.0;
        }
        
        /**
         * Calcular impuestos según tasa
         */
        public static function calculate_tax($base, $rate) {
            return floatval($base) * floatval($rate);
        }
        
        /**
         * Verificar si una fecha está dentro del rango permitido para CFDI
         */
        public static function validate_cfdi_date($date) {
            $now = current_time('timestamp');
            $cfdi_date = is_string($date) ? strtotime($date) : $date;
            
            // No puede ser más de 72 horas en el pasado
            $min_date = $now - (72 * 60 * 60);
            
            // No puede ser fecha futura
            $max_date = $now;
            
            return ($cfdi_date >= $min_date && $cfdi_date <= $max_date);
        }
        
        /**
         * Generar hash para verificación de integridad
         */
        public static function generate_integrity_hash($data) {
            return hash('sha256', serialize($data));
        }
        
        /**
         * Validar estructura de respuesta de API
         */
        public static function validate_api_response($response) {
            if (!is_array($response)) {
                return false;
            }
            
            // Validar estructura básica de Factura.com
            if (!isset($response['response'])) {
                return false;
            }
            
            if ($response['response'] === 'success') {
                return isset($response['UUID']) || isset($response['data']);
            }
            
            return isset($response['message']);
        }
        
        /**
         * Sanitizar datos para API
         */
        public static function sanitize_for_api($data) {
            if (is_array($data)) {
                return array_map([self::class, 'sanitize_for_api'], $data);
            }
            
            if (is_string($data)) {
                // Remover caracteres de control
                $data = preg_replace('/[\x00-\x1F\x7F]/', '', $data);
                
                // Normalizar espacios
                $data = preg_replace('/\s+/', ' ', trim($data));
                
                return $data;
            }
            
            return $data;
        }
        
        /**
         * Obtener información de timezone de México
         */
        public static function get_mexico_timezone() {
            return 'America/Mexico_City';
        }
        
        /**
         * Convertir timestamp a fecha de México
         */
        public static function convert_to_mexico_time($timestamp = null) {
            if ($timestamp === null) {
                $timestamp = time();
            }
            
            $dt = new DateTime();
            $dt->setTimestamp($timestamp);
            $dt->setTimezone(new DateTimeZone(self::get_mexico_timezone()));
            
            return $dt->format('Y-m-d\TH:i:s');
        }
        
        /**
         * Validar que el total calculado coincida con el total del pedido
         */
        public static function validate_order_totals($order, $conceptos) {
            $calculated_subtotal = 0;
            $calculated_tax = 0;
            
            foreach ($conceptos as $concepto) {
                $calculated_subtotal += floatval($concepto['Importe']);
                
                if (isset($concepto['Impuestos']['Traslados'])) {
                    foreach ($concepto['Impuestos']['Traslados'] as $traslado) {
                        $calculated_tax += floatval($traslado['Importe']);
                    }
                }
            }
            
            $calculated_total = $calculated_subtotal + $calculated_tax;
            $order_total = floatval($order->get_total());
            
            // Permitir diferencia de 1 peso por redondeos
            return abs($calculated_total - $order_total) <= 1.0;
        }
        
        /**
         * Limpiar logs antiguos
         */
        public static function cleanup_old_logs($days = 30) {
            global $wpdb;
            
            $table_name = $wpdb->prefix . 'woo_factura_com_logs';
            
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM $table_name WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
                    $days
                ));
            }
        }
        
        /**
         * Obtener estadísticas de CFDIs
         */
        public static function get_cfdi_stats() {
            global $wpdb;
            
            $stats = array(
                'total_generated' => 0,
                'total_cancelled' => 0,
                'demo_generated' => 0,
                'real_generated' => 0,
                'last_30_days' => 0
            );
            
            // Contar CFDIs generados
            $stats['total_generated'] = $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->postmeta} 
                WHERE meta_key = '_factura_com_cfdi_uuid'
            ");
            
            // Contar CFDIs cancelados
            $stats['total_cancelled'] = $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->postmeta} 
                WHERE meta_key = '_factura_com_cfdi_cancelled'
            ");
            
            // Contar CFDIs demo vs reales
            $stats['demo_generated'] = $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->postmeta} 
                WHERE meta_key = '_factura_com_cfdi_environment' 
                AND meta_value = 'demo'
            ");
            
            $stats['real_generated'] = $stats['total_generated'] - $stats['demo_generated'];
            
            // Contar últimos 30 días
            $stats['last_30_days'] = $wpdb->get_var("
                SELECT COUNT(*) FROM {$wpdb->postmeta} pm
                INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
                WHERE pm.meta_key = '_factura_com_cfdi_generated_at'
                AND pm.meta_value >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            
            return $stats;
        }
        
        /**
         * Verificar si WooCommerce HPOS está activo
         */
        public static function is_hpos_enabled() {
            return class_exists('Automattic\WooCommerce\Utilities\OrderUtil') && 
                   \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
        }
        
        /**
         * Log de actividad del plugin
         */
        public static function log_activity($order_id, $action, $status, $message = '', $data = array()) {
            global $wpdb;
            
            $table_name = $wpdb->prefix . 'woo_factura_com_logs';
            
            if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name) {
                $wpdb->insert(
                    $table_name,
                    array(
                        'order_id' => $order_id,
                        'action' => $action,
                        'status' => $status,
                        'message' => $message,
                        'data' => wp_json_encode($data, JSON_UNESCAPED_UNICODE),
                        'created_at' => current_time('mysql')
                    ),
                    array('%d', '%s', '%s', '%s', '%s', '%s')
                );
            }
        }
    }
}

// Funciones de utilidad globales
if (!function_exists('woo_factura_com_validate_rfc')) {
    function woo_factura_com_validate_rfc($rfc) {
        return WooFacturaComUtilities::validate_rfc_complete($rfc);
    }
}

if (!function_exists('woo_factura_com_format_currency')) {
    function woo_factura_com_format_currency($amount) {
        return WooFacturaComUtilities::format_currency_for_cfdi($amount);
    }
}

if (!function_exists('woo_factura_com_clean_description')) {
    function woo_factura_com_clean_description($description) {
        return WooFacturaComUtilities::clean_description_for_cfdi($description);
    }
}

if (!function_exists('woo_factura_com_get_stats')) {
    function woo_factura_com_get_stats() {
        return WooFacturaComUtilities::get_cfdi_stats();
    }
}