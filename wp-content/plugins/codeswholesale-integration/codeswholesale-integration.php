<![CDATA[<?php
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

// Include the API class
require_once plugin_dir_path(__FILE__) . 'includes/class-codeswholesale-api.php';

// Initialize the plugin
add_action('plugins_loaded', function() {
    new CodesWholesale_API();
});

// Add admin menu and settings
add_action('admin_menu', function() {
    add_options_page(
        'CodesWholesale Settings',
        'CodesWholesale',
        'manage_options',
        'codeswholesale',
        'codeswholesale_settings_page'
    );
});

// Settings page callback
function codeswholesale_settings_page() {
    ?>
    <div class="wrap">
        <h1>CodesWholesale Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('codeswholesale_settings');
            do_settings_sections('codeswholesale');
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

// Register settings
add_action('admin_init', function() {
    register_setting('codeswholesale_settings', 'codeswholesale_client_id');
    register_setting('codeswholesale_settings', 'codeswholesale_client_secret');
    
    add_settings_section(
        'codeswholesale_api_settings',
        'API Settings',
        'codeswholesale_api_settings_callback',
        'codeswholesale'
    );
    
    add_settings_field(
        'codeswholesale_client_id',
        'Client ID',
        'codeswholesale_client_id_callback',
        'codeswholesale',
        'codeswholesale_api_settings_settings'
    );
    
    add_settings_field(
        'codeswholesale_client_secret',
        'Client Secret',
        'codeswholesale_client_secret_callback',
        'codeswholesale',
        'codeswholesale_api_settings'
    );
});

function codeswholesale_api_settings_callback() {
    echo '<p>Enter your CodesWholesale API credentials</p>';
}

function codeswholesale_client_id_callback() {
    $client_id = get_option('codeswholesale_client_id');
    echo '<input type="text" name="codeswholesale_client_id" value="' . esc_attr($client_id) . '" class="regular-text" />';
}

function codeswholesale_client_secret_callback() {
    $client_secret = get_option('codeswholesale_client_secret');
    echo '<input type="password" name="codeswholesale_client_secret" value="' . esc_attr($client_secret) . '" class="regular-text" />';
}

// Add WooCommerce product meta box
add_action('add_meta_boxes', function() {
    add_meta_box(
        'codeswholesale-product-meta',
        'CodesWholesale Integration',
        'codeswholesale_product_meta_callback',
        'product',
        'side'
    );
});

function codeswholesale_product_meta_callback($post) {
    wp_nonce_field('codeswholesale_save_meta', 'codeswholesale_meta_nonce');
    
    $product_id = get_post_meta($post->ID, '_codeswholesale_product_id', true);
    echo '<label for="codeswholesale_product_id">CodesWholesale Product ID:</label>';
    echo '<input type="text" id="codeswholesale_product_id" name="codeswholesale_product_id" value="' . esc_attr($product_id) . '" class="widefat" />';
}

// Save meta box data
add_action('save_post', function($post_id) {
    if (!isset($_POST['codeswholesale_meta_nonce']) || !wp_verify_nonce($_POST['codeswholesale_meta_nonce'], 'codeswholesale_save_meta')) {
        return;
    }
    
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    
    if (isset($_POST['codeswholesale_product_id'])) {
        update_post_meta($post_id, '_codeswholesale_product_id', sanitize_text_field($_POST['codeswholesale_product_id']));
    }
});

// Add shortcode for displaying products
add_shortcode('codeswholesale_products', 'codeswholesale_display_products');

function codeswholesale_display_products($atts) {
    $atts = shortcode_atts(array(
        'limit' => 20,
        'page' => 1
    ), $atts);
    
    $api = new CodesWholesale_API();
    $products = $api->get_products($atts['page'], $atts['limit']);
    
    if (!$products) {
        return '<p>Failed to load products from CodesWholesale.</p>';
    }
    
    ob_start();
    echo '<div class="codeswholesale-products">';
    foreach ($products as $product) {
        echo '<div class="codeswholesale-product">';
        echo '<h3>' . esc_html($product['name']) . '</h3>';
        echo '<p>Price: ' . esc_html($product['price']['amount']) . ' ' . esc_html($product['price']['currency']) . '</p>';
        echo '<p>Platform: ' . esc_html($product['platform']) . '</p>';
        echo '<a href="' . esc_url($product['url']) . '" target="_blank">View Details</a>';
        echo '</div>';
    }
    echo '</div>';
    
    return ob_get_clean();
}

// Add order processing functionality
add_action('woocommerce_thankyou', function($order_id) {
    $order = wc_get_order($order_id);
    $api = new CodesWholesale_API();
    
    // Get order items
    $items = $order->get_items();
    foreach ($items as $item) {
        $product = $item->get_product();
        $codeswholesale_id = get_post_meta($product->get_id(), '_codeswholesale_product_id', true);
        
        if (!empty($codeswholesale_id)) {
            // Prepare order data for CodesWholesale
            $order_data = array(
                'productId' => $codeswholesale_id,
                'quantity' => $item->get_quantity(),
                'customer' => array(
                    'email' => $order->get_billing_email(),
                    'firstName' => $order->get_billing_first_name(),
                    'lastName' => $order->get_billing_last_name()
                )
            );
            
            // Create order in CodesWholesale
            $api->create_order($order_data);
        }
    }
});

// Add admin notices for API errors
add_action('admin_notices', function() {
    if (!get_option('codeswholesale_client_id') || !get_option('codeswholesale_client_secret')) {
        echo '<div class="notice notice-warning is-dismissible">';
        echo '<p>Please configure your CodesWholesale API credentials in <a href="' . admin_url('options-general.php?page=codeswholesale') . '">Settings</a>.</p>';
        echo '</div>';
    }
});
]]>
