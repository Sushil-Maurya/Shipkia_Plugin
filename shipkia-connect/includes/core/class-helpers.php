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
        if (!function_exists('wc_get_order')) {
            return '';
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return '';
        }

        // Attempt to get the direct tracking URL first
        $url = $order->get_meta('_shipkia_tracking_url', true);

        if (!empty($url)) {
            return $url;
        }

        return '';
    }

    /**
     * Check if tracking is enabled
     *
     * @return boolean
     */
    public static function is_tracking_enabled()
    {
        if (!function_exists('get_option')) {
            return false;
        }

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
        if (!function_exists('get_option')) {
            return 'Track';
        }

        $default_text = function_exists('__') ? __('Track', 'shipkia-connect') : 'Track';
        $text = get_option('shipkia_tracking_button_text', $default_text);
        return !empty($text) ? $text : $default_text;
    }

    /**
     * Should open in new tab?
     * 
     * @return boolean
     */
    public static function open_in_new_tab()
    {
        if (!function_exists('get_option')) {
            return true;
        }

        return 'yes' === get_option('shipkia_tracking_new_tab', 'yes');
    }
}

/**
 * Global Helper Function for Logging
 * 
 * @param string $message
 * @param string $level
 * @param array $context
 * @param string $source
 */
function shipkia_tracking_log($message, $level = 'info', $context = array(), $source = 'shipkia-connection')
{
    if (class_exists('Shipkia_Logger')) {
        Shipkia_Logger::add($message, $level, $context, $source);
    }
}
