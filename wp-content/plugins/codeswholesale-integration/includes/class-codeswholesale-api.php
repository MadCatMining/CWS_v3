<?php
/**
 * CodesWholesale API Class
 * Handles communication with CodesWholesale API
 */

class CodesWholesale_API {
    private $client_id;
    private $client_secret;
    private $api_url = 'https://api.codeswholesale.com';
    private $token_endpoint = '/oauth/token';
    private $products_endpoint = '/products';
    private $orders_endpoint = '/orders';

    public function __construct() {
        $this->client_id = get_option('codeswholesale_client_id');
        $this->client_secret = get_option('codeswholesale_client_secret');
        
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'settings_init'));
    }

    public function init() {
        // Initialize API functionality
    }

    public function add_admin_menu() {
        add_options_page(
            'CodesWholesale Settings',
            'CodesWholesale',
            'manage_options',
            'codeswholesale',
            array($this, 'options_page')
        );
    }

    public function settings_init() {
        register_setting('codeswholesale_settings', 'codeswholesale_client_id');
        register_setting('codeswholesale_settings', 'codeswholesale_client_secret');
        
        add_settings_section(
            'codeswholesale_api_settings',
            'API Settings',
            array($this, 'api_settings_callback'),
            'codeswholesale'
        );
        
        add_settings_field(
            'codeswholesale_client_id',
            'Client ID',
            array($this, 'client_id_callback'),
            'codeswholesale',
            'codeswholesale_api_settings'
        );
        
        add_settings_field(
            'codeswholesale_client_secret',
            'Client Secret',
            array($this, 'client_secret_callback'),
            'codeswholesale',
            'codeswholesale_api_settings'
        );
    }

    public function api_settings_callback() {
        echo '<p>Enter your CodesWholesale API credentials</p>';
    }

    public function client_id_callback() {
        $client_id = get_option('codeswholesale_client_id');
        echo '<input type="text" name="codeswholesale_client_id" value="' . esc_attr($client_id) . '" />';
    }

    public function client_secret_callback() {
        $client_secret = get_option('codeswholesale_client_secret');
        echo '<input type="password" name="codeswholesale_client_secret" value="' . esc_attr($client_secret) . '" />';
    }

    public function options_page() {
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

    /**
     * Get access token from CodesWholesale API
     */
    public function get_access_token() {
        $token_data = get_option('codeswholesale_token_data');
        
        // Check if we have valid token and it's not expired
        if ($token_data && isset($token_data['expires_at']) && time() < $token_data['expires_at']) {
            return $token_data['access_token'];
        }
        
        // If no token or expired, get new one
        $response = wp_remote_post($this->api_url . $this->token_endpoint, array(
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept' => 'application/json'
            ),
            'body' => array(
                'grant_type' => 'client_credentials',
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret
            )
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!isset($data['access_token'])) {
            return false;
        }
        
        // Store token with expiration time
        $token_data = array(
            'access_token' => $data['access_token'],
            'expires_at' => time() + $data['expires_in'] - 60, // Subtract 60 seconds for safety
            'token_type' => $data['token_type']
        );
        
        update_option('codeswholesale_token_data', $token_data);
        
        return $token_data['access_token'];
    }

    /**
     * Get products from CodesWholesale API
     */
    public function get_products($page = 1, $limit = 20) {
        $token = $this->get_access_token();
        if (!$token) {
            return false;
        }
        
        $response = wp_remote_get($this->api_url . $this->products_endpoint . '?page=' . $page . '&size=' . $limit, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json'
            )
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        return isset($data['content']) ? $data['content'] : false;
    }

    /**
     * Create order in CodesWholesale
     */
    public function create_order($order_data) {
        $token = $this->get_access_token();
        if (!$token) {
            return false;
        }
        
        $response = wp_remote_post($this->api_url . $this->orders_endpoint, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ),
            'body' => json_encode($order_data)
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        return $data;
    }

    /**
     * Create database tables for plugin
     */
    public static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE {$wpdb->prefix}codeswholesale_orders (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            order_id varchar(255) NOT NULL,
            product_id varchar(255) NOT NULL,
            status varchar(50) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
}
