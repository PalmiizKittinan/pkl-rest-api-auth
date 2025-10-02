<?php
/**
 * Database handler for PKL WPZ REST API Auth
 */
if (!defined('ABSPATH')) {
    exit;
}

class PKL_WPZ_REST_API_Auth_Database
{
    /**
     * Table name
     */
    private $table_name;

    /**
     * Cache group
     */
    private $cache_group = 'pkl_wpz_api_auth';

    /**
     * Constructor
     */
    public function __construct()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'pkl_wpz_api_keys';
    }

    /**
     * Create tables
     */
    public function create_tables()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Use case-sensitive collation for access_token
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            access_token varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
            revoked tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY access_token (access_token),
            KEY user_id (user_id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        // Update existing table if needed
        $this->update_token_collation();
    }

    /**
     * Update access_token column to use case-sensitive collation
     */
    private function update_token_collation()
    {
        global $wpdb;

        // Check if column exists and has wrong collation
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $column_info = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT COLLATION_NAME 
                FROM INFORMATION_SCHEMA.COLUMNS 
                WHERE TABLE_SCHEMA = %s 
                AND TABLE_NAME = %s 
                AND COLUMN_NAME = 'access_token'",
                DB_NAME,
                $this->table_name
            ),
            ARRAY_A
        );

        // If column exists and is not using binary collation, update it
        if ($column_info && $column_info['COLLATION_NAME'] !== 'utf8mb4_bin') {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->query(
                "ALTER TABLE {$this->table_name} 
                MODIFY access_token varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL"
            );
        }
    }

    /**
     * Generate API key
     */
    public function generate_api_key($user_id)
    {
        global $wpdb;

        // Generate unique token with mixed case for better security
        $token = 'pkl_wpz_' . bin2hex(random_bytes(32));

        // Delete existing token for user
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->delete(
            $this->table_name,
            array('user_id' => $user_id),
            array('%d')
        );

        // Insert new token
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $result = $wpdb->insert(
            $this->table_name,
            array(
                'user_id' => $user_id,
                'access_token' => $token,
                'revoked' => 0,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%d', '%s')
        );

        if ($result) {
            // Clear cache
            wp_cache_delete('user_api_key_' . $user_id, $this->cache_group);
            wp_cache_delete('all_tokens', $this->cache_group);
            wp_cache_delete('token_' . md5($token), $this->cache_group);

            return $token;
        }

        return false;
    }

    /**
     * Get user by token (case-sensitive)
     */
    public function get_user_by_token($token)
    {
        // Check cache first
        $cache_key = 'token_' . md5($token);
        $cached = wp_cache_get($cache_key, $this->cache_group);

        if (false !== $cached) {
            return $cached;
        }

        global $wpdb;

        // Use BINARY comparison to ensure case-sensitive matching
        $query = $wpdb->prepare(
            "SELECT ak.*, u.user_login, u.user_email 
            FROM {$wpdb->prefix}pkl_wpz_api_keys ak 
            INNER JOIN {$wpdb->users} u ON ak.user_id = u.ID 
            WHERE BINARY ak.access_token = %s",
            $token
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $result = $wpdb->get_row($query, ARRAY_A);

        // Cache the result (including null results to prevent repeated queries)
        wp_cache_set($cache_key, $result, $this->cache_group, 3600);

        return $result;
    }

    /**
     * Get user API key
     */
    public function get_user_api_key($user_id)
    {
        // Check cache first
        $cache_key = 'user_api_key_' . $user_id;
        $cached = wp_cache_get($cache_key, $this->cache_group);

        if (false !== $cached) {
            return $cached;
        }

        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}pkl_wpz_api_keys WHERE user_id = %d ORDER BY created_at DESC LIMIT 1",
            $user_id
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $result = $wpdb->get_row($query, ARRAY_A);

        // Cache the result
        wp_cache_set($cache_key, $result, $this->cache_group, 3600);

        return $result;
    }

    /**
     * Get all tokens
     */
    public function get_all_tokens()
    {
        // Check cache first
        $cache_key = 'all_tokens';
        $cached = wp_cache_get($cache_key, $this->cache_group);

        if (false !== $cached) {
            return $cached;
        }

        global $wpdb;

        // No need to prepare query without placeholders
        $query = "SELECT ak.*, u.user_login, u.user_email 
                  FROM {$wpdb->prefix}pkl_wpz_api_keys ak 
                  INNER JOIN {$wpdb->users} u ON ak.user_id = u.ID 
                  ORDER BY ak.created_at DESC";

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $results = $wpdb->get_results($query, ARRAY_A);

        // Cache the results
        wp_cache_set($cache_key, $results, $this->cache_group, 600);

        return $results;
    }

    /**
     * Revoke token
     */
    public function revoke_token($id)
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->update(
            $this->table_name,
            array('revoked' => 1),
            array('id' => $id),
            array('%d'),
            array('%d')
        );

        if ($result !== false) {
            // Get token data for cache clearing
            $query = $wpdb->prepare(
                "SELECT user_id, access_token FROM {$wpdb->prefix}pkl_wpz_api_keys WHERE id = %d",
                $id
            );

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
            $token_data = $wpdb->get_row($query, ARRAY_A);

            if ($token_data) {
                // Clear cache
                wp_cache_delete('user_api_key_' . $token_data['user_id'], $this->cache_group);
                wp_cache_delete('all_tokens', $this->cache_group);
                wp_cache_delete('token_' . md5($token_data['access_token']), $this->cache_group);
            }
        }

        return $result;
    }

    /**
     * Restore token
     */
    public function restore_token($id)
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->update(
            $this->table_name,
            array('revoked' => 0),
            array('id' => $id),
            array('%d'),
            array('%d')
        );

        if ($result !== false) {
            // Get token data for cache clearing
            $query = $wpdb->prepare(
                "SELECT user_id, access_token FROM {$wpdb->prefix}pkl_wpz_api_keys WHERE id = %d",
                $id
            );

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
            $token_data = $wpdb->get_row($query, ARRAY_A);

            if ($token_data) {
                // Clear cache
                wp_cache_delete('user_api_key_' . $token_data['user_id'], $this->cache_group);
                wp_cache_delete('all_tokens', $this->cache_group);
                wp_cache_delete('token_' . md5($token_data['access_token']), $this->cache_group);
            }
        }

        return $result;
    }

    /**
     * Delete token
     */
    public function delete_token($id)
    {
        global $wpdb;

        // Get token data before deletion for cache clearing
        $query = $wpdb->prepare(
            "SELECT user_id, access_token FROM {$wpdb->prefix}pkl_wpz_api_keys WHERE id = %d",
            $id
        );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $token_data = $wpdb->get_row($query, ARRAY_A);

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $result = $wpdb->delete(
            $this->table_name,
            array('id' => $id),
            array('%d')
        );

        if ($result !== false && $token_data) {
            // Clear cache
            wp_cache_delete('user_api_key_' . $token_data['user_id'], $this->cache_group);
            wp_cache_delete('all_tokens', $this->cache_group);
            wp_cache_delete('token_' . md5($token_data['access_token']), $this->cache_group);
        }

        return $result;
    }

    /**
     * Clear all cache
     */
    public function clear_cache()
    {
        wp_cache_delete('all_tokens', $this->cache_group);
    }
}