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
        // Admin: Add Field to Product General Tab
        add_action('woocommerce_product_options_general_product_data', array($this, 'add_product_fields'));
        add_action('woocommerce_process_product_meta', array($this, 'save_product_fields'));
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
     * Activate Paid Job or Assign Credits.
     */
    public function activate_paid_job($order_id)
    {
        $order = wc_get_order($order_id);
        $user_id = $order->get_user_id();

        foreach ($order->get_items() as $item) {
            // 1. Pay-Per-Post Logic
            $job_id = $item->get_meta('_mjb_job_id');
            if ($job_id) {
                wp_update_post(array(
                    'ID' => $job_id,
                    'post_status' => 'publish',
                ));
                update_post_meta($job_id, '_mjb_paid_order_id', $order_id);
            }

            // 2. Listing Package Logic
            $product_id = $item->get_product_id();
            $credits = get_post_meta($product_id, '_mjb_package_qty', true);

            if ($credits && $user_id) {
                $qty = $item->get_quantity();
                $total_credits = intval($credits) * intval($qty);

                $current_user_credits = get_user_meta($user_id, '_mjb_job_credits', true);
                $current_user_credits = $current_user_credits ? intval($current_user_credits) : 0;

                update_user_meta($user_id, '_mjb_job_credits', $current_user_credits + $total_credits);
            }
        }
    }

    /**
     * Add Product Fields.
     */
    public function add_product_fields()
    {
        echo '<div class="options_group">';
        woocommerce_wp_text_input(array(
            'id' => '_mjb_package_qty',
            'label' => __('Job Listing Credits', 'modern-job-board'),
            'description' => __('Number of job listings this package grants.', 'modern-job-board'),
            'desc_tip' => true,
            'type' => 'number',
        ));
        echo '</div>';
    }

    /**
     * Save Product Fields.
     */
    public function save_product_fields($post_id)
    {
        $credits = isset($_POST['_mjb_package_qty']) ? sanitize_text_field($_POST['_mjb_package_qty']) : '';
        if ($credits) {
            update_post_meta($post_id, '_mjb_package_qty', esc_attr($credits));
        }
    }
}
