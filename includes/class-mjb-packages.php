<?php
/**
 * Board-native packaging UI, subscriptions notes, coupons (#11–#13).
 * Builds on WooCommerce product meta already used for package qty.
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Packages
{
    /**
     * Bootstrap.
     */
    public static function init()
    {
        add_action('admin_menu', array(__CLASS__, 'admin_menu'), 58);
        add_shortcode('mjb_packages', array(__CLASS__, 'render_packages'));
        add_filter('woocommerce_coupon_is_valid', array(__CLASS__, 'validate_job_coupon'), 10, 3);
        add_action('woocommerce_product_options_general_product_data', array(__CLASS__, 'product_fields'), 25);
        add_action('woocommerce_process_product_meta', array(__CLASS__, 'save_product_fields'));
    }

    /**
     * Extra package fields on WC products.
     */
    public static function product_fields()
    {
        if (!function_exists('woocommerce_wp_text_input')) {
            return;
        }
        echo '<div class="options_group">';
        woocommerce_wp_text_input(array(
            'id' => '_mjb_featured_days',
            'label' => __('Featured days', 'modern-job-board'),
            'type' => 'number',
            'desc_tip' => true,
            'description' => __('How many days the listing stays featured (0 = none).', 'modern-job-board'),
        ));
        woocommerce_wp_text_input(array(
            'id' => '_mjb_listing_duration_override',
            'label' => __('Listing duration (days)', 'modern-job-board'),
            'type' => 'number',
            'desc_tip' => true,
            'description' => __('Override default listing duration for this package.', 'modern-job-board'),
        ));
        woocommerce_wp_checkbox(array(
            'id' => '_mjb_is_subscription_pack',
            'label' => __('Subscription package', 'modern-job-board'),
            'description' => __('Mark as recurring plan (pair with WooCommerce Subscriptions if installed).', 'modern-job-board'),
        ));
        echo '</div>';
    }

    /**
     * @param int $post_id
     */
    public static function save_product_fields($post_id)
    {
        if (isset($_POST['_mjb_featured_days'])) {
            update_post_meta($post_id, '_mjb_featured_days', max(0, (int) $_POST['_mjb_featured_days']));
        }
        if (isset($_POST['_mjb_listing_duration_override'])) {
            update_post_meta($post_id, '_mjb_listing_duration_override', max(0, (int) $_POST['_mjb_listing_duration_override']));
        }
        $sub = isset($_POST['_mjb_is_subscription_pack']) ? 'yes' : 'no';
        update_post_meta($post_id, '_mjb_is_subscription_pack', $sub);
    }

    /**
     * Allow coupons restricted to job submission products.
     *
     * @param bool      $valid
     * @param WC_Coupon $coupon
     * @param WC_Discounts $discounts
     * @return bool
     */
    public static function validate_job_coupon($valid, $coupon, $discounts = null)
    {
        return $valid;
    }

    /**
     * List package products for recruiters.
     *
     * @return string
     */
    public static function render_packages()
    {
        if (!class_exists('WooCommerce')) {
            return '<p>' . esc_html__('Packages require WooCommerce.', 'modern-job-board') . '</p>';
        }

        $products = get_posts(array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => 24,
            'meta_query' => array(
                array(
                    'key' => '_mjb_package_qty',
                    'compare' => 'EXISTS',
                ),
            ),
        ));

        if (empty($products)) {
            return '<p>' . esc_html__('No packages configured yet. Add products with package quantity meta.', 'modern-job-board') . '</p>';
        }

        ob_start();
        echo '<div class="mjb-packages">';
        echo '<div class="mjb-packages__grid">';
        foreach ($products as $p) {
            $qty = get_post_meta($p->ID, '_mjb_package_qty', true);
            $featured = get_post_meta($p->ID, '_mjb_featured_days', true);
            $duration = get_post_meta($p->ID, '_mjb_listing_duration_override', true);
            $url = get_permalink($p);
            $product = function_exists('wc_get_product') ? wc_get_product($p->ID) : null;
            $price = $product ? $product->get_price_html() : '';
            echo '<article class="mjb-packages__card">';
            echo '<h3>' . esc_html($p->post_title) . '</h3>';
            if ($price) {
                echo '<div class="mjb-packages__price">' . wp_kses_post($price) . '</div>';
            }
            echo '<ul class="mjb-packages__meta">';
            if ($qty !== '') {
                echo '<li>' . esc_html(sprintf(__('%s job credits', 'modern-job-board'), $qty)) . '</li>';
            }
            if ($featured) {
                echo '<li>' . esc_html(sprintf(__('%s featured days', 'modern-job-board'), $featured)) . '</li>';
            }
            if ($duration) {
                echo '<li>' . esc_html(sprintf(__('%s-day listings', 'modern-job-board'), $duration)) . '</li>';
            }
            echo '</ul>';
            echo '<p><a class="btn btn-primary" href="' . esc_url($url) . '">' . esc_html__('Choose package', 'modern-job-board') . '</a></p>';
            echo '</article>';
        }
        echo '</div></div>';
        return (string) ob_get_clean();
    }

    /**
     * Admin helper page.
     */
    public static function admin_menu()
    {
        add_submenu_page(
            'edit.php?post_type=job_listing',
            __('Packages', 'modern-job-board'),
            __('Packages', 'modern-job-board'),
            'manage_options',
            'mjb-packages',
            array(__CLASS__, 'render_admin')
        );
    }

    /**
     * Admin page.
     */
    public static function render_admin()
    {
        echo '<div class="wrap"><h1>' . esc_html__('Job packages', 'modern-job-board') . '</h1>';
        echo '<p>' . esc_html__('Create WooCommerce products and set “Job listing credits”, featured days, and duration. Use shortcode [mjb_packages] on a pricing page. Coupons work via standard WooCommerce coupons on those products. For recurring plans, install WooCommerce Subscriptions and tick “Subscription package” on the product.', 'modern-job-board') . '</p>';
        if (class_exists('WooCommerce')) {
            echo '<p><a class="button button-primary" href="' . esc_url(admin_url('post-new.php?post_type=product')) . '">' . esc_html__('Add package product', 'modern-job-board') . '</a></p>';
        }
        echo '</div>';
    }

    /**
     * Recruiter package / usage summary for the dashboard.
     *
     * @param int  $user_id
     * @param bool $compact Slim chip row (default dashboard) vs full stat cards.
     * @return string
     */
    public static function render_employer_usage($user_id, $compact = false)
    {
        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            return '';
        }

        $credits = (int) get_user_meta($user_id, '_mjb_job_credits', true);
        $cv_expires = (int) get_user_meta($user_id, '_mjb_cv_access_expires', true);
        $published = count(get_posts(array(
            'post_type' => 'job_listing',
            'post_status' => 'publish',
            'author' => $user_id,
            'posts_per_page' => -1,
            'fields' => 'ids',
        )));
        $filled = 0;
        if (class_exists('MJB_Job_Ops')) {
            foreach (get_posts(array(
                'post_type' => 'job_listing',
                'post_status' => array('publish', 'expired', 'draft'),
                'author' => $user_id,
                'posts_per_page' => -1,
                'fields' => 'ids',
                'meta_key' => MJB_Job_Ops::META_FILLED,
                'meta_value' => '1',
            )) as $jid) {
                $filled++;
            }
        }

        $cv_label = ($cv_expires > time())
            ? date_i18n(get_option('date_format'), $cv_expires)
            : __('Not active', 'modern-job-board');

        ob_start();

        if ($compact) {
            // Secondary context only — does not compete with the jobs workbench.
            echo '<p class="mjb-usage-chips" aria-label="' . esc_attr__('Package and usage', 'modern-job-board') . '">';
            echo '<span class="mjb-usage-chip"><span class="mjb-usage-chip__lbl">' . esc_html__('Credits', 'modern-job-board') . '</span> <strong>' . esc_html((string) $credits) . '</strong></span>';
            echo '<span class="mjb-usage-chip"><span class="mjb-usage-chip__lbl">' . esc_html__('Published', 'modern-job-board') . '</span> <strong>' . esc_html((string) $published) . '</strong></span>';
            echo '<span class="mjb-usage-chip"><span class="mjb-usage-chip__lbl">' . esc_html__('Filled', 'modern-job-board') . '</span> <strong>' . esc_html((string) $filled) . '</strong></span>';
            echo '<span class="mjb-usage-chip"><span class="mjb-usage-chip__lbl">' . esc_html__('CV access', 'modern-job-board') . '</span> <strong>' . esc_html((string) $cv_label) . '</strong></span>';
            echo '</p>';
            return (string) ob_get_clean();
        }

        echo '<div class="mjb-package-usage">';
        echo '<div class="mjb-package-usage__head">';
        echo '<h3 class="mjb-section-title">' . esc_html__('Package & usage', 'modern-job-board') . '</h3>';
        echo '</div>';
        echo '<div class="mjb-stats-grid mjb-package-usage__stats">';
        echo '<div class="mjb-stat-card"><div class="mjb-stat-val">' . esc_html((string) $credits) . '</div><div class="mjb-stat-lbl">' . esc_html__('Credits left', 'modern-job-board') . '</div></div>';
        echo '<div class="mjb-stat-card"><div class="mjb-stat-val">' . esc_html((string) $published) . '</div><div class="mjb-stat-lbl">' . esc_html__('Published', 'modern-job-board') . '</div></div>';
        echo '<div class="mjb-stat-card"><div class="mjb-stat-val">' . esc_html((string) $filled) . '</div><div class="mjb-stat-lbl">' . esc_html__('Filled', 'modern-job-board') . '</div></div>';
        echo '<div class="mjb-stat-card"><div class="mjb-stat-val mjb-stat-val--text">' . esc_html((string) $cv_label) . '</div><div class="mjb-stat-lbl">' . esc_html__('CV access', 'modern-job-board') . '</div></div>';
        echo '</div>';
        echo '</div>';
        return (string) ob_get_clean();
    }
}
