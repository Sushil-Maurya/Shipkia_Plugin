<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Helper Class
 */
class Shipkia_Helpers
{

    /**
     * Get Tracking URL
     * Uses `shipkia_tracking_url` meta if available.
     *
     * @param mixed $order_id_or_url  Order ID to look up meta, or allow passing direct URL/Tracking Number for legacy support?
     *                                Refactored to preferably take Order ID.
     * @return string
     */
    public static function get_tracking_url($order_id)
    {
        $order = wc_get_order($order_id);
        if (!$order) {
            return '';
        }

        // Attempt to get the direct tracking URL first
        $url = $order->get_meta('shipkia_tracking_url', true);

        if (!empty($url)) {
            return $url;
        }

        // Fallback for legacy manually entered numbers
        // $tracking_number = $order->get_meta('_shipkia_tracking_number', true);
        // if (!empty($tracking_number)) {
        //     // Fallback template
        //     $template = get_option('shipkia_tracking_url_template', 'https://tracking.shipkia.com/?tracking={tracking}');
        //     if (empty($template)) {
        //         $template = 'https://tracking.shipkia.com/?tracking={tracking}';
        //     }
        //     return str_replace('{tracking}', $tracking_number, $template);
        // }

        return '';
    }

    /**
     * Check if tracking is enabled
     *
     * @return boolean
     */
    public static function is_tracking_enabled()
    {
        $enabled = get_option('shipkia_tracking_enabled', 'yes');
        return 'yes' === $enabled;
    }

    /**
     * Get Track Button Text
     * 
     * @return string
     */
    public static function get_button_text()
    {
        $text = get_option('shipkia_tracking_button_text', __('Track', 'shipkia-shipment-tracking'));
        return !empty($text) ? $text : __('Track', 'shipkia-shipment-tracking');
    }

    /**
     * Should open in new tab?
     * 
     * @return boolean
     */
    public static function open_in_new_tab()
    {
        return 'yes' === get_option('shipkia_tracking_new_tab', 'yes');
    }
}
