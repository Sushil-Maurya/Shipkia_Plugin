<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin Menu Class
 */
class Shipkia_Admin_Menu
{

    /**
     * Constructor
     */
    public function __construct()
    {
        if (function_exists('add_action')) {
            add_action('admin_menu', array($this, 'register_menus'));
        }
    }

    /**
     * Register Menus
     */
    public function register_menus()
    {
        // Top Level Menu
        add_menu_page(
            __('Shipkia Connect', 'shipkia-connect'),
            __('Shipkia Connect', 'shipkia-connect'),
            'manage_woocommerce',
            'shipkia-connect',
            array('Shipkia_Settings_Page', 'render'),
            'dashicons-location-alt',
            58
        );

        // Settings Submenu
        add_submenu_page(
            'shipkia-connect',
            __('Settings', 'shipkia-connect'),
            __('Settings', 'shipkia-connect'),
            'manage_woocommerce',
            'shipkia-connect',
            array('Shipkia_Settings_Page', 'render')
        );

        // Orders Tracking Submenu
        add_submenu_page(
            'shipkia-connect',
            __('Orders Tracking', 'shipkia-connect'),
            __('Orders Tracking', 'shipkia-connect'),
            'manage_woocommerce',
            'shipkia-orders-tracking',
            array('Shipkia_Orders_Tracking_Page', 'render')
        );
    }
}

new Shipkia_Admin_Menu();
