<?php
/**
 * User Profile handler for PKL REST API Auth
 */

if (!defined('ABSPATH')) {
    exit;
}

class PKL_REST_API_Auth_User_Profile
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
        add_action('show_user_profile', array($this, 'show_api_key_section'));
        add_action('edit_user_profile', array($this, 'show_api_key_section'));
        add_action('personal_options_update', array($this, 'handle_api_key_actions'));
        add_action('edit_user_profile_update', array($this, 'handle_api_key_actions'));

        // AJAX actions
        add_action('wp_ajax_pkl_generate_api_key', array($this, 'ajax_generate_api_key'));
        add_action('wp_ajax_pkl_revoke_api_key', array($this, 'ajax_revoke_api_key'));

        // Enqueue scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * Enqueue scripts
     */
    public function enqueue_scripts($hook)
    {
        if ($hook !== 'profile.php' && $hook !== 'user-edit.php') {
            return;
        }

        wp_enqueue_script('jquery');
        wp_add_inline_script('jquery', '
            jQuery(document).ready(function($) {
                $("#pkl-generate-api-key").click(function(e) {
                    e.preventDefault();
                    if (!confirm("' . esc_js(__('Generate new API key? This will invalidate the previous key.', 'pkl-rest-api-auth')) . '")) {
                        return;
                    }
                    
                    $.post(ajaxurl, {
                        action: "pkl_generate_api_key",
                        user_id: $(this).data("user-id"),
                        _wpnonce: "' . wp_create_nonce('pkl_api_key_action') . '"
                    }, function(response) {
                        if (response.success) {
                            $("#pkl-api-key-display").html("<code>" + response.data.api_key + "</code>");
                            $("#pkl-api-key-status").html("<span style=\"color: green;\">' . esc_js(__('Active', 'pkl-rest-api-auth')) . '</span>");
                        } else {
                            alert("' . esc_js(__('Error generating API key', 'pkl-rest-api-auth')) . '");
                        }
                    });
                });
                
                $("#pkl-revoke-api-key").click(function(e) {
                    e.preventDefault();
                    if (!confirm("' . esc_js(__('Revoke API key? You will not be able to use the API until you generate a new key.', 'pkl-rest-api-auth')) . '")) {
                        return;
                    }
                    
                    $.post(ajaxurl, {
                        action: "pkl_revoke_api_key",
                        user_id: $(this).data("user-id"),
                        _wpnonce: "' . wp_create_nonce('pkl_api_key_action') . '"
                    }, function(response) {
                        if (response.success) {
                            $("#pkl-api-key-status").html("<span style=\"color: red;\">' . esc_js(__('Revoked', 'pkl-rest-api-auth')) . '</span>");
                        } else {
                            alert("' . esc_js(__('Error revoking API key', 'pkl-rest-api-auth')) . '");
                        }
                    });
                });
                
                $("#pkl-copy-api-key").click(function(e) {
                    e.preventDefault();
                    var apiKey = $(this).data("api-key");
                    if (apiKey && navigator.clipboard) {
                        navigator.clipboard.writeText(apiKey).then(function() {
                            alert("' . esc_js(__('API key copied to clipboard!', 'pkl-rest-api-auth')) . '");
                        });
                    }
                });
            });
        ');
    }

    /**
     * Show API key section in user profile
     */
    public function show_api_key_section($user)
    {
        $current_user_id = get_current_user_id();

        // Only show to user themselves or admin
        if ($user->ID !== $current_user_id && !current_user_can('manage_options')) {
            return;
        }

        $api_key_data = $this->database->get_user_api_key($user->ID);
        ?>
        <h3><?php esc_html_e('REST API Access', 'pkl-rest-api-auth'); ?></h3>
        <table class="form-table">
            <tr>
                <th><label><?php esc_html_e('API Key', 'pkl-rest-api-auth'); ?></label></th>
                <td>
                    <?php if ($api_key_data): ?>
                        <div id="pkl-api-key-display">
                            <code><?php echo esc_html($api_key_data['access_token']); ?></code>
                            <button type="button" id="pkl-copy-api-key" class="button button-small" data-api-key="<?php echo esc_attr($api_key_data['access_token']); ?>">
                                <?php esc_html_e('Copy', 'pkl-rest-api-auth'); ?>
                            </button>
                        </div>
                        <p class="description">
                            <?php esc_html_e('Use this API key to authenticate your REST API requests.', 'pkl-rest-api-auth'); ?>
                        </p>

                        <p>
                            <strong><?php esc_html_e('Status:', 'pkl-rest-api-auth'); ?></strong>
                            <span id="pkl-api-key-status">
                                <?php if ($api_key_data['revoked']): ?>
                                    <span style="color: red;"><?php esc_html_e('Revoked', 'pkl-rest-api-auth'); ?></span>
                                <?php else: ?>
                                    <span style="color: green;"><?php esc_html_e('Active', 'pkl-rest-api-auth'); ?></span>
                                <?php endif; ?>
                            </span>
                        </p>

                        <p>
                            <strong><?php esc_html_e('Created:', 'pkl-rest-api-auth'); ?></strong>
                            <?php echo esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $api_key_data['created_at'])); ?>
                        </p>

                        <p>
                            <button type="button" id="pkl-generate-api-key" class="button" data-user-id="<?php echo esc_attr($user->ID); ?>">
                                <?php esc_html_e('Generate New API Key', 'pkl-rest-api-auth'); ?>
                            </button>

                            <?php if (!$api_key_data['revoked']): ?>
                                <button type="button" id="pkl-revoke-api-key" class="button" data-user-id="<?php echo esc_attr($user->ID); ?>">
                                    <?php esc_html_e('Revoke API Key', 'pkl-rest-api-auth'); ?>
                                </button>
                            <?php endif; ?>
                        </p>
                    <?php else: ?>
                        <div id="pkl-api-key-display">
                            <p><?php esc_html_e('No API key generated yet.', 'pkl-rest-api-auth'); ?></p>
                        </div>
                        <p>
                            <button type="button" id="pkl-generate-api-key" class="button button-primary" data-user-id="<?php echo esc_attr($user->ID); ?>">
                                <?php esc_html_e('Generate API Key', 'pkl-rest-api-auth'); ?>
                            </button>
                        </p>
                    <?php endif; ?>

                    <div style="margin-top: 15px; padding: 10px; background: #f9f9f9; border-left: 4px solid #0073aa;">
                        <h4><?php esc_html_e('How to use your API Key:', 'pkl-rest-api-auth'); ?></h4>
                        <p><strong><?php esc_html_e('Method 1: Form-data', 'pkl-rest-api-auth'); ?></strong></p>
                        <code>api_key: your_api_key_here</code>

                        <p><strong><?php esc_html_e('Method 2: Header', 'pkl-rest-api-auth'); ?></strong></p>
                        <code>X-API-Key: your_api_key_here</code>

                        <p><strong><?php esc_html_e('Method 3: Query Parameter', 'pkl-rest-api-auth'); ?></strong></p>
                        <code>?api_key=your_api_key_here</code>
                    </div>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Handle API key actions (non-AJAX fallback)
     */
    public function handle_api_key_actions($user_id)
    {
        // This method can be used for non-AJAX operations if needed
    }

    /**
     * AJAX: Generate API key
     */
    public function ajax_generate_api_key()
    {
        check_ajax_referer('pkl_api_key_action');

        $user_id = intval($_POST['user_id']);
        $current_user_id = get_current_user_id();

        // Security check
        if ($user_id !== $current_user_id && !current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'pkl-rest-api-auth'));
        }

        $api_key = $this->database->generate_api_key($user_id);

        if ($api_key) {
            wp_send_json_success(array(
                'api_key' => $api_key,
                'message' => __('API key generated successfully.', 'pkl-rest-api-auth')
            ));
        } else {
            wp_send_json_error(__('Failed to generate API key.', 'pkl-rest-api-auth'));
        }
    }

    /**
     * AJAX: Revoke API key
     */
    public function ajax_revoke_api_key()
    {
        check_ajax_referer('pkl_api_key_action');

        $user_id = intval($_POST['user_id']);
        $current_user_id = get_current_user_id();

        // Security check
        if ($user_id !== $current_user_id && !current_user_can('manage_options')) {
            wp_die(__('You do not have permission to perform this action.', 'pkl-rest-api-auth'));
        }

        $user = get_userdata($user_id);
        if (!$user) {
            wp_send_json_error(__('User not found.', 'pkl-rest-api-auth'));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'pkl_rest_api_auth_tokens';

        $result = $wpdb->update(
            $table_name,
            array('revoked' => 1),
            array('user_login' => $user->user_login),
            array('%d'),
            array('%s')
        );

        if ($result !== false) {
            wp_send_json_success(__('API key revoked successfully.', 'pkl-rest-api-auth'));
        } else {
            wp_send_json_error(__('Failed to revoke API key.', 'pkl-rest-api-auth'));
        }
    }
}