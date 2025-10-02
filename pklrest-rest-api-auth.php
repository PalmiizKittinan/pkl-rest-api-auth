<?php
/**
 * Plugin Name: PKLREST REST API Auth
 * Plugin URI: https://wordpress.org/plugins/pklrest-rest-api-auth/
 * Description: Simple REST API authentication using API keys
 * Version: 1.0.0
 * Author: kittlam
 * Author URI: https://profiles.wordpress.org/kittlam/
 * Text Domain: pklrest-rest-api-auth
 * Domain Path: /languages
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 5.0
 * Requires PHP: 7.2
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define plugin constants
define('PKL_REST_API_AUTH_VERSION', '1.0.0');
define('PKL_REST_API_AUTH_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PKL_REST_API_AUTH_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PKL_REST_API_AUTH_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Include required files
require_once PKL_REST_API_AUTH_PLUGIN_DIR . 'includes/class-database.php';
require_once PKL_REST_API_AUTH_PLUGIN_DIR . 'includes/class-authenticator.php';
require_once PKL_REST_API_AUTH_PLUGIN_DIR . 'includes/class-user-profile.php';
require_once PKL_REST_API_AUTH_PLUGIN_DIR . 'includes/class-admin.php';
require_once PKL_REST_API_AUTH_PLUGIN_DIR . 'includes/class-admin-page.php';

/**
 * Main plugin class
 */
class PKL_REST_API_Auth_Plugin
{
    /**
     * Plugin instance
     */
    private static $instance = null;

    /**
     * Database handler
     */
    private $database;

    /**
     * Authenticator
     */
    private $authenticator;

    /**
     * User profile handler
     */
    private $user_profile;

    /**
     * Admin handler
     */
    private $admin;

    /**
     * Admin page handler
     */
    private $admin_page;

    /**
     * Get plugin instance
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
        // Initialize components
        $this->database = new PKL_REST_API_Auth_Database();
        $this->authenticator = new PKL_REST_API_Auth_Authenticator($this->database);
        $this->user_profile = new PKL_REST_API_Auth_User_Profile($this->database);
        $this->admin = new PKL_REST_API_Auth_Admin($this->database);
        $this->admin_page = new PKL_REST_API_Auth_Admin_Page($this->database);

        // Register activation/deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Initialize plugin
        add_action('plugins_loaded', array($this, 'init'));
        add_action('init', array($this, 'load_textdomain'));
    }

    /**
     * Initialize plugin
     */
    public function init()
    {
        $this->authenticator->init();
        $this->user_profile->init();
        $this->admin->init();
        $this->admin_page->init();
    }

    /**
     * Load plugin textdomain
     */
    public function load_textdomain()
    {
        load_plugin_textdomain(
            'pklrest-rest-api-auth',
            false,
            dirname(PKL_REST_API_AUTH_PLUGIN_BASENAME) . '/languages'
        );
    }

    /**
     * Plugin activation
     */
    public function activate()
    {
        $this->database->create_table();

        // Set default options
        $default_options = array(
            'enable_api_auth' => true,
            'allowed_user_roles' => array('administrator', 'editor', 'author')
        );

        if (!get_option('pklrest_rest_api_auth_options')) {
            add_option('pklrest_rest_api_auth_options', $default_options);
        }

        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate()
    {
        flush_rewrite_rules();
    }

    /**
     * Get database instance
     */
    public function get_database()
    {
        return $this->database;
    }
}

/**
 * Initialize the plugin
 */
function pklrest_rest_api_auth_init()
{
    return PKL_REST_API_Auth_Plugin::get_instance();
}

// Start the plugin
pklrest_rest_api_auth_init();