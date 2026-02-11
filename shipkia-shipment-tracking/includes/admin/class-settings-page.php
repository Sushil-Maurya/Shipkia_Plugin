<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Settings Page Class
 */
class Shipkia_Settings_Page
{

    /**
     * Render Settings Page
     */
    public static function render()
    {
        if (isset($_GET['settings-updated'])) {
            add_settings_error('shipkia_messages', 'shipkia_message', __('Settings Saved', 'shipkia-shipment-tracking'), 'updated');
        }
        settings_errors('shipkia_messages');
        ?>
        <div class="wrap">
            <h1>
                <?php echo esc_html(get_admin_page_title()); ?>
            </h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('shipkia_settings_group');
                do_settings_sections('shipkia-shipment-tracking');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Init Settings
     */
    public static function init_settings()
    {
        register_setting('shipkia_settings_group', 'shipkia_tracking_enabled');
        // register_setting('shipkia_settings_group', 'shipkia_tracking_url_template');
        register_setting('shipkia_settings_group', 'shipkia_tracking_button_text');
        register_setting('shipkia_settings_group', 'shipkia_tracking_new_tab');
        register_setting('shipkia_settings_group', 'shipkia_app_url');
        register_setting('shipkia_settings_group', 'shipkia_multisite_sites');

        // Shipkia Platform Connection Section
        add_settings_section(
            'shipkia_connection_section',
            __('Shipkia Platform Connection', 'shipkia-shipment-tracking'),
            array('Shipkia_Settings_Page', 'render_connection_section_description'),
            'shipkia-shipment-tracking'
        );

        add_settings_field(
            'shipkia_connection_status',
            __('Connection Status', 'shipkia-shipment-tracking'),
            array('Shipkia_Settings_Page', 'render_connection_status_field'),
            'shipkia-shipment-tracking',
            'shipkia_connection_section'
        );

        add_settings_field(
            'shipkia_app_url',
            __('Shipkia App URL', 'shipkia-shipment-tracking'),
            array('Shipkia_Settings_Page', 'render_app_url_field'),
            'shipkia-shipment-tracking',
            'shipkia_connection_section'
        );

        if (is_multisite()) {
            add_settings_field(
                'shipkia_multisite_sites',
                __('Select Sites to Connect', 'shipkia-shipment-tracking'),
                array('Shipkia_Settings_Page', 'render_multisite_field'),
                'shipkia-shipment-tracking',
                'shipkia_connection_section'
            );
        }

        // General Settings Section
        add_settings_section(
            'shipkia_general_section',
            __('General Settings', 'shipkia-shipment-tracking'),
            null,
            'shipkia-shipment-tracking'
        );

        add_settings_field(
            'shipkia_tracking_enabled',
            __('Enable Tracking', 'shipkia-shipment-tracking'),
            array('Shipkia_Settings_Page', 'render_enabled_field'),
            'shipkia-shipment-tracking',
            'shipkia_general_section'
        );

        // add_settings_field(
        //     'shipkia_tracking_url_template',
        //     __('Tracking URL Template', 'shipkia-shipment-tracking'),
        //     array('Shipkia_Settings_Page', 'render_url_field'),
        //     'shipkia-shipment-tracking',
        //     'shipkia_general_section'
        // );

        add_settings_field(
            'shipkia_tracking_button_text',
            __('Button Text', 'shipkia-shipment-tracking'),
            array('Shipkia_Settings_Page', 'render_text_field'),
            'shipkia-shipment-tracking',
            'shipkia_general_section'
        );

        add_settings_field(
            'shipkia_tracking_new_tab',
            __('Open in new tab', 'shipkia-shipment-tracking'),
            array('Shipkia_Settings_Page', 'render_new_tab_field'),
            'shipkia-shipment-tracking',
            'shipkia_general_section'
        );
    }

    public static function render_enabled_field()
    {
        $val = get_option('shipkia_tracking_enabled', 'yes');
        ?>
        <label><input type="checkbox" name="shipkia_tracking_enabled" value="yes" <?php checked($val, 'yes'); ?> />
            <?php _e('Enable Shipkia Tracking', 'shipkia-shipment-tracking'); ?>
        </label>
        <?php
    }



    public static function render_text_field()
    {
        $val = get_option('shipkia_tracking_button_text', 'Track');
        ?>
        <input type="text" name="shipkia_tracking_button_text" value="<?php echo esc_attr($val); ?>" class="regular-text" />
        <?php
    }

    public static function render_new_tab_field()
    {
        $val = get_option('shipkia_tracking_new_tab', 'yes');
        ?>
        <label><input type="checkbox" name="shipkia_tracking_new_tab" value="yes" <?php checked($val, 'yes'); ?> />
            <?php _e('Open tracking link in a new tab', 'shipkia-shipment-tracking'); ?>
        </label>
        <?php
    }

    public static function render_multisite_field()
    {
        if (!is_multisite()) return;
        
        $sites = get_sites();
        $selected_sites = get_option('shipkia_multisite_sites', array());
        if (!is_array($selected_sites)) $selected_sites = array();
        
        ?>
        <div style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #fff;">
            <?php foreach ($sites as $site): 
                $site_details = get_blog_details($site->blog_id);
                $checked = in_array($site->blog_id, $selected_sites) ? 'checked' : '';
            ?>
            <label style="display: block; margin-bottom: 5px;">
                <input type="checkbox" name="shipkia_multisite_sites[]" value="<?php echo esc_attr($site->blog_id); ?>" <?php echo $checked; ?> /> 
                <?php echo esc_html($site_details->blogname) . ' (' . esc_html($site_details->domain . $site_details->path) . ')'; ?>
            </label>
            <?php endforeach; ?>
        </div>
        <p class="description"><?php _e('Select the sites you want to connect to Shipkia.', 'shipkia-shipment-tracking'); ?></p>
        <?php
    }

    // ==================== Shipkia Connection Fields ====================

    public static function render_connection_section_description()
    {
        ?>
        <p><?php _e('Connect your WooCommerce store to the Shipkia platform for advanced shipment tracking and management.', 'shipkia-shipment-tracking'); ?></p>
        <?php
    }

    public static function render_connection_status_field()
    {
        $status = Shipkia_Auth::get_connection_status();
        $is_connected = $status['connected'];
        $last_error = get_transient('shipkia_connection_error');
        $last_check = get_transient('shipkia_auto_connect_checked_settings') ? 'Just now' : 'More than 5 minutes ago';
        $show_activation_notice = get_transient('shipkia_show_activation_notice');
        if ($show_activation_notice && !$is_connected) {
            delete_transient('shipkia_show_activation_notice');
        }
        ?>
        <div id="shipkia-connection-status">
            <?php if ($show_activation_notice && !$is_connected): ?>
                <div class="notice notice-info inline" style="padding: 15px; border-left: 4px solid #2271b1; margin-bottom: 20px; background: #fff;">
                    <h3 style="margin-top: 0; color: #2271b1;">🚀 <?php _e('Ready to Connect!', 'shipkia-shipment-tracking'); ?></h3>
                    <p><?php _e('Shipkia Shipment Tracking plugin has been activated. Please initialize the connection to start syncing your orders and products.', 'shipkia-shipment-tracking'); ?></p>
                    <button type="button" id="shipkia-initialize-btn" class="button button-primary button-large" style="margin-top: 10px;">
                        <?php _e('Initialize Store Connection', 'shipkia-shipment-tracking'); ?>
                    </button>
                    <div id="shipkia-init-message"></div>
                </div>
                <script>
                jQuery(document).ready(function($) {
                    $('#shipkia-initialize-btn').on('click', function() {
                        var $btn = $(this);
                        $btn.prop('disabled', true).text('<?php _e('Initializing...', 'shipkia-shipment-tracking'); ?>');
                        $('#shipkia-init-message').html('');
                        
                        $.post(ajaxurl, {
                            action: 'shipkia_sync_platform',
                            create_new: 1,
                            nonce: '<?php echo wp_create_nonce("shipkia_connection_nonce"); ?>'
                        }, function(response) {
                            if (response.success) {
                                $('#shipkia-init-message').html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>');
                                setTimeout(function(){ location.reload(); }, 1500);
                            } else {
                                $('#shipkia-init-message').html('<div class="notice notice-error inline"><p>' + (response.data.message || 'Initialization failed') + '</p></div>');
                                $btn.prop('disabled', false).text('<?php _e('Initialize Store Connection', 'shipkia-shipment-tracking'); ?>');
                            }
                        });
                    });
                });
                </script>
            <?php endif; ?>

            <div style="background: #f0f0f1; padding: 15px; border-left: 4px solid <?php echo $is_connected ? '#46b450' : '#dc3232'; ?>; margin-bottom: 15px;">
                <?php if ($is_connected): ?>
                    <p style="color: <?php echo ($is_connected && $status['is_active']) ? '#46b450' : '#dc3232'; ?>; font-weight: bold; margin: 0 0 15px 0; font-size: 14px;">
                        Status: <?php echo ($is_connected && $status['is_active']) ? 'Connected ✅' : ($is_connected ? 'Inactive ⚠' : 'Not Connected ❌'); ?>
                    </p>
                    <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                        <tr>
                            <td style="padding: 5px 0; width: 150px;"><strong>Platform:</strong></td>
                            <td style="padding: 5px 0;"><?php echo esc_html(ucfirst($status['platform'] ?: 'WooCommerce')); ?></td>
                        </tr>
                        <?php if ($status['connected_domain']): ?>
                        <tr>
                            <td style="padding: 5px 0;"><strong>Store Domain:</strong></td>
                            <td style="padding: 5px 0;"><code><?php echo esc_html($status['connected_domain']); ?></code></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($status['shipkia_url']): ?>
                        <tr>
                            <td style="padding: 5px 0;"><strong>Shipkia URL:</strong></td>
                            <td style="padding: 5px 0;">
                                <a href="<?php echo esc_url($status['shipkia_url']); ?>" target="_blank" style="text-decoration: none;">
                                    <code style="color: #0073aa;"><?php echo esc_html($status['shipkia_url']); ?></code>
                                    <span class="dashicons dashicons-external" style="font-size: 12px; vertical-align: middle;"></span>
                                </a>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <tr>
                            <td style="padding: 5px 0;"><strong>Store ID:</strong></td>
                            <td style="padding: 5px 0;"><code><?php echo esc_html($status['store_id']); ?></code></td>
                        </tr>
                        <tr>
                            <td style="padding: 5px 0;"><strong>Platform API:</strong></td>
                            <td style="padding: 5px 0;">
                                <?php if ($status['api_connected']): ?>
                                    <span style="color: #46b450;">✓ Connected</span>
                                <?php else: ?>
                                    <span style="color: #f39c12;">⚠ Not Connected</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 5px 0;"><strong>Webhooks:</strong></td>
                            <td style="padding: 5px 0;">
                                <?php if ($status['webhooks_active']): ?>
                                    <span style="color: #46b450;">✓ Active</span>
                                <?php else: ?>
                                    <span style="color: #f39c12;">⚠ Inactive</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                    
                    <?php if (!$status['api_connected']): ?>
                        <div style="background: #fff; border: 1px solid #f39c12; padding: 10px; margin-top: 15px;">
                            <p style="margin: 0 0 10px 0; color: #e67e22;">
                                <strong>Complete your setup!</strong><br>
                                The plugin is connected, but the Shipkia Platform needs permission to sync orders.
                            </p>
                            <button type="button" id="shipkia-connect-platform-btn" class="button button-primary">
                                Connect Platform API
                            </button>
                        </div>
                    <?php endif; ?>
                    
                    <p style="margin: 15px 0 0 0; display: flex; gap: 10px;">
                        <?php if ($status['is_active']): ?>
                            <button type="button" id="shipkia-sync-btn" class="button button-primary">
                                <span class="dashicons dashicons-update" style="font-size: 16px; vertical-align: middle; line-height: 28px;"></span>
                                Sync Now
                            </button>
                            <?php if ($status['created_from'] === 'Plugin'): 
                                $app_url = get_option('shipkia_app_url', 'https://app.shipkia.com');
                                $sync_url = rtrim($app_url, '/') . '/integrations?sync_shop=' . urlencode($status['store_id']) . '&platform=woocommerce';
                            ?>
                                <a href="<?php echo esc_url($sync_url); ?>" target="_blank" class="button button-secondary">
                                    <span class="dashicons dashicons-cloud-upload" style="font-size: 16px; vertical-align: middle; line-height: 28px;"></span>
                                    Sync Order/Product
                                </a>
                            <?php endif; ?>
                            <button type="button" id="shipkia-disconnect-btn" class="button button-secondary">
                                Disconnect
                            </button>
                        <?php else: ?>
                            <button type="button" id="shipkia-reconnect-btn" class="button button-primary">
                                <span class="dashicons dashicons-update" style="font-size: 16px; vertical-align: middle; line-height: 28px;"></span>
                                Reconnect
                            </button>
                            <button type="button" id="shipkia-remove-btn" class="button button-link-delete" style="color: #dc3232;">
                                Remove Store
                            </button>
                        <?php endif; ?>
                    </p>
                <?php else: ?>
                    <!-- Not Connected View -->
                    <p style="color: #dc3232; font-weight: bold; margin: 0 0 15px 0; font-size: 14px;">
                        Status: Not Connected ❌
                    </p>
                    <?php if ($last_error): ?>
                        <div class="notice notice-error inline" style="margin-bottom: 10px;">
                            <p><?php echo esc_html($last_error); ?></p>
                        </div>
                    <?php endif; ?>
                    <p style="font-size: 12px; color: #666; margin-top: 5px;">
                        Last background check: <?php echo esc_html($last_check); ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>
        <div id="shipkia-connection-message" style="margin-top: 10px;"></div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Function to handle getting platform auth URL
            $('#shipkia-connect-platform-btn').on('click', function() {
                if (!confirm('You are about to be redirected to the Shipkia platform/WooCommerce authorization page to grant permissions. Do you want to proceed?')) {
                    return;
                }

                var $btn = $(this);
                $btn.prop('disabled', true).text('Redirecting...');
                
                $.post(ajaxurl, {
                    action: 'shipkia_get_auth_url',
                    nonce: '<?php echo wp_create_nonce("shipkia_connection_nonce"); ?>'
                }, function(response) {
                    if (response.success && response.data.auth_url) {
                        // Open in new tab as requested
                        window.open(response.data.auth_url, '_blank');
                        // Also update button to allow retry if needed
                        $btn.prop('disabled', false).text('Connect Platform API');
                        $('#shipkia-connection-message').html('<div class="notice notice-info inline"><p>Authorization opened in a new tab. Please complete it and then refresh this page.</p></div>');
                    } else {
                        alert(response.data.message || 'Failed to initiate platform connection.');
                        $btn.prop('disabled', false).text('Connect Platform API');
                    }
                }).fail(function() {
                    alert('Network error. Please try again.');
                    $btn.prop('disabled', false).text('Connect Platform API');
                });
            });
            
            // Existing handlers for sync/disconnect could be here or in main JS file
            // Re-implementing simplified inline for robustness if main JS is missing logic
            $('#shipkia-sync-btn').on('click', function() {
                var $btn = $(this);
                $btn.prop('disabled', true).text('Syncing...');
                $('#shipkia-connection-message').html('');
                
                function performSync(createNew) {
                    $.post(ajaxurl, {
                        action: 'shipkia_sync_platform',
                        create_new: createNew ? 1 : 0,
                        nonce: '<?php echo wp_create_nonce("shipkia_connection_nonce"); ?>'
                    }, function(response) {
                        if (response.success) {
                            $('#shipkia-connection-message').html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>');
                            setTimeout(function(){ location.reload(); }, 2000);
                        } else {
                            if (response.data && response.data.status === 'not_found') {
                                if (confirm(response.data.message || 'Store not found. Create new store?')) {
                                    performSync(true); // Retry with create_new = true
                                    return;
                                }
                            }
                            $('#shipkia-connection-message').html('<div class="notice notice-error inline"><p>' + (response.data.message || 'Sync failed') + '</p></div>');
                            $btn.prop('disabled', false).text('Sync Now');
                        }
                    }).fail(function() {
                        $('#shipkia-connection-message').html('<div class="notice notice-error inline"><p>Network error. Please try again.</p></div>');
                        $btn.prop('disabled', false).text('Sync Now');
                    });
                }

                performSync(false); // Initial attempt without force creating
            });
            
            $('#shipkia-disconnect-btn').on('click', function() {
                if(!confirm('Are you sure you want to disconnect? This will stop all synchronization. You can reconnect later.')) return;
                var $btn = $(this);
                $btn.prop('disabled', true).text('Disconnecting...');
                $('#shipkia-connection-message').html('');
                
                $.post(ajaxurl, {
                    action: 'shipkia_disconnect_platform',
                    nonce: '<?php echo wp_create_nonce("shipkia_connection_nonce"); ?>'
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        $('#shipkia-connection-message').html('<div class="notice notice-error inline"><p>' + (response.data.message || 'Disconnect failed') + '</p></div>');
                        $btn.prop('disabled', false).text('Disconnect');
                    }
                }).fail(function() {
                    $('#shipkia-connection-message').html('<div class="notice notice-error inline"><p>Network error. Please try again.</p></div>');
                    $btn.prop('disabled', false).text('Disconnect');
                });
            });

            $('#shipkia-reconnect-btn').on('click', function() {
                var $btn = $(this);
                $btn.prop('disabled', true).text('Reconnecting...');
                $('#shipkia-connection-message').html('');

                $.post(ajaxurl, {
                    action: 'shipkia_reconnect_platform',
                    nonce: '<?php echo wp_create_nonce("shipkia_connection_nonce"); ?>'
                }, function(response) {
                    if (response.success) {
                        $('#shipkia-connection-message').html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>');
                        setTimeout(function(){ location.reload(); }, 1500);
                    } else {
                        $('#shipkia-connection-message').html('<div class="notice notice-error inline"><p>' + (response.data.message || 'Reconnection failed') + '</p></div>');
                        $btn.prop('disabled', false).text('Reconnect');
                    }
                }).fail(function() {
                    $('#shipkia-connection-message').html('<div class="notice notice-error inline"><p>Network error. Please try again.</p></div>');
                    $btn.prop('disabled', false).text('Reconnect');
                });
            });

            $('#shipkia-remove-btn').on('click', function() {
                if(!confirm('Are you sure you want to permanently remove this store? This action cannot be undone.')) return;
                var $btn = $(this);
                $btn.prop('disabled', true).text('Removing...');
                $('#shipkia-connection-message').html('');
                
                $.post(ajaxurl, {
                    action: 'shipkia_remove_platform',
                    nonce: '<?php echo wp_create_nonce("shipkia_connection_nonce"); ?>'
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        $('#shipkia-connection-message').html('<div class="notice notice-error inline"><p>' + (response.data.message || 'Removal failed') + '</p></div>');
                        $btn.prop('disabled', false).text('Remove Store');
                    }
                }).fail(function() {
                    $('#shipkia-connection-message').html('<div class="notice notice-error inline"><p>Network error. Please try again.</p></div>');
                    $btn.prop('disabled', false).text('Remove Store');
                });
            });
        });
        </script>
        <?php
    }

    public static function render_app_url_field()
    {
        $is_connected = Shipkia_Auth::is_connected();
        $val = get_option('shipkia_app_url', 'https://app.shipkia.com');
        
        ?>
        <div class="shipkia-app-url-container">
            <input type="text" id="shipkia_app_url" name="shipkia_app_url" value="<?php echo esc_attr($val); ?>" 
                   class="regular-text" <?php echo $is_connected ? 'disabled readonly' : ''; ?> 
                   placeholder="https://app.shipkia.com" />
            
            <?php if (!$is_connected): ?>
                <p class="description"><?php _e('Enter your Shipkia platform URL (e.g., https://app.shipkia.com)', 'shipkia-shipment-tracking'); ?></p>
                <button type="button" id="shipkia-connect-btn" class="button button-primary" style="margin-top: 10px;">
                    <?php _e('Connect to Shipkia', 'shipkia-shipment-tracking'); ?>
                </button>
                
                <script>
                jQuery(document).ready(function($) {
                    $('#shipkia-connect-btn').on('click', function() {
                        var appUrl = $('#shipkia_app_url').val();
                        if (!appUrl) {
                            alert('Please enter Shipkia URL');
                            return;
                        }
                        
                        var $btn = $(this);
                        $btn.prop('disabled', true).text('Connecting...');
                        $('#shipkia-connection-message').html('');
                        
                        $.post(ajaxurl, {
                            action: 'shipkia_connect_platform',
                            app_url: appUrl,
                            nonce: '<?php echo wp_create_nonce("shipkia_connection_nonce"); ?>'
                        }, function(response) {
                            if (response.success) {
                                $('#shipkia-connection-message').html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>');
                                setTimeout(function(){ location.reload(); }, 1500);
                            } else {
                                $('#shipkia-connection-message').html('<div class="notice notice-error inline"><p>' + (response.data.message || 'Connection failed') + '</p></div>');
                                $btn.prop('disabled', false).text('Connect to Shipkia');
                            }
                        });
                    });
                });
                </script>
            <?php else: ?>
                <p class="description"><?php _e('Disconnect above to change the Shipkia URL', 'shipkia-shipment-tracking'); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

}

