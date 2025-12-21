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

        // Handle Refunds / Cancellations / Failures
        add_action('woocommerce_order_status_refunded', array($this, 'handle_order_status_change'));
        add_action('woocommerce_order_status_cancelled', array($this, 'handle_order_status_change'));
        add_action('woocommerce_order_status_failed', array($this, 'handle_order_status_change'));
    }

    /**
     * Add job_id to cart.
     */
    public function add_job_id_to_cart($cart_item_data, $product_id)
    {
        if (isset($_GET['mjb_job_id'])) {
            $cart_item_data['mjb_job_id'] = intval($_GET['mjb_job_id']);
        }
        if (isset($_GET['mjb_unlock_application_id'])) {
            $cart_item_data['mjb_unlock_application_id'] = intval($_GET['mjb_unlock_application_id']);
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
        if (isset($values['mjb_unlock_application_id'])) {
            $item->add_meta_data('_mjb_unlock_application_id', $values['mjb_unlock_application_id']);
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

            // 3. Single CV Unlock Logic
            $application_id_unlock = $item->get_meta('_mjb_unlock_application_id');
            if ($application_id_unlock && $user_id) {
                $unlocked = get_user_meta($user_id, '_mjb_unlocked_applications', true);
                if (!is_array($unlocked)) {
                    $unlocked = array();
                }
                if (!in_array($application_id_unlock, $unlocked)) {
                    $unlocked[] = $application_id_unlock;
                    update_user_meta($user_id, '_mjb_unlocked_applications', $unlocked);
                }
            }

            // 4. Access Pass Logic (Duration in Days)
            $access_days = get_post_meta($product_id, '_mjb_cv_access_duration', true);
            if ($access_days && $user_id) {
                // Calculate new expiry
                $current_expiry = get_user_meta($user_id, '_mjb_cv_access_expires', true);
                $now = current_time('timestamp');

                // If currently valid, extend from current expiry. If expired or new, start from now.
                $start_time = ($current_expiry && $current_expiry > $now) ? $current_expiry : $now;
                $new_expiry = strtotime('+' . intval($access_days) . ' days', $start_time);

                update_user_meta($user_id, '_mjb_cv_access_expires', $new_expiry);
            }
        }
    }

    /**
     * Handle Order Status Change (Refund/Cancel/Failed).
     */
    public function handle_order_status_change($order_id)
    {
        $order = wc_get_order($order_id);
        $user_id = $order->get_user_id();

        foreach ($order->get_items() as $item) {
            // 1. Revert Pay-Per-Post
            $job_id = $item->get_meta('_mjb_job_id');
            if ($job_id) {
                // Check if current status is publish before reverting? 
                // Yes, unpublish.
                wp_update_post(array(
                    'ID' => $job_id,
                    'post_status' => 'pending_payment', // Set back to pending payment
                ));
            }

            // 2. Revert Credits
            $product_id = $item->get_product_id();
            $credits = get_post_meta($product_id, '_mjb_package_qty', true);
            if ($credits && $user_id) {
                $qty = $item->get_quantity();
                $total_credits_to_remove = intval($credits) * intval($qty);

                $current_user_credits = get_user_meta($user_id, '_mjb_job_credits', true);
                $current_user_credits = $current_user_credits ? intval($current_user_credits) : 0;

                $new_credits = max(0, $current_user_credits - $total_credits_to_remove); // Prevent negative
                update_user_meta($user_id, '_mjb_job_credits', $new_credits);
            }

            // 3. Revert Single Unlock
            $application_id_unlock = $item->get_meta('_mjb_unlock_application_id');
            if ($application_id_unlock && $user_id) {
                $unlocked = get_user_meta($user_id, '_mjb_unlocked_applications', true);
                if (is_array($unlocked)) {
                    $key = array_search($application_id_unlock, $unlocked);
                    if ($key !== false) {
                        unset($unlocked[$key]);
                        // Re-index array
                        $unlocked = array_values($unlocked);
                        update_user_meta($user_id, '_mjb_unlocked_applications', $unlocked);
                    }
                }
            }

            // 4. Revert Access Pass
            $access_days = get_post_meta($product_id, '_mjb_cv_access_duration', true);
            if ($access_days && $user_id) {
                // We can't easily calculating "amount of time remaining from this specific order" if stacked.
                // Simple approach: Expire immediately.
                update_user_meta($user_id, '_mjb_cv_access_expires', current_time('timestamp') - 3600); // Set to past
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

        woocommerce_wp_text_input(array(
            'id' => '_mjb_cv_access_duration',
            'label' => __('CV Access Pass Duration (Days)', 'modern-job-board'),
            'description' => __('Number of days this pass grants access to all CVs.', 'modern-job-board'),
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

        $duration = isset($_POST['_mjb_cv_access_duration']) ? sanitize_text_field($_POST['_mjb_cv_access_duration']) : '';
        if ($duration) {
            update_post_meta($post_id, '_mjb_cv_access_duration', esc_attr($duration));
        }
    }
}
