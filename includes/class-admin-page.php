<?php
/**
 * Admin page handler for PKL REST API Auth
 */

if (!defined('ABSPATH')) {
    exit;
}

class PKL_REST_API_Auth_Admin_Page
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
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'settings_init'));
        add_action('admin_post_pkl_revoke_token', array($this, 'handle_revoke_token'));
        add_action('admin_post_pkl_restore_token', array($this, 'handle_restore_token'));
        add_action('admin_post_pkl_delete_token', array($this, 'handle_delete_token'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook)
    {
        if ($hook !== 'settings_page_pkl-rest-api-auth') {
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
    public function add_admin_menu()
    {
        add_options_page(
            __('PKL REST API Auth', 'pkl-rest-api-auth'),
            __('PKL REST API Auth', 'pkl-rest-api-auth'),
            'manage_options',
            'pkl-rest-api-auth',
            array($this, 'admin_page')
        );
    }

    /**
     * Initialize settings
     */
    public function settings_init()
    {
        register_setting(
            'pkl_rest_api_auth',
            'pkl_rest_api_auth_enable',
            array(
                'sanitize_callback' => array($this, 'sanitize_checkbox'),
                'default' => 1
            )
        );
    }

    /**
     * Sanitize checkbox
     */
    public function sanitize_checkbox($input)
    {
        return isset($input) && $input == 1 ? 1 : 0;
    }

    /**
     * Handle revoke token
     */
    public function handle_revoke_token()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions.', 'pkl-rest-api-auth'));
        }

        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'pkl_revoke_token')) {
            wp_die(__('Security check failed.', 'pkl-rest-api-auth'));
        }

        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if ($id > 0) {
            $this->database->revoke_token($id);
        }

        wp_redirect(add_query_arg('message', 'revoked', admin_url('options-general.php?page=pkl-rest-api-auth')));
        exit;
    }

    /**
     * Handle restore token
     */
    public function handle_restore_token()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions.', 'pkl-rest-api-auth'));
        }

        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'pkl_restore_token')) {
            wp_die(__('Security check failed.', 'pkl-rest-api-auth'));
        }

        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if ($id > 0) {
            $this->database->restore_token($id);
        }

        wp_redirect(add_query_arg('message', 'restored', admin_url('options-general.php?page=pkl-rest-api-auth')));
        exit;
    }

    /**
     * Handle delete token
     */
    public function handle_delete_token()
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions.', 'pkl-rest-api-auth'));
        }

        if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'pkl_delete_token')) {
            wp_die(__('Security check failed.', 'pkl-rest-api-auth'));
        }

        $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
        if ($id > 0) {
            $this->database->delete_token($id);
        }

        wp_redirect(add_query_arg('message', 'deleted', admin_url('options-general.php?page=pkl-rest-api-auth')));
        exit;
    }

    /**
     * Admin page
     */
    public function admin_page()
    {
        $active_tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : 'settings';
        $tokens = $this->database->get_all_tokens();

        // Show messages
        if (isset($_GET['message'])) {
            $message = sanitize_text_field(wp_unslash($_GET['message']));
            $class = 'notice-success';
            $text = '';

            switch ($message) {
                case 'revoked':
                    $text = __('Token revoked successfully.', 'pkl-rest-api-auth');
                    break;
                case 'restored':
                    $text = __('Token restored successfully.', 'pkl-rest-api-auth');
                    break;
                case 'deleted':
                    $text = __('Token deleted successfully.', 'pkl-rest-api-auth');
                    break;
            }

            if ($text) {
                echo '<div class="notice ' . esc_attr($class) . ' is-dismissible"><p>' . esc_html($text) . '</p></div>';
            }
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('PKL REST API Auth', 'pkl-rest-api-auth'); ?></h1>

            <nav class="nav-tab-wrapper">
                <a href="?page=pkl-rest-api-auth&tab=settings" class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Settings', 'pkl-rest-api-auth'); ?>
                </a>
                <a href="?page=pkl-rest-api-auth&tab=tokens" class="nav-tab <?php echo $active_tab === 'tokens' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Access Tokens', 'pkl-rest-api-auth'); ?>
                    <span class="count">(<?php echo count($tokens); ?>)</span>
                </a>
                <a href="?page=pkl-rest-api-auth&tab=guide" class="nav-tab <?php echo $active_tab === 'guide' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('API Guide', 'pkl-rest-api-auth'); ?>
                </a>
            </nav>

            <div class="tab-content">
                <?php
                switch ($active_tab) {
                    case 'tokens':
                        $this->render_tokens_tab($tokens);
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
    private function render_settings_tab()
    {
        ?>
        <form action="options.php" method="post">
            <?php
            settings_fields('pkl_rest_api_auth');
            ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Enable REST API Authentication', 'pkl-rest-api-auth'); ?></th>
                    <td>
                        <?php $enable = get_option('pkl_rest_api_auth_enable', 1); ?>
                        <label>
                            <input type="checkbox" name="pkl_rest_api_auth_enable" value="1" <?php checked(1, $enable); ?> />
                            <?php esc_html_e('Require authentication for REST API access', 'pkl-rest-api-auth'); ?>
                        </label>
                        <p class="description">
                            <?php esc_html_e('When enabled, only authenticated users can access the WordPress REST API.', 'pkl-rest-api-auth'); ?>
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
    private function render_tokens_tab($tokens)
    {
        ?>
        <div class="pkl-tokens-section">
            <h2><?php esc_html_e('Manage Access Tokens', 'pkl-rest-api-auth'); ?></h2>

            <?php if (empty($tokens)): ?>
                <div class="notice notice-info inline">
                    <p><?php esc_html_e('No access tokens have been generated yet.', 'pkl-rest-api-auth'); ?></p>
                </div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                    <tr>
                        <th><?php esc_html_e('User', 'pkl-rest-api-auth'); ?></th>
                        <th><?php esc_html_e('Email', 'pkl-rest-api-auth'); ?></th>
                        <th><?php esc_html_e('Token (Last 8 chars)', 'pkl-rest-api-auth'); ?></th>
                        <th><?php esc_html_e('Status', 'pkl-rest-api-auth'); ?></th>
                        <th><?php esc_html_e('Created', 'pkl-rest-api-auth'); ?></th>
                        <th><?php esc_html_e('Actions', 'pkl-rest-api-auth'); ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($tokens as $token): ?>
                        <tr class="<?php echo $token['revoked'] ? 'pkl-token-revoked' : 'pkl-token-active'; ?>">
                            <td><strong><?php echo esc_html($token['user_login']); ?></strong></td>
                            <td><?php echo esc_html($token['user_email']); ?></td>
                            <td><code>...<?php echo esc_html(substr($token['access_token'], -8)); ?></code></td>
                            <td>
                                <?php if ($token['revoked']): ?>
                                    <span class="pkl-status-revoked"><?php esc_html_e('Revoked', 'pkl-rest-api-auth'); ?></span>
                                <?php else: ?>
                                    <span class="pkl-status-active"><?php esc_html_e('Active', 'pkl-rest-api-auth'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $token['created_at'])); ?></td>
                            <td class="pkl-actions">
                                <?php if ($token['revoked']): ?>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display: inline;">
                                        <input type="hidden" name="action" value="pkl_restore_token">
                                        <input type="hidden" name="id" value="<?php echo esc_attr($token['id']); ?>">
                                        <?php wp_nonce_field('pkl_restore_token'); ?>
                                        <button type="submit" class="button button-secondary" onclick="return confirm('<?php esc_attr_e('Are you sure you want to restore this token?', 'pkl-rest-api-auth'); ?>')">
                                            <?php esc_html_e('Restore', 'pkl-rest-api-auth'); ?>
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display: inline;">
                                        <input type="hidden" name="action" value="pkl_revoke_token">
                                        <input type="hidden" name="id" value="<?php echo esc_attr($token['id']); ?>">
                                        <?php wp_nonce_field('pkl_revoke_token'); ?>
                                        <button type="submit" class="button button-secondary" onclick="return confirm('<?php esc_attr_e('Are you sure you want to revoke this token?', 'pkl-rest-api-auth'); ?>')">
                                            <?php esc_html_e('Revoke', 'pkl-rest-api-auth'); ?>
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display: inline;">
                                    <input type="hidden" name="action" value="pkl_delete_token">
                                    <input type="hidden" name="id" value="<?php echo esc_attr($token['id']); ?>">
                                    <?php wp_nonce_field('pkl_delete_token'); ?>
                                    <button type="submit" class="button button-link-delete"
                                            onclick="return confirm('<?php esc_attr_e('Are you sure you want to delete this token? This action cannot be undone.', 'pkl-rest-api-auth'); ?>')">
                                        <?php esc_html_e('Delete', 'pkl-rest-api-auth'); ?>
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
    private function render_guide_tab()
    {
        $site_url = get_site_url();
        ?>
        <div class="pkl-guide-section">
            <h2><?php esc_html_e('API Usage Guide', 'pkl-rest-api-auth'); ?></h2>

            <div class="pkl-guide-box">
                <h3><?php esc_html_e('🔐 Step 1: Generate Access Token', 'pkl-rest-api-auth'); ?></h3>
                <p><?php esc_html_e('Send a POST request to get an access token:', 'pkl-rest-api-auth'); ?></p>
                <div class="pkl-code-block">
                    <strong>POST</strong> <?php echo esc_html($site_url); ?>/wp-json/oauth/token
                    <br><br>
                    <strong><?php esc_html_e('Request Body (JSON):', 'pkl-rest-api-auth'); ?></strong>
                    <pre>{
  "email": "user@example.com"
}</pre>


                    <strong><?php esc_html_e('Response:', 'pkl-rest-api-auth'); ?></strong>
                    <pre>{
  "access_token": "abcd1234...",
  "token_type": "Bearer",
  "user": {
    "id": 1,
    "login": "username",
    "email": "user@example.com",
    "display_name": "Display Name"
  },
  "created_at": "2024-01-01 12:00:00"
}</pre>
                </div>
            </div>

            <div class="pkl-guide-box">
                <h3><?php esc_html_e('🚀 Step 2: Use Access Token', 'pkl-rest-api-auth'); ?></h3>
                <p><?php esc_html_e('Include the access token in your API requests using one of these methods:', 'pkl-rest-api-auth'); ?></p>

                <h4><?php esc_html_e('Method 1: Form-data (Recommended)', 'pkl-rest-api-auth'); ?></h4>
                <div class="pkl-code-block">
                    <strong>POST</strong> <?php echo esc_html($site_url); ?>/wp-json/wp/v2/posts
                    <br><br>
                    <strong><?php esc_html_e('Form-data:', 'pkl-rest-api-auth'); ?></strong>
                    <pre>access_token: your_access_token_here
title: Test Post
content: Post content here
status: draft</pre>
                </div>

                <h4><?php esc_html_e('Method 2: Authorization Header', 'pkl-rest-api-auth'); ?></h4>
                <div class="pkl-code-block">
                    <strong><?php esc_html_e('Headers:', 'pkl-rest-api-auth'); ?></strong>
                    <pre>Authorization: Bearer your_access_token_here</pre>
                </div>

                <h4><?php esc_html_e('Method 3: Custom Header', 'pkl-rest-api-auth'); ?></h4>
                <div class="pkl-code-block">
                    <strong><?php esc_html_e('Headers:', 'pkl-rest-api-auth'); ?></strong>
                    <pre>X-Access-Token: your_access_token_here</pre>
                </div>

                <h4><?php esc_html_e('Method 4: Query Parameter', 'pkl-rest-api-auth'); ?></h4>
                <div class="pkl-code-block">
                    <strong>GET</strong> <?php echo esc_html($site_url); ?>/wp-json/wp/v2/posts?access_token=your_access_token_here
                </div>
            </div>

            <div class="pkl-guide-box">
                <h3><?php esc_html_e('📝 Example API Calls', 'pkl-rest-api-auth'); ?></h3>

                <h4><?php esc_html_e('Get Posts:', 'pkl-rest-api-auth'); ?></h4>
                <div class="pkl-code-block">
                    <strong>GET</strong> <?php echo esc_html($site_url); ?>/wp-json/wp/v2/posts
                    <br>
                    <strong><?php esc_html_e('Form-data:', 'pkl-rest-api-auth'); ?></strong> access_token = your_token_here
                </div>

                <h4><?php esc_html_e('Create Post:', 'pkl-rest-api-auth'); ?></h4>
                <div class="pkl-code-block">
                    <strong>POST</strong> <?php echo esc_html($site_url); ?>/wp-json/wp/v2/posts
                    <br>
                    <strong><?php esc_html_e('Form-data:', 'pkl-rest-api-auth'); ?></strong>
                    <pre>access_token: your_token_here
title: My New Post
content: This is the post content
status: draft</pre>
                </div>
            </div>

            <div class="notice notice-info inline">
                <p>
                    <strong><?php esc_html_e('Note:', 'pkl-rest-api-auth'); ?></strong> <?php esc_html_e('Only registered users can generate access tokens. The email must exist in your WordPress user database.', 'pkl-rest-api-auth'); ?>
                </p>
            </div>
        </div>
        <?php
    }
}