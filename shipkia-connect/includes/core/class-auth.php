<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ShipKia Authentication Class
 * 
 * Handles connection to ShipKia platform including:
 * - Auto-connect detection for existing stores
 * - Manual connection flow
 * - Token exchange and refresh
 * - Secure token storage
 * - HMAC signature generation
 */
class Shipkia_Auth
{
    /**
     * ShipKia API base URL
     */
    private static $api_base_url = null;

    /**
     * Plugin secret for HMAC signatures
     */
    private static $plugin_secret = null;

    /**
     * Flag to prevent sync_settings during disconnection
     */
    private static $disconnection_in_progress = false;

    /**
     * Initialize authentication system
     */
    public static function init()
    {
        // Ensure plugin secret exists
        self::ensure_plugin_secret();

        if (function_exists('is_admin') && is_admin()) {
            if (function_exists('add_action')) {
                add_action('admin_init', array(__CLASS__, 'redirect_after_activation'));
            }
        }
    }

    /**
     * Register REST API endpoints
     */
    public static function register_rest_routes()
    {
        register_rest_route('shipkia/v1', '/update-status', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'handle_status_update'),
            'permission_callback' => array(__CLASS__, 'check_update_status_permission'),
        ));
    }

    /**
     * Check permission for incoming status updates from ShipKia ERP.
     */
    public static function check_update_status_permission($request)
    {
        $params = $request->get_json_params();
        $passed_secret = isset($params['secret']) ? sanitize_text_field($params['secret']) : '';

        $expected_secret = self::get_plugin_secret();
        $consumer_secret = self::get_woocommerce_consumer_secret();

        $authorized = false;
        if (!empty($expected_secret) && hash_equals((string)$expected_secret, (string)$passed_secret)) {
            $authorized = true;
        }
        elseif (!empty($consumer_secret) && hash_equals((string)$consumer_secret, (string)$passed_secret)) {
            $authorized = true;
        }

        if (!$authorized) {
            if (!empty($expected_secret) && $passed_secret === $expected_secret) {
                $authorized = true;
            }
            elseif (!empty($consumer_secret) && $passed_secret === $consumer_secret) {
                $authorized = true;
            }
        }

        if (!$authorized) {
            return new WP_Error('rest_forbidden', __('You do not have permission to do this.', 'shipkia-connect'), array('status' => 401));
        }

        return true;
    }

    /**
     * Handle incoming status updates from ShipKia ERP
     */
    public static function handle_status_update($request)
    {
        $params = $request->get_json_params();
        $is_active = isset($params['is_active']) ? (bool)$params['is_active'] : false;

        if (function_exists('update_option')) {
            update_option('shipkia_is_active', $is_active);
            if ($is_active) {
                // If activated from backend, ensure webhooks and api connected status are true
                update_option('shipkia_api_connected', true);
                update_option('shipkia_webhooks_active', true);
            }
            else {
                update_option('shipkia_api_connected', false);
                update_option('shipkia_webhooks_active', false);
            }
            if (function_exists('set_transient')) {
                set_transient('shipkia_connection_verified', true, (defined('HOUR_IN_SECONDS') ? HOUR_IN_SECONDS : 3600));
            }
        }

        return array('success' => true);
    }

    /**
     * Redirect to settings page after plugin activation
     */
    public static function redirect_after_activation()
    {
        if (function_exists('get_transient') && get_transient('shipkia_plugin_just_activated')) {
            if (function_exists('delete_transient')) {
                delete_transient('shipkia_plugin_just_activated');
            }

            $already_connected = self::is_connected();
            if ($already_connected && function_exists('update_option')) {
                update_option('shipkia_initial_sync_done', true);
            }

            // Show first-time setup prompts only for stores that are not already connected.
            if (!$already_connected && function_exists('set_transient')) {
                set_transient('shipkia_show_activation_notice', true, 60);
                set_transient('shipkia_trigger_auto_connect', true, 60);
            }

            // Avoid redirect loops and ensure we're not already on the settings page
            $current_page = filter_input(INPUT_GET, 'page', FILTER_SANITIZE_SPECIAL_CHARS);
            if ($current_page === 'shipkia-connect') {
                return;
            }

            if (function_exists('wp_safe_redirect') && function_exists('admin_url')) {
                wp_safe_redirect(admin_url('admin.php?page=shipkia-connect'));
                exit;
            }
        }
    }

    /**
     * Log buffer for remote debugging
     */
    private static $log_buffer = array();

    /**
     * Custom logging method
     */
    public static function log($message, $level = 'info', $context = array(), $source = 'shipkia-auth')
    {
        $entry = '[' . gmdate('c') . '] [' . strtoupper((string)$level) . '] ' . (string)$message;
        self::$log_buffer[] = $entry;

        if (count(self::$log_buffer) > 100) {
            array_shift(self::$log_buffer);
        }

        // Use new logger class if available
        if (class_exists('Shipkia_Logger')) {
            Shipkia_Logger::add($message, $level, $context, $source);
        }
    }

    /**
     * Auto-connect check on admin init
     * Only runs once per session to avoid performance impact, 
     * but runs more frequently on the settings page or after activation.
     */
    public static function auto_connect_check()
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        $is_settings_page = ($screen && $screen->id === 'toplevel_page_shipkia-connect');

        // If connected and active, we periodically verify the connection status
        $is_active = function_exists('get_option') ? (bool)get_option('shipkia_is_active', false) : false;
        if (self::is_connected() && $is_active) {
            // Verify every 12 hours, or every 1 minute if on settings page
            $verify_interval = $is_settings_page ? 60 : (12 * (defined('HOUR_IN_SECONDS') ? HOUR_IN_SECONDS : 3600));

            if (function_exists('get_transient') && !get_transient('shipkia_connection_verified')) {
                self::attempt_auto_connect();
                if (function_exists('set_transient')) {
                    set_transient('shipkia_connection_verified', true, $verify_interval);
                }
            }
            return;
        }

        // If connected but INACTIVE, do NOT auto-connect (respect user disconnect)
        if (self::is_connected() && !$is_active) {
            return;
        }

        // Check if triggered by plugin activation
        $force_check = function_exists('get_transient') ? get_transient('shipkia_trigger_auto_connect') : null;

        if ($force_check) {
            // Clear the flag
            if (function_exists('delete_transient')) {
                delete_transient('shipkia_trigger_auto_connect');
            }
            // Force auto-connect attempt
            self::attempt_auto_connect();
            return;
        }

        // If on settings page, we check more frequently (every 5 minutes instead of 1 hour)
        $hour_in_seconds = defined('HOUR_IN_SECONDS') ? HOUR_IN_SECONDS : 3600;
        $check_interval = $is_settings_page ? 300 : $hour_in_seconds;

        // Skip if already checked recently
        if (function_exists('get_transient') && get_transient('shipkia_auto_connect_checked')) {
            // Only skip if not on settings page, or if checked VERY recently on settings page
            if (!$is_settings_page || get_transient('shipkia_auto_connect_checked_settings')) {
                return;
            }
        }

        // Mark as checked
        if (function_exists('set_transient')) {
            set_transient('shipkia_auto_connect_checked', true, $hour_in_seconds);
            if ($is_settings_page) {
                set_transient('shipkia_auto_connect_checked_settings', true, 300);
            }
        }

        // Attempt auto-connect
        self::attempt_auto_connect();
    }

    /**
     * Auto-sync on plugin activation
     * Called once when plugin is activated
     */
    public static function auto_sync_on_activate($create_new = true)
    {
        try {
            self::log('Starting auto_sync_on_activate. Create new: ' . ($create_new ? 'Yes' : 'No'));
            $domain = self::get_store_domain();
            $api_url = self::get_api_base_url();

            $is_connected = self::is_connected();
            $keys = null;

            // Explicit connect/reconnect must refresh keys before contacting ERP.
            if ($create_new) {
                self::log('Auto-sync: Explicit connection requested. Generating validated WooCommerce keys first.');
                $keys = self::ensure_rest_api_keys();

                if (!$keys) {
                    return array('success' => false, 'message' => 'Could not generate WooCommerce REST API keys.');
                }

                $data = self::send_auto_sync_request($domain, $api_url, $keys, true);

                if (is_array($data) && isset($data['connected']) && $data['connected'] === true) {
                    self::handle_auto_sync_response($data, $domain);
                    return array('success' => true, 'message' => isset($data['message']) ? $data['message'] : 'Connected successfully');
                }

                return array('success' => false, 'message' => (is_array($data) && isset($data['message'])) ? $data['message'] : 'Could not establish connection automatically.');
            }

            // Try to use existing cached keys first if they were created by WooCommerce.
            $cached_key = function_exists('get_option') ? get_option('shipkia_consumer_key_plaintext') : null;
            $cached_secret = function_exists('get_option') ? get_option('shipkia_consumer_secret_plaintext') : null;
            $key_generation_method = function_exists('get_option') ? get_option('shipkia_key_generation_method') : null;
            if ($cached_key && $cached_secret && $key_generation_method === 'woocommerce_builtin') {
                $keys = array(
                    'consumer_key' => $cached_key,
                    'consumer_secret' => $cached_secret
                );
            }
            elseif ($cached_key || $cached_secret) {
                self::log('Auto-sync: Ignoring cached WooCommerce keys because they were not generated by the WooCommerce key API.');
            }

            // PHASE 1: DISCOVERY - Try to sync with what we have (or nothing)
            // This allows the ERP to identify an already connected store by domain.
            self::log('Auto-sync Phase 1 (Discovery): Attempting sync with existing/no keys...');
            $data = self::send_auto_sync_request($domain, $api_url, $keys, false); // create_new=false for discovery

            // If discovery successful, we are done!
            if (is_array($data) && isset($data['connected']) && $data['connected'] === true) {
                self::log('Discovery successful! Store already connected in backend.');
                self::handle_auto_sync_response($data, $domain);
                return array('success' => true, 'message' => isset($data['message']) ? $data['message'] : 'Connected successfully');
            }

            // If we get here, it means we couldn't connect automatically
            if (is_array($data) && isset($data['store_not_found']) && $data['store_not_found'] === true) {
                return array('success' => false, 'store_not_found' => true, 'message' => $data['message']);
            }

            return array('success' => false, 'message' => (is_array($data) && isset($data['message'])) ? $data['message'] : 'Could not establish connection automatically.');

        }
        catch (Exception $e) {
            self::log('ShipKia auto-sync exception: ' . $e->getMessage(), 'error');
            return array('success' => false, 'message' => $e->getMessage());
        }
    }

    /**
     * Helper to send sync request
     */
    private static function send_auto_sync_request($domain, $api_url, $keys = null, $create_new = false)
    {
        $body_args = array(
            'domain' => $domain,
            'platform' => 'woocommerce',
            'secret' => self::get_plugin_secret(),
            'create_new' => $create_new ? 'true' : 'false'
        );

        if ($keys) {
            $body_args['consumer_key'] = $keys['consumer_key'];
            $body_args['consumer_secret'] = $keys['consumer_secret'];
        }

        // ATTACH LOGS TO REQUEST
        $body_args['client_logs'] = json_encode(self::$log_buffer);

        if (function_exists('wp_remote_post')) {
            $response = wp_remote_post($api_url . '/api/method/bu_ecommerce_integrations.api.woocommerce.auto_sync.auto_sync', array(
                'timeout' => 15,
                'body' => $body_args
            ));

            if (function_exists('is_wp_error') && is_wp_error($response)) {
                self::log('Request failed: ' . $response->get_error_message(), 'error');
                return null;
            }

            $body = json_decode(function_exists('wp_remote_retrieve_body') ? wp_remote_retrieve_body($response) : '', true);

            // Log raw response for debugging (only in debug level)
            // self::log('Raw response: ' . (is_array($body) || is_object($body) ? json_encode($body) : $body), 'debug');

            if (isset($body['message'])) {
                return $body['message'];
            }

            if (is_array($body) && isset($body['connected'])) {
                return $body;
            }
        }
        return null;
    }

    /**
     * Helper to handle response data
     */
    private static function handle_auto_sync_response($data, $domain)
    {
        self::store_connection_data(
            $data['store_id'],
            $data['platform_url'],
            $domain,
            isset($data['api_connected']) ? $data['api_connected'] : false,
            isset($data['webhooks_active']) ? $data['webhooks_active'] : false,
            isset($data['is_active']) ? $data['is_active'] : true,
            isset($data['created_from']) ? $data['created_from'] : 'Plugin',
            isset($data['access_token']) ? $data['access_token'] : null,
            isset($data['refresh_token']) ? $data['refresh_token'] : null,
            isset($data['initial_sync_done']) ? (bool)$data['initial_sync_done'] : false
        );
        self::log('ShipKia: Store connection established/updated.');
    }


    /**
     * Attempt to auto-connect to ShipKia platform
     */
    /**
     * Attempt to auto-connect to ShipKia platform
     */
    private static function attempt_auto_connect()
    {
        // Simply reuse auto_sync to check connection status
        self::auto_sync_on_activate(false);
    }

    /**
     * Manual Sync - re-triggers auto-sync flow from UI
     */
    public static function manual_sync($create_new = false)
    {
        self::log('ShipKia: Manual sync triggered from UI (Create new: ' . ($create_new ? 'Yes' : 'No') . ')');

        // Attempt auto-sync (this will register or update store and get new tokens)
        $result = self::auto_sync_on_activate($create_new);
        $success = is_array($result) ? $result['success'] : $result;

        if ($success) {
            if (function_exists('update_option')) {
                update_option('shipkia_is_active', true);
            }
            if (function_exists('set_transient')) {
                set_transient('shipkia_connection_verified', true, (defined('HOUR_IN_SECONDS') ? HOUR_IN_SECONDS : 3600));
            }
            return array(
                'success' => true,
                'message' => function_exists('__') ? __('Sync successful! Connection and data updated.', 'shipkia-connect') : 'Sync successful! Connection and data updated.'
            );
        }
        else {
            $response = array(
                'success' => false,
                'message' => isset($result['message']) ? $result['message'] : (function_exists('__') ? __('Sync failed.', 'shipkia-connect') : 'Sync failed.')
            );

            if (isset($result['store_not_found'])) {
                $response['store_not_found'] = true;
            }

            return $response;
        }
    }

    /**
     * Manual connection to ShipKia platform (Triggers Auto-Sync)
     */
    public static function manual_connect($app_url, $api_key = null, $api_secret = null)
    {
        try {
            // Validate URL
            if (!filter_var($app_url, FILTER_VALIDATE_URL)) {
                return array('success' => false, 'message' => __('Invalid ShipKia URL', 'shipkia-connect'));
            }

            if (function_exists('update_option')) {
                update_option('shipkia_app_url', $app_url);

                // If keys provided, save them
                if ($api_key)
                    update_option('shipkia_api_key', function_exists('sanitize_text_field') ? sanitize_text_field($api_key) : $api_key);
                if ($api_secret)
                    update_option('shipkia_api_secret', function_exists('sanitize_text_field') ? sanitize_text_field($api_secret) : $api_secret);
            }

            // Trigger registration
            return self::manual_sync(true);

        }
        catch (Exception $e) {
            return array('success' => false, 'message' => $e->getMessage());
        }
    }

    /**
     * Disconnect from ShipKia platform
     */
    public static function disconnect()
    {
        self::$disconnection_in_progress = true;
        try {
            // Update local state IMMEDIATELY for responsiveness
            if (function_exists('update_option')) {
                update_option('shipkia_connected', true);
                update_option('shipkia_is_active', false);
                update_option('shipkia_api_connected', false);
                update_option('shipkia_webhooks_active', false);
            }

            if (function_exists('delete_transient')) {
                delete_transient('shipkia_connection_verified');
            }

            $headers = self::get_auth_headers();
            $domain = self::get_store_domain();
            $api_url = self::get_api_base_url();

            if (!empty($headers) && $api_url) {
                // Notify backend (fire and forget)
                // Set blocking to false so we don't wait for backend response
                if (function_exists('wp_remote_post')) {
                    wp_remote_post($api_url . '/api/method/bu_ecommerce_integrations.api.woocommerce.plugin_auth.disconnect_plugin', array(
                        'timeout' => 5,
                        'blocking' => false,
                        'headers' => $headers,
                        'body' => array('store_domain' => $domain)
                    ));
                }
            }

            // Delete credentials for security and to force regeneration on reconnect
            // WE DO THIS AFTER THE NOTIFICATION so headers are still available above
            if (function_exists('delete_option')) {
                delete_option('shipkia_api_key');
                delete_option('shipkia_api_secret');
            }

            return array(
                'success' => true,
                'message' => function_exists('__') ? __('Store marked as Inactive.', 'shipkia-connect') : 'Store marked as Inactive.'
            );
        }
        catch (Exception $e) {
            update_option('shipkia_is_active', false);
            return array('success' => false, 'message' => $e->getMessage());
        }
    }

    /**
     * Reconnect an Inactive store
     */
    public static function reconnect()
    {
        try {
            $api_url = self::get_api_base_url();
            $domain = self::get_store_domain();

            if (!$api_url) {
                return array('success' => false, 'message' => function_exists('__') ? __('ShipKia API URL is missing.', 'shipkia-connect') : 'ShipKia API URL is missing.');
            }

            self::log('Reconnect: Reactivating via auto-sync with create_new=true...');

            // Disconnect removes the platform API token locally, so reconnect should
            // use auto-sync first. It can reactivate by domain and refresh WC keys.
            $keys = self::ensure_rest_api_keys();
            $data = self::send_auto_sync_request($domain, $api_url, $keys, true);

            if (is_array($data) && isset($data['connected']) && $data['connected'] === true) {
                self::handle_auto_sync_response($data, $domain);

                if (function_exists('update_option')) {
                    update_option('shipkia_connected', true);
                    update_option('shipkia_is_active', true);
                    update_option('shipkia_api_connected', isset($data['api_connected']) ? (bool)$data['api_connected'] : (bool)$keys);
                    update_option('shipkia_webhooks_active', isset($data['webhooks_active']) ? (bool)$data['webhooks_active'] : (bool)$keys);
                }

                if (isset($data['access_token']) && isset($data['refresh_token']) && function_exists('update_option')) {
                    update_option('shipkia_api_key', $data['access_token']);
                    update_option('shipkia_api_secret', $data['refresh_token']);
                }

                if (function_exists('set_transient')) {
                    set_transient('shipkia_connection_verified', true, (defined('HOUR_IN_SECONDS') ? HOUR_IN_SECONDS : 3600));
                }

                self::sync_settings();
                return array('success' => true, 'message' => function_exists('__') ? __('Store reconnected successfully!', 'shipkia-connect') : 'Store reconnected successfully!');
            }

            self::log('Reconnect: Auto-sync reactivation failed. Response: ' . (is_array($data) ? json_encode($data) : $data), 'error');
            return array('success' => false, 'message' => function_exists('__') ? __('Could not reactivate store. Please initialize the connection again.', 'shipkia-connect') : 'Could not reactivate store. Please initialize the connection again.');
        }
        catch (Exception $e) {
            return array('success' => false, 'message' => $e->getMessage());
        }
    }

    /**
     * Synchronize settings between plugin and ShipKia platform
     */
    public static function sync_settings()
    {
        if (self::$disconnection_in_progress || !self::is_connected()) {
            return false;
        }

        try {
            $api_url = self::get_api_base_url();
            $headers = self::get_auth_headers();
            $domain = self::get_store_domain();

            if (!$api_url || empty($headers)) {
                return false;
            }

            $settings = array(
                'tracking_enabled' => function_exists('get_option') ? get_option('shipkia_tracking_enabled', 'yes') : 'yes',
                'button_text' => function_exists('get_option') ? get_option('shipkia_tracking_button_text', 'Track') : 'Track',
                'new_tab' => function_exists('get_option') ? get_option('shipkia_tracking_new_tab', 'yes') : 'yes',
            );

            if (function_exists('wp_remote_post')) {
                wp_remote_post($api_url . '/api/method/bu_ecommerce_integrations.api.woocommerce.plugin_auth.sync_plugin_settings', array(
                    'timeout' => 15,
                    'headers' => $headers,
                    'body' => array(
                        'domain' => $domain,
                        'settings' => json_encode($settings)
                    )
                ));
            }

            return true;
        }
        catch (Exception $e) {
            return false;
        }
    }


    /**
     * Store connection data securely (Tokens removed)
     */
    private static function store_connection_data($store_id, $platform_url = null, $domain = null, $api_connected = false, $webhooks_active = false, $is_active = true, $created_from = null, $api_key = null, $api_secret = null, $initial_sync_done = false)
    {
        if (function_exists('update_option')) {
            $existing_store_id = get_option('shipkia_store_id');
            $was_connected = (bool) get_option('shipkia_connected', false);
            update_option('shipkia_connected', true);
            $is_existing_store = $was_connected && !empty($existing_store_id) && (string) $existing_store_id === (string) $store_id;
            $sync_requested = (bool)$initial_sync_done || (bool)get_option('shipkia_initial_sync_done', false) || $is_existing_store;
            update_option('shipkia_initial_sync_done', $sync_requested);

            // Clear activation notices and triggers since we are now connected
            if (function_exists('delete_transient')) {
                delete_transient('shipkia_show_activation_notice');
                delete_transient('shipkia_trigger_auto_connect');
            }

            // Clear deprecated token fields
            if (function_exists('delete_option')) {
                delete_option('shipkia_access_token');
                delete_option('shipkia_refresh_token');
                delete_option('shipkia_token_expiry');
            }

            update_option('shipkia_store_id', $store_id);
            update_option('shipkia_platform', 'woocommerce');

            update_option('shipkia_api_connected', $api_connected);
            update_option('shipkia_webhooks_active', $webhooks_active);
            update_option('shipkia_is_active', $is_active);

            if ($api_key) {
                update_option('shipkia_api_key', $api_key);
            }
            if ($api_secret) {
                update_option('shipkia_api_secret', $api_secret);
            }

            if ($created_from) {
                update_option('shipkia_created_from', $created_from);
            }

            if ($platform_url) {
                update_option('shipkia_platform_url', $platform_url);
                update_option('shipkia_shipkia_url', $platform_url);
            }

            if ($domain) {
                update_option('shipkia_connected_domain', $domain);
            }
            else {
                update_option('shipkia_connected_domain', self::get_store_domain());
            }
        }
    }


    /**
     * Completely remove the store (delete everything)
     */
    public static function remove_store()
    {
        try {
            $access_token = self::get_access_token();
            $store_id = get_option('shipkia_store_id');
            $api_url = self::get_api_base_url();

            if ($access_token && $api_url && $store_id) {
                $domain = self::get_store_domain();
                $timestamp = gmdate('Y-m-d\TH:i:s\Z');
                $signature = self::generate_signature($domain, $timestamp);

                // Notify backend (fire and forget)
                if (function_exists('wp_remote_post')) {
                    wp_remote_post($api_url . '/api/method/bu_ecommerce_integrations.api.woocommerce.plugin_auth.remove_store', array(
                        'timeout' => 5,
                        'body' => array(
                            'store_id' => $store_id,
                            'access_token' => $access_token,
                            'store_domain' => $domain,
                            'plugin_signature' => $signature,
                            'timestamp' => $timestamp
                        )
                    ));
                }
            }

            self::disconnect_locally();

            return array(
                'success' => true,
                'message' => __('Store removed successfully.', 'shipkia-connect')
            );
        }
        catch (Exception $e) {
            self::disconnect_locally();
            return array(
                'success' => false,
                'message' => __('Error: ', 'shipkia-connect') . $e->getMessage()
            );
        }
    }

    /**
     * Generate HMAC signature for requests
     */
    private static function generate_signature($domain, $timestamp = null)
    {
        $secret = (string) self::get_plugin_secret();
        $message = self::normalize_domain($domain);

        if ($timestamp) {
            $message .= ':' . $timestamp;
        }

        return hash_hmac('sha256', $message, $secret ?: '', true);
    }

    /**
     * Get or create plugin secret (uses WooCommerce consumer secret)
     */
    private static function ensure_plugin_secret()
    {
        // Use WooCommerce consumer secret if available
        $secret = self::get_woocommerce_consumer_secret();

        if (!$secret) {
            // Fallback: generate and store a plugin-specific secret
            $secret = function_exists('get_option') ? get_option('shipkia_plugin_secret') : null;
            if (!$secret) {
                if (function_exists('wp_generate_password')) {
                    $secret = wp_generate_password(64, true, true);
                    if (function_exists('update_option')) {
                        update_option('shipkia_plugin_secret', $secret);
                    }
                }
            }
        }

        self::$plugin_secret = $secret;
    }

    /**
     * Get WooCommerce consumer secret from WooCommerce settings
     */
    private static function get_woocommerce_consumer_secret()
    {
        global $wpdb;

        // Try cache first
        $cached = wp_cache_get('wc_consumer_secret', 'shipkia_auth');
        if (false !== $cached) {
            return $cached;
        }

        // Try to get consumer secret from WooCommerce API keys table
        $table_name = $wpdb->prefix . 'woocommerce_api_keys';

        // Check if table exists (Safety check for direct query)
        // Check if table exists (Safety check for direct query)
        $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name;

        $secret = null;
        if ($table_exists) {
            // Priority 1: Check for specific key ID generated for Shipkia
            $key_id = get_option('shipkia_key_id');
            if ($key_id) {
                $result = $wpdb->get_row($wpdb->prepare(
                    "SELECT consumer_secret FROM {$wpdb->prefix}woocommerce_api_keys 
                     WHERE key_id = %d",
                    $key_id
                ));

                if ($result && !empty($result->consumer_secret)) {
                    $secret = $result->consumer_secret;
                }
            }

            if (!$secret) {
                // Priority 2: Fallback to most recent active consumer secret
                $result = $wpdb->get_row($wpdb->prepare(
                    "SELECT consumer_secret FROM {$wpdb->prefix}woocommerce_api_keys 
                     WHERE permissions = %s 
                     ORDER BY key_id DESC 
                     LIMIT 1",
                    'read_write'
                ));

                if ($result && !empty($result->consumer_secret)) {
                    $secret = $result->consumer_secret;
                }
            }
        }

        if ($secret) {
            wp_cache_set('wc_consumer_secret', $secret, 'shipkia_auth', 3600);
        }

        return $secret;
    }

    /**
     * Ensure REST API keys exist or create them
     */
    private static function ensure_rest_api_keys()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'woocommerce_api_keys';

        // Check if table exists (Cached for performance and compliance)
        $table_exists = wp_cache_get('wc_api_keys_table_exists', 'shipkia_auth');
        if (false === $table_exists) {
            $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) === $table_name;
            wp_cache_set('wc_api_keys_table_exists', $table_exists, 'shipkia_auth', DAY_IN_SECONDS);
        }

        if (!$table_exists) {
            return false;
        }

        // 1. Try to recover keys from local cache (Plaintext)
        $cached_key = get_option('shipkia_consumer_key_plaintext');
        $cached_secret = get_option('shipkia_consumer_secret_plaintext');
        $cached_key_id = get_option('shipkia_key_id');
        $key_generation_method = get_option('shipkia_key_generation_method');

        if ($cached_key && $cached_secret && $cached_key_id) {
            self::log('Ensure keys: Found cached plaintext keys. Verifying ID: ' . $cached_key_id);
            // Verify this key ID still exists in DB and is active
            $valid = $wpdb->get_var($wpdb->prepare(
                "SELECT key_id FROM {$wpdb->prefix}woocommerce_api_keys WHERE key_id = %d AND permissions = 'read_write'",
                $cached_key_id
            ));

            if ($valid && $key_generation_method === 'woocommerce_builtin') {
                self::log('Ensure keys: Cache valid. Returning keys.');
                return array(
                    'consumer_key' => $cached_key,
                    'consumer_secret' => $cached_secret
                );
            }
            else {
                self::log('Ensure keys: Cache invalid, stale, or generated by legacy method. Clearing.');
                if ($valid) {
                    $wpdb->delete($table_name, array('key_id' => $cached_key_id), array('%d'));
                }
                delete_option('shipkia_consumer_key_plaintext');
                delete_option('shipkia_consumer_secret_plaintext');
                delete_option('shipkia_key_id');
                delete_option('shipkia_key_generation_method');
                wp_cache_delete('wc_consumer_secret', 'shipkia_auth');
            }
        }
        else {
            self::log('Ensure keys: No complete cache found.');
        }

        // 2. If no valid cache, only remove the plugin's own previously auto-generated key
        // (tracked by shipkia_key_id). Do NOT delete manually-created Shipkia keys
        // that may have been created via the WooCommerce REST API UI or Platform connection.
        $old_key_id = get_option('shipkia_key_id');
        if ($old_key_id) {
            self::log('Ensure keys: Removing own previous auto-generated key ID: ' . $old_key_id);
            $wpdb->delete($table_name, array('key_id' => $old_key_id), array('%d'));
            delete_option('shipkia_key_id');
            delete_option('shipkia_key_generation_method');
        }

        // 3. Create new keys
        $user_id = self::get_rest_api_key_owner_user_id();
        self::log('Ensure keys: Creating new keys for User ID: ' . $user_id);

        if (!class_exists('WC_REST_API_Keys') && defined('WC_ABSPATH')) {
            $wc_keys_file = WC_ABSPATH . 'includes/class-wc-rest-api-keys.php';
            if (file_exists($wc_keys_file)) {
                require_once $wc_keys_file;
            }
        }

        if (class_exists('WC_REST_API_Keys') && method_exists('WC_REST_API_Keys', 'create_key')) {
            $description = 'ShipKia Integration - Autogenerated (' . gmdate('Y-m-d H:i:s', time() + (5 * 3600 + 30 * 60)) . ')';
            $key = WC_REST_API_Keys::create_key($description, $user_id, 'read_write');

            if (is_array($key) && !empty($key['consumer_key']) && !empty($key['consumer_secret'])) {
                update_option('shipkia_consumer_key_plaintext', $key['consumer_key']);
                update_option('shipkia_consumer_secret_plaintext', $key['consumer_secret']);
                update_option('shipkia_key_id', isset($key['key_id']) ? absint($key['key_id']) : 0);
                update_option('shipkia_key_generation_method', 'woocommerce_builtin');
                wp_cache_delete('wc_consumer_secret', 'shipkia_auth');

                self::log('Shipkia: Generated new REST API keys via WooCommerce API.');
                return array(
                    'consumer_key' => $key['consumer_key'],
                    'consumer_secret' => $key['consumer_secret']
                );
            }

            self::log('Ensure keys: WooCommerce key API returned an unexpected response.', 'warning');
        }
        else {
            self::log('Ensure keys: WooCommerce key API class unavailable; using direct insert fallback.', 'warning');
        }

        // WooCommerce keys are char(43) for secret. Prefix 'cs_' is 3 chars. 
        // So max random length is 40.
        // consumer_key is char(64) but we keep it consistent.
        $consumer_key = 'ck_' . (function_exists('wp_generate_password') ? wp_generate_password(40, false, false) : bin2hex(random_bytes(20)));
        $consumer_secret = 'cs_' . (function_exists('wp_generate_password') ? wp_generate_password(40, false, false) : bin2hex(random_bytes(20)));

        // Hash the consumer key for storage (WooCommerce expects a hash)
        $consumer_key_hash = function_exists('wc_api_hash') ? wc_api_hash($consumer_key) : hash_hmac('sha256', $consumer_key, 'wc-api');

        // Use IST (UTC+5:30) timezone for the key description
        $ist_time = gmdate('Y-m-d H:i:s', time() + (5 * 3600 + 30 * 60));

        $result = $wpdb->insert(
            $table_name,
            array(
            'user_id' => $user_id,
            'description' => 'ShipKia Integration - Autogenerated (' . $ist_time . ')',
            'permissions' => 'read_write',
            'consumer_key' => $consumer_key_hash,
            'consumer_secret' => $consumer_secret,
            'truncated_key' => substr($consumer_key, -7)
        ),
            array('%d', '%s', '%s', '%s', '%s', '%s')
        );

        if ($result) {
            $new_key_id = $wpdb->insert_id;
            self::log('Ensure keys: Key created successfully. ID: ' . $new_key_id);

            // Cache the plaintext keys
            update_option('shipkia_consumer_key_plaintext', $consumer_key);
            update_option('shipkia_consumer_secret_plaintext', $consumer_secret);
            update_option('shipkia_key_id', $new_key_id);
            update_option('shipkia_key_generation_method', 'direct_insert_fallback');
            wp_cache_delete('wc_consumer_secret', 'shipkia_auth');

            self::log('Shipkia: Generated new REST API keys');
            return array(
                'consumer_key' => $consumer_key,
                'consumer_secret' => $consumer_secret
            );
        }

        self::log('Ensure keys: Insert failed! DB Error: ' . $wpdb->last_error, 'error');
        return false;
    }

    /**
     * Get a user that can own WooCommerce REST API keys.
     */
    private static function get_rest_api_key_owner_user_id()
    {
        $user_id = function_exists('get_current_user_id') ? absint(get_current_user_id()) : 0;

        if ($user_id && (user_can($user_id, 'manage_woocommerce') || user_can($user_id, 'manage_options'))) {
            return $user_id;
        }

        $users = get_users(array(
            'role__in' => array('administrator', 'shop_manager'),
            'number' => 1,
            'fields' => 'ID',
        ));

        if (!empty($users)) {
            return absint($users[0]);
        }

        return $user_id ? $user_id : 1;
    }

    /**
     * Get plugin secret
     * @return string
     */
    private static function get_plugin_secret()
    {
        if (!self::$plugin_secret) {
            self::ensure_plugin_secret();
        }

        return self::$plugin_secret;
    }

    /**
     * Get store domain
     */
    private static function get_store_domain()
    {
        $url = trim(get_site_url());
        return rtrim($url, '/');
    }

    /**
     * Normalize domain (remove protocol and trailing slash)
     */
    private static function normalize_domain($url)
    {
        $url = trim($url);
        $url = rtrim($url, '/');

        // Remove protocol
        $url = preg_replace('#^https?://#', '', $url);

        return $url;
    }

    /**
     * Get API base URL
     */
    private static function get_api_base_url()
    {
        if (self::$api_base_url) {
            return self::$api_base_url;
        }

        $url = get_option('shipkia_app_url', 'https://app.shipkia.com');
        self::$api_base_url = rtrim($url, '/');

        return self::$api_base_url;
    }

    /**
     * Clear local connection data
     */
    public static function disconnect_locally()
    {
        if (function_exists('delete_option')) {
            delete_option('shipkia_connected');

            // Clear tokens and API credentials
            delete_option('shipkia_access_token');
            delete_option('shipkia_refresh_token');
            delete_option('shipkia_token_expiry');
            delete_option('shipkia_api_key');
            delete_option('shipkia_api_secret');

            delete_option('shipkia_store_id');
            delete_option('shipkia_platform');
            delete_option('shipkia_platform_url');
            delete_option('shipkia_shipkia_url');
            delete_option('shipkia_connected_domain');

            delete_option('shipkia_api_connected');
            delete_option('shipkia_webhooks_active');
            delete_option('shipkia_is_active');
            delete_option('shipkia_created_from');
            delete_option('shipkia_initial_sync_done');
        }

        if (function_exists('delete_transient')) {
            delete_transient('shipkia_auto_connect_checked');
            delete_transient('shipkia_connection_verified');
        }
    }

    /**
     * Check if connected to Shipkia
     */
    public static function is_connected()
    {
        return (bool)get_option('shipkia_connected', false);
    }

    /**
     * Get connection status info
     */
    public static function get_connection_status()
    {
        if (!self::is_connected()) {
            return array(
                'connected' => false,
                'store_id' => null,
                'platform' => null,
                'platform_url' => null,
                'connected_domain' => null,
                'shipkia_url' => null,
                'token_valid' => false,
                'api_connected' => false,
                'webhooks_active' => false,
                'created_from' => null
            );
        }

        $store_id = get_option('shipkia_store_id');
        $platform = get_option('shipkia_platform', 'woocommerce');
        $platform_url = get_option('shipkia_platform_url');
        $connected_domain = get_option('shipkia_connected_domain');
        $shipkia_url = get_option('shipkia_shipkia_url');

        $api_key = get_option('shipkia_api_key');
        $token_valid = !empty($api_key);

        $api_connected = (bool)get_option('shipkia_api_connected', false);
        $webhooks_active = (bool)get_option('shipkia_webhooks_active', false);
        $created_from = get_option('shipkia_created_from', 'Platform');

        return array(
            'connected' => true,
            'store_id' => $store_id,
            'platform' => $platform,
            'platform_url' => $platform_url,
            'connected_domain' => $connected_domain,
            'shipkia_url' => $shipkia_url,
            'token_valid' => $token_valid,
            'token_expires_at' => null,
            'api_connected' => $api_connected,
            'webhooks_active' => $webhooks_active,
            'is_active' => (bool)get_option('shipkia_is_active', false),
            'initial_sync_done' => (bool)get_option('shipkia_initial_sync_done', false),
            'created_from' => $created_from
        );
    }

    /**
     * Get Platform Auth URL
     * 
     * @param string $return_url
     * @return string
     */
    public static function get_platform_auth_url($return_url)
    {
        $app_url = function_exists('get_option') ? get_option('shipkia_app_url', 'https://app.shipkia.com') : 'https://app.shipkia.com';
        $store_id = function_exists('get_option') ? get_option('shipkia_store_id') : null;
        $shop_url = function_exists('get_home_url') ? get_home_url() : '';

        // Remove trailing slash from app_url if exists
        $app_url = rtrim($app_url, '/');

        // If we have a store_id, we want to return back to the Shipkia Integrations page 
        // with the sync_shop param to trigger the initial sync dialog.
        if ($store_id) {
            $return_url = $app_url . '/integrations?sync_shop=' . urlencode($store_id);
        }

        $auth_url = $app_url . '/api/method/bu_ecommerce_integrations.api.woocommerce.connection.get_auth_url';

        $params = array(
            'shop_url' => $shop_url,
            'return_url' => $return_url
        );

        return function_exists('add_query_arg') ? add_query_arg($params, $auth_url) : $auth_url . '?' . http_build_query($params);
    }
    /**
     * Get API headers for Shipkia platform
     */
    public static function get_auth_headers()
    {
        $api_key = get_option('shipkia_api_key');
        $api_secret = get_option('shipkia_api_secret');

        if (!$api_key || !$api_secret) {
            return array();
        }

        return array(
            'Authorization' => 'token ' . $api_key . ':' . $api_secret,
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Accept' => 'application/json'
        );
    }

    /**
     * Get access token (API Key)
     */
    public static function get_access_token()
    {
        return get_option('shipkia_api_key');
    }
}

// Initialize auth system
Shipkia_Auth::init();
