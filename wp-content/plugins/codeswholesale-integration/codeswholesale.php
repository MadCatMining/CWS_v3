<?php
/**
 * Plugin Name: CodesWholesale Integration
 * Description: Integrates WooCommerce with CodesWholesale API for game key sales
 * Version: 1.0.0
 * Author: CodesWholesale
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('CODESWHOLESALE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CODESWHOLESALE_PLUGIN_PATH', plugin_dir_path(__FILE__));

// Include required files
require_once CODESWHOLESALE_PLUGIN_PATH . 'includes/class-codeswholesale-api.php';
require_once CODESWHOLESALE_PLUGIN_PATH . 'includes/class-codeswholesale-admin.php';
require_once CODESWHOLESALE_PLUGIN_PATH . 'includes/class-codeswholesale-frontend.php';

// Initialize the plugin
add_action('plugins_loaded', function() {
    new CodesWholesale_API();
    new CodesWholesale_Admin();
    new CodesWholesale_Frontend();
});

// Activation hook
register_activation_hook(__FILE__, function() {
    // Create necessary database tables
    CodesWholesale_API::create_tables();
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    // Cleanup if needed
});
