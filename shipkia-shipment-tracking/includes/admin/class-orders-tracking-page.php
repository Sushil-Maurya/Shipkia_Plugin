<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Orders Tracking Page Class
 */
class Shipkia_Orders_Tracking_Page
{

    /**
     * Render Page
     */
    public static function render()
    {
        if (!function_exists('wc_get_orders')) {
            return;
        }

        $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $args = array(
            'limit' => 20,
            'page' => $paged,
            'orderby' => 'date',
            'order' => 'DESC',
            'return' => 'ids',
        );
        $orders = wc_get_orders($args);
        ?>
        <div class="wrap">
            <h1><?php if (function_exists('_e')) _e('Orders Tracking Report', 'shipkia-shipment-tracking'); else echo 'Orders Tracking Report'; ?></h1>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th class="manage-column"><?php if (function_exists('_e')) _e('Order', 'shipkia-shipment-tracking'); else echo 'Order'; ?></th>
                        <th class="manage-column"><?php if (function_exists('_e')) _e('Customer', 'shipkia-shipment-tracking'); else echo 'Customer'; ?></th>
                        <th class="manage-column"><?php if (function_exists('_e')) _e('Date', 'shipkia-shipment-tracking'); else echo 'Date'; ?></th>
                        <th class="manage-column"><?php if (function_exists('_e')) _e('Order Status', 'shipkia-shipment-tracking'); else echo 'Order Status'; ?></th>
                        <th class="manage-column"><?php if (function_exists('_e')) _e('Courier Partner', 'shipkia-shipment-tracking'); else echo 'Courier Partner'; ?></th>
                        <th class="manage-column"><?php if (function_exists('_e')) _e('AWB Number', 'shipkia-shipment-tracking'); else echo 'AWB Number'; ?></th>
                        <th class="manage-column"><?php if (function_exists('_e')) _e('Delivery Status', 'shipkia-shipment-tracking'); else echo 'Delivery Status'; ?></th>
                        <th class="manage-column"><?php if (function_exists('_e')) _e('Action', 'shipkia-shipment-tracking'); else echo 'Action'; ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (empty($orders)) {
                        echo '<tr><td colspan="8">' . (function_exists('__') ? __('No orders found.', 'shipkia-shipment-tracking') : 'No orders found.') . '</td></tr>';
                    } else {
                        foreach ($orders as $order_id) {
                            if (!function_exists('wc_get_order')) {
                                continue;
                            }
                            $order = wc_get_order($order_id);
                            if (!$order)
                                continue;

                            $awb = $order->get_meta('_shipkia_awb_number', true);
                            $courier = $order->get_meta('_shipkia_courier_partner', true);
                            $status = $order->get_meta('_shipkia_delivery_status', true);
                            $url = Shipkia_Helpers::get_tracking_url($order_id);
                            ?>
                            <tr>
                                <td>#<?php echo $order->get_order_number(); ?></td>
                                <td><?php echo (function_exists('esc_html') ? esc_html($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()) : ($order->get_billing_first_name() . ' ' . $order->get_billing_last_name())); ?></td>
                                <td><?php echo (function_exists('wc_format_datetime') ? wc_format_datetime($order->get_date_created()) : ($order->get_date_created() ? $order->get_date_created()->date('Y-m-d H:i:s') : '')); ?></td>
                                <td><?php echo (function_exists('esc_html') ? esc_html($order->get_status()) : $order->get_status()); ?></td>
                                <td><?php echo (function_exists('esc_html') ? esc_html($courier) : $courier); ?></td>
                                <td><?php echo (function_exists('esc_html') ? esc_html($awb) : $awb); ?></td>
                                <td><?php echo (function_exists('esc_html') ? esc_html($status) : $status); ?></td>
                                <td>
                                    <?php if (!empty($url)): ?>
                                        <a href="<?php echo (function_exists('esc_url') ? esc_url($url) : $url); ?>" target="_blank"
                                           class="button button-small"><?php echo (function_exists('esc_html') ? esc_html(Shipkia_Helpers::get_button_text()) : Shipkia_Helpers::get_button_text()); ?></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php
                        }
                    }
                    ?>

                </tbody>
            </table>
        </div>
        <?php
    }
}
