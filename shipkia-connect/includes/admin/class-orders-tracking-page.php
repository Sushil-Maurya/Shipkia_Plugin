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
        if (!current_user_can('manage_woocommerce') && !current_user_can('manage_options')) {
            return;
        }
        if (!function_exists('wc_get_orders')) {
            return;
        }

        $paged = filter_input(INPUT_GET, 'paged', FILTER_SANITIZE_NUMBER_INT);
        $paged = $paged ? max(1, intval($paged)) : 1;
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
            <h1><?php esc_html_e('Orders Tracking Report', 'shipkia-connect'); ?></h1>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th class="manage-column"><?php esc_html_e('Order', 'shipkia-connect'); ?></th>
                        <th class="manage-column"><?php esc_html_e('Customer', 'shipkia-connect'); ?></th>
                        <th class="manage-column"><?php esc_html_e('Date', 'shipkia-connect'); ?></th>
                        <th class="manage-column"><?php esc_html_e('Order Status', 'shipkia-connect'); ?></th>
                        <th class="manage-column"><?php esc_html_e('Courier Partner', 'shipkia-connect'); ?></th>
                        <th class="manage-column"><?php esc_html_e('AWB Number', 'shipkia-connect'); ?></th>
                        <th class="manage-column"><?php esc_html_e('Delivery Status', 'shipkia-connect'); ?></th>
                        <th class="manage-column"><?php esc_html_e('Action', 'shipkia-connect'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (empty($orders)) {
                        echo '<tr><td colspan="8">' . esc_html__('No orders found.', 'shipkia-connect') . '</td></tr>';
                    } else {
                        foreach ($orders as $order_id) {
                            $order = wc_get_order($order_id);
                            if (!$order)
                                continue;

                            $awb = $order->get_meta('_shipkia_awb_number', true);
                            $courier = $order->get_meta('_shipkia_courier_partner', true);
                            $status = $order->get_meta('_shipkia_delivery_status', true);
                            $url = Shipkia_Helpers::get_tracking_url($order_id);
                            ?>
                            <tr>
                                <td>#<?php echo esc_html($order->get_order_number()); ?></td>
                                <td><?php echo esc_html($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()); ?></td>
                                <td><?php echo esc_html(wc_format_datetime($order->get_date_created())); ?></td>
                                <td><?php echo esc_html(wc_get_order_status_name($order->get_status())); ?></td>
                                <td><?php echo esc_html($courier); ?></td>
                                <td><?php echo esc_html($awb); ?></td>
                                <td><?php echo esc_html($status); ?></td>
                                <td>
                                    <?php if (!empty($url)): ?>
                                        <a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer"
                                           class="button button-small"><?php echo esc_html(Shipkia_Helpers::get_button_text()); ?></a>
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
