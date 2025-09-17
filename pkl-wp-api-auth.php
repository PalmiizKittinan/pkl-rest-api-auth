<?php
/**
 * Plugin Name: PKL REST API Auth
 * Plugin URI: https://github.com/PalmiizKittinan/pkl-rest-api-auth
 * Description: Control WordPress REST API access by requiring user authentication. Disable API access for non-logged-in users with customizable settings.
 * Version: 1.1.0
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
define('PKL_REST_API_AUTH_VERSION', '1.1.0');
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
     * Check if email authentication is provided
     *
     * @return bool|WP_User
     */
    private function check_email_auth() {
        $email = '';
        
        // Check in $_POST (with proper sanitization and unslashing)
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- This is for API authentication, not form processing
        if (isset($_POST['email']) && !empty($_POST['email'])) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.NonceVerification.Missing
            $email = sanitize_email(wp_unslash($_POST['email']));
        }
        
        // Check in raw input for form-data
        if (empty($email)) {
            $input = file_get_contents('php://input');
            $content_type = isset($_SERVER['CONTENT_TYPE']) ? sanitize_text_field(wp_unslash($_SERVER['CONTENT_TYPE'])) : '';
            
            if (strpos($content_type, 'multipart/form-data') !== false) {
                // Parse multipart form data manually
                $boundary = substr($input, 0, strpos($input, "\r\n"));
                $parts = array_slice(explode($boundary, $input), 1);
                
                foreach ($parts as $part) {
                    if (strpos($part, 'name="email"') !== false) {
                        $lines = explode("\r\n", $part);
                        foreach ($lines as $line) {
                            if (!empty(trim($line)) && strpos($line, 'name=') === false && strpos($line, 'Content-Disposition') === false) {
                                $email = sanitize_email(trim($line));
                                break;
                            }
                        }
                        break;
                    }
                }
            }
        }
        
        // Check in headers
        if (empty($email)) {
            $headers = getallheaders();
            if (is_array($headers)) {
                if (isset($headers['X-Email'])) {
                    $email = sanitize_email($headers['X-Email']);
                } elseif (isset($headers['x-email'])) {
                    $email = sanitize_email($headers['x-email']);
                }
            }
        }
        
        // Check in query parameters (with proper sanitization and unslashing)
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- This is for API authentication, not form processing
        if (empty($email) && isset($_GET['email'])) {
            // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.NonceVerification.Recommended
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
     *
     * @param WP_Error|null|bool $result
     * @return WP_Error|null|bool
     */
    public function restrict_rest_api($result) {
        // If authentication has already failed, return the error
        if (is_wp_error($result)) {
            return $result;
        }
        
        // Check if user is logged in (traditional authentication)
        if (is_user_logged_in()) {
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
        
        // Check for email authentication
        $user = $this->check_email_auth();
        if ($user) {
            // Set the current user for this request
            wp_set_current_user($user->ID);
            
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
        
        // No authentication found
        return new WP_Error(
            'rest_not_logged_in',
            __('You are not currently logged in. Please provide a valid email address.', 'pkl-rest-api-auth'),
            array('status' => 401)
        );
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
        echo '<div class="notice notice-info inline">';
        echo '<p><strong>' . esc_html__('Authentication Methods:', 'pkl-rest-api-auth') . '</strong></p>';
        echo '<ul style="list-style-type: disc; margin-left: 20px;">';
        echo '<li>' . esc_html__('Traditional WordPress login (cookies)', 'pkl-rest-api-auth') . '</li>';
        echo '<li>' . esc_html__('Email authentication via form-data (key: email)', 'pkl-rest-api-auth') . '</li>';
        echo '<li>' . esc_html__('Email authentication via header (X-Email)', 'pkl-rest-api-auth') . '</li>';
        echo '<li>' . esc_html__('Email authentication via query parameter (?email=)', 'pkl-rest-api-auth') . '</li>';
        echo '</ul>';
        echo '</div>';
    }
    
    /**
     * Enable field callback
     */
    public function enable_field_callback() {
        $enable = get_option('pkl_rest_api_auth_enable', 1);
        echo '<input type="checkbox" id="pkl_rest_api_auth_enable" name="pkl_rest_api_auth_enable" value="1" ' . checked(1, $enable, false) . ' />';
        echo '<label for="pkl_rest_api_auth_enable">' . esc_html__('Require authentication for REST API access', 'pkl-rest-api-auth') . '</label>';
        echo '<p class="description">' . esc_html__('When enabled, only logged-in users or users with valid email authentication can access the WordPress REST API.', 'pkl-rest-api-auth') . '</p>';
    }
    
    /**
     * Settings page
     */
    public function settings_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="notice notice-warning">
                <h3><?php esc_html_e('🛠️ Developer Guide', 'pkl-rest-api-auth'); ?></h3>
                <h4><?php esc_html_e('🌐 For Development API Platform', 'pkl-rest-api-auth'); ?></h4>
                <h5><?php esc_html_e('🛡️ Credential Method with Registered Email', 'pkl-rest-api-auth'); ?></h5>
                
                <h4><?php esc_html_e('Method 1: Form-data (Recommended for Postman)', 'pkl-rest-api-auth'); ?></h4>
                <p><?php esc_html_e('Add to Body > form-data:', 'pkl-rest-api-auth'); ?></p>
                <code>Key: email | Value: user@example.com</code>
                
                <h4><?php esc_html_e('Method 2: Header', 'pkl-rest-api-auth'); ?></h4>
                <p><?php esc_html_e('Add to Headers:', 'pkl-rest-api-auth'); ?></p>
                <code>X-Email: user@example.com</code>
                
                <h4><?php esc_html_e('Method 3: Query Parameter', 'pkl-rest-api-auth'); ?></h4>
                <p><?php esc_html_e('Add to URL:', 'pkl-rest-api-auth'); ?></p>
                <code>?email=user@example.com</code>
            </div>
            
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
