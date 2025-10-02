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
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_page_scripts'));
    }

    /**
     * Add settings page
     */
    public function add_settings_page()
    {
        add_options_page(
            __('REST API Auth Settings', 'pklrest-rest-api-auth'),
            __('REST API Auth', 'pklrest-rest-api-auth'),
            'manage_options',
            'pklrest-rest-api-auth-settings',
            array($this, 'render_settings_page')
        );
    }

    /**
     * Register settings
     */
    public function register_settings()
    {
        register_setting('pklrest_rest_api_auth_settings', 'pklrest_rest_api_auth_options', array(
            'sanitize_callback' => array($this, 'sanitize_settings')
        ));

        add_settings_section(
            'pklrest_rest_api_auth_general',
            __('General Settings', 'pklrest-rest-api-auth'),
            array($this, 'render_general_section'),
            'pklrest-rest-api-auth-settings'
        );

        add_settings_field(
            'pklrest_enable_api_auth',
            __('Enable API Authentication', 'pklrest-rest-api-auth'),
            array($this, 'render_enable_field'),
            'pklrest-rest-api-auth-settings',
            'pklrest_rest_api_auth_general'
        );

        add_settings_field(
            'pklrest_allowed_user_roles',
            __('Allowed User Roles', 'pklrest-rest-api-auth'),
            array($this, 'render_roles_field'),
            'pklrest-rest-api-auth-settings',
            'pklrest_rest_api_auth_general'
        );
    }

    /**
     * Sanitize settings
     */
    public function sanitize_settings($input)
    {
        $sanitized = array();

        if (isset($input['enable_api_auth'])) {
            $sanitized['enable_api_auth'] = (bool) $input['enable_api_auth'];
        }

        if (isset($input['allowed_user_roles']) && is_array($input['allowed_user_roles'])) {
            $sanitized['allowed_user_roles'] = array_map('sanitize_text_field', $input['allowed_user_roles']);
        } else {
            $sanitized['allowed_user_roles'] = array();
        }

        return $sanitized;
    }

    /**
     * Render general section
     */
    public function render_general_section()
    {
        echo '<p>' . esc_html__('Configure REST API authentication settings.', 'pklrest-rest-api-auth') . '</p>';
    }

    /**
     * Render enable field
     */
    public function render_enable_field()
    {
        $options = get_option('pklrest_rest_api_auth_options', array());
        $enabled = isset($options['enable_api_auth']) ? $options['enable_api_auth'] : true;
        ?>
        <label>
            <input type="checkbox" name="pklrest_rest_api_auth_options[enable_api_auth]" value="1" <?php checked($enabled, true); ?>>
            <?php esc_html_e('Enable REST API authentication using API keys', 'pklrest-rest-api-auth'); ?>
        </label>
        <p class="description">
            <?php esc_html_e('When enabled, users can generate API keys to authenticate REST API requests.', 'pklrest-rest-api-auth'); ?>
        </p>
        <?php
    }

    /**
     * Render roles field
     */
    public function render_roles_field()
    {
        $options = get_option('pklrest_rest_api_auth_options', array());
        $allowed_roles = isset($options['allowed_user_roles']) ? $options['allowed_user_roles'] : array();

        if (empty($allowed_roles)) {
            $allowed_roles = array('administrator', 'editor', 'author');
        }

        $wp_roles = wp_roles();
        $all_roles = $wp_roles->get_names();

        ?>
        <fieldset>
            <?php foreach ($all_roles as $role_key => $role_name): ?>
                <label style="display: block; margin-bottom: 5px;">
                    <input type="checkbox"
                           name="pklrest_rest_api_auth_options[allowed_user_roles][]"
                           value="<?php echo esc_attr($role_key); ?>"
                        <?php checked(in_array($role_key, $allowed_roles)); ?>>
                    <?php echo esc_html($role_name); ?>
                </label>
            <?php endforeach; ?>
        </fieldset>
        <p class="description">
            <?php esc_html_e('Select which user roles can generate and use API keys.', 'pklrest-rest-api-auth'); ?>
        </p>
        <?php
    }

    /**
     * Enqueue admin page scripts
     */
    public function enqueue_admin_page_scripts($hook)
    {
        if ($hook !== 'settings_page_pklrest-rest-api-auth-settings') {
            return;
        }

        wp_enqueue_style(
            'pklrest-rest-api-auth-admin-page',
            PKL_REST_API_AUTH_PLUGIN_URL . 'assets/admin-page.css',
            array(),
            PKL_REST_API_AUTH_VERSION
        );
    }

    /**
     * Render settings page
     */
    public function render_settings_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'pklrest-rest-api-auth'));
        }

        // Get statistics
        global $wpdb;
        $table_name = $wpdb->prefix . 'pklrest_api_keys';

        $total_keys = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
        $active_keys = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE revoked = 0");
        $revoked_keys = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} WHERE revoked = 1");

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('REST API Authentication Settings', 'pklrest-rest-api-auth'); ?></h1>

            <div class="pklrest-stats-container">
                <div class="pklrest-stat-box">
                    <div class="pklrest-stat-number"><?php echo esc_html($total_keys); ?></div>
                    <div class="pklrest-stat-label"><?php esc_html_e('Total API Keys', 'pklrest-rest-api-auth'); ?></div>
                </div>
                <div class="pklrest-stat-box pklrest-stat-active">
                    <div class="pklrest-stat-number"><?php echo esc_html($active_keys); ?></div>
                    <div class="pklrest-stat-label"><?php esc_html_e('Active Keys', 'pklrest-rest-api-auth'); ?></div>
                </div>
                <div class="pklrest-stat-box pklrest-stat-revoked">
                    <div class="pklrest-stat-number"><?php echo esc_html($revoked_keys); ?></div>
                    <div class="pklrest-stat-label"><?php esc_html_e('Revoked Keys', 'pklrest-rest-api-auth'); ?></div>
                </div>
            </div>

            <form method="post" action="options.php">
                <?php
                settings_fields('pklrest_rest_api_auth_settings');
                do_settings_sections('pklrest-rest-api-auth-settings');
                submit_button();
                ?>
            </form>

            <div class="pklrest-info-box">
                <h2><?php esc_html_e('How It Works', 'pklrest-rest-api-auth'); ?></h2>
                <ol>
                    <li><?php esc_html_e('Users can generate API keys from their profile page (Users > Your Profile).', 'pklrest-rest-api-auth'); ?></li>
                    <li><?php esc_html_e('API keys are used to authenticate REST API requests.', 'pklrest-rest-api-auth'); ?></li>
                    <li><?php esc_html_e('Include the API key in the Authorization header: Authorization: Bearer YOUR_API_KEY', 'pklrest-rest-api-auth'); ?></li>
                    <li><?php esc_html_e('Administrators can manage all API keys from Tools > API Keys.', 'pklrest-rest-api-auth'); ?></li>
                </ol>
            </div>

            <div class="pklrest-info-box">
                <h2><?php esc_html_e('API Documentation', 'pklrest-rest-api-auth'); ?></h2>
                <h3><?php esc_html_e('Using cURL', 'pklrest-rest-api-auth'); ?></h3>
                <pre><code>curl -H "Authorization: Bearer YOUR_API_KEY" <?php echo esc_url(rest_url('wp/v2/posts')); ?></code></pre>

                <h3><?php esc_html_e('Using JavaScript (Fetch)', 'pklrest-rest-api-auth'); ?></h3>
                <pre><code>fetch('<?php echo esc_url(rest_url('wp/v2/posts')); ?>', {
    headers: {
        'Authorization': 'Bearer YOUR_API_KEY'
    }
})
.then(response => response.json())
.then(data => console.log(data));</code></pre>

                <h3><?php esc_html_e('Using PHP', 'pklrest-rest-api-auth'); ?></h3>
                <pre><code>$response = wp_remote_get('<?php echo esc_url(rest_url('wp/v2/posts')); ?>', array(
    'headers' => array(
        'Authorization' => 'Bearer YOUR_API_KEY'
    )
));

$data = json_decode(wp_remote_retrieve_body($response));</code></pre>
            </div>

            <div class="pklrest-quick-links">
                <h2><?php esc_html_e('Quick Links', 'pklrest-rest-api-auth'); ?></h2>
                <ul>
                    <li><a href="<?php echo esc_url(admin_url('tools.php?page=pklrest-api-keys')); ?>"><?php esc_html_e('Manage API Keys', 'pklrest-rest-api-auth'); ?></a></li>
                    <li><a href="<?php echo esc_url(admin_url('profile.php')); ?>"><?php esc_html_e('Your Profile (Generate API Key)', 'pklrest-rest-api-auth'); ?></a></li>
                    <li><a href="<?php echo esc_url(rest_url()); ?>" target="_blank"><?php esc_html_e('REST API Endpoint', 'pklrest-rest-api-auth'); ?></a></li>
                </ul>
            </div>
        </div>
        <?php
    }
}