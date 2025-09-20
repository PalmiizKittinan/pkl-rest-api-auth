<?php
/**
 * Plugin Name: PKL REST API Auth
 * Plugin URI: https://github.com/PalmiizKittinan/pkl-rest-api-auth
 * Description: Control WordPress REST API access by requiring user authentication with API key system.
 * Version: 2.2.1
 * Author: Kittinan Lamkaek
 * Author URI: https://github.com/PalmiizKittinan
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: pkl-rest-api-auth
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.8
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('PKL_REST_API_AUTH_VERSION', '2.2.0');
define('PKL_REST_API_AUTH_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PKL_REST_API_AUTH_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PKL_REST_API_AUTH_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main plugin class
 */
class PKL_REST_API_Auth {
    
    /**
     * Single instance of the class
     */
    private static $instance = null;
    
    /**
     * Database handler
     */
    public $database;
    
    /**
     * OAuth API handler
     */
    public $oauth_api;
    
    /**
     * Admin page handler
     */
    public $admin_page;
    
    /**
     * User profile handler
     */
    public $user_profile;
    
    /**
     * Get single instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    /**
     * Load dependencies
     */
    private function load_dependencies() {
        require_once PKL_REST_API_AUTH_PLUGIN_DIR . 'includes/class-database.php';
        require_once PKL_REST_API_AUTH_PLUGIN_DIR . 'includes/class-oauth-api.php';
        require_once PKL_REST_API_AUTH_PLUGIN_DIR . 'includes/class-admin-page.php';
        require_once PKL_REST_API_AUTH_PLUGIN_DIR . 'includes/class-user-profile.php';
        
        $this->database = new PKL_REST_API_Auth_Database();
        $this->oauth_api = new PKL_REST_API_Auth_OAuth_API($this->database);
        $this->admin_page = new PKL_REST_API_Auth_Admin_Page($this->database);
        $this->user_profile = new PKL_REST_API_Auth_User_Profile($this->database);
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        add_action('init', array($this, 'init'));
    }
    
    /**
     * Initialize the plugin
     */
    public function init() {
        // WordPress automatically loads translations since 4.6
        // No need to call load_plugin_textdomain() manually
        
        // Initialize components
        $this->oauth_api->init();
        $this->user_profile->init();
        
        if (is_admin()) {
            $this->admin_page->init();
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
     * Check if API key authentication is provided
     */
    private function check_api_key_auth() {
        $api_key = '';

        // Method 1: Check in form-data
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- This is for API authentication, not form processing
        if (isset($_POST['api_key']) && !empty($_POST['api_key'])) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.NonceVerification.Missing
            $api_key = sanitize_text_field(wp_unslash($_POST['api_key']));
        }

        // Method 2 & 4: Check in headers (X-API-Key and Authorization Bearer)
        if (empty($api_key)) {
            $headers = getallheaders();
            if (is_array($headers)) {
                // Method 2: X-API-Key header
                if (isset($headers['X-API-Key'])) {
                    $api_key = sanitize_text_field($headers['X-API-Key']);
                } elseif (isset($headers['x-api-key'])) {
                    $api_key = sanitize_text_field($headers['x-api-key']);
                }
                // Method 4: Authorization Bearer header
                elseif (isset($headers['Authorization'])) {
                    $auth_header = $headers['Authorization'];
                    if (strpos($auth_header, 'Bearer ') === 0) {
                        $api_key = sanitize_text_field(substr($auth_header, 7)); // Remove "Bearer " prefix
                    }
                } elseif (isset($headers['authorization'])) {
                    $auth_header = $headers['authorization'];
                    if (strpos($auth_header, 'Bearer ') === 0) {
                        $api_key = sanitize_text_field(substr($auth_header, 7)); // Remove "Bearer " prefix
                    }
                }
            }
        }

        // Method 3: Check in query parameters
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is for API authentication, not form processing
        if (empty($api_key) && isset($_GET['api_key'])) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.NonceVerification.Recommended
            $api_key = sanitize_text_field(wp_unslash($_GET['api_key']));
        }

        if (!empty($api_key)) {
            $user = $this->database->get_user_by_token($api_key);
            if ($user && !$user['revoked']) {
                return get_user_by('login', $user['user_login']);
            }
        }

        return false;
    }
    
    /**
     * Restrict REST API access
     */
    public function restrict_rest_api($result) {
        // If authentication has already failed, return the error
        if (is_wp_error($result)) {
            return $result;
        }
        
        // Check if user is logged in (traditional authentication)
        if (is_user_logged_in()) {
            if (!current_user_can('read')) {
                return new WP_Error(
                    'rest_insufficient_permissions',
                    __('You do not have sufficient permissions to access this API.', 'pkl-rest-api-auth'),
                    array('status' => 403)
                );
            }
            return $result;
        }
        
        // Check for API key authentication
        $user = $this->check_api_key_auth();
        if ($user) {
            wp_set_current_user($user->ID);
            if (!current_user_can('read')) {
                return new WP_Error(
                    'rest_insufficient_permissions',
                    __('You do not have sufficient permissions to access this API.', 'pkl-rest-api-auth'),
                    array('status' => 403)
                );
            }
            return $result;
        }
        
        // No authentication found
        return new WP_Error(
            'rest_not_logged_in',
            __('You are not currently logged in. Please provide a valid API key via your user profile.', 'pkl-rest-api-auth'),
            array('status' => 401)
        );
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables
        $this->database->create_tables();
        
        // Set default options
        add_option('pkl_rest_api_auth_enable', 1);
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }
}

/**
 * Initialize the plugin
 */
function pkl_rest_api_auth() {
    return PKL_REST_API_Auth::get_instance();
}

// Initialize plugin
pkl_rest_api_auth();



