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
            <h1><?php _e('Orders Tracking Report', 'shipkia-shipment-tracking'); ?></h1>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th class="manage-column"><?php _e('Order', 'shipkia-shipment-tracking'); ?></th>
                        <th class="manage-column"><?php _e('Date', 'shipkia-shipment-tracking'); ?></th>
                        <th class="manage-column"><?php _e('Customer', 'shipkia-shipment-tracking'); ?></th>
                        <th class="manage-column"><?php _e('AWB Number', 'shipkia-shipment-tracking'); ?></th>
                        <th class="manage-column"><?php _e('Courier Partner', 'shipkia-shipment-tracking'); ?></th>
                        <th class="manage-column"><?php _e('Status', 'shipkia-shipment-tracking'); ?></th>
                        <th class="manage-column"><?php _e('Action', 'shipkia-shipment-tracking'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                        <tr>
                            <td colspan="7"><?php _e('No orders found.', 'shipkia-shipment-tracking'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($orders as $order_id):
                            $order = wc_get_order($order_id);
                            if (!$order)
                                continue;

                            $awb = $order->get_meta('shipkia_awb_number', true);
                            $courier = $order->get_meta('shipkia_courier_partner', true);
                            $status = $order->get_meta('shipkia_delivery_status', true);
                            $url = Shipkia_Helpers::get_tracking_url($order_id);
                            ?>
                            <tr>
                                <td><a
                                        href="<?php echo esc_url($order->get_edit_order_url()); ?>">#<?php echo $order->get_order_number(); ?></a>
                                </td>
                                <td><?php echo esc_html(wc_format_datetime($order->get_date_created())); ?></td>
                                <td><?php echo esc_html($order->get_formatted_billing_full_name()); ?></td>
                                <td><?php echo $awb ? esc_html($awb) : '&ndash;'; ?></td>
                                <td><?php echo $courier ? esc_html($courier) : '&ndash;'; ?></td>
                                <td><?php echo $status ? esc_html($status) : '&ndash;'; ?></td>
                                <td>
                                    <?php if ($url):
                                        $target = Shipkia_Helpers::open_in_new_tab() ? '_blank' : '_self';
                                        ?>
                                        <a href="<?php echo esc_url($url); ?>" target="<?php echo $target; ?>"
                                            class="button button-primary"><?php echo esc_html(Shipkia_Helpers::get_button_text()); ?></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
