<?php
/**
 * Database handler for PKL REST API Auth
 */

if (!defined('ABSPATH')) {
    exit;
}

class PKL_REST_API_Auth_Database
{

    /**
     * Table name
     */
    private $table_name;

    /**
     * Constructor
     */
    public function __construct()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'pkl_rest_api_auth_tokens';
    }

    /**
     * Create database tables
     */
    public function create_tables()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = $wpdb->prepare(
            "CREATE TABLE %i (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                user_login varchar(60) NOT NULL,
                user_email varchar(100) NOT NULL,
                access_token varchar(255) NOT NULL,
                revoked tinyint(1) NOT NULL DEFAULT 0,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY user_login (user_login),
                UNIQUE KEY access_token (access_token),
                KEY user_email (user_email)
            ) $charset_collate",
            $this->table_name
        );

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Generate API key for user
     */
    public function generate_api_key($user_id) {
        global $wpdb;

        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }

        // Generate unique API key
        $api_key = 'pkl_' . wp_generate_password(32, false);

        // Check if user already has an API key
        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT id, revoked FROM %i WHERE user_login = %s",
                $this->table_name,
                $user->user_login
            )
        );

        if ($existing) {
            // Keep the previous revoked status when updating
            $revoked_status = $existing->revoked;

            // Update existing API key
            $result = $wpdb->update(
                $this->table_name,
                array(
                    'access_token' => $api_key,
                    'revoked' => $revoked_status,
                    'created_at' => current_time('mysql')
                ),
                array('user_login' => $user->user_login),
                array('%s', '%d', '%s'),
                array('%s')
            );
        } else {
            // Insert new API key
            $result = $wpdb->insert(
                $this->table_name,
                array(
                    'user_login' => $user->user_login,
                    'user_email' => $user->user_email,
                    'access_token' => $api_key,
                    'revoked' => 0,
                    'created_at' => current_time('mysql')
                ),
                array('%s', '%s', '%s', '%d', '%s')
            );
        }

        if ($result !== false) {
            return $api_key;
        }

        return false;
    }

    /**
     * Get user by token
     */
    public function get_user_by_token($access_token) {
        global $wpdb;

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM %i WHERE access_token = %s COLLATE utf8mb4_bin",
                $this->table_name,
                $access_token
            ),
            ARRAY_A
        );
    }

    /**
     * Get all tokens
     */
    public function get_all_tokens() {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM %i ORDER BY created_at DESC",
                $this->table_name
            ),
            ARRAY_A
        );
    }

    /**
     * Revoke token
     */
    public function revoke_token($id) {
        global $wpdb;

        return $wpdb->update(
            $this->table_name,
            array('revoked' => 1),
            array('id' => $id),
            array('%d'),
            array('%d')
        );
    }

    /**
     * Restore token
     */
    public function restore_token($id) {
        global $wpdb;

        return $wpdb->update(
            $this->table_name,
            array('revoked' => 0),
            array('id' => $id),
            array('%d'),
            array('%d')
        );
    }

    /**
     * Delete token
     */
    public function delete_token($id) {
        global $wpdb;

        return $wpdb->delete(
            $this->table_name,
            array('id' => $id),
            array('%d')
        );
    }

    /**
     * Get user's API key
     */
    public function get_user_api_key($user_id) {
        global $wpdb;

        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM %i WHERE user_login = %s",
                $this->table_name,
                $user->user_login
            ),
            ARRAY_A
        );
    }
}
