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
        if (function_exists('add_action')) {
            // Add to Order View (My Account)
            add_action('woocommerce_order_details_after_order_table', array($this, 'display_tracking_info'));
        }

        if (function_exists('add_filter')) {
            // Add Track Button to Orders List
            add_filter('woocommerce_my_account_my_orders_actions', array($this, 'add_track_button'), 10, 2);
        }
    }

    /**
     * Display Tracking Info in Order Details
     */
    public function display_tracking_info($order_id)
    {
        if (!function_exists('wc_get_order')) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $url = Shipkia_Helpers::get_tracking_url($order_id);

        if (!empty($url)) {
            $tracking_url = $order->get_meta('_shipkia_tracking_url', true);
            $status = $order->get_meta('_shipkia_delivery_status', true);
            $awb = $order->get_meta('_shipkia_awb_number', true);
            $target = Shipkia_Helpers::open_in_new_tab() ? '_blank' : '_self';

            echo '<div class="shipkia-tracking-details" style="margin-bottom: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #eee;">';
            echo '<h4>' . (function_exists('__') ? __('Shipment Tracking', 'shipkia-shipment-tracking') : 'Shipment Tracking') . '</h4>';

            if (!empty($status)) {
                echo '<p><strong>' . (function_exists('__') ? __('Status', 'shipkia-shipment-tracking') : 'Status') . ':</strong> ' . (function_exists('esc_html') ? esc_html($status) : $status) . '</p>';
            }

            if (!empty($awb)) {
                echo '<p><strong>' . (function_exists('__') ? __('AWB Number', 'shipkia-shipment-tracking') : 'AWB Number') . ':</strong> ' . (function_exists('esc_html') ? esc_html($awb) : $awb) . '</p>';
            }

            echo '<a href="' . (function_exists('esc_url') ? esc_url($url) : $url) . '" target="' . (function_exists('esc_attr') ? esc_attr($target) : $target) . '" class="button">' . (function_exists('__') ? __('Track Shipment', 'shipkia-shipment-tracking') : 'Track Shipment') . '</a>';
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
                'url' => function_exists('esc_url') ? esc_url($url) : $url,
                'name' => Shipkia_Helpers::get_button_text(),
            );
        }

        return $actions;
    }
}

new Shipkia_My_Account_Actions();
