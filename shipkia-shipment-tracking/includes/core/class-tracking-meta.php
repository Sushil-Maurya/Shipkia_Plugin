<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Tracking Meta Class
 */
class Shipkia_Tracking_Meta
{

    public function __construct()
    {
        // Add Meta Box for Tracking Info
        add_action('add_meta_boxes', array($this, 'add_tracking_meta_box'));

        // Remove save action as fields are read-only (synced from external source)
        // add_action( 'woocommerce_process_shop_order_meta', array( $this, 'save_tracking_field' ), 45, 2 );

        // Add Column to Orders List
        add_filter('manage_edit-shop_order_columns', array($this, 'add_tracking_column'), 20);
        add_action('manage_shop_order_posts_custom_column', array($this, 'render_tracking_column'), 20, 2);
        // Also support HPOS (High Performance Order Storage)
        add_filter('manage_woocommerce_page_wc-orders_columns', array($this, 'add_tracking_column'), 20);
        add_action('manage_woocommerce_page_wc-orders_custom_column', array($this, 'render_tracking_column_hpos'), 20, 2);
    }

    /**
     * Add Tracking Fields (Read Only)
     */
    /**
     * Add Meta Box
     */
    public function add_tracking_meta_box()
    {
        if (!function_exists('add_meta_box')) {
            return;
        }

        add_meta_box(
            'shipkia_tracking_meta_box',
            function_exists('__') ? __('Shipkia Shipment Tracking', 'shipkia-shipment-tracking') : 'Shipkia Shipment Tracking',
            array($this, 'render_tracking_meta_box'),
            'shop_order', // For legacy CPT
            'normal',
            'high'
        );

        // HPOS Support
        if (class_exists('\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController') && function_exists('wc_get_page_screen_id')) {
            $screen = wc_get_page_screen_id('shop_order');
            add_meta_box(
                'shipkia_tracking_meta_box',
                function_exists('__') ? __('Shipkia Shipment Tracking', 'shipkia-shipment-tracking') : 'Shipkia Shipment Tracking',
                array($this, 'render_tracking_meta_box'),
                $screen,
                'normal',
                'high'
            );
        }
    }

    /**
     * Render Meta Box
     */
    public function render_tracking_meta_box($post_or_order_object)
    {
        if (!function_exists('wc_get_order')) {
            return;
        }

        // Compatibility: Get Order ID
        $order = ($post_or_order_object instanceof WP_Post) ? wc_get_order($post_or_order_object->ID) : $post_or_order_object;

        if (!$order) {
            return;
        }

        $order_id = $order->get_id();

        // Use order object methods for better HPOS compatibility
        $awb = $order->get_meta('_shipkia_awb_number', true);
        $courier = $order->get_meta('_shipkia_courier_partner', true);
        $status = $order->get_meta('_shipkia_delivery_status', true);
        $tracking_url = $order->get_meta('_shipkia_tracking_url', true);
        $shipkia_order_id = $order->get_meta('_shipkia_order_id', true);

        echo '<div class="shipkia_tracking_fields">';

        echo '<div class="shipkia-admin-row">';

        // AWB Number with Copy Button
        echo '<p class="form-field shipkia-col shipkia-awb-field">';
        echo '<label for="shipkia_awb_number_display">' . (function_exists('__') ? __('AWB Number', 'shipkia-shipment-tracking') : 'AWB Number') . '</label>';
        echo '<span class="shipkia-input-wrapper">';
        echo '<input type="text" id="shipkia_awb_number_display" value="' . (function_exists('esc_attr') ? esc_attr($awb) : $awb) . '" readonly style="background-color: #f0f0f1; cursor: text; user-select: text;" />';
        if (!empty($awb)) {
            echo '<button type="button" class="button shipkia-copy-btn" data-copy="' . (function_exists('esc_attr') ? esc_attr($awb) : $awb) . '" title="' . (function_exists('__') ? __('Copy AWB', 'shipkia-shipment-tracking') : 'Copy AWB') . '">📋</button>';
        }
        echo '</span>';
        echo '</p>';

        // Courier Partner
        if (function_exists('woocommerce_wp_text_input')) {
            woocommerce_wp_text_input(array(
                'id' => 'shipkia_courier_partner_display',
                'label' => function_exists('__') ? __('Courier Partner', 'shipkia-shipment-tracking') : 'Courier Partner',
                'value' => $courier,
                'custom_attributes' => array('readonly' => 'readonly', 'disabled' => 'disabled'),
                'wrapper_class' => 'shipkia-col',
            ));

            // Delivery Status
            woocommerce_wp_text_input(array(
                'id' => 'shipkia_delivery_status_display',
                'label' => function_exists('__') ? __('Delivery Status', 'shipkia-shipment-tracking') : 'Delivery Status',
                'value' => $status,
                'custom_attributes' => array('readonly' => 'readonly', 'disabled' => 'disabled'),
                'wrapper_class' => 'shipkia-col',
            ));
        }

        echo '</div>'; // End shipkia-admin-row

        // Second Row for Order ID and Tracking URL
        echo '<div class="shipkia-admin-row" style="margin-top: 15px;">';

        // Shipkia Order ID
        if (function_exists('woocommerce_wp_text_input')) {
            woocommerce_wp_text_input(array(
                'id' => 'shipkia_order_id_display',
                'label' => function_exists('__') ? __('Shipkia Order ID', 'shipkia-shipment-tracking') : 'Shipkia Order ID',
                'value' => $shipkia_order_id,
                'custom_attributes' => array('readonly' => 'readonly', 'disabled' => 'disabled'),
                'wrapper_class' => 'shipkia-col',
            ));
        }

        // Tracking URL as clickable link
        echo '<p class="form-field shipkia-col">';
        echo '<label for="shipkia_tracking_url_display">' . (function_exists('__') ? __('Tracking URL', 'shipkia-shipment-tracking') : 'Tracking URL') . '</label>';
        if (!empty($tracking_url)) {
            echo '<a href="' . (function_exists('esc_url') ? esc_url($tracking_url) : $tracking_url) . '" target="_blank" class="button button-primary">' . (function_exists('__') ? __('Open Tracking Page', 'shipkia-shipment-tracking') : 'Open Tracking Page') . ' ↗</a>';
        } else {
            echo '<span class="description">' . (function_exists('__') ? __('No tracking URL available', 'shipkia-shipment-tracking') : 'No tracking URL available') . '</span>';
        }
        echo '</p>';

        echo '</div>'; // End second shipkia-admin-row

        echo '</div>'; // End shipkia_tracking_fields
    }

