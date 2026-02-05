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
        add_meta_box(
            'shipkia_tracking_meta_box',
            __('Shipkia Shipment Tracking', 'shipkia-shipment-tracking'),
            array($this, 'render_tracking_meta_box'),
            'shop_order', // For legacy CPT
            'normal',
            'high'
        );

        // HPOS Support
        if (class_exists('\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController')) {
            $screen = wc_get_page_screen_id('shop_order');
            add_meta_box(
                'shipkia_tracking_meta_box',
                __('Shipkia Shipment Tracking', 'shipkia-shipment-tracking'),
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
        // Compatibility: Get Order ID
        $order = ($post_or_order_object instanceof WP_Post) ? wc_get_order($post_or_order_object->ID) : $post_or_order_object;

        if (!$order) {
            return;
        }

        $order_id = $order->get_id();

        // Use order object methods for better HPOS compatibility
        $awb = $order->get_meta('shipkia_awb_number', true);
        $courier = $order->get_meta('shipkia_courier_partner', true);
        $status = $order->get_meta('shipkia_delivery_status', true);
        $tracking_url = $order->get_meta('shipkia_tracking_url', true);
        $shipkia_order_id = $order->get_meta('shipkia_order_id', true);

        echo '<div class="shipkia_tracking_fields">';

        echo '<div class="shipkia-admin-row">';

        // AWB Number with Copy Button
        echo '<p class="form-field shipkia-col shipkia-awb-field">';
        echo '<label for="shipkia_awb_number_display">' . __('AWB Number', 'shipkia-shipment-tracking') . '</label>';
        echo '<span class="shipkia-input-wrapper">';
        echo '<input type="text" id="shipkia_awb_number_display" value="' . esc_attr($awb) . '" readonly style="background-color: #f0f0f1; cursor: text; user-select: text;" />';
        if (!empty($awb)) {
            echo '<button type="button" class="button shipkia-copy-btn" data-copy="' . esc_attr($awb) . '" title="' . __('Copy AWB', 'shipkia-shipment-tracking') . '">📋</button>';
        }
        echo '</span>';
        echo '</p>';

        // Courier Partner
        woocommerce_wp_text_input(array(
            'id' => 'shipkia_courier_partner_display',
            'label' => __('Courier Partner', 'shipkia-shipment-tracking'),
            'value' => $courier,
            'custom_attributes' => array('readonly' => 'readonly', 'disabled' => 'disabled'),
            'wrapper_class' => 'shipkia-col',
        ));

        // Delivery Status
        woocommerce_wp_text_input(array(
            'id' => 'shipkia_delivery_status_display',
            'label' => __('Delivery Status', 'shipkia-shipment-tracking'),
            'value' => $status,
            'custom_attributes' => array('readonly' => 'readonly', 'disabled' => 'disabled'),
            'wrapper_class' => 'shipkia-col',
        ));

        echo '</div>'; // End shipkia-admin-row

        // Second Row for Order ID and Tracking URL
        echo '<div class="shipkia-admin-row" style="margin-top: 15px;">';

        // Shipkia Order ID
        woocommerce_wp_text_input(array(
            'id' => 'shipkia_order_id_display',
            'label' => __('Shipkia Order ID', 'shipkia-shipment-tracking'),
            'value' => $shipkia_order_id,
            'custom_attributes' => array('readonly' => 'readonly', 'disabled' => 'disabled'),
            'wrapper_class' => 'shipkia-col',
        ));

        // Tracking URL as clickable link
        echo '<p class="form-field shipkia-col">';
        echo '<label for="shipkia_tracking_url_display">' . __('Tracking URL', 'shipkia-shipment-tracking') . '</label>';
        if (!empty($tracking_url)) {
            echo '<a href="' . esc_url($tracking_url) . '" target="_blank" class="button button-primary">' . __('Open Tracking Page', 'shipkia-shipment-tracking') . ' ↗</a>';
        } else {
            echo '<span class="description">' . __('No tracking URL available', 'shipkia-shipment-tracking') . '</span>';
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

        foreach ($columns as $key => $column) {
            $new_columns[$key] = $column;
            if ('order_status' === $key) {
                $new_columns['shipkia_tracking'] = __('Shipment Status', 'shipkia-shipment-tracking');
            }
        }

        if (!isset($new_columns['shipkia_tracking'])) {
            $new_columns['shipkia_tracking'] = __('Shipment Status', 'shipkia-shipment-tracking');
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
        $order = wc_get_order($order_id);
        if (!$order) {
            echo '<span class="na">&ndash;</span>';
            return;
        }

        $status = $order->get_meta('shipkia_delivery_status', true);
        $awb = $order->get_meta('shipkia_awb_number', true);
        $url = $order->get_meta('shipkia_tracking_url', true);

        if (!empty($status)) {
            echo '<strong>' . esc_html($status) . '</strong>';
            if (!empty($awb)) {
                echo '<br><small class="meta">' . esc_html($awb) . '</small>';
            }
        } elseif (!empty($awb)) {
            echo esc_html($awb);
        } else {
            echo '<span class="na">&ndash;</span>';
        }

        if (!empty($url)) {
            echo '<br><a href="' . esc_url($url) . '" target="_blank" class="button button-small shipkia-track-btn">' . __('Track', 'shipkia-shipment-tracking') . '</a>';
        }
    }
}

new Shipkia_Tracking_Meta();
