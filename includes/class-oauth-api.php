<?php
/**
 * OAuth API handler for PKL REST API Auth
 */

if (!defined('ABSPATH')) {
    exit;
}

class PKL_REST_API_Auth_OAuth_API
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
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    /**
     * Register REST routes
     */
    public function register_routes()
    {
        register_rest_route('oauth', '/token', array(
            'methods' => 'POST',
            'callback' => array($this, 'generate_token'),
            'permission_callback' => '__return_true',
            'args' => array(
                'email' => array(
                    'required' => true,
                    'type' => 'string',
                    'format' => 'email',
                    'sanitize_callback' => 'sanitize_email',
                    'validate_callback' => function ($param) {
                        return is_email($param);
                    }
                )
            )
        ));
    }

    /**
     * Generate access token
     */
    public function generate_token($request)
    {
        $email = $request->get_param('email');

        // Verify user exists
        $user = get_user_by('email', $email);
        if (!$user) {
            return new WP_Error(
                'invalid_email',
                __('User with this email address does not exist.', 'pkl-rest-api-auth'),
                array('status' => 404)
            );
        }

        // Generate token
        $access_token = $this->database->generate_access_token($email);

        if (!$access_token) {
            return new WP_Error(
                'token_generation_failed',
                __('Failed to generate access token.', 'pkl-rest-api-auth'),
                array('status' => 500)
            );
        }

        return rest_ensure_response(array(
            'access_token' => $access_token,
            'token_type' => 'Bearer',
            'user' => array(
                'id' => $user->ID,
                'login' => $user->user_login,
                'email' => $user->user_email,
                'display_name' => $user->display_name
            ),
            'created_at' => current_time('mysql')
        ));
    }
}