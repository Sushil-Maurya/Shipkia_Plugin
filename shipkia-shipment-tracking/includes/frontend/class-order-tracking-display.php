<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Order Tracking Display Class
 */
class Shipkia_Order_Tracking_Display
{

    /**
     * Constructor
     */
    public function __construct()
    {
        if (function_exists('add_action')) {
            // Show on Order Tracking Page
            add_action('woocommerce_order_tracking_status', array($this, 'display_on_tracking_page'), 10, 1);
        }
    }

    /**
     * Display on Tracking Page
     */
    public function display_on_tracking_page($order)
    {
        if (!Shipkia_Helpers::is_tracking_enabled()) {
            return;
        }

        $url = Shipkia_Helpers::get_tracking_url($order->get_id());

        if (!empty($url)) {
            $target = Shipkia_Helpers::open_in_new_tab() ? '_blank' : '_self';
            $text = Shipkia_Helpers::get_button_text();
            $status = $order->get_meta('_shipkia_delivery_status', true);

            echo '<div class="shipkia-tracking-status">';
            if (!empty($status)) {
                echo '<p class="shipkia-tracking-status-text">';
                echo '<strong>' . (function_exists('__') ? __('Tracking Status', 'shipkia-shipment-tracking') : 'Tracking Status') . ':</strong> ';
                echo '<span>' . (function_exists('esc_html') ? esc_html($status) : $status) . '</span>';
                echo '</p>';
            }

            echo '<p class="shipkia-track-btn-wrapper">';
            echo '<a href="' . (function_exists('esc_url') ? esc_url($url) : $url) . '" target="' . (function_exists('esc_attr') ? esc_attr($target) : $target) . '" class="button shipkia-track-link">' . (function_exists('esc_html') ? esc_html($text) : $text) . '</a>';
            echo '</p>';
        }
    }
}

new Shipkia_Order_Tracking_Display();
