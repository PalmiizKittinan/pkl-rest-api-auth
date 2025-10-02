<?php
/**
 * Admin handler for PKL REST API Auth
 */
if (!defined('ABSPATH')) {
    exit;
}

class PKL_REST_API_Auth_Admin
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
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_pklrest_delete_api_key', array($this, 'ajax_delete_api_key'));
        add_action('wp_ajax_pklrest_revoke_api_key_admin', array($this, 'ajax_revoke_api_key'));
        add_action('wp_ajax_pklrest_restore_api_key', array($this, 'ajax_restore_api_key'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu()
    {
        add_management_page(
            __('API Keys Management', 'pklrest-rest-api-auth'),
            __('API Keys', 'pklrest-rest-api-auth'),
            'manage_options',
            'pklrest-api-keys',
            array($this, 'render_admin_page')
        );
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook)
    {
        if ($hook !== 'tools_page_pklrest-api-keys') {
            return;
        }

        wp_enqueue_style(
            'pklrest-rest-api-auth-admin',
            PKL_REST_API_AUTH_PLUGIN_URL . 'assets/admin.css',
            array(),
            PKL_REST_API_AUTH_VERSION
        );

        wp_enqueue_script('jquery');

        wp_register_script(
            'pklrest-rest-api-auth-admin-js',
            '',
            array('jquery'),
            PKL_REST_API_AUTH_VERSION,
            true
        );
        wp_enqueue_script('pklrest-rest-api-auth-admin-js');

        wp_add_inline_script('pklrest-rest-api-auth-admin-js', $this->get_admin_js());

        wp_localize_script('pklrest-rest-api-auth-admin-js', 'pklrestAdminApiAuth', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('pklrest_admin_api_key_action'),
            'strings' => array(
                'confirmDelete' => __('Are you sure you want to delete this API key?', 'pklrest-rest-api-auth'),
                'confirmRevoke' => __('Are you sure you want to revoke this API key?', 'pklrest-rest-api-auth'),
                'confirmRestore' => __('Are you sure you want to restore this API key?', 'pklrest-rest-api-auth'),
                'deleted' => __('Deleted', 'pklrest-rest-api-auth'),
                'revoked' => __('Revoked', 'pklrest-rest-api-auth'),
                'active' => __('Active', 'pklrest-rest-api-auth')
            )
        ));
    }

    /**
     * Get admin JavaScript
     */
    private function get_admin_js()
    {
        return "
            jQuery(document).ready(function($) {
                // Delete API key
                $('.pklrest-delete-key').on('click', function(e) {
                    e.preventDefault();
                    
                    if (!confirm(pklrestAdminApiAuth.strings.confirmDelete)) {
                        return;
                    }
                    
                    var button = $(this);
                    var tokenId = button.data('token-id');
                    var row = button.closest('tr');
                    
                    button.prop('disabled', true);
                    
                    $.post(pklrestAdminApiAuth.ajaxurl, {
                        action: 'pklrest_delete_api_key',
                        token_id: tokenId,
                        _wpnonce: pklrestAdminApiAuth.nonce
                    }, function(response) {
                        if (response.success) {
                            row.fadeOut(400, function() {
                                $(this).remove();
                                if ($('.pklrest-api-keys-table tbody tr').length === 0) {
                                    location.reload();
                                }
                            });
                        } else {
                            alert(response.data);
                            button.prop('disabled', false);
                        }
                    });
                });
                
                // Revoke API key
                $('.pklrest-revoke-key').on('click', function(e) {
                    e.preventDefault();
                    
                    if (!confirm(pklrestAdminApiAuth.strings.confirmRevoke)) {
                        return;
                    }
                    
                    var button = $(this);
                    var tokenId = button.data('token-id');
                    var row = button.closest('tr');
                    
                    button.prop('disabled', true);
                    
                    $.post(pklrestAdminApiAuth.ajaxurl, {
                        action: 'pklrest_revoke_api_key_admin',
                        token_id: tokenId,
                        _wpnonce: pklrestAdminApiAuth.nonce
                    }, function(response) {
                        if (response.success) {
                            row.find('.pklrest-status').removeClass('pklrest-status-active').addClass('pklrest-status-revoked').text(pklrestAdminApiAuth.strings.revoked);
                            button.hide();
                            row.find('.pklrest-restore-key').show();
                        } else {
                            alert(response.data);
                        }
                    }).always(function() {
                        button.prop('disabled', false);
                    });
                });
                
                // Restore API key
                $('.pklrest-restore-key').on('click', function(e) {
                    e.preventDefault();
                    
                    if (!confirm(pklrestAdminApiAuth.strings.confirmRestore)) {
                        return;
                    }
                    
                    var button = $(this);
                    var tokenId = button.data('token-id');
                    var row = button.closest('tr');
                    
                    button.prop('disabled', true);
                    
                    $.post(pklrestAdminApiAuth.ajaxurl, {
                        action: 'pklrest_restore_api_key',
                        token_id: tokenId,
                        _wpnonce: pklrestAdminApiAuth.nonce
                    }, function(response) {
                        if (response.success) {
                            row.find('.pklrest-status').removeClass('pklrest-status-revoked').addClass('pklrest-status-active').text(pklrestAdminApiAuth.strings.active);
                            button.hide();
                            row.find('.pklrest-revoke-key').show();
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
     * Render admin page
     */
    public function render_admin_page()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have sufficient permissions to access this page.', 'pklrest-rest-api-auth'));
        }

        $search_term = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';

        if ($search_term) {
            $api_keys = $this->database->search_api_keys($search_term);
        } else {
            $api_keys = $this->database->get_all_api_keys();
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('API Keys Management', 'pklrest-rest-api-auth'); ?></h1>

            <form method="get" class="pklrest-search-form">
                <input type="hidden" name="page" value="pklrest-api-keys">
                <p class="search-box">
                    <input type="search" name="s" value="<?php echo esc_attr($search_term); ?>" placeholder="<?php esc_attr_e('Search by username, email, or token...', 'pklrest-rest-api-auth'); ?>">
                    <input type="submit" class="button" value="<?php esc_attr_e('Search', 'pklrest-rest-api-auth'); ?>">
                    <?php if ($search_term): ?>
                        <a href="<?php echo esc_url(admin_url('tools.php?page=pklrest-api-keys')); ?>" class="button"><?php esc_html_e('Clear', 'pklrest-rest-api-auth'); ?></a>
                    <?php endif; ?>
                </p>
            </form>

            <?php if (empty($api_keys)): ?>
                <p><?php esc_html_e('No API keys found.', 'pklrest-rest-api-auth'); ?></p>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped pklrest-api-keys-table">
                    <thead>
                    <tr>
                        <th><?php esc_html_e('User', 'pklrest-rest-api-auth'); ?></th>
                        <th><?php esc_html_e('API Key', 'pklrest-rest-api-auth'); ?></th>
                        <th><?php esc_html_e('Created', 'pklrest-rest-api-auth'); ?></th>
                        <th><?php esc_html_e('Status', 'pklrest-rest-api-auth'); ?></th>
                        <th><?php esc_html_e('Actions', 'pklrest-rest-api-auth'); ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($api_keys as $key): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($key['display_name']); ?></strong><br>
                                <small><?php echo esc_html($key['user_login']); ?> (<?php echo esc_html($key['user_email']); ?>)</small>
                            </td>
                            <td><code><?php echo esc_html($key['access_token']); ?></code></td>
                            <td><?php echo esc_html($key['created_at']); ?></td>
                            <td>
                                    <span class="pklrest-status <?php echo $key['revoked'] ? 'pklrest-status-revoked' : 'pklrest-status-active'; ?>">
                                        <?php echo $key['revoked'] ? esc_html__('Revoked', 'pklrest-rest-api-auth') : esc_html__('Active', 'pklrest-rest-api-auth'); ?>
                                    </span>
                            </td>
                            <td>
                                <?php if (!$key['revoked']): ?>
                                    <button class="button button-small pklrest-revoke-key" data-token-id="<?php echo esc_attr($key['id']); ?>">
                                        <?php esc_html_e('Revoke', 'pklrest-rest-api-auth'); ?>
                                    </button>
                                <?php else: ?>
                                    <button class="button button-small pklrest-restore-key" data-token-id="<?php echo esc_attr($key['id']); ?>">
                                        <?php esc_html_e('Restore', 'pklrest-rest-api-auth'); ?>
                                    </button>
                                <?php endif; ?>
                                <button class="button button-small button-link-delete pklrest-delete-key" data-token-id="<?php echo esc_attr($key['id']); ?>">
                                    <?php esc_html_e('Delete', 'pklrest-rest-api-auth'); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <div class="pklrest-usage-guide">
                <h2><?php esc_html_e('How to Use the API Key', 'pklrest-rest-api-auth'); ?></h2>
                <p><?php esc_html_e('To authenticate API requests, include the API key in the Authorization header:', 'pklrest-rest-api-auth'); ?></p>
                <pre><code>Authorization: Bearer YOUR_API_KEY</code></pre>
                <p><?php esc_html_e('Example using cURL:', 'pklrest-rest-api-auth'); ?></p>
                <pre><code>curl -H "Authorization: Bearer YOUR_API_KEY" <?php echo esc_url(rest_url('wp/v2/posts')); ?></code></pre>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX: Delete API key
     */
    public function ajax_delete_api_key()
    {
        check_ajax_referer('pklrest_admin_api_key_action');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(esc_html__('You do not have permission to perform this action.', 'pklrest-rest-api-auth'));
        }

        $token_id = isset($_POST['token_id']) ? intval($_POST['token_id']) : 0;

        if (!$token_id) {
            wp_send_json_error(esc_html__('Invalid token ID.', 'pklrest-rest-api-auth'));
        }

        $result = $this->database->delete_api_key($token_id);

        if ($result !== false) {
            wp_send_json_success(esc_html__('API key deleted successfully.', 'pklrest-rest-api-auth'));
        } else {
            wp_send_json_error(esc_html__('Failed to delete API key.', 'pklrest-rest-api-auth'));
        }
    }

    /**
     * AJAX: Revoke API key
     */
    public function ajax_revoke_api_key()
    {
        check_ajax_referer('pklrest_admin_api_key_action');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(esc_html__('You do not have permission to perform this action.', 'pklrest-rest-api-auth'));
        }

        $token_id = isset($_POST['token_id']) ? intval($_POST['token_id']) : 0;

        if (!$token_id) {
            wp_send_json_error(esc_html__('Invalid token ID.', 'pklrest-rest-api-auth'));
        }

        $result = $this->database->revoke_token($token_id);

        if ($result !== false) {
            wp_send_json_success(esc_html__('API key revoked successfully.', 'pklrest-rest-api-auth'));
        } else {
            wp_send_json_error(esc_html__('Failed to revoke API key.', 'pklrest-rest-api-auth'));
        }
    }

    /**
     * AJAX: Restore API key
     */
    public function ajax_restore_api_key()
    {
        check_ajax_referer('pklrest_admin_api_key_action');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(esc_html__('You do not have permission to perform this action.', 'pklrest-rest-api-auth'));
        }

        $token_id = isset($_POST['token_id']) ? intval($_POST['token_id']) : 0;

        if (!$token_id) {
            wp_send_json_error(esc_html__('Invalid token ID.', 'pklrest-rest-api-auth'));
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'pklrest_api_keys';

        $result = $wpdb->update(
            $table_name,
            array('revoked' => 0),
            array('id' => $token_id),
            array('%d'),
            array('%d')
        );

        if ($result !== false) {
            wp_send_json_success(esc_html__('API key restored successfully.', 'pklrest-rest-api-auth'));
        } else {
            wp_send_json_error(esc_html__('Failed to restore API key.', 'pklrest-rest-api-auth'));
        }
    }
}