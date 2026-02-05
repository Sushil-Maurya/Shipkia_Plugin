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
        add_action('admin_menu', array($this, 'register_menus'));
    }

    /**
     * Register Menus
     */
    public function register_menus()
    {
        // Top Level Menu
        add_menu_page(
            __('Shipkia Tracking', 'shipkia-shipment-tracking'),
            __('Shipkia Tracking', 'shipkia-shipment-tracking'),
            'manage_woocommerce',
            'shipkia-shipment-tracking',
            array('Shipkia_Settings_Page', 'render'),
            'dashicons-location-alt',
            58
        );

        // Settings Submenu
        add_submenu_page(
            'shipkia-shipment-tracking',
            __('Settings', 'shipkia-shipment-tracking'),
            __('Settings', 'shipkia-shipment-tracking'),
            'manage_woocommerce',
            'shipkia-shipment-tracking',
            array('Shipkia_Settings_Page', 'render')
        );

        // Orders Tracking Submenu
        add_submenu_page(
            'shipkia-shipment-tracking',
            __('Orders Tracking', 'shipkia-shipment-tracking'),
            __('Orders Tracking', 'shipkia-shipment-tracking'),
            'manage_woocommerce',
            'shipkia-orders-tracking',
            array('Shipkia_Orders_Tracking_Page', 'render')
        );
    }
}

new Shipkia_Admin_Menu();
