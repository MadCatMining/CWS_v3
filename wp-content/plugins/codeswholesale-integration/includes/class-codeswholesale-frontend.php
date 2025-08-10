<?php
/**
 * CodesWholesale Frontend Class
 * Handles frontend functionality for the plugin
 */

class CodesWholesale_Frontend {
    
    public function __construct() {
        add_shortcode('codeswholesale_products', array($this, 'display_products'));
        add_action('woocommerce_thankyou', array($this, 'process_order'));
    }
    
    public function display_products($atts) {
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
    
    public function process_order($order_id) {
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
    }
}