    /**
     * Add Column
     */
    public function add_tracking_column($columns)
    {
        $new_columns = array();
        $tracking_label = function_exists('__') ? __('Shipment Status', 'shipkia-shipment-tracking') : 'Shipment Status';

        foreach ($columns as $key => $column) {
            $new_columns[$key] = $column;
            if ('order_status' === $key) {
                $new_columns['shipkia_tracking'] = $tracking_label;
            }
        }

        if (!isset($new_columns['shipkia_tracking'])) {
            $new_columns['shipkia_tracking'] = $tracking_label;
        }

        return $new_columns;
    }

    /**
     * Render Column (CPT Based)
     */
    public function render_tracking_column($column, $post_id)
    {
        if ('shipkia_tracking' === $column) {
            $this->render_column_content($post_id);
        }
    }

    /**
     * Render Column (HPOS Based)
     * 
     * @param string $column
     * @param WC_Order $order
     */
    public function render_tracking_column_hpos($column, $order)
    {
        if ('shipkia_tracking' === $column) {
            $this->render_column_content($order->get_id());
        }
    }

    /**
     * Get Column Content Helper
     */
    private function render_column_content($order_id)
    {
        if (!function_exists('wc_get_order')) {
            echo '<span class="na">&ndash;</span>';
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            echo '<span class="na">&ndash;</span>';
            return;
        }

        $status = $order->get_meta('_shipkia_delivery_status', true);
        $awb = $order->get_meta('_shipkia_awb_number', true);
        $url = $order->get_meta('_shipkia_tracking_url', true);

        if (!empty($status)) {
            echo '<strong>' . (function_exists('esc_html') ? esc_html($status) : $status) . '</strong>';
            if (!empty($awb)) {
                echo '<br><small class="meta">' . (function_exists('esc_html') ? esc_html($awb) : $awb) . '</small>';
            }
        } elseif (!empty($awb)) {
            echo (function_exists('esc_html') ? esc_html($awb) : $awb);
        } else {
            echo '<span class="na">&ndash;</span>';
        }

        if (!empty($url)) {
            echo '<br><a href="' . (function_exists('esc_url') ? esc_url($url) : $url) . '" target="_blank" class="button button-small shipkia-track-btn">' . (function_exists('__') ? __('Track', 'shipkia-shipment-tracking') : 'Track') . '</a>';
        }
    }
}

new Shipkia_Tracking_Meta();
