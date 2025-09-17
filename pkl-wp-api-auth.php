<?php
/**
 * Plugin Name: PKL REST API Auth
 * Plugin URI: https://github.com/PalmiizKittinan/pkl-wp-rest-api-auth
 * Description: Control WordPress REST API access by requiring user authentication. Disable API access for non-logged-in users with customizable settings.
 * Version: 1.0.0
 * Author: Kittinan Lamkaek
 * Author URI: https://github.com/PalmiizKittinan
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: pkl-rest-api-auth
 * Requires at least: 5.0
 * Tested up to: 6.8
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('PKL_REST_API_AUTH_VERSION', '1.0.0');
define('PKL_REST_API_AUTH_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PKL_REST_API_AUTH_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Main plugin class
 */
class PKL_REST_API_Auth {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Initialize the plugin
     */
    public function init() {
        // Add settings page
        if (is_admin()) {
            add_action('admin_menu', array($this, 'add_admin_menu'));
            add_action('admin_init', array($this, 'settings_init'));
        }
        
        // Apply REST API authentication filter
        $this->setup_rest_auth();
    }
    
    /**
     * Setup REST API authentication
     */
    private function setup_rest_auth() {
        $enable_auth = get_option('pkl_rest_api_auth_enable', 1);
        
        if ($enable_auth) {
            add_filter('rest_authentication_errors', array($this, 'restrict_rest_api'));
        }
    }
    
    /**
     * Restrict REST API access
     *
     * @param WP_Error|null|bool $result
     * @return WP_Error|null|bool
     */
    public function restrict_rest_api($result) {
        // If authentication has already failed, return the error
        if (is_wp_error($result)) {
            return $result;
        }
        
        // Check if user is logged in
        if (!is_user_logged_in()) {
            return new WP_Error(
                'rest_not_logged_in',
                __('You are not currently logged in.', 'pkl-rest-api-auth'),
                array('status' => 401)
            );
        }
        
        // Check if user has read capability
        if (!current_user_can('read')) {
            return new WP_Error(
                'rest_insufficient_permissions',
                __('You do not have sufficient permissions to access this API.', 'pkl-rest-api-auth'),
                array('status' => 403)
            );
        }
        
        return $result;
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            __('PKL REST API Auth Settings', 'pkl-rest-api-auth'),
            __('PKL REST API Auth', 'pkl-rest-api-auth'),
            'manage_options',
            'pkl-rest-api-auth',
            array($this, 'settings_page')
        );
    }
    
    /**
     * Sanitize checkbox input
     *
     * @param mixed $input
     * @return int
     */
    public function sanitize_checkbox($input) {
        return isset($input) && $input == 1 ? 1 : 0;
    }
    
    /**
     * Initialize settings
     */
    public function settings_init() {
        register_setting(
            'pkl_rest_api_auth', 
            'pkl_rest_api_auth_enable',
            array(
                'sanitize_callback' => array($this, 'sanitize_checkbox'),
                'default' => 1
            )
        );
        
        add_settings_section(
            'pkl_rest_api_auth_section',
            __('REST API Authentication Settings', 'pkl-rest-api-auth'),
            array($this, 'settings_section_callback'),
            'pkl_rest_api_auth'
        );
        
        add_settings_field(
            'pkl_rest_api_auth_enable',
            __('Enable REST API Authentication', 'pkl-rest-api-auth'),
            array($this, 'enable_field_callback'),
            'pkl_rest_api_auth',
            'pkl_rest_api_auth_section'
        );
    }
    
    /**
     * Settings section callback
     */
    public function settings_section_callback() {
        echo '<p>' . esc_html__('Configure REST API authentication settings.', 'pkl-rest-api-auth') . '</p>';
    }
    
    /**
     * Enable field callback
     */
    public function enable_field_callback() {
        $enable = get_option('pkl_rest_api_auth_enable', 1);
        echo '<input type="checkbox" id="pkl_rest_api_auth_enable" name="pkl_rest_api_auth_enable" value="1" ' . checked(1, $enable, false) . ' />';
        echo '<label for="pkl_rest_api_auth_enable">' . esc_html__('Require authentication for REST API access', 'pkl-rest-api-auth') . '</label>';
        echo '<p class="description">' . esc_html__('When enabled, only logged-in users can access the WordPress REST API.', 'pkl-rest-api-auth') . '</p>';
    }
    
    /**
     * Settings page
     */
    public function settings_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('pkl_rest_api_auth');
                do_settings_sections('pkl_rest_api_auth');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Set default options
        add_option('pkl_rest_api_auth_enable', 1);
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clean up if needed (optional)
    }
}

// Initialize the plugin
new PKL_REST_API_Auth();
