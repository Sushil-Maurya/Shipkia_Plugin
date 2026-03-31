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
        if (!function_exists('add_menu_page')) {
            return;
        }

        // Top Level Menu
        add_menu_page(
            (function_exists('__') ? __('Shipkia Tracking', 'shipkia-shipment-tracking') : 'Shipkia Tracking'),
            (function_exists('__') ? __('Shipkia Tracking', 'shipkia-shipment-tracking') : 'Shipkia Tracking'),
            'manage_woocommerce',
            'shipkia-shipment-tracking',
            array('Shipkia_Settings_Page', 'render'),
            'dashicons-location-alt',
            58
        );

        if (function_exists('add_submenu_page')) {
            // Settings Submenu
            add_submenu_page(
                'shipkia-shipment-tracking',
                (function_exists('__') ? __('Settings', 'shipkia-shipment-tracking') : 'Settings'),
                (function_exists('__') ? __('Settings', 'shipkia-shipment-tracking') : 'Settings'),
                'manage_woocommerce',
                'shipkia-shipment-tracking',
                array('Shipkia_Settings_Page', 'render')
            );

            // Orders Tracking Submenu
            add_submenu_page(
                'shipkia-shipment-tracking',
                (function_exists('__') ? __('Orders Tracking', 'shipkia-shipment-tracking') : 'Orders Tracking'),
                (function_exists('__') ? __('Orders Tracking', 'shipkia-shipment-tracking') : 'Orders Tracking'),
                'manage_woocommerce',
                'shipkia-orders-tracking',
                array('Shipkia_Orders_Tracking_Page', 'render')
            );
        }
    }
}

new Shipkia_Admin_Menu();
