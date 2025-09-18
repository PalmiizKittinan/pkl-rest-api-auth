<?php
/**
 * Database handler for PKL REST API Auth
 */

if (!defined('ABSPATH')) {
	exit;
}

class PKL_REST_API_Auth_Database {

	/**
	 * Table name
	 */
	private $table_name;

	/**
	 * Constructor
	 */
	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'pkl_rest_api_auth_tokens';
	}

	/**
	 * Create database tables
	 */
	public function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Use esc_sql for table name to avoid interpolation warning
		$table_name = esc_sql($this->table_name);

		$sql = "CREATE TABLE {$table_name} (
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
        ) {$charset_collate}";

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

		// Use esc_sql for table name
		$table_name = esc_sql($this->table_name);

		// Check if user already has an API key
		$existing = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, revoked FROM {$table_name} WHERE user_login = %s",
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
			// Clear relevant caches
			$this->clear_user_cache($user->user_login);
			return $api_key;
		}

		return false;
	}

	/**
	 * Get user by token
	 */
	public function get_user_by_token($access_token) {
		global $wpdb;

		// Create cache key
		$cache_key = 'pkl_api_token_' . md5($access_token);
		$cached_result = wp_cache_get($cache_key, 'pkl_rest_api_auth');

		if ($cached_result !== false) {
			return $cached_result;
		}

		// Use esc_sql for table name
		$table_name = esc_sql($this->table_name);

		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE access_token = %s COLLATE utf8mb4_bin",
				$access_token
			),
			ARRAY_A
		);

		// Cache the result for 5 minutes
		wp_cache_set($cache_key, $result, 'pkl_rest_api_auth', 300);

		return $result;
	}

	/**
	 * Get all tokens
	 */
	public function get_all_tokens() {
		global $wpdb;

		$cache_key = 'pkl_all_tokens';
		$cached_result = wp_cache_get($cache_key, 'pkl_rest_api_auth');

		if ($cached_result !== false) {
			return $cached_result;
		}

		// Use esc_sql for table name
		$table_name = esc_sql($this->table_name);

		$result = $wpdb->get_results(
			"SELECT * FROM {$table_name} ORDER BY created_at DESC",
			ARRAY_A
		);

		// Cache the result for 2 minutes
		wp_cache_set($cache_key, $result, 'pkl_rest_api_auth', 120);

		return $result;
	}

	/**
	 * Revoke token
	 */
	public function revoke_token($id) {
		global $wpdb;

		$result = $wpdb->update(
			$this->table_name,
			array('revoked' => 1),
			array('id' => $id),
			array('%d'),
			array('%d')
		);

		if ($result !== false) {
			$this->clear_all_caches();
		}

		return $result;
	}

	/**
	 * Restore token
	 */
	public function restore_token($id) {
		global $wpdb;

		$result = $wpdb->update(
			$this->table_name,
			array('revoked' => 0),
			array('id' => $id),
			array('%d'),
			array('%d')
		);

		if ($result !== false) {
			$this->clear_all_caches();
		}

		return $result;
	}

	/**
	 * Delete token
	 */
	public function delete_token($id) {
		global $wpdb;

		$result = $wpdb->delete(
			$this->table_name,
			array('id' => $id),
			array('%d')
		);

		if ($result !== false) {
			$this->clear_all_caches();
		}

		return $result;
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

		$cache_key = 'pkl_user_api_key_' . $user_id;
		$cached_result = wp_cache_get($cache_key, 'pkl_rest_api_auth');

		if ($cached_result !== false) {
			return $cached_result;
		}

		// Use esc_sql for table name
		$table_name = esc_sql($this->table_name);

		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE user_login = %s",
				$user->user_login
			),
			ARRAY_A
		);

		// Cache the result for 5 minutes
		wp_cache_set($cache_key, $result, 'pkl_rest_api_auth', 300);

		return $result;
	}

	/**
	 * Clear user-specific cache
	 */
	private function clear_user_cache($user_login) {
		$user = get_user_by('login', $user_login);
		if ($user) {
			wp_cache_delete('pkl_user_api_key_' . $user->ID, 'pkl_rest_api_auth');
		}
	}

	/**
	 * Clear all caches
	 */
	private function clear_all_caches() {
		wp_cache_delete('pkl_all_tokens', 'pkl_rest_api_auth');

		// Clear token-specific caches would require knowing all tokens
		// For now, we'll rely on cache expiration
	}
}