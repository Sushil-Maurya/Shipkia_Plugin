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
     * Check whether the current user can manage this plugin.
     */
    private static function current_user_can_manage_plugin()
    {
        return current_user_can('manage_woocommerce') || current_user_can('manage_options');
    }

    /**
     * Render Settings Page
     */
    public static function render()
    {
        if (function_exists('add_settings_error') && filter_input(INPUT_GET, 'settings-updated')) {
            add_settings_error('shipkia_messages', 'shipkia_message', (function_exists('__') ? __('Settings Saved', 'shipkia-connect') : 'Settings Saved'), 'updated');
        }
        if (function_exists('settings_errors')) {
            settings_errors('shipkia_messages');
        }
        ?>
        <div class="wrap">
            <h1>
                <?php echo esc_html(get_admin_page_title()); ?>
            </h1>
            <form action="options.php" method="post">
                <?php
                if (function_exists('settings_fields')) {
                    settings_fields('shipkia_settings_group');
                }
                if (function_exists('do_settings_sections')) {
                    do_settings_sections('shipkia-connect');
                }
                if (function_exists('submit_button')) {
                    submit_button();
                }
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
        if (!function_exists('register_setting')) {
            return;
        }

        register_setting('shipkia_settings_group', 'shipkia_tracking_enabled', 'sanitize_text_field');
        register_setting('shipkia_settings_group', 'shipkia_tracking_button_text', 'sanitize_text_field');
        register_setting('shipkia_settings_group', 'shipkia_tracking_new_tab', 'sanitize_text_field');
        register_setting('shipkia_settings_group', 'shipkia_app_url', 'esc_url_raw');
        register_setting('shipkia_settings_group', 'shipkia_multisite_sites', array('Shipkia_Settings_Page', 'sanitize_multisite_sites'));

        if (function_exists('add_settings_section')) {
            // ShipKia Platform Connection Section
            add_settings_section(
                'shipkia_connection_section',
                (function_exists('__') ? __('ShipKia Platform Connection', 'shipkia-connect') : 'ShipKia Platform Connection'),
                array('Shipkia_Settings_Page', 'render_connection_section_description'),
                'shipkia-connect'
            );

            // General Settings Section
            add_settings_section(
                'shipkia_general_section',
                (function_exists('__') ? __('General Settings', 'shipkia-connect') : 'General Settings'),
                null,
                'shipkia-connect'
            );
        }

        if (function_exists('add_settings_field')) {
            add_settings_field(
                'shipkia_connection_status',
                (function_exists('__') ? __('Connection Status', 'shipkia-connect') : 'Connection Status'),
                array('Shipkia_Settings_Page', 'render_connection_status_field'),
                'shipkia-connect',
                'shipkia_connection_section'
            );

            add_settings_field(
                'shipkia_app_url',
                (function_exists('__') ? __('ShipKia App URL', 'shipkia-connect') : 'ShipKia App URL'),
                array('Shipkia_Settings_Page', 'render_app_url_field'),
                'shipkia-connect',
                'shipkia_connection_section'
            );

            if (function_exists('is_multisite') && is_multisite()) {
                add_settings_field(
                    'shipkia_multisite_sites',
                    (function_exists('__') ? __('Select Sites to Connect', 'shipkia-connect') : 'Select Sites to Connect'),
                    array('Shipkia_Settings_Page', 'render_multisite_field'),
                    'shipkia-connect',
                    'shipkia_connection_section'
                );
            }

            add_settings_field(
                'shipkia_tracking_enabled',
                (function_exists('__') ? __('Enable Tracking', 'shipkia-connect') : 'Enable Tracking'),
                array('Shipkia_Settings_Page', 'render_enabled_field'),
                'shipkia-connect',
                'shipkia_general_section'
            );

            add_settings_field(
                'shipkia_tracking_button_text',
                (function_exists('__') ? __('Button Text', 'shipkia-connect') : 'Button Text'),
                array('Shipkia_Settings_Page', 'render_text_field'),
                'shipkia-connect',
                'shipkia_general_section'
            );

            add_settings_field(
                'shipkia_tracking_new_tab',
                (function_exists('__') ? __('Open in new tab', 'shipkia-connect') : 'Open in new tab'),
                array('Shipkia_Settings_Page', 'render_new_tab_field'),
                'shipkia-connect',
                'shipkia_general_section'
            );
        }
    }

    public static function render_enabled_field()
    {
        $val = function_exists('get_option') ? get_option('shipkia_tracking_enabled', 'yes') : 'yes';
        ?>
        <label><input type="checkbox" name="shipkia_tracking_enabled" value="yes" <?php checked($val, 'yes'); ?> />
            <?php esc_html_e('Enable ShipKia Connect', 'shipkia-connect'); ?>
        </label>
        <?php
    }

    public static function render_text_field()
    {
        $val = function_exists('get_option') ? get_option('shipkia_tracking_button_text', 'Track') : 'Track';
        ?>
        <input type="text" name="shipkia_tracking_button_text"
            value="<?php echo esc_attr($val); ?>" class="regular-text" />
        <?php
    }

    public static function render_new_tab_field()
    {
        $val = function_exists('get_option') ? get_option('shipkia_tracking_new_tab', 'yes') : 'yes';
        ?>
        <label><input type="checkbox" name="shipkia_tracking_new_tab" value="yes" <?php checked($val, 'yes'); ?> />
            <?php esc_html_e('Open tracking link in a new tab', 'shipkia-connect'); ?>
        </label>
        <?php
    }

    public static function render_multisite_field()
    {
        if (!function_exists('is_multisite') || !is_multisite())
            return;

        $sites = function_exists('get_sites') ? get_sites() : array();
        $selected_sites = function_exists('get_option') ? get_option('shipkia_multisite_sites', array()) : array();
        if (!is_array($selected_sites))
            $selected_sites = array();

        ?>
        <div style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; background: #fff;">
            <?php foreach ($sites as $site):
                $site_details = function_exists('get_blog_details') ? get_blog_details($site->blog_id) : null;
                $checked = in_array($site->blog_id, $selected_sites) ? 'checked' : '';
                ?>
                <label style="display: block; margin-bottom: 5px;">
                    <input type="checkbox" name="shipkia_multisite_sites[]"
                        value="<?php echo esc_attr($site->blog_id); ?>" <?php checked($checked, 'checked'); ?> />
                    <?php
                    if ($site_details) {
                        echo esc_html($site_details->blogname) . ' (' . esc_html($site_details->domain . $site_details->path) . ')';
                    }
                    ?>
                </label>
            <?php endforeach; ?>
        </div>
        <p class="description">
            <?php esc_html_e('Select the sites you want to connect to ShipKia.', 'shipkia-connect'); ?>
        </p>
        <?php
    }

    // ==================== ShipKia Connection Fields ====================

    public static function render_connection_section_description()
    {
        ?>
        <p><?php esc_html_e('Connect your WooCommerce store to the ShipKia platform for advanced shipment tracking and management.', 'shipkia-connect'); ?>
        </p>
        <?php
    }

    public static function render_connection_status_field()
    {
        $status = Shipkia_Auth::get_connection_status();
        $is_connected = $status['connected'];
        $last_error = function_exists('get_transient') ? get_transient('shipkia_connection_error') : null;
        $last_check = (function_exists('get_transient') && get_transient('shipkia_auto_connect_checked_settings')) ? 'Just now' : 'More than 5 minutes ago';
        $show_activation_notice = function_exists('get_transient') ? get_transient('shipkia_show_activation_notice') : null;
        if ($show_activation_notice && !$is_connected) {
            if (function_exists('delete_transient')) {
                delete_transient('shipkia_show_activation_notice');
            }
        }
        ?>
        <div id="shipkia-connection-status">
            <?php if ($show_activation_notice && !$is_connected): ?>
                <div class="notice notice-info inline"
                    style="padding: 15px; border-left: 4px solid #2271b1; margin-bottom: 20px; background: #fff;">
                    <h3 style="margin-top: 0; color: #2271b1;">🚀
                        <?php esc_html_e('Ready to Connect!', 'shipkia-connect'); ?>
                    </h3>
                    <p><?php esc_html_e('ShipKia Connect plugin has been activated. Please initialize the connection to start syncing your orders and products.', 'shipkia-connect'); ?>
                    </p>
                    <button type="button" id="shipkia-initialize-btn" class="button button-primary button-large"
                        style="margin-top: 10px;">
                        <?php esc_html_e('Initialize Store Connection', 'shipkia-connect'); ?>
                    </button>
                    <div id="shipkia-init-message"></div>
                </div>
            <?php endif; ?>

            <div
                style="background: #f0f0f1; padding: 15px; border-left: 4px solid <?php echo $is_connected ? '#46b450' : '#dc3232'; ?>; margin-bottom: 15px;">
                <?php if ($is_connected): ?>
                    <p
                        style="color: <?php echo ($is_connected && $status['is_active']) ? '#46b450' : '#dc3232'; ?>; font-weight: bold; margin: 0 0 15px 0; font-size: 14px;">
                        Status:
                        <?php echo ($is_connected && $status['is_active']) ? 'Connected ✅' : ($is_connected ? 'Inactive ⚠' : 'Not Connected ❌'); ?>
                    </p>
                    <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                        <tr>
                            <td style="padding: 5px 0; width: 150px;"><strong>Platform:</strong></td>
                            <td style="padding: 5px 0;">
                                <?php echo esc_html(ucfirst($status['platform'] ?: 'WooCommerce')); ?>
                            </td>
                        </tr>
                        <?php if ($status['connected_domain']): ?>
                            <tr>
                                <td style="padding: 5px 0;"><strong>Store Domain:</strong></td>
                                <td style="padding: 5px 0;">
                                    <code><?php echo esc_html($status['connected_domain']); ?></code>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <?php if ($status['shipkia_url']): ?>
                            <tr>
                                <td style="padding: 5px 0;"><strong>ShipKia URL:</strong></td>
                                <td style="padding: 5px 0;">
                                    <a href="<?php echo esc_url($status['shipkia_url']); ?>"
                                        target="_blank" rel="noopener noreferrer" style="text-decoration: none;">
                                        <code
                                            style="color: #0073aa;"><?php echo esc_html($status['shipkia_url']); ?></code>
                                        <span class="dashicons dashicons-external"
                                            style="font-size: 12px; vertical-align: middle;"></span>
                                    </a>
                                </td>
                            </tr>
                        <?php endif; ?>
                        <tr>
                            <td style="padding: 5px 0;"><strong>Store ID:</strong></td>
                            <td style="padding: 5px 0;">
                                <code><?php echo esc_html($status['store_id']); ?></code>
                            </td>
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
                                The plugin is connected, but the ShipKia Platform needs permission to sync orders.
                            </p>
                            <button type="button" id="shipkia-connect-platform-btn" class="button button-primary">
                                Connect Platform API
                            </button>
                        </div>
                    <?php endif; ?>

                    <p style="margin: 15px 0 0 0; display: flex; gap: 10px;">
                        <?php if ($status['is_active']): ?>
                            <button type="button" id="shipkia-sync-btn" class="button button-primary">
                                <span class="dashicons dashicons-update"
                                    style="font-size: 16px; vertical-align: middle; line-height: 28px;"></span>
                                Sync Now
                            </button>
                            <?php if ($status['created_from'] === 'Plugin' && !$status['initial_sync_done']):
                                $app_url = function_exists('get_option') ? get_option('shipkia_app_url', 'https://app.shipkia.com') : 'https://app.shipkia.com';
                                $sync_url = rtrim($app_url, '/') . '/integrations?sync_shop=' . urlencode($status['store_id']) . '&platform=woocommerce';
                                ?>
                                <a href="<?php echo esc_url($sync_url); ?>"
                                    id="shipkia-sync-order-product-link" target="_blank" rel="noopener noreferrer" class="button button-secondary">
                                    <span class="dashicons dashicons-cloud-upload"
                                        style="font-size: 16px; vertical-align: middle; line-height: 28px;"></span>
                                    Sync to ShipKia
                                </a>
                            <?php endif; ?>
                            <button type="button" id="shipkia-disconnect-btn" class="button button-secondary">
                                Disconnect
                            </button>
                        <?php else: ?>
                            <button type="button" id="shipkia-reconnect-btn" class="button button-primary">
                                <span class="dashicons dashicons-update"
                                    style="font-size: 16px; vertical-align: middle; line-height: 28px;"></span>
                                Reconnect
                            </button>
                            <!-- <button type="button" id="shipkia-remove-btn" class="button button-link-delete" style="color: #dc3232;">
                                Remove Store
                            </button> -->
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
        <?php
    }

    public static function render_app_url_field()
    {
        $is_connected = Shipkia_Auth::is_connected();
        $val = function_exists('get_option') ? get_option('shipkia_app_url', 'https://app.shipkia.com') : 'https://app.shipkia.com';

        ?>
        <div class="shipkia-app-url-container">
            <input type="text" id="shipkia_app_url" name="shipkia_app_url"
                value="<?php echo esc_attr($val); ?>" class="regular-text" <?php echo $is_connected ? 'disabled readonly' : ''; ?> placeholder="https://app.shipkia.com" />

            <?php if (!$is_connected): ?>
                <p class="description">
                    <?php esc_html_e('Enter your ShipKia platform URL (e.g., https://app.shipkia.com)', 'shipkia-connect'); ?>
                </p>
                <button type="button" id="shipkia-connect-btn" class="button button-primary" style="margin-top: 10px;">
                    <?php esc_html_e('Connect to ShipKia', 'shipkia-connect'); ?>
                </button>

            <?php else: ?>
                <p class="description">
                    <?php esc_html_e('Disconnect above to change the ShipKia URL', 'shipkia-connect'); ?>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Init Hooks
     */
    public static function init()
    {
        add_action('admin_init', array(__CLASS__, 'init_settings'));
        
        // AJAX Handlers
        add_action('wp_ajax_shipkia_connect_platform', array(__CLASS__, 'handle_connect_ajax'));
        add_action('wp_ajax_shipkia_disconnect_platform', array(__CLASS__, 'handle_disconnect_ajax'));
        add_action('wp_ajax_shipkia_sync_platform', array(__CLASS__, 'handle_sync_ajax'));
        add_action('wp_ajax_shipkia_remove_platform', array(__CLASS__, 'handle_remove_ajax'));
        add_action('wp_ajax_shipkia_reconnect_platform', array(__CLASS__, 'handle_reconnect_ajax'));
        add_action('wp_ajax_shipkia_get_auth_url', array(__CLASS__, 'handle_get_auth_url_ajax'));
        add_action('wp_ajax_shipkia_mark_sync_requested', array(__CLASS__, 'handle_mark_sync_requested_ajax'));

        // Settings change hooks
        add_action('update_option_shipkia_tracking_enabled', array(__CLASS__, 'on_settings_update'));
        add_action('update_option_shipkia_tracking_button_text', array(__CLASS__, 'on_settings_update'));
        add_action('update_option_shipkia_tracking_new_tab', array(__CLASS__, 'on_settings_update'));

        add_filter('option_page_capability_shipkia_settings_group', array(__CLASS__, 'get_settings_capability'));
    }

    /**
     * Capability required to save ShipKia settings.
     */
    public static function get_settings_capability()
    {
        return 'manage_woocommerce';
    }

    /**
     * AJAX: Reconnect
     */
    public static function handle_reconnect_ajax()
    {
        check_ajax_referer('shipkia_connection_nonce', 'nonce');

        if (!self::current_user_can_manage_plugin()) {
            wp_send_json_error(array('message' => __('Unauthorized', 'shipkia-connect')));
            return;
        }

        $result = Shipkia_Auth::reconnect();

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX: Get Auth URL
     */
    public static function handle_get_auth_url_ajax()
    {
        check_ajax_referer('shipkia_connection_nonce', 'nonce');
        
        if (!self::current_user_can_manage_plugin()) {
            wp_send_json_error(array('message' => __('Unauthorized', 'shipkia-connect')));
            return;
        }

        // Determine return URL (current settings page)
        $return_url = admin_url('admin.php?page=shipkia-connect');

        $backend_api_url = Shipkia_Auth::get_platform_auth_url($return_url);

        if (!$backend_api_url) {
            wp_send_json_error(array('message' => __('Failed to generate backend API URL.', 'shipkia-connect')));
            return;
        }

        // Call backend server-side to get the real WooCommerce authorize URL
        $response = wp_remote_get($backend_api_url, array('timeout' => 15));

        if (is_wp_error($response)) {
            // translators: %s is the error message from the backend communication.
            wp_send_json_error(array('message' => sprintf(__('Backend communication failed: %s', 'shipkia-connect'), $response->get_error_message())));
            return;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code < 200 || $status_code >= 300) {
            wp_send_json_error(array('message' => sprintf(__('Backend returned HTTP %d while generating the authorization URL.', 'shipkia-connect'), intval($status_code))));
            return;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $auth_url = isset($body['message']['auth_url']) ? $body['message']['auth_url'] : null;

        if ($auth_url) {
            wp_send_json_success(array('auth_url' => $auth_url));
        } else {
            $msg = isset($body['message']) && is_string($body['message']) ? $body['message'] : __('Failed to retrieve WooCommerce authorize URL.', 'shipkia-connect');
            wp_send_json_error(array('message' => $msg));
        }
    }

    /**
     * AJAX: Connect
     */
    public static function handle_connect_ajax()
    {
        check_ajax_referer('shipkia_connection_nonce', 'nonce');

        if (!self::current_user_can_manage_plugin()) {
            wp_send_json_error(array('message' => __('Unauthorized', 'shipkia-connect')));
            return;
        }

        $app_url = filter_input(INPUT_POST, 'app_url', FILTER_SANITIZE_URL);

        if (empty($app_url)) {
            wp_send_json_error(array('message' => __('ShipKia URL is required', 'shipkia-connect')));
            return;
        }

        $result = Shipkia_Auth::manual_connect($app_url);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX: Disconnect
     */
    public static function handle_disconnect_ajax()
    {
        check_ajax_referer('shipkia_connection_nonce', 'nonce');

        if (!self::current_user_can_manage_plugin()) {
            wp_send_json_error(array('message' => __('Unauthorized', 'shipkia-connect')));
            return;
        }

        $result = Shipkia_Auth::disconnect();

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX: Remove
     */
    public static function handle_remove_ajax()
    {
        check_ajax_referer('shipkia_connection_nonce', 'nonce');

        if (!self::current_user_can_manage_plugin()) {
            wp_send_json_error(array('message' => __('Unauthorized', 'shipkia-connect')));
            return;
        }

        $result = Shipkia_Auth::remove_store();

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX: Sync
     */
    public static function handle_sync_ajax()
    {
        check_ajax_referer('shipkia_connection_nonce', 'nonce');

        if (!self::current_user_can_manage_plugin()) {
            wp_send_json_error(array('message' => __('Unauthorized', 'shipkia-connect')));
            return;
        }

        $create_new = filter_input(INPUT_POST, 'create_new', FILTER_VALIDATE_BOOLEAN);
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

    /**
     * AJAX: Mark Sync Requested
     */
    public static function handle_mark_sync_requested_ajax()
    {
        check_ajax_referer('shipkia_connection_nonce', 'nonce');

        if (!self::current_user_can_manage_plugin()) {
            wp_send_json_error(array('message' => __('Unauthorized', 'shipkia-connect')));
            return;
        }

        update_option('shipkia_initial_sync_done', true);
        wp_send_json_success();
    }

    /**
     * On Settings Update
     */
    public static function on_settings_update()
    {
        Shipkia_Auth::sync_settings();
    }

    /**
     * Sanitize Multisite Sites
     */
    public static function sanitize_multisite_sites($input)
    {
        if (!is_array($input)) {
            return array();
        }
        return array_map('intval', $input);
    }
}

// Initialize Settings Page Hooks
Shipkia_Settings_Page::init();
