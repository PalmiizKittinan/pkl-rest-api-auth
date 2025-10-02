<?php
/**
 * Admin page handler for PKL REST API Auth
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PKL_REST_API_Auth_Admin_Page {

	/**
	 * Database handler
	 */
	private $database;

	/**
	 * Constructor
	 */
	public function __construct( $database ) {
		$this->database = $database;
	}

	/**
	 * Initialize
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'settings_init' ) );
		add_action( 'admin_post_pkl_revoke_token', array( $this, 'handle_revoke_token' ) );
		add_action( 'admin_post_pkl_restore_token', array( $this, 'handle_restore_token' ) );
		add_action( 'admin_post_pkl_delete_token', array( $this, 'handle_delete_token' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Enqueue admin assets
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( $hook !== 'settings_page_pkl-rest-api-auth' ) {
			return;
		}

		wp_enqueue_style(
			'pkl-rest-api-auth-admin',
			PKL_REST_API_AUTH_PLUGIN_URL . 'assets/admin.css',
			array(),
			PKL_REST_API_AUTH_VERSION
		);
	}

	/**
	 * Add admin menu
	 */
	public function add_admin_menu() {
		add_options_page(
			__( 'PKL REST API Auth', 'pkl-rest-api-auth' ),
			__( 'PKL REST API Auth', 'pkl-rest-api-auth' ),
			'manage_options',
			'pkl-rest-api-auth',
			array( $this, 'admin_page' )
		);
	}

	/**
	 * Initialize settings
	 */
	public function settings_init() {
		register_setting(
			'pkl_rest_api_auth',
			'pkl_rest_api_auth_enable',
			array(
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
				'default'           => 1
			)
		);

		register_setting(
			'pkl_rest_api_auth',
			'pkl_rest_api_auth_allow_root_endpoint',
			array(
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
				'default'           => 0
			)
		);

		register_setting(
			'pkl_rest_api_auth',
			'pkl_rest_api_auth_allow_pages',
			array(
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
				'default'           => 1
			)
		);

		register_setting(
			'pkl_rest_api_auth',
			'pkl_rest_api_auth_allow_posts',
			array(
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
				'default'           => 1
			)
		);

	}

	/**
	 * Sanitize checkbox
	 */
	public function sanitize_checkbox( $input ) {
		return isset( $input ) && $input == 1 ? 1 : 0;
	}

	/**
	 * Handle revoke token
	 */
	public function handle_revoke_token() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'pkl-rest-api-auth' ) );
		}

		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'pkl_revoke_token' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'pkl-rest-api-auth' ) );
		}

		$id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
		if ( $id > 0 ) {
			$this->database->revoke_token( $id );
		}

		wp_redirect( add_query_arg( 'message', 'revoked', admin_url( 'options-general.php?page=pkl-rest-api-auth' ) ) );
		exit;
	}

	/**
	 * Handle restore token
	 */
	public function handle_restore_token() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'pkl-rest-api-auth' ) );
		}

		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'pkl_restore_token' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'pkl-rest-api-auth' ) );
		}

		$id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
		if ( $id > 0 ) {
			$this->database->restore_token( $id );
		}

		wp_redirect( add_query_arg( 'message', 'restored', admin_url( 'options-general.php?page=pkl-rest-api-auth' ) ) );
		exit;
	}

	/**
	 * Handle delete token
	 */
	public function handle_delete_token() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions.', 'pkl-rest-api-auth' ) );
		}

		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'pkl_delete_token' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'pkl-rest-api-auth' ) );
		}

		$id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
		if ( $id > 0 ) {
			$this->database->delete_token( $id );
		}

		wp_redirect( add_query_arg( 'message', 'deleted', admin_url( 'options-general.php?page=pkl-rest-api-auth' ) ) );
		exit;
	}

	/**
	 * Admin page
	 */
	public function admin_page() {
		// Verify nonce for tab parameter
		$active_tab = 'settings';
		if ( isset( $_GET['tab'] ) && wp_verify_nonce( wp_create_nonce( 'pkl_admin_tab' ), 'pkl_admin_tab' ) ) {
			$active_tab = sanitize_text_field( wp_unslash( $_GET['tab'] ) );
		} elseif ( isset( $_GET['tab'] ) ) {
			$active_tab = sanitize_text_field( wp_unslash( $_GET['tab'] ) );
		}

		$tokens = $this->database->get_all_tokens();

		// Show messages
		if ( isset( $_GET['message'] ) ) {
			if ( ! wp_verify_nonce( wp_create_nonce( 'pkl_admin_message' ), 'pkl_admin_message' ) ) {
				wp_die( 'Security check failed' );
			}

			$message = sanitize_text_field( wp_unslash( $_GET['message'] ) );
			$class   = 'notice-success';
			$text    = '';

			switch ( $message ) {
				case 'revoked':
					$text = __( 'Token revoked successfully.', 'pkl-rest-api-auth' );
					break;
				case 'restored':
					$text = __( 'Token restored successfully.', 'pkl-rest-api-auth' );
					break;
				case 'deleted':
					$text = __( 'Token deleted successfully.', 'pkl-rest-api-auth' );
					break;
			}

			if ( $text ) {
				echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( $text ) . '</p></div>';
			}
		}
		?>
        <div class="wrap">
            <h1><?php esc_html_e( 'PKL REST API Auth', 'pkl-rest-api-auth' ); ?></h1>

            <nav class="nav-tab-wrapper">
                <a href="?page=pkl-rest-api-auth&tab=settings" class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Settings', 'pkl-rest-api-auth' ); ?>
                </a>
                <a href="?page=pkl-rest-api-auth&tab=tokens" class="nav-tab <?php echo $active_tab === 'tokens' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Access Tokens', 'pkl-rest-api-auth' ); ?>
                    <span class="count">(<?php echo count( $tokens ); ?>)</span>
                </a>
                <a href="?page=pkl-rest-api-auth&tab=guide" class="nav-tab <?php echo $active_tab === 'guide' ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'API Guide', 'pkl-rest-api-auth' ); ?>
                </a>
            </nav>

            <div class="tab-content">
				<?php
				switch ( $active_tab ) {
					case 'tokens':
						$this->render_tokens_tab( $tokens );
						break;
					case 'guide':
						$this->render_guide_tab();
						break;
					default:
						$this->render_settings_tab();
						break;
				}
				?>
            </div>
        </div>
		<?php
	}

	/**
	 * Render settings tab
	 */
	private function render_settings_tab() {
		?>
        <form action="options.php" method="post">
			<?php
			settings_fields( 'pkl_rest_api_auth' );
			?>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Enable REST API Authentication', 'pkl-rest-api-auth' ); ?></th>
                    <td>
						<?php $enable = get_option( 'pkl_rest_api_auth_enable', 1 ); ?>
                        <label>
                            <input type="checkbox" name="pkl_rest_api_auth_enable" value="1" <?php checked( 1, $enable ); ?> />
							<?php esc_html_e( 'Require authentication for REST API access', 'pkl-rest-api-auth' ); ?>
                        </label>
                        <p class="description">
							<?php esc_html_e( 'When enabled, only authenticated users can access the WordPress REST API.', 'pkl-rest-api-auth' ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Allow /wp-json/ REST API Endpoint', 'pkl-rest-api-auth' ); ?></th>
                    <td>
						<?php $allow_root = get_option( 'pkl_rest_api_auth_allow_root_endpoint', 0 ); ?>
                        <label>
                            <input type="checkbox" name="pkl_rest_api_auth_allow_root_endpoint" value="1" <?php checked( 1, $allow_root ); ?> />
							<?php esc_html_e( 'Allow public GET access to /wp-json/ endpoint only (without authentication)', 'pkl-rest-api-auth' ); ?>
                        </label>
                        <p class="description">
							<?php esc_html_e( 'When enabled, GET requests to /wp-json/ (root endpoint only) will not require authentication. All other HTTP methods (POST, PUT, DELETE, etc.) and endpoints will still require authentication.', 'pkl-rest-api-auth' ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Allow GET /wp-json/wp/v2/pages Endpoint', 'pkl-rest-api-auth' ); ?></th>
                    <td>
						<?php $allow_pages = get_option( 'pkl_rest_api_auth_allow_pages', 1 ); ?>
                        <label>
                            <input type="checkbox" name="pkl_rest_api_auth_allow_pages" value="1" <?php checked( 1, $allow_pages ); ?> />
							<?php esc_html_e( 'Allow public GET access to /wp-json/wp/v2/pages endpoint (without authentication)', 'pkl-rest-api-auth' ); ?>
                        </label>
                        <p class="description">
							<?php esc_html_e( 'When enabled, GET requests to /wp-json/wp/v2/pages and its sub-endpoints will not require authentication. POST, PUT, DELETE and other methods will still require authentication.', 'pkl-rest-api-auth' ); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Allow GET /wp-json/wp/v2/posts Endpoint', 'pkl-rest-api-auth' ); ?></th>
                    <td>
						<?php $allow_posts = get_option( 'pkl_rest_api_auth_allow_posts', 1 ); ?>
                        <label>
                            <input type="checkbox" name="pkl_rest_api_auth_allow_posts" value="1" <?php checked( 1, $allow_posts ); ?> />
							<?php esc_html_e( 'Allow public GET access to /wp-json/wp/v2/posts endpoint (without authentication)', 'pkl-rest-api-auth' ); ?>
                        </label>
                        <p class="description">
							<?php esc_html_e( 'When enabled, GET requests to /wp-json/wp/v2/posts and its sub-endpoints will not require authentication. POST, PUT, DELETE and other methods will still require authentication.', 'pkl-rest-api-auth' ); ?>
                        </p>
                    </td>
                </tr>
            </table>
			<?php submit_button(); ?>
        </form>
		<?php
	}

	/**
	 * Render tokens tab
	 */
	private function render_tokens_tab( $tokens ) {
		?>
        <div class="pkl-tokens-section">
            <h2><?php esc_html_e( 'Manage Access Tokens', 'pkl-rest-api-auth' ); ?></h2>

			<?php if ( empty( $tokens ) ): ?>
                <div class="notice notice-info inline">
                    <p><?php esc_html_e( 'No access tokens have been generated yet.', 'pkl-rest-api-auth' ); ?></p>
                </div>
			<?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                    <tr>
                        <th><?php esc_html_e( 'User', 'pkl-rest-api-auth' ); ?></th>
                        <th><?php esc_html_e( 'Email', 'pkl-rest-api-auth' ); ?></th>
                        <th><?php esc_html_e( 'Token (Last 8 chars)', 'pkl-rest-api-auth' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'pkl-rest-api-auth' ); ?></th>
                        <th><?php esc_html_e( 'Created', 'pkl-rest-api-auth' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'pkl-rest-api-auth' ); ?></th>
                    </tr>
                    </thead>
                    <tbody>
					<?php foreach ( $tokens as $token ): ?>
                        <tr class="<?php echo $token['revoked'] ? 'pkl-token-revoked' : 'pkl-token-active'; ?>">
                            <td><strong><?php echo esc_html( $token['user_login'] ); ?></strong></td>
                            <td><?php echo esc_html( $token['user_email'] ); ?></td>
                            <td><code>...<?php echo esc_html( substr( $token['access_token'], - 8 ) ); ?></code></td>
                            <td>
								<?php if ( $token['revoked'] ): ?>
                                    <span class="pkl-status-revoked"><?php esc_html_e( 'Revoked', 'pkl-rest-api-auth' ); ?></span>
								<?php else: ?>
                                    <span class="pkl-status-active"><?php esc_html_e( 'Active', 'pkl-rest-api-auth' ); ?></span>
								<?php endif; ?>
                            </td>
                            <td>
								<?php
								if ( function_exists( 'wp_timezone' ) ) {
									$wp_timezone  = wp_timezone();
									$created_date = new DateTime( $api_key_data['created_at'], $wp_timezone );

									echo esc_html( $created_date->format( 'Y-m-d\TH:i:s' ) );
									echo ' ' . esc_html( $wp_timezone->getName() ) . ' ';
									echo esc_html__( '(WordPress Site Time)', 'pkl-rest-api-auth' );
								} else {
									$server_timezone = new DateTimeZone( date_default_timezone_get() );
									$created_date    = new DateTime( $api_key_data['created_at'], $server_timezone );

									echo esc_html( $created_date->format( 'Y-m-d\TH:i:s' ) );
									echo ' ' . esc_html( $server_timezone->getName() ) . ' ';
									echo esc_html__( '(Server Time)', 'pkl-rest-api-auth' );
								}
								?>
                            </td>
                            <td class="pkl-actions">
								<?php if ( $token['revoked'] ): ?>
                                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline;">
                                        <input type="hidden" name="action" value="pkl_restore_token">
                                        <input type="hidden" name="id" value="<?php echo esc_attr( $token['id'] ); ?>">
										<?php wp_nonce_field( 'pkl_restore_token' ); ?>
                                        <button type="submit" class="button button-secondary" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to restore this token?', 'pkl-rest-api-auth' ); ?>')">
											<?php esc_html_e( 'Restore', 'pkl-rest-api-auth' ); ?>
                                        </button>
                                    </form>
								<?php else: ?>
                                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline;">
                                        <input type="hidden" name="action" value="pkl_revoke_token">
                                        <input type="hidden" name="id" value="<?php echo esc_attr( $token['id'] ); ?>">
										<?php wp_nonce_field( 'pkl_revoke_token' ); ?>
                                        <button type="submit" class="button button-secondary" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to revoke this token?', 'pkl-rest-api-auth' ); ?>')">
											<?php esc_html_e( 'Revoke', 'pkl-rest-api-auth' ); ?>
                                        </button>
                                    </form>
								<?php endif; ?>

                                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline;">
                                    <input type="hidden" name="action" value="pkl_delete_token">
                                    <input type="hidden" name="id" value="<?php echo esc_attr( $token['id'] ); ?>">
									<?php wp_nonce_field( 'pkl_delete_token' ); ?>
                                    <button type="submit" class="button button-link-delete"
                                            onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this token? This action cannot be undone.', 'pkl-rest-api-auth' ); ?>')">
										<?php esc_html_e( 'Delete', 'pkl-rest-api-auth' ); ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
					<?php endforeach; ?>
                    </tbody>
                </table>
			<?php endif; ?>
        </div>
		<?php
	}

	/**
	 * Render guide tab
	 */
	private function render_guide_tab() {
		?>
        <div class="pkl-guide-section">
            <h2><?php esc_html_e( 'API Usage Guide', 'pkl-rest-api-auth' ); ?></h2>

            <div class="pkl-guide-box">
                <h3><?php esc_html_e( '🔐 Step 1: Generate API Key', 'pkl-rest-api-auth' ); ?></h3>
                <p><?php esc_html_e( 'Go to your User Profile page and generate an API key:', 'pkl-rest-api-auth' ); ?></p>
                <ol>
                    <li><?php esc_html_e( 'Navigate to Users → Your Profile (or Profile)', 'pkl-rest-api-auth' ); ?></li>
                    <li><?php esc_html_e( 'Scroll down to "REST API Access" section', 'pkl-rest-api-auth' ); ?></li>
                    <li><?php esc_html_e( 'Click "Generate API Key"', 'pkl-rest-api-auth' ); ?></li>
                    <li><?php esc_html_e( 'Copy your API key', 'pkl-rest-api-auth' ); ?></li>
                </ol>
            </div>

            <div class="pkl-guide-box">
                <h3><?php esc_html_e( '🚀 Step 2: Use API Key', 'pkl-rest-api-auth' ); ?></h3>
                <p><?php esc_html_e( 'Include your API key in REST API requests using one of these methods:', 'pkl-rest-api-auth' ); ?></p>

                <h4><?php esc_html_e( 'Method 1: Form-data', 'pkl-rest-api-auth' ); ?></h4>
                <div class="pkl-code-block">
                    <strong>POST</strong> <?php echo esc_html( get_site_url() ); ?>/wp-json/wp/v2/posts
                    <br><br>
                    <strong><?php esc_html_e( 'Form-data:', 'pkl-rest-api-auth' ); ?></strong>
                    <pre>
                        api_key: pkl_abcd1234...
                        title: Test Post
                        content: Post content here
                        status: draft
                    </pre>
                </div>

                <h4><?php esc_html_e( 'Method 2: Header X-API-Key', 'pkl-rest-api-auth' ); ?></h4>
                <div class="pkl-code-block">
                    <strong><?php esc_html_e( 'Headers:', 'pkl-rest-api-auth' ); ?></strong>
                    <pre>X-API-Key: pkl_abcd1234...</pre>
                </div>

                <h4><?php esc_html_e( 'Method 3: Query Parameter', 'pkl-rest-api-auth' ); ?></h4>
                <div class="pkl-code-block">
                    <strong>GET</strong> <?php echo esc_html( get_site_url() ); ?>/wp-json/wp/v2/posts?api_key=pkl_abcd1234...
                </div>

                <h4><?php esc_html_e( 'Method 4: Authorization Bearer Token (Recommended)', 'pkl-rest-api-auth' ); ?></h4>
                <div class="pkl-code-block">
                    <strong><?php esc_html_e( 'Headers:', 'pkl-rest-api-auth' ); ?></strong>
                    <pre>Authorization: Bearer pkl_abcd1234...</pre>
                </div>
            </div>

            <div class="pkl-guide-box">
                <h3><?php esc_html_e( '📝 Example API Calls', 'pkl-rest-api-auth' ); ?></h3>

                <h4><?php esc_html_e( 'Get Posts with Bearer Token:', 'pkl-rest-api-auth' ); ?></h4>
                <div class="pkl-code-block">
                    <strong>GET</strong> <?php echo esc_html( get_site_url() ); ?>/wp-json/wp/v2/posts
                    <br>
                    <strong><?php esc_html_e( 'Headers:', 'pkl-rest-api-auth' ); ?></strong> Authorization: Bearer pkl_abcd1234...
                </div>

                <h4><?php esc_html_e( 'Create Post with Bearer Token:', 'pkl-rest-api-auth' ); ?></h4>
                <div class="pkl-code-block">
                    <strong>POST</strong> <?php echo esc_html( get_site_url() ); ?>/wp-json/wp/v2/posts
                    <br>
                    <strong><?php esc_html_e( 'Headers:', 'pkl-rest-api-auth' ); ?></strong>
                    <pre>
                        Authorization: Bearer pkl_abcd1234...
                        Content-Type: application/json
                    </pre>
                    <strong><?php esc_html_e( 'Body (JSON):', 'pkl-rest-api-auth' ); ?></strong>
                    <pre>
                        {
                          "title": "My New Post",
                          "content": "This is the post content",
                          "status": "draft"
                        }
                    </pre>
                </div>
            </div>

            <div class="notice notice-info inline">
                <p><strong><?php esc_html_e( 'Security Note:', 'pkl-rest-api-auth' ); ?></strong></p>
                <ul>
                    <li><?php esc_html_e( '🥇 Authorization Bearer Token (Most Secure & Standard)', 'pkl-rest-api-auth' ); ?></li>
                    <li><?php esc_html_e( '🥈 X-API-Key Header (Secure)', 'pkl-rest-api-auth' ); ?></li>
                    <li><?php esc_html_e( '🥉 Form-data (Good for testing)', 'pkl-rest-api-auth' ); ?></li>
                    <li><?php esc_html_e( '🚫 Query Parameter (Development only - not recommended for production)', 'pkl-rest-api-auth' ); ?></li>
                    <li><?php esc_html_e( 'Keep your API key secure and do not share it', 'pkl-rest-api-auth' ); ?></li>
                </ul>
            </div>
        </div>
		<?php
	}
}