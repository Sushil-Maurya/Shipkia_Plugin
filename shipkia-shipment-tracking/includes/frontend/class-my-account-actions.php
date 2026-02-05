<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * My Account Actions Class
 */
class Shipkia_My_Account_Actions
{

    /**
     * Constructor
     */
    public function __construct()
    {
        // Add to Order View (My Account)
        add_action('woocommerce_order_details_after_order_table', array($this, 'display_tracking_info'));

        // Add Track Button to Orders List
        add_filter('woocommerce_my_account_my_orders_actions', array($this, 'add_track_button'), 10, 2);
    }

    /**
     * Display Tracking Info in Order Details
     */
    public function display_tracking_info($order)
    {
        if (!Shipkia_Helpers::is_tracking_enabled()) {
            return;
        }

        $order_id = $order->get_id();
        $url = Shipkia_Helpers::get_tracking_url($order_id);

        if (!empty($url)) {
            $awb = $order->get_meta('shipkia_awb_number', true);
            $status = $order->get_meta('shipkia_delivery_status', true);
            $target = Shipkia_Helpers::open_in_new_tab() ? '_blank' : '_self';

            echo '<div class="shipkia-tracking-details" style="margin-bottom: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #eee;">';
            echo '<h3>' . __('Shipment Tracking', 'shipkia-shipment-tracking') . '</h3>';

            if (!empty($status)) {
                echo '<p><strong>' . __('Status:', 'shipkia-shipment-tracking') . '</strong> ' . esc_html($status) . '</p>';
            }
            if (!empty($awb)) {
                echo '<p><strong>' . __('AWB Number:', 'shipkia-shipment-tracking') . '</strong> ' . esc_html($awb) . '</p>';
            }

            // echo '<a href="' . esc_url($url) . '" class="button" target="' . $target . '">' . esc_html(Shipkia_Helpers::get_button_text()) . '</a>';
            echo '</div>';
        }
    }

    /**
     * Add Track Button to Orders List
     */
    public function add_track_button($actions, $order)
    {
        if (!Shipkia_Helpers::is_tracking_enabled()) {
            return $actions;
        }

        $url = Shipkia_Helpers::get_tracking_url($order->get_id());

        if (!empty($url)) {
            $actions['shipkia_track'] = array(
                'url' => esc_url($url),
                'name' => Shipkia_Helpers::get_button_text(),
            );
        }

        return $actions;
    }
}

new Shipkia_My_Account_Actions();
