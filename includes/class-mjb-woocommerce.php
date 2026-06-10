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
        add_action('woocommerce_product_options_general_product_data', array($this, 'add_product_fields'));
        add_action('woocommerce_process_product_meta', array($this, 'save_product_fields'));
        add_filter('woocommerce_add_cart_item_data', array($this, 'add_job_id_to_cart'), 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'save_job_id_to_order'), 10, 4);
        add_action('woocommerce_payment_complete', array($this, 'activate_paid_job'));
        add_action('woocommerce_order_status_completed', array($this, 'activate_paid_job'));
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
            $job_id = intval($_GET['mjb_job_id']);
            if (apply_filters('mjb_wc_user_can_purchase_job', self::user_can_purchase_job($job_id), $job_id)) {
                $cart_item_data['mjb_job_id'] = $job_id;
            }
        }

        if (isset($_GET['mjb_unlock_application_id'])) {
            $application_id = intval($_GET['mjb_unlock_application_id']);
            if (apply_filters('mjb_wc_user_can_unlock_application', self::user_can_unlock_application($application_id), $application_id)) {
                $cart_item_data['mjb_unlock_application_id'] = $application_id;
            }
        }

        return apply_filters('mjb_wc_cart_item_data', $cart_item_data, $product_id);
    }

    /**
     * Whether the current user may purchase a specific job listing.
     *
     * @param int $job_id
     * @return bool
     */
    public static function user_can_purchase_job($job_id)
    {
        if (!is_user_logged_in()) {
            return false;
        }

        $job_id = intval($job_id);
        $job = $job_id ? get_post($job_id) : null;

        if (!$job || $job->post_type !== 'job_listing') {
            return false;
        }

        $user_id = get_current_user_id();

        return user_can($user_id, 'manage_options') || intval($job->post_author) === $user_id;
    }

    /**
     * Whether the current user may unlock a specific application.
     *
     * @param int $application_id
     * @return bool
     */
    public static function user_can_unlock_application($application_id)
    {
        if (!is_user_logged_in()) {
            return false;
        }

        $application_id = intval($application_id);
        $job_id = intval(get_post_meta($application_id, '_job_applied_for', true));
        $job = $job_id ? get_post($job_id) : null;

        if (!$job || $job->post_type !== 'job_listing') {
            return false;
        }

        $user_id = get_current_user_id();

        return user_can($user_id, 'manage_options') || intval($job->post_author) === $user_id;
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
        if ($this->order_already_processed($order_id)) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        do_action('mjb_before_activate_paid_job', $order_id);

        $user_id = $order->get_user_id();

        foreach ($order->get_items() as $item) {
            $job_id = $item->get_meta('_mjb_job_id');
            if ($job_id) {
                wp_update_post(array(
                    'ID' => $job_id,
                    'post_status' => 'publish',
                ));
                update_post_meta($job_id, '_mjb_paid_order_id', $order_id);
            }

            $product_id = $item->get_product_id();
            $credits = get_post_meta($product_id, '_mjb_package_qty', true);

            if ($credits && $user_id) {
                $qty = $item->get_quantity();
                $total_credits = intval($credits) * intval($qty);

                $current_user_credits = get_user_meta($user_id, '_mjb_job_credits', true);
                $current_user_credits = $current_user_credits ? intval($current_user_credits) : 0;

                update_user_meta($user_id, '_mjb_job_credits', $current_user_credits + $total_credits);
            }

            $application_id_unlock = $item->get_meta('_mjb_unlock_application_id');
            if ($application_id_unlock && $user_id) {
                $unlocked = get_user_meta($user_id, '_mjb_unlocked_applications', true);
                if (!is_array($unlocked)) {
                    $unlocked = array();
                }
                if (!in_array($application_id_unlock, $unlocked, true)) {
                    $unlocked[] = $application_id_unlock;
                    update_user_meta($user_id, '_mjb_unlocked_applications', $unlocked);
                }
            }

            $access_days = get_post_meta($product_id, '_mjb_cv_access_duration', true);
            if ($access_days && $user_id) {
                $current_expiry = get_user_meta($user_id, '_mjb_cv_access_expires', true);
                $now = current_time('timestamp');
                $start_time = ($current_expiry && $current_expiry > $now) ? $current_expiry : $now;
                $new_expiry = strtotime('+' . intval($access_days) . ' days', $start_time);

                update_user_meta($user_id, '_mjb_cv_access_expires', $new_expiry);
            }
        }

        $this->mark_order_processed($order_id);

        do_action('mjb_after_activate_paid_job', $order_id);
    }

    /**
     * Handle Order Status Change (Refund/Cancel/Failed).
     */
    public function handle_order_status_change($order_id)
    {
        if (!$this->order_already_processed($order_id)) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $user_id = $order->get_user_id();

        foreach ($order->get_items() as $item) {
            $job_id = $item->get_meta('_mjb_job_id');
            if ($job_id) {
                wp_update_post(array(
                    'ID' => $job_id,
                    'post_status' => 'pending_payment',
                ));
            }

            $product_id = $item->get_product_id();
            $credits = get_post_meta($product_id, '_mjb_package_qty', true);
            if ($credits && $user_id) {
                $qty = $item->get_quantity();
                $total_credits_to_remove = intval($credits) * intval($qty);

                $current_user_credits = get_user_meta($user_id, '_mjb_job_credits', true);
                $current_user_credits = $current_user_credits ? intval($current_user_credits) : 0;

                $new_credits = max(0, $current_user_credits - $total_credits_to_remove);
                update_user_meta($user_id, '_mjb_job_credits', $new_credits);
            }

            $application_id_unlock = $item->get_meta('_mjb_unlock_application_id');
            if ($application_id_unlock && $user_id) {
                $unlocked = get_user_meta($user_id, '_mjb_unlocked_applications', true);
                if (is_array($unlocked)) {
                    $key = array_search($application_id_unlock, $unlocked, true);
                    if ($key !== false) {
                        unset($unlocked[$key]);
                        $unlocked = array_values($unlocked);
                        update_user_meta($user_id, '_mjb_unlocked_applications', $unlocked);
                    }
                }
            }

            $access_days = get_post_meta($product_id, '_mjb_cv_access_duration', true);
            if ($access_days && $user_id) {
                update_user_meta($user_id, '_mjb_cv_access_expires', current_time('timestamp') - 3600);
            }
        }

        delete_post_meta($order_id, '_mjb_benefits_processed');
    }

    /**
     * Whether order benefits were already applied.
     *
     * @param int $order_id
     * @return bool
     */
    public static function is_order_processed($order_id)
    {
        return get_post_meta($order_id, '_mjb_benefits_processed', true) === 'yes';
    }

    /**
     * Instance wrapper for order processed check.
     *
     * @param int $order_id
     * @return bool
     */
    private function order_already_processed($order_id)
    {
        return self::is_order_processed($order_id);
    }

    /**
     * Mark an order as processed.
     *
     * @param int $order_id
     */
    private function mark_order_processed($order_id)
    {
        update_post_meta($order_id, '_mjb_benefits_processed', 'yes');
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