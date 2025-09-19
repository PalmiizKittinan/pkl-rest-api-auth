<?php
/**
 * User profile handler for PKL REST API Auth
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
        add_action('show_user_profile', array($this, 'add_api_key_fields'));
        add_action('edit_user_profile', array($this, 'add_api_key_fields'));
        add_action('wp_ajax_pkl_generate_api_key', array($this, 'ajax_generate_api_key'));
        add_action('wp_ajax_pkl_revoke_api_key', array($this, 'ajax_revoke_api_key'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_profile_scripts'));
    }

    /**
     * Enqueue profile scripts
     */
    public function enqueue_profile_scripts($hook)
    {
        if ($hook !== 'profile.php' && $hook !== 'user-edit.php') {
            return;
        }

        wp_enqueue_script('jquery');
        wp_add_inline_script('jquery', $this->get_profile_js());
    }

    /**
     * Get profile JavaScript
     */
    private function get_profile_js()
    {
        return "
        jQuery(document).ready(function($) {
            $('#pkl-generate-api-key').on('click', function(e) {
                e.preventDefault();
                var button = $(this);
                var userId = button.data('user-id');
                
                button.prop('disabled', true).text('" . esc_js(__('Generating...', 'pkl-rest-api-auth')) . "');
                
                $.post(ajaxurl, {
                    action: 'pkl_generate_api_key',
                    user_id: userId,
                    _wpnonce: '" . wp_create_nonce('pkl_api_key_action') . "'
                }, function(response) {
                    if (response.success) {
                        $('#pkl-api-key-display').text(response.data.api_key).show();
                        $('#pkl-api-key-status').removeClass('pkl-status-revoked').addClass('pkl-status-active').text('" . esc_js(__('Active', 'pkl-rest-api-auth')) . "');
                        $('#pkl-revoke-api-key').show();
                        alert(response.data.message);
                    } else {
                        alert(response.data);
                    }
                }).always(function() {
                    button.prop('disabled', false).text('" . esc_js(__('Generate New API Key', 'pkl-rest-api-auth')) . "');
                });
            });
            
            $('#pkl-revoke-api-key').on('click', function(e) {
                e.preventDefault();
                
                if (!confirm('" . esc_js(__('Are you sure you want to revoke your API key? This will disable API access.', 'pkl-rest-api-auth')) . "')) {
                    return;
                }
                
                var button = $(this);
                var userId = button.data('user-id');
                
                button.prop('disabled', true);
                
                $.post(ajaxurl, {
                    action: 'pkl_revoke_api_key',
                    user_id: userId,
                    _wpnonce: '" . wp_create_nonce('pkl_api_key_action') . "'
                }, function(response) {
                    if (response.success) {
                        $('#pkl-api-key-status').removeClass('pkl-status-active').addClass('pkl-status-revoked').text('" . esc_js(__('Revoked', 'pkl-rest-api-auth')) . "');
                        button.hide();
                        alert(response.data);
                    } else {
                        alert(response.data);
                    }
                }).always(function() {
                    button.prop('disabled', false);
                });
            });
        });
        ";
    }

    /**
     * Add API key fields to user profile
     */
    public function add_api_key_fields($user)
    {
        $current_user_id = get_current_user_id();

        // Only show to the user themselves or admins
        if ($user->ID !== $current_user_id && !current_user_can('manage_options')) {
            return;
        }

        $api_key_data = $this->database->get_user_api_key($user->ID);
        $is_revoked = $api_key_data && $api_key_data['revoked'];
        $is_admin = current_user_can('manage_options');
        ?>
        <h3><?php esc_html_e('REST API Access', 'pkl-rest-api-auth'); ?></h3>
        <table class="form-table">
            <tr>
                <th><label><?php esc_html_e('API Key', 'pkl-rest-api-auth'); ?></label></th>
                <td>
                    <?php if ($api_key_data): ?>
                        <p>
                            <code id="pkl-api-key-display" style="<?php echo $api_key_data['revoked'] ? 'display:none;' : ''; ?>">
                                <?php echo $api_key_data['revoked'] ? '' : esc_html($api_key_data['access_token']); ?>
                            </code>
                        </p>
                        <p>
                            <strong><?php esc_html_e('Status:', 'pkl-rest-api-auth'); ?></strong>
                            <span id="pkl-api-key-status" class="<?php echo $api_key_data['revoked'] ? 'pkl-status-revoked' : 'pkl-status-active'; ?>">
                                <?php echo $api_key_data['revoked'] ? esc_html__('Revoked', 'pkl-rest-api-auth') : esc_html__('Active', 'pkl-rest-api-auth'); ?>
                            </span>
                        </p>
                        <p>
                            <strong><?php esc_html_e('Created:', 'pkl-rest-api-auth'); ?></strong>
                            <?php echo esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $api_key_data['created_at'])); ?>
                        </p>
                    <?php else: ?>
                        <p><em><?php esc_html_e('No API key generated yet.', 'pkl-rest-api-auth'); ?></em></p>
                        <code id="pkl-api-key-display" style="display:none;"></code>
                        <p>
                            <strong><?php esc_html_e('Status:', 'pkl-rest-api-auth'); ?></strong>
                            <span id="pkl-api-key-status" class="pkl-status-revoked"><?php esc_html_e('No Key', 'pkl-rest-api-auth'); ?></span>
                        </p>
                    <?php endif; ?>

                    <?php if ($is_revoked && !$is_admin): ?>
                        <!-- Revoked user can't generate new key -->
                        <div class="notice notice-error inline" style="margin: 10px 0;">
                            <p><strong><?php esc_html_e('Access Restricted', 'pkl-rest-api-auth'); ?></strong></p>
                            <p><?php esc_html_e('Your API key was revoked. Please contact the administrator to restore access.', 'pkl-rest-api-auth'); ?></p>
                        </div>
                        <p>
                            <button type="button" class="button button-primary" disabled>
                                <?php esc_html_e('Generate New API Key', 'pkl-rest-api-auth'); ?>
                            </button>
                            <span class="description" style="margin-left: 10px;">
                                <?php esc_html_e('Contact administrator for access restoration', 'pkl-rest-api-auth'); ?>
                            </span>
                        </p>
                    <?php else: ?>
                        <!-- Normal users or admins can generate keys -->
                        <p>
                            <button type="button" id="pkl-generate-api-key" class="button button-primary" data-user-id="<?php echo esc_attr($user->ID); ?>">
                                <?php esc_html_e('Generate New API Key', 'pkl-rest-api-auth'); ?>
                            </button>

                            <?php if ($api_key_data && !$api_key_data['revoked']): ?>
                                <button type="button" id="pkl-revoke-api-key" class="button button-secondary" data-user-id="<?php echo esc_attr($user->ID); ?>">
                                    <?php esc_html_e('Revoke API Key', 'pkl-rest-api-auth'); ?>
                                </button>
                            <?php else: ?>
                                <button type="button" id="pkl-revoke-api-key" class="button button-secondary" data-user-id="<?php echo esc_attr($user->ID); ?>" style="display:none;">
                                    <?php esc_html_e('Revoke API Key', 'pkl-rest-api-auth'); ?>
                                </button>
                            <?php endif; ?>
                        </p>

                        <?php if ($is_admin && $is_revoked): ?>
                            <div class="notice notice-warning inline" style="margin: 10px 0;">
                                <p><strong><?php esc_html_e('Administrator Notice', 'pkl-rest-api-auth'); ?></strong></p>
                                <p><?php esc_html_e('This user\'s API key is revoked. As an administrator, you can generate a new key to restore access.', 'pkl-rest-api-auth'); ?></p>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <p class="description">
                        <?php esc_html_e('Use this API key to authenticate REST API requests. Keep it secure and do not share it with others.', 'pkl-rest-api-auth'); ?>
                    </p>
                </td>
            </tr>
        </table>

        <style>
            .pkl-status-active {
                color: #2e7d32;
                font-weight: bold;
            }

            .pkl-status-revoked {
                color: #c62828;
                font-weight: bold;
            }

            #pkl-api-key-display {
                background: #f8f9fa;
                padding: 8px;
                border: 1px solid #ddd;
                border-radius: 4px;
                word-break: break-all;
                display: block;
                max-width: 500px;
            }

            .notice.inline {
                padding: 12px;
                margin: 5px 0 15px 0;
                background: #fff;
                border-left: 4px solid;
                box-shadow: 0 1px 1px rgba(0, 0, 0, .04);
            }

            .notice.notice-error.inline {
                border-left-color: #dc3232;
            }

            .notice.notice-warning.inline {
                border-left-color: #ffba00;
            }
        </style>
        <?php
    }

    /**
     * AJAX: Generate API key
     */
    public function ajax_generate_api_key()
    {
        check_ajax_referer('pkl_api_key_action');

        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $current_user_id = get_current_user_id();

        // Security check
        if ($user_id !== $current_user_id && !current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'pkl-rest-api-auth'));
        }

        // Check if user's API key is revoked (only applies to non-admins)
        if (!current_user_can('manage_options')) {
            $existing_api_key = $this->database->get_user_api_key($user_id);
            if ($existing_api_key && $existing_api_key['revoked']) {
                wp_send_json_error(esc_html__('Your API key was revoked. Please contact the administrator to restore access.', 'pkl-rest-api-auth'));
                return;
            }
        }

        $api_key = $this->database->generate_api_key($user_id);

        if ($api_key) {
            wp_send_json_success(array(
                    'api_key' => $api_key,
                    'message' => esc_html__('API key generated successfully.', 'pkl-rest-api-auth')
            ));
        } else {
            wp_send_json_error(esc_html__('Failed to generate API key.', 'pkl-rest-api-auth'));
        }
    }

    /**
     * AJAX: Revoke API key
     */
    public function ajax_revoke_api_key()
    {
        check_ajax_referer('pkl_api_key_action');

        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $current_user_id = get_current_user_id();

        // Security check
        if ($user_id !== $current_user_id && !current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'pkl-rest-api-auth'));
        }

        $user = get_userdata($user_id);
        if (!$user) {
            wp_send_json_error(esc_html__('User not found.', 'pkl-rest-api-auth'));
        }

        // Use database class method instead of direct query
        $user_api_key = $this->database->get_user_api_key($user_id);

        if ($user_api_key) {
            $result = $this->database->revoke_token($user_api_key['id']);

            if ($result !== false) {
                wp_send_json_success(esc_html__('API key revoked successfully.', 'pkl-rest-api-auth'));
            } else {
                wp_send_json_error(esc_html__('Failed to revoke API key.', 'pkl-rest-api-auth'));
            }
        } else {
            wp_send_json_error(esc_html__('No API key found for this user.', 'pkl-rest-api-auth'));
        }
    }
}