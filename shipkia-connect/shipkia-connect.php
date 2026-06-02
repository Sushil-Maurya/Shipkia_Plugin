<?php
/*
Plugin Name: ShipKia Connect
Description: Adds ShipKia tracking number to WooCommerce Orders and displays on Tracking page.
Version:     1.0.0
Tested up to: 6.9
Author:      ShipKia
Author URI:  https://shipkia.com/
License:     GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: shipkia-connect
Domain Path: /languages
*/

if (!defined('ABSPATH')) {
    exit;
}

// Define Constants
define('SHIPKIA_CONNECT_VERSION', '1.0.0');
define('SHIPKIA_CONNECT_PATH', plugin_dir_path(__FILE__));
define('SHIPKIA_CONNECT_URL', plugin_dir_url(__FILE__));

/**
 * Main Plugin Class
 */
class Shipkia_Connect
{
    /**
     * Instance of this class.
     *
     * @var object
     */
    protected static $instance = null;

    /**
     * Return an instance of this class.
     *
     * @return object A single instance of this class.
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct()
    {
        // Include Classes
        $this->includes();

        // Init Hooks
        if (function_exists('add_action')) {
            add_action('plugins_loaded', array($this, 'init'));
        }
    }

    /**
     * Include required files
     */
    private function includes()
    {
        // Core
        require_once SHIPKIA_CONNECT_PATH . 'includes/core/class-helpers.php';
        require_once SHIPKIA_CONNECT_PATH . 'includes/core/class-logger.php';
        require_once SHIPKIA_CONNECT_PATH . 'includes/core/class-tracking-meta.php';
        require_once SHIPKIA_CONNECT_PATH . 'includes/core/class-auth.php';

        // Admin
        if (function_exists('is_admin') && is_admin()) {
            require_once SHIPKIA_CONNECT_PATH . 'includes/admin/class-admin-menu.php';
            require_once SHIPKIA_CONNECT_PATH . 'includes/admin/class-settings-page.php';
            require_once SHIPKIA_CONNECT_PATH . 'includes/admin/class-orders-tracking-page.php';
        }

        // Frontend
        if (function_exists('is_admin') && (!is_admin() || defined('DOING_AJAX'))) {
            require_once SHIPKIA_CONNECT_PATH . 'includes/frontend/class-my-account-actions.php';
            require_once SHIPKIA_CONNECT_PATH . 'includes/frontend/class-order-tracking-display.php';
        }
    }

    /**
     * Initialize Plugin
     */
    public function init()
    {
        // Load Text Domain (Optional since WP 4.6 if on .org)
        /* 
        if (function_exists('load_plugin_textdomain')) {
            load_plugin_textdomain('shipkia-connect', false, dirname(plugin_basename(__FILE__)) . '/languages');
        }
        */

        // Enqueue Scripts
        if (function_exists('add_action')) {
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
            add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));

            // Add REST API routes
            if (class_exists('Shipkia_Auth')) {
                add_action('rest_api_init', array('Shipkia_Auth', 'register_rest_routes'));
            }
        }

        // Auto-connect check (only in admin)
        if (function_exists('is_admin') && is_admin()) {
            if (function_exists('add_action')) {
                add_action('admin_init', array('Shipkia_Auth', 'auto_connect_check'));
            }
            if (function_exists('add_filter')) {
                add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_plugin_settings_link'));
            }
        }
    }

    /**
     * Add Settings link to Plugin list
     */
    public function add_plugin_settings_link($links)
    {
        $settings_label = function_exists('__') ? __('Settings', 'shipkia-connect') : 'Settings';
        $settings_link = '<a href="admin.php?page=shipkia-connect">' . $settings_label . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Enqueue Admin Assets
     */
    public function enqueue_admin_assets($hook)
    {
        // Only load on plugin specific pages or order edit page
        $allowed_hooks = array(
            'toplevel_page_shipkia-connect',
            'shipkia-connect_page_shipkia-orders-tracking',
            'shop_order',
            'post.php',
            'post-new.php',
            'woocommerce_page_wc-orders' // HPOS support
        );

        if (!in_array($hook, $allowed_hooks, true)) {
            return;
        }

        if (function_exists('wp_enqueue_style')) {
            wp_enqueue_style('shipkia-admin-css', SHIPKIA_CONNECT_URL . 'assets/css/admin.css', array(), SHIPKIA_CONNECT_VERSION);
        }
        if (function_exists('wp_enqueue_script')) {
            wp_enqueue_script('shipkia-admin-js', SHIPKIA_CONNECT_URL . 'assets/js/admin.js', array('jquery'), SHIPKIA_CONNECT_VERSION, true);
        }

        // Localize script for AJAX
        if (function_exists('wp_localize_script')) {
            wp_localize_script('shipkia-admin-js', 'shipkiaAdmin', array(
                'ajaxurl' => function_exists('admin_url') ? admin_url('admin-ajax.php') : '',
                'nonce' => function_exists('wp_create_nonce') ? wp_create_nonce('shipkia_connection_nonce') : ''
            ));
        }
    }

    /**
     * Enqueue Frontend Assets
     */
    public function enqueue_frontend_assets()
    {
        // Only load on WooCommerce tracking page or my account
        if (function_exists('is_account_page') && (is_account_page() || is_order_received_page() || is_wc_endpoint_url('view-order'))) {
            if (function_exists('wp_enqueue_style')) {
                wp_enqueue_style('shipkia-frontend-css', SHIPKIA_CONNECT_URL . 'assets/css/frontend.css', array(), SHIPKIA_CONNECT_VERSION);
            }
        }
    }
}

// Instantiate Plugin
Shipkia_Connect::get_instance();

// Plugin Activation Hook - Trigger auto-sync immediately
if (function_exists('register_activation_hook')) {
    register_activation_hook(__FILE__, 'shipkia_tracking_plugin_activated');
}

function shipkia_tracking_plugin_activated()
{
    if (function_exists('get_option') && function_exists('update_option')) {
        $already_connected = get_option('shipkia_connected') && get_option('shipkia_store_id');
        if ($already_connected) {
            update_option('shipkia_initial_sync_done', true);
        }
    }

    // Mark that we just activated to trigger redirect and confirmation UI
    if (function_exists('set_transient')) {
        set_transient('shipkia_plugin_just_activated', true, 60);
    }
}
