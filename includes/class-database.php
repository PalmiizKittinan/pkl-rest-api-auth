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
        $this->table_name = $wpdb->prefix . 'pklrest_api_keys';
    }

    /**
     * Create database table
     */
    public function create_table()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            access_token varchar(64) NOT NULL,
            created_at datetime NOT NULL,
            revoked tinyint(1) NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            UNIQUE KEY access_token (access_token),
            KEY user_id (user_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Generate API key for user
     */
    public function generate_api_key($user_id)
    {
        global $wpdb;

        // Generate secure random token
        $access_token = bin2hex(random_bytes(32));

        // Delete existing tokens for this user
        $wpdb->delete(
            $this->table_name,
            array('user_id' => $user_id),
            array('%d')
        );

        // Insert new token
        $result = $wpdb->insert(
            $this->table_name,
            array(
                'user_id' => $user_id,
                'access_token' => $access_token,
                'created_at' => current_time('mysql'),
                'revoked' => 0
            ),
            array('%d', '%s', '%s', '%d')
        );

        if ($result === false) {
            return false;
        }

        return $access_token;
    }

    /**
     * Validate API key
     */
    public function validate_api_key($access_token)
    {
        global $wpdb;

        $token_data = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE access_token = %s AND revoked = 0",
                $access_token
            ),
            ARRAY_A
        );

        if (!$token_data) {
            return false;
        }

        return $token_data;
    }

    /**
     * Get user's API key
     */
    public function get_user_api_key($user_id)
    {
        global $wpdb;

        $api_key = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE user_id = %d ORDER BY created_at DESC LIMIT 1",
                $user_id
            ),
            ARRAY_A
        );

        return $api_key;
    }

    /**
     * Revoke token
     */
    public function revoke_token($token_id)
    {
        global $wpdb;

        return $wpdb->update(
            $this->table_name,
            array('revoked' => 1),
            array('id' => $token_id),
            array('%d'),
            array('%d')
        );
    }

    /**
     * Get all API keys with user info
     */
    public function get_all_api_keys()
    {
        global $wpdb;

        $results = $wpdb->get_results(
            "SELECT k.*, u.user_login, u.user_email, u.display_name 
            FROM {$this->table_name} k
            LEFT JOIN {$wpdb->users} u ON k.user_id = u.ID
            ORDER BY k.created_at DESC",
            ARRAY_A
        );

        return $results;
    }

    /**
     * Delete API key
     */
    public function delete_api_key($token_id)
    {
        global $wpdb;

        return $wpdb->delete(
            $this->table_name,
            array('id' => $token_id),
            array('%d')
        );
    }

    /**
     * Search API keys
     */
    public function search_api_keys($search_term)
    {
        global $wpdb;

        $search_term = '%' . $wpdb->esc_like($search_term) . '%';

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT k.*, u.user_login, u.user_email, u.display_name 
                FROM {$this->table_name} k
                LEFT JOIN {$wpdb->users} u ON k.user_id = u.ID
                WHERE u.user_login LIKE %s 
                   OR u.user_email LIKE %s 
                   OR u.display_name LIKE %s
                   OR k.access_token LIKE %s
                ORDER BY k.created_at DESC",
                $search_term,
                $search_term,
                $search_term,
                $search_term
            ),
            ARRAY_A
        );

        return $results;
    }
}