// AJAX Handlers
add_action('wp_ajax_shipkia_connect_platform', 'shipkia_handle_connect_ajax');
add_action('wp_ajax_shipkia_disconnect_platform', 'shipkia_handle_disconnect_ajax');
add_action('wp_ajax_shipkia_sync_platform', 'shipkia_handle_sync_ajax');
add_action('wp_ajax_shipkia_remove_platform', 'shipkia_handle_remove_ajax');
add_action('wp_ajax_shipkia_reconnect_platform', 'shipkia_handle_reconnect_ajax');
add_action('wp_ajax_shipkia_get_auth_url', 'shipkia_handle_get_auth_url_ajax');

function shipkia_handle_reconnect_ajax()
{
    check_ajax_referer('shipkia_connection_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Unauthorized', 'shipkia-shipment-tracking')));
    }

    $result = Shipkia_Auth::reconnect();

    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result);
    }
}

function shipkia_handle_get_auth_url_ajax()
{
    check_ajax_referer('shipkia_connection_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Unauthorized', 'shipkia-shipment-tracking')));
    }
    
    // Determine return URL (current settings page)
    $return_url = admin_url('admin.php?page=shipkia-settings');
    
    $backend_api_url = Shipkia_Auth::get_platform_auth_url($return_url);
    
    if (!$backend_api_url) {
        wp_send_json_error(array('message' => 'Failed to generate backend API URL.'));
    }

    // Call backend server-side to get the real WooCommerce authorize URL
    $response = wp_remote_get($backend_api_url, array('timeout' => 15));

    if (is_wp_error($response)) {
        wp_send_json_error(array('message' => 'Backend communication failed: ' . $response->get_error_message()));
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    $auth_url = isset($body['message']['auth_url']) ? $body['message']['auth_url'] : null;
    
    if ($auth_url) {
        wp_send_json_success(array('auth_url' => $auth_url));
    } else {
        $msg = isset($body['message']) && is_string($body['message']) ? $body['message'] : 'Failed to retrieve WooCommerce authorize URL.';
        wp_send_json_error(array('message' => $msg));
    }
}

