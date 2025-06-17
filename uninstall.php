<?php
/**
 * Fired when the plugin is uninstalled
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Cargar el instalador
require_once plugin_dir_path(__FILE__) . 'includes/class-install.php';

// Ejecutar desinstalación
WooFacturaComInstaller::uninstall();