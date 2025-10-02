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
        add_action('wp_ajax_pklrest_generate_api_key', array($this, 'ajax_generate_api_key'));
        add_action('wp_ajax_pklrest_revoke_api_key', array($this, 'ajax_revoke_api_key'));
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

        // Enqueue CSS
        wp_enqueue_style(
            'pklrest-rest-api-auth-profile',
            PKL_REST_API_AUTH_PLUGIN_URL . 'assets/user-profile.css',
            array(),
            PKL_REST_API_AUTH_VERSION
        );

        // Enqueue jQuery
        wp_enqueue_script('jquery');

        // Create a separate script handle for better organization
        wp_register_script(
            'pklrest-rest-api-auth-profile-js',
            '', // empty URL for inline script
            array('jquery'),
            PKL_REST_API_AUTH_VERSION,
            true
        );
        wp_enqueue_script('pklrest-rest-api-auth-profile-js');

        // Add inline script
        wp_add_inline_script('pklrest-rest-api-auth-profile-js', $this->get_profile_js());

        // Localize script for AJAX
        wp_localize_script('pklrest-rest-api-auth-profile-js', 'pklrestApiAuth', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('pklrest_api_key_action'),
            'strings' => array(
                'generating' => __('Generating...', 'pklrest-rest-api-auth'),
                'generateBtn' => __('Generate New API Key', 'pklrest-rest-api-auth'),
                'active' => __('Active', 'pklrest-rest-api-auth'),
                'confirmRevoke' => __('Are you sure you want to revoke your API key? This will disable API access.', 'pklrest-rest-api-auth'),
                'revoked' => __('Revoked', 'pklrest-rest-api-auth')
            )
        ));
    }

    /**
     * Get profile JavaScript
     */
    private function get_profile_js()
    {
        return "
            jQuery(document).ready(function($) {
                $('#pklrest-generate-api-key').on('click', function(e) {
                    e.preventDefault();
                    var button = $(this);
                    var userId = button.data('user-id');
                    
                    sessionStorage.setItem('scrollPos', window.scrollY);
                    
                    button.prop('disabled', true).text(pklrestApiAuth.strings.generating);
                    
                    $.post(pklrestApiAuth.ajaxurl, {
                        action: 'pklrest_generate_api_key',
                        user_id: userId,
                        _wpnonce: pklrestApiAuth.nonce
                    }, function(response) {
                        if (response.success) {
                            $('#pklrest-api-key-display').text(response.data.api_key).show();
                            $('#pklrest-api-key-status').removeClass('pklrest-status-revoked').addClass('pklrest-status-active').text(pklrestApiAuth.strings.active);
                            $('#pklrest-revoke-api-key').show();
                            alert(response.data.message);
                        } else {
                            alert(response.data);
                        }
                    }).always(function() {
                        button.prop('disabled', false).text(pklrestApiAuth.strings.generateBtn);
                    });
                });
                
                $('#pklrest-revoke-api-key').on('click', function(e) {
                    e.preventDefault();
                    
                    if (!confirm(pklrestApiAuth.strings.confirmRevoke)) {
                        return;
                    }
                    
                    var button = $(this);
                    var userId = button.data('user-id');
                    
                    button.prop('disabled', true);
                    
                    $.post(pklrestApiAuth.ajaxurl, {
                        action: 'pklrest_revoke_api_key',
                        user_id: userId,
                        _wpnonce: pklrestApiAuth.nonce
                    }, function(response) {
                        if (response.success) {
                            $('#pklrest-api-key-status').removeClass('pklrest-status-active').addClass('pklrest-status-revoked').text(pklrestApiAuth.strings.revoked);
                            button.hide();
                            alert(response.data);
                        } else {
                            alert(response.data);
                        }
                    }).always(function() {
                        button.prop('disabled', false);
                    });
                });
                
                const scrollPos = sessionStorage.getItem('scrollPos');
                if (scrollPos !== null) {
                    window.scrollTo(0, parseInt(scrollPos));
                    sessionStorage.removeItem('scrollPos');
                }
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
        <h3><?php esc_html_e('REST API Access', 'pklrest-rest-api-auth'); ?></h3>
        <table class="form-table">
            <tr>
                <th><label><?php esc_html_e('API Key', 'pklrest-rest-api-auth'); ?></label></th>
                <td>
                    <?php if ($api_key_data): ?>
                        <p>
                            <code id="pklrest-api-key-display" style="<?php echo $api_key_data['revoked'] ? 'display:none;' : ''; ?>">
                                <?php echo $api_key_data['revoked'] ? '' : esc_html($api_key_data['access_token']); ?>
                            </code>
                        </p>
                        <p>
                            <strong><?php esc_html_e('Status:', 'pklrest-rest-api-auth'); ?></strong>
                            <span id="pklrest-api-key-status" class="<?php echo $api_key_data['revoked'] ? 'pklrest-status-revoked' : 'pklrest-status-active'; ?>">
                            <?php echo $api_key_data['revoked'] ? esc_html__('Revoked', 'pklrest-rest-api-auth') : esc_html__('Active', 'pklrest-rest-api-auth'); ?>
                        </span>
                        </p>
                        <p>
                            <strong><?php esc_html_e('Created:', 'pklrest-rest-api-auth'); ?></strong>
                            <?php
                            if (function_exists('wp_timezone')) {
                                $wp_timezone = wp_timezone();
                                $created_date = new DateTime($api_key_data['created_at'], $wp_timezone);
                                echo esc_html($created_date->format('Y-m-d\TH:i:s'));
                                echo ' ' . esc_html($wp_timezone->getName()) . ' ';
                                echo esc_html__('(WordPress Site Time)', 'pklrest-rest-api-auth');
                            } else {
                                $server_timezone = new DateTimeZone(date_default_timezone_get());
                                $created_date = new DateTime($api_key_data['created_at'], $server_timezone);
                                echo esc_html($created_date->format('Y-m-d\TH:i:s'));
                                echo ' ' . esc_html($server_timezone->getName()) . ' ';
                                echo esc_html__('(Server Time)', 'pklrest-rest-api-auth');
                            }
                            ?>
                        </p>
                    <?php else: ?>
                        <p><em><?php esc_html_e('No API key generated yet.', 'pklrest-rest-api-auth'); ?></em></p>
                        <code id="pklrest-api-key-display" style="display:none;"></code>
                        <p>
                            <strong><?php esc_html_e('Status:', 'pklrest-rest-api-auth'); ?></strong>
                            <span id="pklrest-api-key-status" class="pklrest-status-revoked"><?php esc_html_e('No Key', 'pklrest-rest-api-auth'); ?></span>
                        </p>
                    <?php endif; ?>

                    <?php if ($is_revoked && !$is_admin): ?>
                        <div class="notice notice-error inline" style="margin: 10px 0;">
                            <p><strong><?php esc_html_e('Access Restricted', 'pklrest-rest-api-auth'); ?></strong></p>
                            <p><?php esc_html_e('Your API key was revoked. Please contact the administrator to restore access.', 'pklrest-rest-api-auth'); ?></p>
                        </div>
                        <p>
                            <button type="button" class="button button-primary" disabled>
                                <?php esc_html_e('Generate New API Key', 'pklrest-rest-api-auth'); ?>
                            </button>
                            <span class="description" style="margin-left: 10px;">
                            <?php esc_html_e('Contact administrator for access restoration', 'pklrest-rest-api-auth'); ?>
                        </span>
                        </p>
                    <?php else: ?>
                        <p>
                            <button type="button" id="pklrest-generate-api-key" class="button button-primary" data-user-id="<?php echo esc_attr($user->ID); ?>">
                                <?php esc_html_e('Generate New API Key', 'pklrest-rest-api-auth'); ?>
                            </button>
                            <?php if ($api_key_data && !$api_key_data['revoked']): ?>
                                <button type="button" id="pklrest-revoke-api-key" class="button button-secondary" data-user-id="<?php echo esc_attr($user->ID); ?>">
                                    <?php esc_html_e('Revoke API Key', 'pklrest-rest-api-auth'); ?>
                                </button>
                            <?php else: ?>
                                <button type="button" id="pklrest-revoke-api-key" class="button button-secondary" data-user-id="<?php echo esc_attr($user->ID); ?>" style="display:none;">
                                    <?php esc_html_e('Revoke API Key', 'pklrest-rest-api-auth'); ?>
                                </button>
                            <?php endif; ?>
                        </p>
                        <?php if ($is_admin && $is_revoked): ?>
                            <div class="notice notice-warning inline" style="margin: 10px 0;">
                                <p><strong><?php esc_html_e('Administrator Notice', 'pklrest-rest-api-auth'); ?></strong></p>
                                <p><?php esc_html_e('This user\'s API key is revoked. As an administrator, you can generate a new key to restore access.', 'pklrest-rest-api-auth'); ?></p>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <p class="description">
                        <?php esc_html_e('Use this API key to authenticate REST API requests. Keep it secure and do not share it with others.', 'pklrest-rest-api-auth'); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * AJAX: Generate API key
     */
    public function ajax_generate_api_key()
    {
        check_ajax_referer('pklrest_api_key_action');

        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $current_user_id = get_current_user_id();

        if ($user_id !== $current_user_id && !current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'pklrest-rest-api-auth'));
        }

        if (!current_user_can('manage_options')) {
            $existing_api_key = $this->database->get_user_api_key($user_id);
            if ($existing_api_key && $existing_api_key['revoked']) {
                wp_send_json_error(esc_html__('Your API key was revoked. Please contact the administrator to restore access.', 'pklrest-rest-api-auth'));
                return;
            }
        }

        $api_key = $this->database->generate_api_key($user_id);

        if ($api_key) {
            wp_send_json_success(array(
                'api_key' => $api_key,
                'message' => esc_html__('API key generated successfully.', 'pklrest-rest-api-auth')
            ));
        } else {
            wp_send_json_error(esc_html__('Failed to generate API key.', 'pklrest-rest-api-auth'));
        }
    }

    /**
     * AJAX: Revoke API key
     */
    public function ajax_revoke_api_key()
    {
        check_ajax_referer('pklrest_api_key_action');

        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $current_user_id = get_current_user_id();

        if ($user_id !== $current_user_id && !current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'pklrest-rest-api-auth'));
        }

        $user = get_userdata($user_id);
        if (!$user) {
            wp_send_json_error(esc_html__('User not found.', 'pklrest-rest-api-auth'));
        }

        $user_api_key = $this->database->get_user_api_key($user_id);
        if ($user_api_key) {
            $result = $this->database->revoke_token($user_api_key['id']);
            if ($result !== false) {
                wp_send_json_success(esc_html__('API key revoked successfully.', 'pklrest-rest-api-auth'));
            } else {
                wp_send_json_error(esc_html__('Failed to revoke API key.', 'pklrest-rest-api-auth'));
            }
        } else {
            wp_send_json_error(esc_html__('No API key found for this user.', 'pklrest-rest-api-auth'));
        }
    }
}