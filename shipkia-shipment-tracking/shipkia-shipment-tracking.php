<?php
/*
Plugin Name: Shipkia Shipment Tracking for WooCommerce
Plugin URI:  https://shipkia.com/
Description: Adds Shipkia tracking number to WooCommerce Orders and displays on Tracking page.
Version:     1.0.0
Author:      Shipkia
Text Domain: shipkia-shipment-tracking
Domain Path: /languages
*/

if (!defined('ABSPATH')) {
    exit;
}

// Define Constants
define('SHIPKIA_Tracking_VERSION', '1.0.0');
define('SHIPKIA_Tracking_PATH', plugin_dir_path(__FILE__));
define('SHIPKIA_Tracking_URL', plugin_dir_url(__FILE__));

/**
 * Main Plugin Class
 */
class Shipkia_Shipment_Tracking
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
        add_action('plugins_loaded', array($this, 'init'));
    }

    /**
     * Include required files
     */
    private function includes()
    {
        // Core
        require_once SHIPKIA_Tracking_PATH . 'includes/core/class-helpers.php';
        require_once SHIPKIA_Tracking_PATH . 'includes/core/class-tracking-meta.php';
        require_once SHIPKIA_Tracking_PATH . 'includes/core/class-auth.php';

        // Admin
        if (is_admin()) {
            require_once SHIPKIA_Tracking_PATH . 'includes/admin/class-admin-menu.php';
            require_once SHIPKIA_Tracking_PATH . 'includes/admin/class-settings-page.php';
            require_once SHIPKIA_Tracking_PATH . 'includes/admin/class-orders-tracking-page.php';
        }

        // Frontend
        if (!is_admin() || defined('DOING_AJAX')) {
            require_once SHIPKIA_Tracking_PATH . 'includes/frontend/class-my-account-actions.php';
            require_once SHIPKIA_Tracking_PATH . 'includes/frontend/class-order-tracking-display.php';
        }
    }

    /**
     * Initialize Plugin
     */
    public function init()
    {
        // Load Text Domain
        load_plugin_textdomain('shipkia-shipment-tracking', false, dirname(plugin_basename(__FILE__)) . '/languages');

        // Enqueue Scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));

        // Auto-connect check (only in admin)
        if (is_admin()) {
            add_action('admin_init', array('Shipkia_Auth', 'auto_connect_check'));
            add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_plugin_settings_link'));
        }
    }

    /**
     * Add Settings link to Plugin list
     */
    public function add_plugin_settings_link($links)
    {
        $settings_link = '<a href="admin.php?page=shipkia-settings">' . __('Settings', 'shipkia-shipment-tracking') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Enqueue Admin Assets
     */
    public function enqueue_admin_assets()
    {
        wp_enqueue_style('shipkia-admin-css', SHIPKIA_Tracking_URL . 'assets/css/admin.css', array(), SHIPKIA_Tracking_VERSION);
        wp_enqueue_script('shipkia-admin-js', SHIPKIA_Tracking_URL . 'assets/js/admin.js', array('jquery'), SHIPKIA_Tracking_VERSION, true);
        
        // Localize script for AJAX
        wp_localize_script('shipkia-admin-js', 'shipkiaAdmin', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('shipkia_connection_nonce')
        ));
    }

    /**
     * Enqueue Frontend Assets
     */
    public function enqueue_frontend_assets()
    {
        wp_enqueue_style('shipkia-frontend-css', SHIPKIA_Tracking_URL . 'assets/css/frontend.css', array(), SHIPKIA_Tracking_VERSION);
    }
}

// Instantiate Plugin
Shipkia_Shipment_Tracking::get_instance();

// Plugin Activation Hook - Trigger auto-sync immediately
register_activation_hook(__FILE__, 'shipkia_plugin_activated');

function shipkia_plugin_activated()
{
    // Attempt auto-sync on activation
    $synced = Shipkia_Auth::auto_sync_on_activate();
    
    if (!$synced) {
        // If auto-sync fails, fall back to auto-connect check
        delete_transient('shipkia_auto_connect_checked');
        set_transient('shipkia_trigger_auto_connect', true, 60);
    }
}
