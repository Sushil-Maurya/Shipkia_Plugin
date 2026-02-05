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
        
        ?>
        <div id="shipkia-connection-status">
            <div style="background: #f0f0f1; padding: 15px; border-left: 4px solid <?php echo $is_connected ? '#46b450' : '#dc3232'; ?>; margin-bottom: 15px;">
                <?php if ($is_connected): ?>
                    <!-- Connected View -->
                    <p style="color: #46b450; font-weight: bold; margin: 0 0 15px 0; font-size: 14px;">
                        Status: Connected ✅
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
                            <td style="padding: 5px 0;"><strong>Token Status:</strong></td>
                            <td style="padding: 5px 0;">
                                <?php if ($status['token_valid']): ?>
                                    <span style="color: #46b450;">✓ Active</span>
                                <?php else: ?>
                                    <span style="color: #dc3232;">✗ Expired</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                    <p style="margin: 15px 0 0 0; display: flex; gap: 10px;">
                        <button type="button" id="shipkia-sync-btn" class="button button-primary">
                            <span class="dashicons dashicons-update" style="font-size: 16px; vertical-align: middle; line-height: 28px;"></span>
                            Sync Now
                        </button>
                        <button type="button" id="shipkia-disconnect-btn" class="button button-secondary">
                            Disconnect
                        </button>
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

function shipkia_handle_sync_ajax()
{
    check_ajax_referer('shipkia_connection_nonce', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => __('Unauthorized', 'shipkia-shipment-tracking')));
    }

    $result = Shipkia_Auth::manual_sync();

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
