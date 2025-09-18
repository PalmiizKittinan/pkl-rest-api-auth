<?php
/**
 * Plugin Name: PKL REST API Auth
 * Plugin URI: https://github.com/PalmiizKittinan/pkl-rest-api-auth
 * Description: Control WordPress REST API access by requiring user authentication with OAuth token system.
 * Version: 2.0.0
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
define('PKL_REST_API_AUTH_VERSION', '2.0.0');
define('PKL_REST_API_AUTH_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PKL_REST_API_AUTH_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PKL_REST_API_AUTH_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main plugin class
 */
class PKL_REST_API_Auth
{

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
     * Get single instance
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Load dependencies
     */
    private function load_dependencies()
    {
        require_once PKL_REST_API_AUTH_PLUGIN_DIR . 'includes/class-database.php';
        require_once PKL_REST_API_AUTH_PLUGIN_DIR . 'includes/class-oauth-api.php';
        require_once PKL_REST_API_AUTH_PLUGIN_DIR . 'includes/class-admin-page.php';

        $this->database = new PKL_REST_API_Auth_Database();
        $this->oauth_api = new PKL_REST_API_Auth_OAuth_API($this->database);
        $this->admin_page = new PKL_REST_API_Auth_Admin_Page($this->database);
    }

    /**
     * Initialize hooks
     */
    private function init_hooks()
    {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        add_action('init', array($this, 'init'));
    }

    /**
     * Initialize the plugin
     */
    public function init()
    {
        // Load text domain
        load_plugin_textdomain('pkl-rest-api-auth', false, dirname(PKL_REST_API_AUTH_PLUGIN_BASENAME) . '/languages');

        // Initialize components
        $this->oauth_api->init();

        if (is_admin()) {
            $this->admin_page->init();
        }

        // Apply REST API authentication filter
        $this->setup_rest_auth();
    }

    /**
     * Setup REST API authentication
     */
    private function setup_rest_auth()
    {
        $enable_auth = get_option('pkl_rest_api_auth_enable', 1);

        if ($enable_auth) {
            add_filter('rest_authentication_errors', array($this, 'restrict_rest_api'));
        }
    }

    /**
     * Check if access token authentication is provided
     */
    private function check_token_auth()
    {
        $access_token = '';

        // Check in form-data
        if (isset($_POST['access_token']) && !empty($_POST['access_token'])) {
            $access_token = sanitize_text_field(wp_unslash($_POST['access_token']));
        }

        // Check in headers
        if (empty($access_token)) {
            $headers = getallheaders();
            if (is_array($headers)) {
                if (isset($headers['Authorization'])) {
                    $auth_header = $headers['Authorization'];
                    if (strpos($auth_header, 'Bearer ') === 0) {
                        $access_token = sanitize_text_field(substr($auth_header, 7));
                    }
                } elseif (isset($headers['X-Access-Token'])) {
                    $access_token = sanitize_text_field($headers['X-Access-Token']);
                }
            }
        }

        // Check in query parameters
        if (empty($access_token) && isset($_GET['access_token'])) {
            $access_token = sanitize_text_field(wp_unslash($_GET['access_token']));
        }

        if (!empty($access_token)) {
            $user = $this->database->get_user_by_token($access_token);
            if ($user && !$user['revoked']) {
                return get_user_by('login', $user['user_login']);
            }
        }

        return false;
    }

    /**
     * Check if email authentication is provided (legacy support)
     */
    private function check_email_auth()
    {
        $email = '';

        // Check in $_POST
        if (isset($_POST['email']) && !empty($_POST['email'])) {
            $email = sanitize_email(wp_unslash($_POST['email']));
        }

        // Check in headers
        if (empty($email)) {
            $headers = getallheaders();
            if (is_array($headers)) {
                if (isset($headers['X-Email'])) {
                    $email = sanitize_email($headers['X-Email']);
                }
            }
        }

        // Check in query parameters
        if (empty($email) && isset($_GET['email'])) {
            $email = sanitize_email(wp_unslash($_GET['email']));
        }

        if (!empty($email) && is_email($email)) {
            $user = get_user_by('email', $email);
            if ($user && !is_wp_error($user)) {
                return $user;
            }
        }

        return false;
    }

    /**
     * Restrict REST API access
     */
    public function restrict_rest_api($result)
    {
        // If authentication has already failed, return the error
        if (is_wp_error($result)) {
            return $result;
        }

        // Skip authentication for OAuth token endpoint
        $request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';
        if (strpos($request_uri, '/wp-json/oauth/token') !== false) {
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

        // Check for access token authentication (priority)
        $user = $this->check_token_auth();
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

        // Check for email authentication (legacy support)
        $user = $this->check_email_auth();
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
            __('You are not currently logged in. Please provide a valid access token or email address.', 'pkl-rest-api-auth'),
            array('status' => 401)
        );
    }

    /**
     * Plugin activation
     */
    public function activate()
    {
        // Create database tables
        $this->database->create_tables();

        // Set default options
        add_option('pkl_rest_api_auth_enable', 1);
    }

    /**
     * Plugin deactivation
     */
    public function deactivate()
    {
        // Clean up if needed
    }
}

// Initialize the plugin
function pkl_rest_api_auth()
{
    return PKL_REST_API_Auth::get_instance();
}

pkl_rest_api_auth();