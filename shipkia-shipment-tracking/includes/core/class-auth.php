<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shipkia Authentication Class
 * 
 * Handles connection to Shipkia platform including:
 * - Auto-connect detection for existing stores
 * - Manual connection flow
 * - Token exchange and refresh
 * - Secure token storage
 * - HMAC signature generation
 */
class Shipkia_Auth
{
    /**
     * Shipkia API base URL
     */
    private static $api_base_url = null;

    /**
     * Plugin secret for HMAC signatures
     */
    private static $plugin_secret = null;

    /**
     * Initialize authentication system
     */
    public static function init()
    {
        // Ensure plugin secret exists
        self::ensure_plugin_secret();
    }

    /**
     * Custom logging method
     */
    public static function log($message, $level = 'info')
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $timestamp = date('Y-m-d H:i:s');
            $upload_dir = wp_upload_dir();
            $log_file = $upload_dir['basedir'] . '/shipkia-tracking.log';
            $log_entry = "[$timestamp] [$level] [Shipkia_Auth] $message" . PHP_EOL;
            file_put_contents($log_file, $log_entry, FILE_APPEND);
        }
        
        // Also log to standard error_log
        if ($level === 'error') {
            error_log('[Shipkia Error] ' . $message);
        }
    }

    /**
     * Auto-connect check on admin init
     * Only runs once per session to avoid performance impact, 
     * but runs more frequently on the settings page or after activation.
     */
    public static function auto_connect_check()
    {
        $screen = get_current_screen();
        $is_settings_page = ($screen && $screen->id === 'shipkia-shipment-tracking_page_shipkia-settings');
        
        // If connected, we periodically verify the connection status
        if (self::is_connected()) {
            // Verify every 12 hours, or every 1 hour if on settings page
            $verify_interval = $is_settings_page ? HOUR_IN_SECONDS : (12 * HOUR_IN_SECONDS);
            
            if (!get_transient('shipkia_connection_verified')) {
                self::attempt_auto_connect();
                set_transient('shipkia_connection_verified', true, $verify_interval);
            }
            return;
        }

        // Check if triggered by plugin activation
        $force_check = get_transient('shipkia_trigger_auto_connect');
        
        if ($force_check) {
            // Clear the flag
            delete_transient('shipkia_trigger_auto_connect');
            // Force auto-connect attempt
            self::attempt_auto_connect();
            return;
        }

        // If on settings page, we check more frequently (every 5 minutes instead of 1 hour)
        $check_interval = $is_settings_page ? 300 : HOUR_IN_SECONDS;

        // Skip if already checked recently
        if (get_transient('shipkia_auto_connect_checked')) {
            // Only skip if not on settings page, or if checked VERY recently on settings page
            if (!$is_settings_page || get_transient('shipkia_auto_connect_checked_settings')) {
                return;
            }
        }

        // Mark as checked
        set_transient('shipkia_auto_connect_checked', true, HOUR_IN_SECONDS);
        if ($is_settings_page) {
            set_transient('shipkia_auto_connect_checked_settings', true, 300);
        }

        // Attempt auto-connect
        self::attempt_auto_connect();
    }

    /**
     * Auto-sync on plugin activation
     * Called once when plugin is activated
     */
    public static function auto_sync_on_activate()
    {
        try {
            $domain = self::get_store_domain();
            $api_url = self::get_api_base_url();
            
            // Get plugin info
            $plugin_data = get_plugin_data(SHIPKIA_Tracking_PATH . 'shipkia-shipment-tracking.php');
            $plugin_version = isset($plugin_data['Version']) ? $plugin_data['Version'] : '1.0.0';

            $response = wp_remote_post($api_url . '/api/method/bu_ecommerce_integrations.api.woocommerce.auto_sync.auto_sync', array(
                'timeout' => 15,
                'body' => array(
                    'domain' => $domain,
                    'platform' => 'woocommerce',
                    'plugin' => 'shipkia-shipment-tracking',
                    'plugin_version' => $plugin_version,
                    'secret' => self::get_plugin_secret()
                )
            ));

            if (is_wp_error($response)) {
                self::log('Shipkia auto-sync failed: ' . $response->get_error_message(), 'error');
                return false;
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);

            if (!$body || !isset($body['message'])) {
                return false;
            }

            $data = $body['message'];

            // Check if auto-sync was successful
            if (isset($data['connected']) && $data['connected'] === true) {
                // Store tokens and connection info directly
                self::store_connection_data(
                    $data['access_token'],
                    $data['refresh_token'],
                    $data['store_id'],
                    $data['expires_in'],
                    $data['platform_url'],
                    $domain
                );

                self::log('Shipkia: Auto-sync successful - store connected');
                return true;
            }

            return false;
        } catch (Exception $e) {
            self::log('Shipkia auto-sync exception: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Attempt to auto-connect to Shipkia platform
     */
    private static function attempt_auto_connect()
    {
        try {
            $domain = self::get_store_domain();
            $api_url = self::get_api_base_url();

            if (!$api_url) {
                return;
            }

            // Generate signature
            $timestamp = gmdate('Y-m-d\TH:i:s\Z');
            $signature = self::generate_signature($domain, $timestamp);

            // Call verification endpoint
            $response = wp_remote_post($api_url . '/api/method/bu_ecommerce_integrations.api.woocommerce.plugin_auth.verify_plugin_connection', array(
                'timeout' => 15,
                'body' => array(
                    'domain' => $domain,
                    'plugin_signature' => $signature,
                    'timestamp' => $timestamp
                )
            ));

            if (is_wp_error($response)) {
                self::log('Shipkia auto-connect failed: ' . $response->get_error_message(), 'error');
                return;
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);

            if (!$body || !isset($body['message'])) {
                return;
            }

            $data = $body['message'];

            // Check if store is connected
            if (isset($data['connected']) && $data['connected'] === true) {
                // If we were disconnected locally but connected on backend, re-connect
                // Or if we are already connected, we just update tokens if provided
                if (isset($data['temp_token'])) {
                    $platform_url = isset($data['platform_url']) ? $data['platform_url'] : null;
                    self::exchange_token($data['temp_token'], $data['store_id'], $platform_url);
                }
            } else {
                // Store is NOT connected on backend
                if (self::is_connected()) {
                    self::log('Shipkia: Store disconnected on backend, disconnecting locally');
                    self::disconnect_locally();
                }
            }
        } catch (Exception $e) {
            self::log('Shipkia auto-connect exception: ' . $e->getMessage(), 'error');
        }
    }

    /**
     * Manual Sync - re-triggers auto-sync flow from UI
     */
    public static function manual_sync()
    {
        self::log('Shipkia: Manual sync triggered from UI');
        
        // Attempt auto-sync (this will register or update store and get new tokens)
        $success = self::auto_sync_on_activate();
        
        if ($success) {
            set_transient('shipkia_connection_verified', true, HOUR_IN_SECONDS);
            return array(
                'success' => true,
                'message' => __('Sync successful! Connection and data updated.', 'shipkia-shipment-tracking')
            );
        } else {
            return array(
                'success' => false,
                'message' => __('Sync failed. Please check your connection to Shipkia.', 'shipkia-shipment-tracking')
            );
        }
    }

    /**
     * Exchange temporary token for access and refresh tokens
     */
    private static function exchange_token($temp_token, $store_id, $platform_url = null)
    {
        try {
            $domain = self::get_store_domain();
            $api_url = self::get_api_base_url();

            $response = wp_remote_post($api_url . '/api/method/bu_ecommerce_integrations.api.woocommerce.plugin_auth.exchange_plugin_token', array(
                'timeout' => 15,
                'body' => array(
                    'temp_token' => $temp_token,
                    'store_domain' => $domain,
                    'store_id' => $store_id
                )
            ));

            if (is_wp_error($response)) {
                self::log('Shipkia token exchange failed: ' . $response->get_error_message(), 'error');
                return false;
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);

            if (!$body || !isset($body['message'])) {
                return false;
            }

            $data = $body['message'];

            if (isset($data['status']) && $data['status'] === 'success') {
                // Store tokens and platform URL
                self::store_tokens(
                    $data['access_token'],
                    $data['refresh_token'],
                    $store_id,
                    $data['expires_in'],
                    $platform_url
                );

                // Sync settings after successful connection
                self::sync_settings();

                self::log('Shipkia: Auto-connected successfully');
                return true;
            } else {
                if (isset($data['message'])) {
                    set_transient('shipkia_connection_error', $data['message'], HOUR_IN_SECONDS);
                }
            }

            return false;
        } catch (Exception $e) {
            self::log('Shipkia token exchange exception: ' . $e->getMessage(), 'error');
            set_transient('shipkia_connection_error', $e->getMessage(), HOUR_IN_SECONDS);
            return false;
        }
    }

    /**
     * Manual connection to Shipkia platform
     */
    public static function manual_connect($app_url)
    {
        try {
            // Validate URL
            if (!filter_var($app_url, FILTER_VALIDATE_URL)) {
                return array(
                    'success' => false,
                    'message' => __('Invalid Shipkia URL', 'shipkia-shipment-tracking')
                );
            }

            // Store API URL
            update_option('shipkia_app_url', $app_url);

            // Attempt connection
            $domain = self::get_store_domain();
            $timestamp = gmdate('Y-m-d\TH:i:s\Z');
            $signature = self::generate_signature($domain, $timestamp);

            $response = wp_remote_post($app_url . '/api/method/bu_ecommerce_integrations.api.woocommerce.plugin_auth.verify_plugin_connection', array(
                'timeout' => 15,
                'body' => array(
                    'domain' => $domain,
                    'plugin_signature' => $signature,
                    'timestamp' => $timestamp
                )
            ));

            if (is_wp_error($response)) {
                return array(
                    'success' => false,
                    'message' => __('Connection failed: ', 'shipkia-shipment-tracking') . $response->get_error_message()
                );
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);

            if (!$body || !isset($body['message'])) {
                return array(
                    'success' => false,
                    'message' => __('Invalid response from Shipkia', 'shipkia-shipment-tracking')
                );
            }

            $data = $body['message'];

            if (isset($data['connected']) && $data['connected'] === true) {
                // Exchange token
                $platform_url = isset($data['platform_url']) ? $data['platform_url'] : null;
                if (self::exchange_token($data['temp_token'], $data['store_id'], $platform_url)) {
                    delete_transient('shipkia_connection_error');
                    return array(
                        'success' => true,
                        'message' => __('Connected to Shipkia successfully!', 'shipkia-shipment-tracking')
                    );
                }
            } else {
                // Fallback: If store not found, try auto-sync (registration)
                if (isset($data['status']) && $data['status'] === 'not_found') {
                    self::log('Shipkia manual_connect: Store not found, attempting auto-sync fallback');
                    if (self::auto_sync_on_activate()) {
                        return array(
                            'success' => true,
                            'message' => __('Store registered and connected to Shipkia successfully!', 'shipkia-shipment-tracking')
                        );
                    }
                }

                $error_msg = isset($data['message']) ? $data['message'] : __('Store not found in Shipkia. Please register it first.', 'shipkia-shipment-tracking');
                set_transient('shipkia_connection_error', $error_msg, HOUR_IN_SECONDS);
                return array(
                    'success' => false,
                    'message' => $error_msg
                );
            }

            return array(
                'success' => false,
                'message' => __('Connection failed', 'shipkia-shipment-tracking')
            );
        } catch (Exception $e) {
            set_transient('shipkia_connection_error', $e->getMessage(), HOUR_IN_SECONDS);
            return array(
                'success' => false,
                'message' => __('Error: ', 'shipkia-shipment-tracking') . $e->getMessage()
            );
        }
    }

    /**
     * Disconnect from Shipkia platform
     */
    public static function disconnect()
    {
        try {
            $access_token = self::get_access_token();
            $domain = self::get_store_domain();
            $api_url = self::get_api_base_url();

            if ($access_token && $api_url) {
                // Notify backend (fire and forget)
                wp_remote_post($api_url . '/api/method/bu_ecommerce_integrations.api.woocommerce.plugin_auth.disconnect_plugin', array(
                    'timeout' => 5,
                    'body' => array(
                        'store_domain' => $domain,
                        'access_token' => $access_token
                    )
                ));
            }

            self::disconnect_locally();

            return array(
                'success' => true,
                'message' => __('Disconnected from Shipkia', 'shipkia-shipment-tracking')
            );
        } catch (Exception $e) {
            self::disconnect_locally();
            return array(
                'success' => false,
                'message' => __('Error: ', 'shipkia-shipment-tracking') . $e->getMessage()
            );
        }
    }

    /**
     * Clear local connection data
     */
    public static function disconnect_locally()
    {
        delete_option('shipkia_connected');
        delete_option('shipkia_access_token');
        delete_option('shipkia_refresh_token');
        delete_option('shipkia_store_id');
        delete_option('shipkia_token_expiry');
        delete_option('shipkia_platform');
        delete_option('shipkia_platform_url');
        delete_option('shipkia_shipkia_url');
        delete_option('shipkia_connected_domain');
        
        delete_transient('shipkia_auto_connect_checked');
        delete_transient('shipkia_connection_verified');
    }

    /**
     * Check if connected to Shipkia
     */
    public static function is_connected()
    {
        return (bool) get_option('shipkia_connected', false);
    }

    /**
     * Get valid access token (auto-refresh if needed)
     */
    public static function get_access_token()
    {
        if (!self::is_connected()) {
            return null;
        }

        $access_token = get_option('shipkia_access_token');
        $expiry = get_option('shipkia_token_expiry');

        // Check if token is expired or about to expire (within 5 minutes)
        if ($expiry && (time() + 300) >= $expiry) {
            // Attempt to refresh
            if (self::refresh_token()) {
                $access_token = get_option('shipkia_access_token');
            } else {
                // Refresh failed, disconnect
                self::disconnect();
                return null;
            }
        }

        return $access_token;
    }

    /**
     * Synchronize settings between plugin and Shipkia platform
     */
    public static function sync_settings()
    {
        if (!self::is_connected()) {
            return false;
        }

        try {
            $api_url = self::get_api_base_url();
            $access_token = self::get_access_token();
            $domain = self::get_store_domain();

            if (!$api_url || !$access_token) {
                return false;
            }

            // Prepare local settings to push
            $settings = array(
                'tracking_enabled' => get_option('shipkia_tracking_enabled', 'yes'),
                'button_text' => get_option('shipkia_tracking_button_text', 'Track'),
                'new_tab' => get_option('shipkia_tracking_new_tab', 'yes'),
                'plugin_version' => SHIPKIA_Tracking_VERSION
            );

            $response = wp_remote_post($api_url . '/api/method/bu_ecommerce_integrations.api.woocommerce.plugin_auth.sync_plugin_settings', array(
                'timeout' => 15,
                'headers' => array(
                    'Authorization' => 'Bearer ' . $access_token
                ),
                'body' => array(
                    'domain' => $domain,
                    'settings' => json_encode($settings)
                )
            ));

            if (is_wp_error($response)) {
                return false;
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);

            // If platform has updated settings, we could update local too here
            // (One-way push for now as per simple sync)
            
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Refresh access token using refresh token
     */
    private static function refresh_token()
    {
        try {
            $refresh_token = get_option('shipkia_refresh_token');
            $domain = self::get_store_domain();
            $api_url = self::get_api_base_url();

            if (!$refresh_token || !$api_url) {
                return false;
            }

            $response = wp_remote_post($api_url . '/api/method/bu_ecommerce_integrations.api.woocommerce.plugin_auth.refresh_plugin_token', array(
                'timeout' => 15,
                'body' => array(
                    'refresh_token' => $refresh_token,
                    'store_domain' => $domain
                )
            ));

            if (is_wp_error($response)) {
                self::log('Shipkia token refresh failed: ' . $response->get_error_message(), 'error');
                return false;
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);

            if (!$body || !isset($body['message'])) {
                return false;
            }

            $data = $body['message'];

            if (isset($data['status']) && $data['status'] === 'success') {
                // Update access token
                update_option('shipkia_access_token', $data['access_token']);
                update_option('shipkia_token_expiry', time() + $data['expires_in']);

                self::log('Shipkia: Token refreshed successfully');
                return true;
            }

            return false;
        } catch (Exception $e) {
            self::log('Shipkia token refresh exception: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Store connection data securely
     */
    private static function store_connection_data($access_token, $refresh_token, $store_id, $expires_in, $platform_url = null, $domain = null)
    {
        update_option('shipkia_connected', true);
        update_option('shipkia_access_token', $access_token);
        update_option('shipkia_refresh_token', $refresh_token);
        update_option('shipkia_store_id', $store_id);
        update_option('shipkia_token_expiry', time() + $expires_in);
        update_option('shipkia_platform', 'woocommerce');
        
        if ($platform_url) {
            update_option('shipkia_platform_url', $platform_url);
            update_option('shipkia_shipkia_url', $platform_url);
        }
        
        if ($domain) {
            update_option('shipkia_connected_domain', $domain);
        } else {
            update_option('shipkia_connected_domain', self::get_store_domain());
        }
    }
    
    /**
     * Store tokens securely (legacy method for backward compatibility)
     */
    private static function store_tokens($access_token, $refresh_token, $store_id, $expires_in, $platform_url = null)
    {
        self::store_connection_data($access_token, $refresh_token, $store_id, $expires_in, $platform_url);
    }

    /**
     * Generate HMAC signature for requests
     */
    private static function generate_signature($domain, $timestamp = null)
    {
        $secret = self::get_plugin_secret();
        $message = self::normalize_domain($domain);

        if ($timestamp) {
            $message .= ':' . $timestamp;
        }

        return hash_hmac('sha256', $message, $secret);
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
            $secret = get_option('shipkia_plugin_secret');
            if (!$secret) {
                $secret = wp_generate_password(64, true, true);
                update_option('shipkia_plugin_secret', $secret);
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
        
        // Try to get consumer secret from WooCommerce API keys table
        $table_name = $wpdb->prefix . 'woocommerce_api_keys';
        
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
        
        if ($table_exists) {
            // Get the most recent active consumer secret
            $result = $wpdb->get_row(
                "SELECT consumer_secret FROM {$table_name} 
                 WHERE permissions = 'read_write' 
                 ORDER BY key_id DESC 
                 LIMIT 1"
            );
            
            if ($result && !empty($result->consumer_secret)) {
                return $result->consumer_secret;
            }
        }
        
        return null;
    }

    /**
     * Get plugin secret
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
                'token_valid' => false
            );
        }

        $store_id = get_option('shipkia_store_id');
        $platform = get_option('shipkia_platform', 'woocommerce');
        $platform_url = get_option('shipkia_platform_url');
        $connected_domain = get_option('shipkia_connected_domain');
        $shipkia_url = get_option('shipkia_shipkia_url');
        $expiry = get_option('shipkia_token_expiry');
        $token_valid = $expiry && time() < $expiry;

        return array(
            'connected' => true,
            'store_id' => $store_id,
            'platform' => $platform,
            'platform_url' => $platform_url,
            'connected_domain' => $connected_domain,
            'shipkia_url' => $shipkia_url,
            'token_valid' => $token_valid,
            'token_expires_at' => $expiry ? date('Y-m-d H:i:s', $expiry) : null
        );
    }
}

// Initialize auth system
Shipkia_Auth::init();
