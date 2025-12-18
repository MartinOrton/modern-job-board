<?php
/**
 * Modern Job Board WooCommerce Integration
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_WooCommerce
{

    /**
     * Initialize WooCommerce Integration.
     */
    public function init()
    {
        // Add job_id to cart item data
        add_filter('woocommerce_add_cart_item_data', array($this, 'add_job_id_to_cart'), 10, 2);

        // Save job_id to order line item
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'save_job_id_to_order'), 10, 4);

        // Handle payment complete
        add_action('woocommerce_payment_complete', array($this, 'activate_paid_job'));
        add_action('woocommerce_order_status_completed', array($this, 'activate_paid_job'));
    }

    /**
     * Add job_id to cart.
     */
    public function add_job_id_to_cart($cart_item_data, $product_id)
    {
        if (isset($_GET['mjb_job_id'])) {
            $cart_item_data['mjb_job_id'] = intval($_GET['mjb_job_id']);
        }
        return $cart_item_data;
    }

    /**
     * Save job_id to order.
     */
    public function save_job_id_to_order($item, $cart_item_key, $values, $order)
    {
        if (isset($values['mjb_job_id'])) {
            $item->add_meta_data('_mjb_job_id', $values['mjb_job_id']);
        }
    }

    /**
     * Activate Paid Job.
     */
    public function activate_paid_job($order_id)
    {
        $order = wc_get_order($order_id);

        foreach ($order->get_items() as $item) {
            $job_id = $item->get_meta('_mjb_job_id');

            if ($job_id) {
                // Determine new status
                // If moderation is required, maybe set to pending (but it was already pending_payment which implies it needs explicit step? Usually "Pending Review" is the next step).
                // Or if paid = publish directly?
                // Let's assume Published for now for instant gratification, or Pending if we prefer.
                // Let's set to 'pending' to be safe (Pending Review), or 'publish'.
                // Standard WP job boards often publish immediately after payment unless moderation is global.

                // Let's stick to 'publish' to confirm payment worked, unless we want to force moderation.
                // Ideally we'd have a setting 'Publish on Payment'. Let's default to 'publish'.

                $post = array(
                    'ID' => $job_id,
                    'post_status' => 'publish',
                );

                wp_update_post($post);

                // Update meta to say paid
                update_post_meta($job_id, '_mjb_paid_order_id', $order_id);
            }
        }
    }
}
