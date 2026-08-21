<?php
/**
 * Memberships: candidate alert slots, employer trial, à-la-carte feature job (WPJB Tier B5, B6, B10).
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Memberships
{
    const OPTION_TRIAL_CREDITS = 'mjb_trial_job_credits';
    const OPTION_TRIAL_FEATURED_DAYS = 'mjb_trial_featured_days';
    const OPTION_TRIAL_CV_DAYS = 'mjb_trial_cv_access_days';
    const OPTION_FREE_ALERT_SLOTS = 'mjb_free_alert_slots';
    const META_ALERT_SLOTS = '_mjb_alert_slots';
    const META_CANDIDATE_PACK = '_mjb_candidate_package';
    const META_FEATURE_PRODUCT = '_mjb_is_feature_job_product';
    const META_FEATURE_DAYS = '_mjb_feature_days';
    const USER_TRIAL_GRANTED = '_mjb_trial_granted';

    /**
     * Bootstrap.
     */
    public static function init()
    {
        add_action('admin_init', array(__CLASS__, 'register_settings'));
        add_action('mjb_employer_registered', array(__CLASS__, 'grant_employer_trial'), 10, 3);
        add_action('woocommerce_product_options_general_product_data', array(__CLASS__, 'product_fields'), 30);
        add_action('woocommerce_process_product_meta', array(__CLASS__, 'save_product_fields'));
        add_action('woocommerce_order_status_completed', array(__CLASS__, 'fulfill_order'), 25);
        add_action('mjb_before_employer_dashboard', array(__CLASS__, 'render_feature_job_cta'), 15);
        add_filter('woocommerce_add_cart_item_data', array(__CLASS__, 'cart_item_feature_job'), 10, 2);
        add_filter('woocommerce_get_item_data', array(__CLASS__, 'display_cart_feature_job'), 10, 2);
        add_action('woocommerce_checkout_create_order_line_item', array(__CLASS__, 'order_item_feature_job'), 10, 4);
        add_action('wp_ajax_mjb_feature_job_add_to_cart', array(__CLASS__, 'ajax_feature_job'));
        add_action('wp_footer', array(__CLASS__, 'print_feature_job_script'), 40);
    }

    /**
     * Minimal dashboard script for Feature button → cart.
     */
    public static function print_feature_job_script()
    {
        if (!is_user_logged_in()) {
            return;
        }
        ?>
        <script>
        (function () {
            document.addEventListener('click', function (e) {
                var btn = e.target.closest('.mjb-feature-job-btn');
                if (!btn) return;
                e.preventDefault();
                var body = new FormData();
                body.append('action', 'mjb_feature_job_add_to_cart');
                body.append('security', btn.getAttribute('data-security') || '');
                body.append('job_id', btn.getAttribute('data-job-id') || '');
                btn.disabled = true;
                fetch((window.mjb_ajax && window.mjb_ajax.ajax_url) || (window.ajaxurl || '/wp-admin/admin-ajax.php'), {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: body
                }).then(function (r) { return r.json(); }).then(function (json) {
                    btn.disabled = false;
                    if (json && json.success && json.data && json.data.cart_url) {
                        window.location.href = json.data.cart_url;
                        return;
                    }
                    alert((json && json.data && json.data.message) ? json.data.message : 'Could not add feature package.');
                }).catch(function () {
                    btn.disabled = false;
                    alert('Could not add feature package.');
                });
            });
        })();
        </script>
        <?php
    }

    /**
     * Settings: trial + free alert slots.
     */
    public static function register_settings()
    {
        register_setting('mjb_settings_group', self::OPTION_TRIAL_CREDITS, array(
            'type' => 'integer',
            'default' => 0,
            'sanitize_callback' => function ($v) {
                return max(0, min(100, (int) $v));
            },
        ));
        register_setting('mjb_settings_group', self::OPTION_TRIAL_FEATURED_DAYS, array(
            'type' => 'integer',
            'default' => 0,
            'sanitize_callback' => function ($v) {
                return max(0, min(90, (int) $v));
            },
        ));
        register_setting('mjb_settings_group', self::OPTION_TRIAL_CV_DAYS, array(
            'type' => 'integer',
            'default' => 0,
            'sanitize_callback' => function ($v) {
                return max(0, min(365, (int) $v));
            },
        ));
        register_setting('mjb_settings_group', self::OPTION_FREE_ALERT_SLOTS, array(
            'type' => 'integer',
            'default' => 3,
            'sanitize_callback' => function ($v) {
                return max(0, min(50, (int) $v));
            },
        ));

        add_settings_section(
            'mjb_memberships_section',
            __('Memberships & trials', 'modern-job-board'),
            function () {
                echo '<p>' . esc_html__('Recruiter trial on register, candidate alert slots, and à-la-carte feature products.', 'modern-job-board') . '</p>';
            },
            'mjb-settings'
        );

        add_settings_field(
            self::OPTION_TRIAL_CREDITS,
            __('Trial job credits', 'modern-job-board'),
            function () {
                $v = (int) get_option(self::OPTION_TRIAL_CREDITS, 0);
                echo '<input type="number" min="0" max="100" name="' . esc_attr(self::OPTION_TRIAL_CREDITS) . '" value="' . esc_attr((string) $v) . '"> ';
                echo '<p class="description">' . esc_html__('Granted once when a recruiter registers. 0 disables trial credits.', 'modern-job-board') . '</p>';
            },
            'mjb-settings',
            'mjb_memberships_section'
        );
        add_settings_field(
            self::OPTION_TRIAL_CV_DAYS,
            __('Trial CV access (days)', 'modern-job-board'),
            function () {
                $v = (int) get_option(self::OPTION_TRIAL_CV_DAYS, 0);
                echo '<input type="number" min="0" max="365" name="' . esc_attr(self::OPTION_TRIAL_CV_DAYS) . '" value="' . esc_attr((string) $v) . '">';
            },
            'mjb-settings',
            'mjb_memberships_section'
        );
        add_settings_field(
            self::OPTION_FREE_ALERT_SLOTS,
            __('Free job alert slots', 'modern-job-board'),
            function () {
                $v = (int) get_option(self::OPTION_FREE_ALERT_SLOTS, 3);
                echo '<input type="number" min="0" max="50" name="' . esc_attr(self::OPTION_FREE_ALERT_SLOTS) . '" value="' . esc_attr((string) $v) . '"> ';
                echo '<p class="description">' . esc_html__('Candidates can buy more slots via a product with “Candidate package → Alert slots”.', 'modern-job-board') . '</p>';
            },
            'mjb-settings',
            'mjb_memberships_section'
        );
    }

    /**
     * B6: grant trial package on employer registration.
     *
     * @param int  $user_id
     * @param int  $company_id
     * @param bool $requires_approval
     */
    public static function grant_employer_trial($user_id, $company_id = 0, $requires_approval = false)
    {
        unset($company_id, $requires_approval);
        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            return;
        }
        if (get_user_meta($user_id, self::USER_TRIAL_GRANTED, true)) {
            return;
        }

        $credits = (int) get_option(self::OPTION_TRIAL_CREDITS, 0);
        $cv_days = (int) get_option(self::OPTION_TRIAL_CV_DAYS, 0);
        if ($credits <= 0 && $cv_days <= 0) {
            return;
        }

        if ($credits > 0) {
            $current = (int) get_user_meta($user_id, '_mjb_job_credits', true);
            update_user_meta($user_id, '_mjb_job_credits', $current + $credits);
        }
        if ($cv_days > 0) {
            $now = function_exists('current_time') ? current_time('timestamp') : time();
            $current_expiry = (int) get_user_meta($user_id, '_mjb_cv_access_expires', true);
            $start = ($current_expiry > $now) ? $current_expiry : $now;
            update_user_meta($user_id, '_mjb_cv_access_expires', $start + ($cv_days * DAY_IN_SECONDS));
        }

        update_user_meta($user_id, self::USER_TRIAL_GRANTED, time());
        do_action('mjb_employer_trial_granted', $user_id, $credits, $cv_days);
    }

    /**
     * Max job alerts for a candidate.
     *
     * @param int $user_id
     * @return int
     */
    public static function alert_slot_limit($user_id)
    {
        $user_id = (int) $user_id;
        $free = (int) get_option(self::OPTION_FREE_ALERT_SLOTS, 3);
        $extra = (int) get_user_meta($user_id, self::META_ALERT_SLOTS, true);
        $limit = max(0, $free + $extra);
        /**
         * Filter candidate alert slot limit.
         *
         * @param int $limit
         * @param int $user_id
         */
        return (int) apply_filters('mjb_alert_slot_limit', $limit, $user_id);
    }

    /**
     * Whether user may create another alert.
     *
     * @param int $user_id
     * @return bool|WP_Error
     */
    public static function can_create_alert($user_id)
    {
        $user_id = (int) $user_id;
        $limit = self::alert_slot_limit($user_id);
        if ($limit <= 0) {
            return new WP_Error('mjb_alert_slots', __('Job alerts are disabled. Purchase a candidate package to enable alerts.', 'modern-job-board'));
        }
        $count = 0;
        if (class_exists('MJB_Job_Alerts')) {
            $count = count(MJB_Job_Alerts::get_user_alerts($user_id));
        }
        if ($count >= $limit) {
            return new WP_Error(
                'mjb_alert_limit',
                sprintf(
                    /* translators: %d: max alerts */
                    __('You have reached your job alert limit (%d). Upgrade your candidate membership for more slots.', 'modern-job-board'),
                    $limit
                )
            );
        }
        return true;
    }

    /**
     * WC product fields for candidate packs + feature-this-job.
     */
    public static function product_fields()
    {
        if (!function_exists('woocommerce_wp_text_input')) {
            return;
        }
        echo '<div class="options_group">';
        woocommerce_wp_checkbox(array(
            'id' => self::META_CANDIDATE_PACK,
            'label' => __('Candidate package', 'modern-job-board'),
            'description' => __('Grants extra job alert slots to the buyer.', 'modern-job-board'),
        ));
        woocommerce_wp_text_input(array(
            'id' => self::META_ALERT_SLOTS,
            'label' => __('Alert slots granted', 'modern-job-board'),
            'type' => 'number',
            'desc_tip' => true,
            'description' => __('Extra alert slots for candidates (added to free slots).', 'modern-job-board'),
        ));
        woocommerce_wp_checkbox(array(
            'id' => self::META_FEATURE_PRODUCT,
            'label' => __('“Feature this job” product', 'modern-job-board'),
            'description' => __('À-la-carte boost: cart must include a job ID; on complete, marks the job featured.', 'modern-job-board'),
        ));
        woocommerce_wp_text_input(array(
            'id' => self::META_FEATURE_DAYS,
            'label' => __('Feature days (à-la-carte)', 'modern-job-board'),
            'type' => 'number',
            'desc_tip' => true,
            'description' => __('How many days the job stays featured after purchase. 0 = featured until manually cleared.', 'modern-job-board'),
        ));
        echo '</div>';
    }

    /**
     * @param int $post_id
     */
    public static function save_product_fields($post_id)
    {
        $cand = isset($_POST[self::META_CANDIDATE_PACK]) ? 'yes' : 'no';
        update_post_meta($post_id, self::META_CANDIDATE_PACK, $cand);
        if (isset($_POST[self::META_ALERT_SLOTS])) {
            update_post_meta($post_id, self::META_ALERT_SLOTS, max(0, (int) $_POST[self::META_ALERT_SLOTS]));
        }
        $feat = isset($_POST[self::META_FEATURE_PRODUCT]) ? 'yes' : 'no';
        update_post_meta($post_id, self::META_FEATURE_PRODUCT, $feat);
        if (isset($_POST[self::META_FEATURE_DAYS])) {
            update_post_meta($post_id, self::META_FEATURE_DAYS, max(0, (int) $_POST[self::META_FEATURE_DAYS]));
        }
    }

    /**
     * Fulfill candidate packs + feature purchases.
     *
     * @param int $order_id
     */
    public static function fulfill_order($order_id)
    {
        if (!function_exists('wc_get_order')) {
            return;
        }
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        $user_id = $order->get_user_id();
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $qty = max(1, (int) $item->get_quantity());

            if ($user_id && get_post_meta($product_id, self::META_CANDIDATE_PACK, true) === 'yes') {
                $slots = (int) get_post_meta($product_id, self::META_ALERT_SLOTS, true);
                if ($slots > 0) {
                    $current = (int) get_user_meta($user_id, self::META_ALERT_SLOTS, true);
                    update_user_meta($user_id, self::META_ALERT_SLOTS, $current + ($slots * $qty));
                }
            }

            if (get_post_meta($product_id, self::META_FEATURE_PRODUCT, true) === 'yes') {
                $job_id = (int) $item->get_meta('_mjb_feature_job_id');
                if (!$job_id) {
                    $job_id = (int) $item->get_meta('_mjb_job_id');
                }
                if ($job_id > 0) {
                    self::feature_job($job_id, (int) get_post_meta($product_id, self::META_FEATURE_DAYS, true));
                }
            }
        }
    }

    /**
     * Mark a job featured, optionally with expiry meta.
     *
     * @param int $job_id
     * @param int $days
     */
    public static function feature_job($job_id, $days = 0)
    {
        $job_id = (int) $job_id;
        if ($job_id <= 0) {
            return;
        }
        update_post_meta($job_id, '_featured', '1');
        $days = max(0, (int) $days);
        if ($days > 0) {
            $until = (function_exists('current_time') ? current_time('timestamp') : time()) + ($days * DAY_IN_SECONDS);
            update_post_meta($job_id, '_mjb_featured_until', $until);
        }
        do_action('mjb_job_featured', $job_id, $days);
    }

    /**
     * First feature product ID, if any.
     *
     * @return int
     */
    public static function get_feature_product_id()
    {
        $ids = get_posts(array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'meta_key' => self::META_FEATURE_PRODUCT,
            'meta_value' => 'yes',
        ));
        return !empty($ids) ? (int) $ids[0] : 0;
    }

    /**
     * Dashboard CTA for feature-this-job.
     *
     * @param int $user_id
     */
    public static function render_feature_job_cta($user_id)
    {
        $product_id = self::get_feature_product_id();
        if ($product_id <= 0 || !function_exists('wc_get_product')) {
            return;
        }
        $product = wc_get_product($product_id);
        if (!$product) {
            return;
        }
        echo '<div class="mjb-feature-job-cta notice">';
        echo '<p><strong>' . esc_html__('Boost a listing', 'modern-job-board') . '</strong> — ';
        echo esc_html(sprintf(
            /* translators: %s: product price html stripped */
            __('Feature a job with “%1$s” (%2$s). Use the Feature button next to each published job.', 'modern-job-board'),
            $product->get_name(),
            wp_strip_all_tags($product->get_price_html())
        ));
        echo '</p></div>';
        unset($user_id);
    }

    /**
     * Cart item data from query args.
     *
     * @param array $cart_item_data
     * @param int   $product_id
     * @return array
     */
    public static function cart_item_feature_job($cart_item_data, $product_id)
    {
        if (get_post_meta($product_id, self::META_FEATURE_PRODUCT, true) !== 'yes') {
            return $cart_item_data;
        }
        $job_id = 0;
        if (isset($_REQUEST['mjb_feature_job_id'])) {
            $job_id = absint($_REQUEST['mjb_feature_job_id']);
        }
        if ($job_id > 0) {
            $cart_item_data['mjb_feature_job_id'] = $job_id;
            $cart_item_data['unique_key'] = md5($product_id . '_' . $job_id . microtime());
        }
        return $cart_item_data;
    }

    /**
     * @param array $item_data
     * @param array $cart_item
     * @return array
     */
    public static function display_cart_feature_job($item_data, $cart_item)
    {
        if (!empty($cart_item['mjb_feature_job_id'])) {
            $jid = (int) $cart_item['mjb_feature_job_id'];
            $item_data[] = array(
                'key' => __('Feature job', 'modern-job-board'),
                'value' => get_the_title($jid) ? get_the_title($jid) : '#' . $jid,
            );
        }
        return $item_data;
    }

    /**
     * @param WC_Order_Item_Product $item
     * @param string                $cart_item_key
     * @param array                 $values
     * @param WC_Order              $order
     */
    public static function order_item_feature_job($item, $cart_item_key, $values, $order)
    {
        unset($cart_item_key, $order);
        if (!empty($values['mjb_feature_job_id'])) {
            $item->add_meta_data('_mjb_feature_job_id', (int) $values['mjb_feature_job_id']);
        }
    }

    /**
     * AJAX: add feature product + job to cart.
     */
    public static function ajax_feature_job()
    {
        check_ajax_referer('mjb_feature_job', 'security');
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Login required.', 'modern-job-board')), 401);
        }
        $job_id = isset($_POST['job_id']) ? absint($_POST['job_id']) : 0;
        $job = get_post($job_id);
        if (!$job || $job->post_type !== 'job_listing') {
            wp_send_json_error(array('message' => __('Invalid job.', 'modern-job-board')), 400);
        }
        $user_id = get_current_user_id();
        if ((int) $job->post_author !== $user_id && !current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Not your job.', 'modern-job-board')), 403);
        }
        $product_id = self::get_feature_product_id();
        if ($product_id <= 0 || !function_exists('WC')) {
            wp_send_json_error(array('message' => __('Feature product not configured.', 'modern-job-board')), 400);
        }
        $added = WC()->cart->add_to_cart($product_id, 1, 0, array(), array('mjb_feature_job_id' => $job_id));
        if (!$added) {
            wp_send_json_error(array('message' => __('Could not add to cart.', 'modern-job-board')), 400);
        }
        wp_send_json_success(array(
            'cart_url' => wc_get_cart_url(),
            'message' => __('Feature package added to cart.', 'modern-job-board'),
        ));
    }
}
