<?php
/**
 * REST API authenticator for PKL REST API Auth
 */
if (!defined('ABSPATH')) {
    exit;
}

class PKL_REST_API_Auth_Authenticator
{
    /**
     * Database handler
     */
    private $database;

    /**
     * Constructor
     */
    public function __construct($database)
    {
        $this->database = $database;
    }

    /**
     * Initialize
     */
    public function init()
    {
        add_filter('determine_current_user', array($this, 'determine_current_user'), 20);
        add_filter('rest_authentication_errors', array($this, 'rest_authentication_errors'));
    }

    /**
     * Determine current user from API key
     */
    public function determine_current_user($user_id)
    {
        // If user is already determined, return it
        if ($user_id) {
            return $user_id;
        }

        // Only authenticate for REST API requests
        if (!defined('REST_REQUEST') || !REST_REQUEST) {
            return $user_id;
        }

        // Get access token from Authorization header
        $access_token = $this->get_access_token();

        if (!$access_token) {
            return $user_id;
        }

        // Validate the token
        $token_data = $this->database->validate_api_key($access_token);

        if (!$token_data) {
            return $user_id;
        }

        return $token_data['user_id'];
    }

    /**
     * Handle REST API authentication errors
     */
    public function rest_authentication_errors($result)
    {
        // If already an error, return it
        if (is_wp_error($result)) {
            return $result;
        }

        // If user is already authenticated, return the result
        if (get_current_user_id()) {
            return $result;
        }

        // Get access token
        $access_token = $this->get_access_token();

        // If no token provided, don't interfere with other auth methods
        if (!$access_token) {
            return $result;
        }

        // Validate the token
        $token_data = $this->database->validate_api_key($access_token);

        if (!$token_data) {
            return new WP_Error(
                'pklrest_invalid_token',
                __('Invalid or revoked API key.', 'pklrest-rest-api-auth'),
                array('status' => 401)
            );
        }

        return $result;
    }

    /**
     * Get access token from request headers
     */
    private function get_access_token()
    {
        // Check Authorization header
        $authorization = $this->get_authorization_header();

        if (!$authorization) {
            return false;
        }

        // Extract Bearer token
        if (preg_match('/Bearer\s+(.+)/i', $authorization, $matches)) {
            return trim($matches[1]);
        }

        return false;
    }

    /**
     * Get Authorization header
     */
    private function get_authorization_header()
    {
        // Try different methods to get the Authorization header
        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            return $_SERVER['HTTP_AUTHORIZATION'];
        }

        if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            return $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }

        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            if (isset($headers['Authorization'])) {
                return $headers['Authorization'];
            }
            if (isset($headers['authorization'])) {
                return $headers['authorization'];
            }
        }

        return false;
    }
}