<?php
/**
 * Admin payments / memberships overview (WPJB Tier B7).
 * WC-native: summary + deep links, not a parallel billing system.
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Commerce_Admin
{
    /**
     * Bootstrap.
     */
    public static function init()
    {
        add_action('admin_menu', array(__CLASS__, 'admin_menu'), 57);
    }

    /**
     * Submenu under job listings.
     */
    public static function admin_menu()
    {
        add_submenu_page(
            'edit.php?post_type=job_listing',
            __('Payments & memberships', 'modern-job-board'),
            __('Payments', 'modern-job-board'),
            'manage_options',
            'mjb-payments',
            array(__CLASS__, 'render_admin')
        );
    }

    /**
     * Count users with job credits / CV access / alert slots.
     *
     * @return array{credits:int,cv:int,alerts:int,trial:int}
     */
    public static function membership_counts()
    {
        global $wpdb;
        $counts = array(
            'credits' => 0,
            'cv' => 0,
            'alerts' => 0,
            'trial' => 0,
        );
        if (!$wpdb) {
            return $counts;
        }
        // Best-effort; tests may not have $wpdb.
        if (method_exists($wpdb, 'get_var')) {
            $now = time();
            $counts['credits'] = (int) $wpdb->get_var(
                "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta} WHERE meta_key = '_mjb_job_credits' AND CAST(meta_value AS UNSIGNED) > 0"
            );
            $counts['cv'] = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta} WHERE meta_key = '_mjb_cv_access_expires' AND CAST(meta_value AS UNSIGNED) > %d",
                $now
            ));
            $counts['alerts'] = (int) $wpdb->get_var(
                "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta} WHERE meta_key = '_mjb_alert_slots' AND CAST(meta_value AS UNSIGNED) > 0"
            );
            $counts['trial'] = (int) $wpdb->get_var(
                "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta} WHERE meta_key = '_mjb_trial_granted' AND meta_value != ''"
            );
        }
        return $counts;
    }

    /**
     * Recent WC orders that include MJB package products.
     *
     * @param int $limit
     * @return array<int, array{id:int,status:string,total:string,date:string,customer:string}>
     */
    public static function recent_package_orders($limit = 10)
    {
        if (!function_exists('wc_get_orders')) {
            return array();
        }
        $orders = wc_get_orders(array(
            'limit' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
            'status' => array('wc-completed', 'wc-processing', 'wc-on-hold', 'wc-pending'),
        ));
        $out = array();
        foreach ($orders as $order) {
            $has_mjb = false;
            foreach ($order->get_items() as $item) {
                $pid = $item->get_product_id();
                if (
                    get_post_meta($pid, '_mjb_package_qty', true) !== ''
                    || get_post_meta($pid, '_mjb_cv_access_duration', true) !== ''
                    || get_post_meta($pid, MJB_Memberships::META_CANDIDATE_PACK, true) === 'yes'
                    || get_post_meta($pid, MJB_Memberships::META_FEATURE_PRODUCT, true) === 'yes'
                    || $item->get_meta('_mjb_job_id')
                    || $item->get_meta('_mjb_feature_job_id')
                ) {
                    $has_mjb = true;
                    break;
                }
            }
            if (!$has_mjb) {
                continue;
            }
            $out[] = array(
                'id' => $order->get_id(),
                'status' => $order->get_status(),
                'total' => $order->get_formatted_order_total(),
                'date' => $order->get_date_created() ? $order->get_date_created()->date_i18n(get_option('date_format')) : '',
                'customer' => $order->get_formatted_billing_full_name(),
                'edit_url' => $order->get_edit_order_url(),
            );
        }
        return $out;
    }

    /**
     * Admin screen.
     */
    public static function render_admin()
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $wc = class_exists('WooCommerce');
        $counts = self::membership_counts();

        echo '<div class="wrap"><h1>' . esc_html__('Payments & memberships', 'modern-job-board') . '</h1>';
        echo '<p>' . esc_html__('MJB uses WooCommerce for packages, CV access, feature boosts, and candidate memberships. This panel summarises board memberships and links into WooCommerce.', 'modern-job-board') . '</p>';

        if (!$wc) {
            echo '<div class="notice notice-warning"><p>' . esc_html__('WooCommerce is not active. Install and activate it to sell packages.', 'modern-job-board') . '</p></div>';
        }

        echo '<div class="mjb-commerce-cards">';
        self::stat_card(__('Users with job credits', 'modern-job-board'), (string) $counts['credits']);
        self::stat_card(__('Active CV access', 'modern-job-board'), (string) $counts['cv']);
        self::stat_card(__('Paid alert slots', 'modern-job-board'), (string) $counts['alerts']);
        self::stat_card(__('Recruiter trials granted', 'modern-job-board'), (string) $counts['trial']);
        echo '</div>';

        echo '<h2>' . esc_html__('Quick links', 'modern-job-board') . '</h2><p>';
        if ($wc) {
            echo '<a class="button button-primary" href="' . esc_url(admin_url('edit.php?post_type=shop_order')) . '">' . esc_html__('All orders', 'modern-job-board') . '</a> ';
            echo '<a class="button" href="' . esc_url(admin_url('edit.php?post_type=product')) . '">' . esc_html__('Products', 'modern-job-board') . '</a> ';
            echo '<a class="button" href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=checkout')) . '">' . esc_html__('Payment gateways', 'modern-job-board') . '</a> ';
        }
        echo '<a class="button" href="' . esc_url(admin_url('edit.php?post_type=job_listing&page=mjb-packages')) . '">' . esc_html__('Package setup', 'modern-job-board') . '</a> ';
        echo '<a class="button" href="' . esc_url(admin_url('edit.php?post_type=job_listing&page=mjb-settings')) . '">' . esc_html__('Trial & membership settings', 'modern-job-board') . '</a>';
        echo '</p>';

        if ($wc) {
            echo '<h2>' . esc_html__('Recent board-related orders', 'modern-job-board') . '</h2>';
            $orders = self::recent_package_orders(15);
            if (empty($orders)) {
                echo '<p>' . esc_html__('No recent orders with MJB package / feature / CV products.', 'modern-job-board') . '</p>';
            } else {
                echo '<table class="widefat striped"><thead><tr>';
                echo '<th>' . esc_html__('Order', 'modern-job-board') . '</th>';
                echo '<th>' . esc_html__('Customer', 'modern-job-board') . '</th>';
                echo '<th>' . esc_html__('Date', 'modern-job-board') . '</th>';
                echo '<th>' . esc_html__('Status', 'modern-job-board') . '</th>';
                echo '<th>' . esc_html__('Total', 'modern-job-board') . '</th>';
                echo '</tr></thead><tbody>';
                foreach ($orders as $o) {
                    echo '<tr>';
                    echo '<td><a href="' . esc_url($o['edit_url']) . '">#' . esc_html((string) $o['id']) . '</a></td>';
                    echo '<td>' . esc_html($o['customer']) . '</td>';
                    echo '<td>' . esc_html($o['date']) . '</td>';
                    echo '<td>' . esc_html($o['status']) . '</td>';
                    echo '<td>' . wp_kses_post($o['total']) . '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
            }
        }

        echo '<h2>' . esc_html__('Product meta checklist', 'modern-job-board') . '</h2>';
        echo '<ul class="ul-disc">';
        echo '<li>' . esc_html__('Job credits: _mjb_package_qty on product', 'modern-job-board') . '</li>';
        echo '<li>' . esc_html__('CV access days: _mjb_cv_access_duration', 'modern-job-board') . '</li>';
        echo '<li>' . esc_html__('Candidate alert slots: Candidate package + Alert slots granted', 'modern-job-board') . '</li>';
        echo '<li>' . esc_html__('À-la-carte feature: “Feature this job” product checkbox', 'modern-job-board') . '</li>';
        echo '<li>' . esc_html__('Recruiter trial: Settings → Memberships & trials', 'modern-job-board') . '</li>';
        echo '</ul></div>';
    }

    /**
     * @param string $label
     * @param string $value
     */
    private static function stat_card($label, $value)
    {
        echo '<div class="mjb-commerce-card mjb-admin-card">';
        echo '<div class="mjb-commerce-card__value">' . esc_html($value) . '</div>';
        echo '<div class="mjb-commerce-card__label">' . esc_html($label) . '</div>';
        echo '</div>';
    }
}
