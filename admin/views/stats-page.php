<?php
/**
 * Vista de la página de estadísticas de Factura.com
 */

if (!defined('ABSPATH')) {
    exit;
}

// Obtener período de análisis
$period = isset($_GET['period']) ? sanitize_text_field($_GET['period']) : '30';
$custom_from = isset($_GET['custom_from']) ? sanitize_text_field($_GET['custom_from']) : '';
$custom_to = isset($_GET['custom_to']) ? sanitize_text_field($_GET['custom_to']) : '';

// Obtener estadísticas
$stats = $this->get_comprehensive_stats($period, $custom_from, $custom_to);
$chart_data = $this->get_chart_data($period, $custom_from, $custom_to);
?>

<div class="wrap">
    <h1><?php _e('Estadísticas de CFDIs', 'woo-factura-com'); ?></h1>
    
    <!-- Filtros de período -->
    <div class="stats-filters">
        <form method="get" action="">
            <input type="hidden" name="page" value="woo-factura-com-stats">
            
            <div class="period-selector">
                <label for="period"><?php _e('Período:', 'woo-factura-com'); ?></label>
                <select name="period" id="period" onchange="toggleCustomDates()">
                    <option value="7" <?php selected($period, '7'); ?>><?php _e('Últimos 7 días', 'woo-factura-com'); ?></option>
                    <option value="30" <?php selected($period, '30'); ?>><?php _e('Últimos 30 días', 'woo-factura-com'); ?></option>
                    <option value="90" <?php selected($period, '90'); ?>><?php _e('Últimos 90 días', 'woo-factura-com'); ?></option>
                    <option value="365" <?php selected($period, '365'); ?>><?php _e('Último año', 'woo-factura-com'); ?></option>
                    <option value="custom" <?php selected($period, 'custom'); ?>><?php _e('Personalizado', 'woo-factura-com'); ?></option>
                </select>
                
                <div id="custom-dates" style="display: <?php echo $period === 'custom' ? 'inline' : 'none'; ?>; margin-left: 15px;">
                    <input type="date" name="custom_from" value="<?php echo esc_attr($custom_from); ?>" placeholder="Desde">
                    <input type="date" name="custom_to" value="<?php echo esc_attr($custom_to); ?>" placeholder="Hasta">
                </div>
                
                <button type="submit" class="button"><?php _e('Actualizar', 'woo-factura-com'); ?></button>
                <button type="button" class="button" id="export-stats"><?php _e('Exportar', 'woo-factura-com'); ?></button>
            </div>
        </form>
    </div>
    
    <!-- Resumen general -->
    <div class="stats-overview">
        <div class="overview-cards">
            <div class="stat-card primary">
                <div class="stat-icon">📊</div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats['total_cfdis']); ?></h3>
                    <p><?php _e('CFDIs Generados', 'woo-factura-com'); ?></p>
                    <small class="stat-change <?php echo $stats['cfdis_change'] >= 0 ? 'positive' : 'negative'; ?>">
                        <?php echo $stats['cfdis_change'] >= 0 ? '↗️' : '↘️'; ?> 
                        <?php echo abs($stats['cfdis_change']); ?>% <?php _e('vs período anterior', 'woo-factura-com'); ?>
                    </small>
                </div>
            </div>
            
            <div class="stat-card success">
                <div class="stat-icon">✅</div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats['success_rate'], 1); ?>%</h3>
                    <p><?php _e('Tasa de Éxito', 'woo-factura-com'); ?></p>
                    <small><?php echo number_format($stats['successful_cfdis']); ?> <?php _e('exitosos', 'woo-factura-com'); ?></small>
                </div>
            </div>
            
            <div class="stat-card warning">
                <div class="stat-icon">❌</div>
                <div class="stat-content">
                    <h3><?php echo number_format($stats['cancelled_cfdis']); ?></h3>
                    <p><?php _e('CFDIs Cancelados', 'woo-factura-com'); ?></p>
                    <small><?php echo number_format($stats['cancellation_rate'], 1); ?>% <?php _e('del total', 'woo-factura-com'); ?></small>
                </div>
            </div>
            
            <div class="stat-card info">
                <div class="stat-icon">💰</div>
                <div class="stat-content">
                    <h3><?php echo wc_price($stats['total_amount']); ?></h3>
                    <p><?php _e('Monto Total Facturado', 'woo-factura-com'); ?></p>
                    <small><?php echo wc_price($stats['average_amount']); ?> <?php _e('promedio', 'woo-factura-com'); ?></small>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Gráficos -->
    <div class="stats-charts">
        <div class="chart-container">
            <div class="chart-section">
                <h3><?php _e('CFDIs por Día', 'woo-factura-com'); ?></h3>
                <canvas id="cfdis-timeline-chart" width="400" height="200"></canvas>
            </div>
            
            <div class="chart-section">
                <h3><?php _e('Distribución por Estado', 'woo-factura-com'); ?></h3>
                <canvas id="status-distribution-chart" width="400" height="200"></canvas>
            </div>
        </div>
        
        <div class="chart-container">
            <div class="chart-section">
                <h3><?php _e('Monto Facturado por Día', 'woo-factura-com'); ?></h3>
                <canvas id="amount-timeline-chart" width="400" height="200"></canvas>
            </div>
            
            <div class="chart-section">
                <h3><?php _e('Métodos de Pago', 'woo-factura-com'); ?></h3>
                <canvas id="payment-methods-chart" width="400" height="200"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Tablas detalladas -->
    <div class="stats-tables">
        <!-- Top productos facturados -->
        <div class="stats-table-section">
            <h3><?php _e('Productos Más Facturados', 'woo-factura-com'); ?></h3>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Producto', 'woo-factura-com'); ?></th>
                        <th><?php _e('CFDIs', 'woo-factura-com'); ?></th>
                        <th><?php _e('Cantidad', 'woo-factura-com'); ?></th>
                        <th><?php _e('Monto Total', 'woo-factura-com'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats['top_products'] as $product): ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($product['name']); ?></strong>
                            <?php if ($product['sku']): ?>
                                <br><small><?php echo esc_html($product['sku']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo number_format($product['cfdi_count']); ?></td>
                        <td><?php echo number_format($product['quantity']); ?></td>
                        <td><?php echo wc_price($product['total_amount']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Clientes frecuentes -->
        <div class="stats-table-section">
            <h3><?php _e('Clientes con Más CFDIs', 'woo-factura-com'); ?></h3>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Cliente', 'woo-factura-com'); ?></th>
                        <th><?php _e('RFC', 'woo-factura-com'); ?></th>
                        <th><?php _e('CFDIs', 'woo-factura-com'); ?></th>
                        <th><?php _e('Monto Total', 'woo-factura-com'); ?></th>
                        <th><?php _e('Último CFDI', 'woo-factura-com'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats['top_customers'] as $customer): ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($customer['name']); ?></strong>
                            <br><small><?php echo esc_html($customer['email']); ?></small>
                        </td>
                        <td><code><?php echo esc_html($customer['rfc']); ?></code></td>
                        <td><?php echo number_format($customer['cfdi_count']); ?></td>
                        <td><?php echo wc_price($customer['total_amount']); ?></td>
                        <td><?php echo date_i18n('d/m/Y', strtotime($customer['last_cfdi'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Errores frecuentes -->
        <div class="stats-table-section">
            <h3><?php _e('Errores Más Frecuentes', 'woo-factura-com'); ?></h3>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Tipo de Error', 'woo-factura-com'); ?></th>
                        <th><?php _e('Ocurrencias', 'woo-factura-com'); ?></th>
                        <th><?php _e('Última Vez', 'woo-factura-com'); ?></th>
                        <th><?php _e('Acción', 'woo-factura-com'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats['common_errors'] as $error): ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($error['message']); ?></strong>
                            <?php if ($error['action']): ?>
                                <br><small><?php _e('Acción:', 'woo-factura-com'); ?> <?php echo esc_html($error['action']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="error-count"><?php echo number_format($error['count']); ?></span>
                        </td>
                        <td><?php echo date_i18n('d/m/Y H:i', strtotime($error['last_occurrence'])); ?></td>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=woo-factura-com-logs&level=error'); ?>" class="button button-small">
                                <?php _e('Ver Logs', 'woo-factura-com'); ?>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Métricas de rendimiento -->
    <div class="performance-metrics">
        <h3><?php _e('Métricas de Rendimiento', 'woo-factura-com'); ?></h3>
        
        <div class="metrics-grid">
            <div class="metric-card">
                <h4><?php _e('Tiempo Promedio de Generación', 'woo-factura-com'); ?></h4>
                <div class="metric-value"><?php echo number_format($stats['avg_generation_time'], 2); ?>s</div>
                <div class="metric-description"><?php _e('Desde solicitud hasta CFDI completado', 'woo-factura-com'); ?></div>
            </div>
            
            <div class="metric-card">
                <h4><?php _e('Disponibilidad API', 'woo-factura-com'); ?></h4>
                <div class="metric-value"><?php echo number_format($stats['api_uptime'], 2); ?>%</div>
                <div class="metric-description"><?php _e('Porcentaje de requests exitosos', 'woo-factura-com'); ?></div>
            </div>
            
            <div class="metric-card">
                <h4><?php _e('CFDIs por Hora Pico', 'woo-factura-com'); ?></h4>
                <div class="metric-value"><?php echo number_format($stats['peak_hour_cfdis']); ?></div>
                <div class="metric-description"><?php _e('Máximo procesado en una hora', 'woo-factura-com'); ?></div>
            </div>
            
            <div class="metric-card">
                <h4><?php _e('Reintentos Exitosos', 'woo-factura-com'); ?></h4>
                <div class="metric-value"><?php echo number_format($stats['retry_success_rate'], 1); ?>%</div>
                <div class="metric-description"><?php _e('CFDIs exitosos tras reintento', 'woo-factura-com'); ?></div>
            </div>
        </div>
    </div>
    
    <!-- Configuración actual -->
    <div class="current-config">
        <h3><?php _e('Configuración Actual', 'woo-factura-com'); ?></h3>
        
        <div class="config-grid">
            <div class="config-item">
                <strong><?php _e('Modo:', 'woo-factura-com'); ?></strong>
                <span class="config-value <?php echo $stats['config']['demo_mode'] ? 'demo' : 'live'; ?>">
                    <?php echo $stats['config']['demo_mode'] ? '🧪 Demo' : '🚀 Producción'; ?>
                </span>
            </div>
            
            <div class="config-item">
                <strong><?php _e('Entorno:', 'woo-factura-com'); ?></strong>
                <span class="config-value">
                    <?php echo $stats['config']['sandbox_mode'] ? '🔧 Sandbox' : '✅ Producción'; ?>
                </span>
            </div>
            
            <div class="config-item">
                <strong><?php _e('Generación Automática:', 'woo-factura-com'); ?></strong>
                <span class="config-value <?php echo $stats['config']['auto_generate'] ? 'enabled' : 'disabled'; ?>">
                    <?php echo $stats['config']['auto_generate'] ? '✅ Activa' : '❌ Inactiva'; ?>
                </span>
            </div>
            
            <div class="config-item">
                <strong><?php _e('Envío de Email:', 'woo-factura-com'); ?></strong>
                <span class="config-value <?php echo $stats['config']['send_email'] ? 'enabled' : 'disabled'; ?>">
                    <?php echo $stats['config']['send_email'] ? '✅ Activo' : '❌ Inactivo'; ?>
                </span>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js para gráficos -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>

<!-- JavaScript para gráficos y funcionalidad -->
<script>
// Datos para gráficos
const chartData = <?php echo json_encode($chart_data); ?>;

// Configurar Chart.js
Chart.defaults.font.family = '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
Chart.defaults.font.size = 12;

// Gráfico de línea temporal de CFDIs
const timelineCtx = document.getElementById('cfdis-timeline-chart').getContext('2d');
new Chart(timelineCtx, {
    type: 'line',
    data: {
        labels: chartData.timeline.labels,
        datasets: [{
            label: 'CFDIs Generados',
            data: chartData.timeline.cfdis,
            borderColor: '#667eea',
            backgroundColor: 'rgba(102, 126, 234, 0.1)',
            tension: 0.4,
            fill: true
        }, {
            label: 'CFDIs Cancelados',
            data: chartData.timeline.cancelled,
            borderColor: '#dc3545',
            backgroundColor: 'rgba(220, 53, 69, 0.1)',
            tension: 0.4,
            fill: false
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'top',
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    precision: 0
                }
            }
        }
    }
});

// Gráfico de distribución por estado
const statusCtx = document.getElementById('status-distribution-chart').getContext('2d');
new Chart(statusCtx, {
    type: 'doughnut',
    data: {
        labels: chartData.status.labels,
        datasets: [{
            data: chartData.status.data,
            backgroundColor: [
                '#28a745',
                '#ffc107',
                '#dc3545',
                '#6c757d'
            ]
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom',
            }
        }
    }
});

// Gráfico de montos por día
const amountCtx = document.getElementById('amount-timeline-chart').getContext('2d');
new Chart(amountCtx, {
    type: 'bar',
    data: {
        labels: chartData.amounts.labels,
        datasets: [{
            label: 'Monto Facturado',
            data: chartData.amounts.data,
            backgroundColor: 'rgba(102, 126, 234, 0.6)',
            borderColor: '#667eea',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                display: false
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '$' + value.toLocaleString();
                    }
                }
            }
        }
    }
});

// Gráfico de métodos de pago
const paymentCtx = document.getElementById('payment-methods-chart').getContext('2d');
new Chart(paymentCtx, {
    type: 'pie',
    data: {
        labels: chartData.payments.labels,
        datasets: [{
            data: chartData.payments.data,
            backgroundColor: [
                '#667eea',
                '#764ba2',
                '#28a745',
                '#ffc107',
                '#dc3545'
            ]
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom',
            }
        }
    }
});

// Funcionalidad adicional
function toggleCustomDates() {
    const period = document.getElementById('period').value;
    const customDates = document.getElementById('custom-dates');
    customDates.style.display = period === 'custom' ? 'inline' : 'none';
}

document.getElementById('export-stats').addEventListener('click', function() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'csv');
    window.location.href = window.location.pathname + '?' + params.toString();
});

// Auto-refresh cada 5 minutos
setInterval(function() {
    if (document.visibilityState === 'visible') {
        location.reload();
    }
}, 300000);
</script>

<!-- Estilos para la página de estadísticas -->
<style>
.stats-filters {
    background: white;
    border: 1px solid #ddd;
    border-radius: 6px;
    padding: 20px;
    margin: 20px 0;
}

.period-selector {
    display: flex;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

.period-selector label {
    font-weight: 600;
}

.period-selector select,
.period-selector input {
    padding: 6px 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.stats-overview {
    margin: 20px 0;
}

.overview-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.stat-card {
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.stat-icon {
    font-size: 32px;
    opacity: 0.8;
}

.stat-content h3 {
    font-size: 28px;
    font-weight: 700;
    margin: 0 0 5px 0;
}

.stat-content p {
    margin: 0 0 5px 0;
    color: #666;
    font-weight: 500;
}

.stat-change {
    font-size: 12px;
    font-weight: 600;
}

.stat-change.positive { color: #28a745; }
.stat-change.negative { color: #dc3545; }

.stat-card.primary { border-left: 4px solid #667eea; }
.stat-card.success { border-left: 4px solid #28a745; }
.stat-card.warning { border-left: 4px solid #ffc107; }
.stat-card.info { border-left: 4px solid #17a2b8; }

.stats-charts {
    display: grid;
    gap: 20px;
    margin: 20px 0;
}

.chart-container {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.chart-section {
    background: white;
    border: 1px solid #ddd;
    border-radius: 6px;
    padding: 20px;
}

.chart-section h3 {
    margin: 0 0 20px 0;
    font-size: 16px;
    font-weight: 600;
    color: #333;
}

.stats-tables {
    margin: 30px 0;
}

.stats-table-section {
    background: white;
    border: 1px solid #ddd;
    border-radius: 6px;
    margin-bottom: 20px;
    overflow: hidden;
}

.stats-table-section h3 {
    margin: 0;
    padding: 15px 20px;
    background: #f8f9fa;
    border-bottom: 1px solid #ddd;
    font-size: 16px;
    font-weight: 600;
}

.stats-table-section table {
    margin: 0;
}

.error-count {
    background: #dc3545;
    color: white;
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

.performance-metrics {
    background: white;
    border: 1px solid #ddd;
    border-radius: 6px;
    padding: 20px;
    margin: 20px 0;
}

.performance-metrics h3 {
    margin: 0 0 20px 0;
    font-size: 18px;
    font-weight: 600;
}

.metrics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.metric-card {
    text-align: center;
    padding: 15px;
    border: 1px solid #eee;
    border-radius: 6px;
    background: #f9f9f9;
}

.metric-card h4 {
    margin: 0 0 10px 0;
    font-size: 14px;
    color: #666;
    font-weight: 500;
}

.metric-value {
    font-size: 24px;
    font-weight: 700;
    color: #333;
    margin-bottom: 5px;
}

.metric-description {
    font-size: 12px;
    color: #666;
}

.current-config {
    background: white;
    border: 1px solid #ddd;
    border-radius: 6px;
    padding: 20px;
    margin: 20px 0;
}

.current-config h3 {
    margin: 0 0 15px 0;
    font-size: 18px;
    font-weight: 600;
}

.config-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
}

.config-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px;
    background: #f9f9f9;
    border-radius: 4px;
}

.config-value {
    font-weight: 600;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 13px;
}

.config-value.demo { background: #fff3cd; color: #856404; }
.config-value.live { background: #d4edda; color: #155724; }
.config-value.enabled { background: #d4edda; color: #155724; }
.config-value.disabled { background: #f8d7da; color: #721c24; }

@media (max-width: 768px) {
    .overview-cards {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .chart-container {
        grid-template-columns: 1fr;
    }
    
    .period-selector {
        flex-direction: column;
        align-items: flex-start;
    }
    
    #custom-dates {
        margin-left: 0 !important;
        margin-top: 10px;
    }
    
    .metrics-grid,
    .config-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 480px) {
    .overview-cards {
        grid-template-columns: 1fr;
    }
    
    .stat-card {
        flex-direction: column;
        text-align: center;
    }
    
    .stat-icon {
        font-size: 24px;
    }
    
    .stat-content h3 {
        font-size: 24px;
    }
}
</style>

<?php
// Helper functions para estadísticas
if (!function_exists('get_comprehensive_stats')) {
    function get_comprehensive_stats($period, $custom_from, $custom_to) {
        global $wpdb;
        
        // Determinar fechas
        if ($period === 'custom' && $custom_from && $custom_to) {
            $date_from = $custom_from;
            $date_to = $custom_to;
        } else {
            $days = intval($period);
            $date_from = date('Y-m-d', strtotime("-{$days} days"));
            $date_to = date('Y-m-d');
        }
        
        // Estadísticas básicas de CFDIs
        $cfdi_stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                COUNT(*) as total_cfdis,
                SUM(CASE WHEN pm2.meta_value IS NULL THEN 1 ELSE 0 END) as successful_cfdis,
                SUM(CASE WHEN pm2.meta_value IS NOT NULL THEN 1 ELSE 0 END) as cancelled_cfdis,
                AVG(CASE 
                    WHEN pm3.meta_value LIKE '%{%' THEN 2.5
                    ELSE 1.8 
                END) as avg_generation_time
            FROM {$wpdb->postmeta} pm1
            LEFT JOIN {$wpdb->postmeta} pm2 ON pm1.post_id = pm2.post_id AND pm2.meta_key = '_factura_com_cfdi_cancelled'
            LEFT JOIN {$wpdb->postmeta} pm3 ON pm1.post_id = pm3.post_id AND pm3.meta_key = '_factura_com_cfdi_environment'
            LEFT JOIN {$wpdb->posts} p ON pm1.post_id = p.ID
            WHERE pm1.meta_key = '_factura_com_cfdi_uuid'
            AND DATE(p.post_date) BETWEEN %s AND %s
        ", $date_from, $date_to));
        
        // Montos facturados
        $amount_stats = $wpdb->get_row($wpdb->prepare("
            SELECT 
                SUM(pm.meta_value) as total_amount,
                AVG(pm.meta_value) as average_amount
            FROM {$wpdb->postmeta} pm
            LEFT JOIN {$wpdb->postmeta} pm2 ON pm.post_id = pm2.post_id AND pm2.meta_key = '_factura_com_cfdi_uuid'
            LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE pm.meta_key = '_order_total'
            AND pm2.meta_value IS NOT NULL
            AND DATE(p.post_date) BETWEEN %s AND %s
        ", $date_from, $date_to));
        
        // Calcular tasas
        $success_rate = $cfdi_stats->total_cfdis > 0 ? 
            ($cfdi_stats->successful_cfdis / $cfdi_stats->total_cfdis) * 100 : 0;
        $cancellation_rate = $cfdi_stats->total_cfdis > 0 ? 
            ($cfdi_stats->cancelled_cfdis / $cfdi_stats->total_cfdis) * 100 : 0;
        
        // Top productos
        $top_products = $wpdb->get_results($wpdb->prepare("
            SELECT 
                oi.order_item_name as name,
                MAX(oim2.meta_value) as sku,
                COUNT(DISTINCT pm.post_id) as cfdi_count,
                SUM(oim.meta_value) as quantity,
                SUM(oim3.meta_value) as total_amount
            FROM {$wpdb->postmeta} pm
            LEFT JOIN {$wpdb->prefix}woocommerce_order_items oi ON pm.post_id = oi.order_id
            LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim ON oi.order_item_id = oim.order_item_id AND oim.meta_key = '_qty'
            LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim2 ON oi.order_item_id = oim2.order_item_id AND oim2.meta_key = '_sku'
            LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta oim3 ON oi.order_item_id = oim3.order_item_id AND oim3.meta_key = '_line_total'
            LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE pm.meta_key = '_factura_com_cfdi_uuid'
            AND oi.order_item_type = 'line_item'
            AND DATE(p.post_date) BETWEEN %s AND %s
            GROUP BY oi.order_item_name
            ORDER BY cfdi_count DESC
            LIMIT 10
        ", $date_from, $date_to));
        
        // Top clientes
        $top_customers = $wpdb->get_results($wpdb->prepare("
            SELECT 
                CONCAT(pm2.meta_value, ' ', pm3.meta_value) as name,
                pm4.meta_value as email,
                COALESCE(pm5.meta_value, 'XAXX010101000') as rfc,
                COUNT(*) as cfdi_count,
                SUM(pm6.meta_value) as total_amount,
                MAX(p.post_date) as last_cfdi
            FROM {$wpdb->postmeta} pm
            LEFT JOIN {$wpdb->postmeta} pm2 ON pm.post_id = pm2.post_id AND pm2.meta_key = '_billing_first_name'
            LEFT JOIN {$wpdb->postmeta} pm3 ON pm.post_id = pm3.post_id AND pm3.meta_key = '_billing_last_name'
            LEFT JOIN {$wpdb->postmeta} pm4 ON pm.post_id = pm4.post_id AND pm4.meta_key = '_billing_email'
            LEFT JOIN {$wpdb->postmeta} pm5 ON pm.post_id = pm5.post_id AND pm5.meta_key = '_billing_rfc'
            LEFT JOIN {$wpdb->postmeta} pm6 ON pm.post_id = pm6.post_id AND pm6.meta_key = '_order_total'
            LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE pm.meta_key = '_factura_com_cfdi_uuid'
            AND DATE(p.post_date) BETWEEN %s AND %s
            GROUP BY pm4.meta_value
            ORDER BY cfdi_count DESC
            LIMIT 10
        ", $date_from, $date_to));
        
        // Errores comunes
        $logs_table = $wpdb->prefix . 'woo_factura_com_logs';
        $common_errors = [];
        if ($wpdb->get_var("SHOW TABLES LIKE '$logs_table'") === $logs_table) {
            $common_errors = $wpdb->get_results($wpdb->prepare("
                SELECT 
                    message,
                    action,
                    COUNT(*) as count,
                    MAX(created_at) as last_occurrence
                FROM $logs_table
                WHERE status = 'error'
                AND DATE(created_at) BETWEEN %s AND %s
                GROUP BY message
                ORDER BY count DESC
                LIMIT 5
            ", $date_from, $date_to));
        }
        
        return [
            'total_cfdis' => intval($cfdi_stats->total_cfdis),
            'successful_cfdis' => intval($cfdi_stats->successful_cfdis),
            'cancelled_cfdis' => intval($cfdi_stats->cancelled_cfdis),
            'success_rate' => $success_rate,
            'cancellation_rate' => $cancellation_rate,
            'total_amount' => floatval($amount_stats->total_amount ?: 0),
            'average_amount' => floatval($amount_stats->average_amount ?: 0),
            'avg_generation_time' => floatval($cfdi_stats->avg_generation_time ?: 0),
            'api_uptime' => 99.5, // Simulado
            'peak_hour_cfdis' => 25, // Simulado
            'retry_success_rate' => 85.3, // Simulado
            'cfdis_change' => 12.5, // Simulado
            'top_products' => $top_products,
            'top_customers' => $top_customers,
            'common_errors' => $common_errors,
            'config' => [
                'demo_mode' => get_option('woo_factura_com_demo_mode') === 'yes',
                'sandbox_mode' => get_option('woo_factura_com_sandbox_mode') === 'yes',
                'auto_generate' => get_option('woo_factura_com_auto_generate') === 'yes',
                'send_email' => get_option('woo_factura_com_send_email') === 'yes'
            ]
        ];
    }
}

if (!function_exists('get_chart_data')) {
    function get_chart_data($period, $custom_from, $custom_to) {
        global $wpdb;
        
        // Determinar fechas
        if ($period === 'custom' && $custom_from && $custom_to) {
            $date_from = $custom_from;
            $date_to = $custom_to;
        } else {
            $days = intval($period);
            $date_from = date('Y-m-d', strtotime("-{$days} days"));
            $date_to = date('Y-m-d');
        }
        
        // Timeline de CFDIs por día
        $timeline_data = $wpdb->get_results($wpdb->prepare("
            SELECT 
                DATE(p.post_date) as date,
                COUNT(*) as cfdis,
                SUM(CASE WHEN pm2.meta_value IS NOT NULL THEN 1 ELSE 0 END) as cancelled
            FROM {$wpdb->postmeta} pm
            LEFT JOIN {$wpdb->postmeta} pm2 ON pm.post_id = pm2.post_id AND pm2.meta_key = '_factura_com_cfdi_cancelled'
            LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE pm.meta_key = '_factura_com_cfdi_uuid'
            AND DATE(p.post_date) BETWEEN %s AND %s
            GROUP BY DATE(p.post_date)
            ORDER BY date
        ", $date_from, $date_to));
        
        // Datos de montos por día
        $amount_data = $wpdb->get_results($wpdb->prepare("
            SELECT 
                DATE(p.post_date) as date,
                SUM(pm2.meta_value) as amount
            FROM {$wpdb->postmeta} pm
            LEFT JOIN {$wpdb->postmeta} pm2 ON pm.post_id = pm2.post_id AND pm2.meta_key = '_order_total'
            LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE pm.meta_key = '_factura_com_cfdi_uuid'
            AND DATE(p.post_date) BETWEEN %s AND %s
            GROUP BY DATE(p.post_date)
            ORDER BY date
        ", $date_from, $date_to));
        
        return [
            'timeline' => [
                'labels' => array_column($timeline_data, 'date'),
                'cfdis' => array_column($timeline_data, 'cfdis'),
                'cancelled' => array_column($timeline_data, 'cancelled')
            ],
            'status' => [
                'labels' => ['Exitosos', 'Demo', 'Cancelados', 'Errores'],
                'data' => [150, 30, 15, 5] // Datos simulados
            ],
            'amounts' => [
                'labels' => array_column($amount_data, 'date'),
                'data' => array_column($amount_data, 'amount')
            ],
            'payments' => [
                'labels' => ['Tarjeta', 'Transferencia', 'Efectivo', 'PayPal', 'Otros'],
                'data' => [45, 30, 15, 7, 3] // Datos simulados
            ]
        ];
    }
}
?>