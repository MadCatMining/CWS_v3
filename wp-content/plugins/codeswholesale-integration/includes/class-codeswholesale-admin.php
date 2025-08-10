<?php
/**
 * CodesWholesale Admin Class
 * Handles admin functionality for the plugin
 */

class CodesWholesale_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('add_meta_boxes', array($this, 'add_product_meta_box'));
        add_action('save_post', array($this, 'save_product_meta'));
        add_action('admin_notices', array($this, 'admin_notices'));
    }
    
    public function add_admin_menu() {
        add_options_page(
            'CodesWholesale Settings',
            'CodesWholesale',
            'manage_options',
            'codeswholesale',
            array($this, 'settings_page')
        );
    }
    
    public function settings_page() {
        ?>
        <div class="wrap">
            <h1>CodesWholesale Integration</h1>
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
    
    public function add_product_meta_box() {
        add_meta_box(
            'codeswholesale-product-meta',
            'CodesWholesale Integration',
            array($this, 'product_meta_callback'),
            'product',
            'side'
        );
    }
    
    public function product_meta_callback($post) {
        wp_nonce_field('codeswholesale_save_meta', 'codeswholesale_meta_nonce');
        
        $product_id = get_post_meta($post->ID, '_codeswholesale_product_id', true);
        echo '<label for="codeswholesale_product_id">CodesWholesale Product ID:</label>';
        echo '<input type="text" id="codeswholesale_product_id" name="codeswholesale_product_id" value="' . esc_attr($product_id) . '" class="widefat" />';
    }
    
    public function save_product_meta($post_id) {
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
    }
    
    public function admin_notices() {
        if (!get_option('codeswholesale_client_id') || !get_option('codeswholesale_client_secret')) {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p>Please configure your CodesWholesale API credentials in <a href="' . admin_url('options-general.php?page=codeswholesale') . '">Settings</a>.</p>';
            echo '</div>';
        }
    }
}
