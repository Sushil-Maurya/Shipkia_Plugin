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
        // Show on Order Tracking Page
        add_action('woocommerce_order_tracking_status', array($this, 'display_on_tracking_page'), 10, 1);
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
            $status = $order->get_meta('shipkia_delivery_status', true);

            echo '<div class="shipkia-tracking-status">';
            if (!empty($status)) {
                echo '<p><strong>' . __('Delivery Status:', 'shipkia-shipment-tracking') . '</strong> ' . esc_html($status) . '</p>';
            }
            echo '<p><a href="' . esc_url($url) . '" class="button tracking-btn shipkia-track-btn" target="' . $target . '">' . esc_html($text) . '</a></p>';
            echo '</div>';
        }
    }
}

new Shipkia_Order_Tracking_Display();
