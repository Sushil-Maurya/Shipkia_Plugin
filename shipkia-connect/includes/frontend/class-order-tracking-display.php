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
                echo '<strong>' . esc_html__('Tracking Status', 'shipkia-connect') . ':</strong> ';
                echo '<span>' . esc_html($status) . '</span>';
                echo '</p>';
            }

            echo '<p class="shipkia-track-btn-wrapper">';
            echo '<a href="' . esc_url($url) . '" target="' . esc_attr($target) . '" rel="noopener noreferrer" class="button shipkia-track-link">' . esc_html($text) . '</a>';
            echo '</p>';
            echo '</div>';
        }
    }
}

new Shipkia_Order_Tracking_Display();