function shipkia_handle_connect_ajax()
{
    check_ajax_referer('shipkia_connection_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Unauthorized', 'shipkia-shipment-tracking')));
    }

    $app_url = isset($_POST['app_url']) ? sanitize_text_field($_POST['app_url']) : '';

    if (empty($app_url)) {
        wp_send_json_error(array('message' => __('Shipkia URL is required', 'shipkia-shipment-tracking')));
    }

    $result = Shipkia_Auth::manual_connect($app_url);

    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result);
    }
}

function shipkia_handle_disconnect_ajax()
{
    check_ajax_referer('shipkia_connection_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Unauthorized', 'shipkia-shipment-tracking')));
    }

    $result = Shipkia_Auth::disconnect();

    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result);
    }
}

function shipkia_handle_remove_ajax()
{
    check_ajax_referer('shipkia_connection_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Unauthorized', 'shipkia-shipment-tracking')));
    }

    $result = Shipkia_Auth::remove_store();

    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result);
    }
}

function shipkia_handle_sync_ajax()
{
    check_ajax_referer('shipkia_connection_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Unauthorized', 'shipkia-shipment-tracking')));
    }

    $create_new = isset($_POST['create_new']) ? filter_var($_POST['create_new'], FILTER_VALIDATE_BOOLEAN) : false;
    $result = Shipkia_Auth::manual_sync($create_new);

    if (!$result['success'] && isset($result['store_not_found'])) {
        // Specifically handle store not found to allow confirmation
        wp_send_json_error(array(
            'status' => 'not_found',
            'message' => $result['message']
        ));
        return;
    }

    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result);
    }
}

add_action('update_option_shipkia_tracking_enabled', 'shipkia_on_settings_update');
add_action('update_option_shipkia_tracking_button_text', 'shipkia_on_settings_update');
add_action('update_option_shipkia_tracking_new_tab', 'shipkia_on_settings_update');

function shipkia_on_settings_update() {
    Shipkia_Auth::sync_settings();
}

add_action('admin_init', array('Shipkia_Settings_Page', 'init_settings'));
