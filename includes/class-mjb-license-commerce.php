<?php
/**
 * License purchase URLs, vendor key issuance, and WooCommerce license fulfillment.
 *
 * Customer sites: configure purchase URLs + activate keys (see MJB_License).
 * Vendor / sales sites: mark WooCommerce products with a plan meta → order complete
 * emails an offline license key. Admins can also mint keys manually.
 *
 * @package ModernJobBoard
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_License_Commerce
{
    const OPTION_URL_PRO      = 'mjb_purchase_url_pro';
    const OPTION_URL_BUSINESS = 'mjb_purchase_url_business';
    const OPTION_URL_COMPLETE = 'mjb_purchase_url_complete';
    const OPTION_SALES_EMAIL  = 'mjb_sales_email';

    /** Product meta: free|pro|business|complete_site */
    const PRODUCT_META_PLAN = '_mjb_license_plan';

    /** Order meta: already issued keys for this order. */
    const ORDER_META_KEYS = '_mjb_license_keys_issued';

    /**
     * Bootstrap commerce hooks (always on — not gated by customer plan).
     */
    public static function init()
    {
        add_action('admin_post_mjb_generate_license_key', array(__CLASS__, 'handle_generate_key'));

        if (class_exists('WooCommerce')) {
            add_action('woocommerce_product_options_general_product_data', array(__CLASS__, 'product_fields'));
            add_action('woocommerce_process_product_meta', array(__CLASS__, 'save_product_fields'));
            add_action('woocommerce_order_status_completed', array(__CLASS__, 'fulfill_order_licenses'), 20);
            add_action('woocommerce_payment_complete', array(__CLASS__, 'fulfill_order_licenses'), 20);
        }
    }

    /**
     * Default sales contact (filterable).
     *
     * @return string
     */
    public static function get_sales_email()
    {
        $stored = get_option(self::OPTION_SALES_EMAIL, '');
        if (is_string($stored) && is_email($stored)) {
            return $stored;
        }

        $admin = get_option('admin_email');
        $default = is_email($admin) ? $admin : 'hello@martinorton.com';

        return (string) apply_filters('mjb_sales_email', $default);
    }

    /**
     * Checkout / buy URL for a paid plan. Empty string for free.
     * Falls back to a structured mailto when no URL is configured.
     *
     * @param string $plan pro|business|complete_site
     * @return string
     */
    public static function get_purchase_url($plan)
    {
        $plan = sanitize_key($plan);
        $map = array(
            MJB_License::PLAN_PRO      => self::OPTION_URL_PRO,
            MJB_License::PLAN_BUSINESS => self::OPTION_URL_BUSINESS,
            MJB_License::PLAN_COMPLETE => self::OPTION_URL_COMPLETE,
        );

        $url = '';
        if (isset($map[$plan])) {
            $url = (string) get_option($map[$plan], '');
        }

        $url = (string) apply_filters('mjb_purchase_url', $url, $plan);

        if ($url !== '' && self::is_safe_url($url)) {
            return $url;
        }

        if ($plan === MJB_License::PLAN_FREE || $plan === '') {
            return '';
        }

        return self::mailto_purchase_url($plan);
    }

    /**
     * Whether a URL is http(s) or mailto.
     *
     * @param string $url
     * @return bool
     */
    public static function is_safe_url($url)
    {
        $url = trim((string) $url);
        if ($url === '') {
            return false;
        }
        if (stripos($url, 'mailto:') === 0) {
            return true;
        }
        return (bool) preg_match('#^https?://#i', $url);
    }

    /**
     * Structured mailto fallback when checkout URLs are not set.
     *
     * @param string $plan
     * @return string
     */
    public static function mailto_purchase_url($plan)
    {
        $label = MJB_License::get_plan_label($plan);
        $email = self::get_sales_email();
        $subject = sprintf('Modern Job Board — %s license', $label);
        $body = sprintf(
            "Hi,\n\nI'd like to purchase a Modern Job Board %s license.\n\nSite URL: %s\n\nPlease send a license key and payment instructions.\n",
            $label,
            home_url('/')
        );

        return 'mailto:' . $email
            . '?subject=' . rawurlencode($subject)
            . '&body=' . rawurlencode($body);
    }

    /**
     * Sanitize purchase URL option (http(s) or mailto, or empty).
     *
     * @param mixed $value
     * @return string
     */
    public static function sanitize_purchase_url($value)
    {
        $value = is_string($value) ? trim($value) : '';
        if ($value === '') {
            return '';
        }
        if (stripos($value, 'mailto:') === 0) {
            return sanitize_text_field($value);
        }
        $url = esc_url_raw($value);
        return self::is_safe_url($url) ? $url : '';
    }

    /**
     * HTML for upgrade / buy CTAs (admin notices, locked features).
     *
     * @param string $needed_plan pro|business|complete_site
     * @return string
     */
    public static function purchase_cta_html($needed_plan = MJB_License::PLAN_PRO)
    {
        $needed_plan = sanitize_key($needed_plan);
        if (!MJB_License::is_valid_plan($needed_plan) || $needed_plan === MJB_License::PLAN_FREE) {
            $needed_plan = MJB_License::PLAN_PRO;
        }

        $url = self::get_purchase_url($needed_plan);
        $label = MJB_License::get_plan_label($needed_plan);

        $html = '<p class="mjb-purchase-cta">';
        $html .= '<a class="button button-primary" href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">';
        $html .= esc_html(sprintf(
            /* translators: %s: plan name */
            __('Buy %s', 'modern-job-board'),
            $label
        ));
        $html .= '</a> ';
        $html .= '<a class="button button-secondary" href="' . esc_url(admin_url('admin.php?page=modern-job-board&tab=settings#mjb-license')) . '">';
        $html .= esc_html__('I already have a key', 'modern-job-board');
        $html .= '</a></p>';

        return $html;
    }

    /**
     * Issue a signed key for a plan (vendor tooling).
     *
     * @param string $plan
     * @param string $expires YYYYMMDD or 00000000
     * @return string|WP_Error
     */
    public static function issue_key($plan, $expires = '00000000')
    {
        return MJB_License::generate_key($plan, $expires);
    }

    /**
     * Email a license key to a customer.
     *
     * @param string $to
     * @param string $key
     * @param string $plan
     * @param int    $order_id Optional.
     * @return bool
     */
    public static function email_license_key($to, $key, $plan, $order_id = 0)
    {
        $to = sanitize_email($to);
        if (!is_email($to) || $key === '') {
            return false;
        }

        $label = MJB_License::get_plan_label($plan);
        $subject = sprintf(
            /* translators: %s: plan label */
            __('[%1$s] Your Modern Job Board %2$s license key', 'modern-job-board'),
            wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES),
            $label
        );

        $lines = array(
            sprintf(__('Thank you for your purchase.', 'modern-job-board')),
            '',
            sprintf(
                /* translators: %s: plan label */
                __('Plan: %s', 'modern-job-board'),
                $label
            ),
            sprintf(
                /* translators: %s: license key */
                __('License key: %s', 'modern-job-board'),
                $key
            ),
            '',
            __('Activation:', 'modern-job-board'),
            __('1. Install and activate Modern Job Board on your WordPress site.', 'modern-job-board'),
            __('2. Go to Job Board → Settings → License & plan.', 'modern-job-board'),
            __('3. Paste the key and save.', 'modern-job-board'),
            '',
        );

        if ($order_id > 0) {
            $lines[] = sprintf(
                /* translators: %d: order id */
                __('Order #%d', 'modern-job-board'),
                $order_id
            );
            $lines[] = '';
        }

        $lines[] = sprintf(
            /* translators: %s: site name */
            __('— %s', 'modern-job-board'),
            wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES)
        );

        $body = implode("\n", $lines);
        $headers = array('Content-Type: text/plain; charset=UTF-8');

        /**
         * Filter license key email before send.
         *
         * @param array  $email { to, subject, body, headers, key, plan, order_id }
         */
        $email = apply_filters('mjb_license_key_email', array(
            'to' => $to,
            'subject' => $subject,
            'body' => $body,
            'headers' => $headers,
            'key' => $key,
            'plan' => $plan,
            'order_id' => $order_id,
        ));

        $sent = wp_mail(
            $email['to'],
            $email['subject'],
            $email['body'],
            $email['headers']
        );

        do_action('mjb_license_key_emailed', $sent, $email);

        return (bool) $sent;
    }

    /**
     * WooCommerce: product field for plugin license plan.
     */
    public static function product_fields()
    {
        if (!function_exists('woocommerce_wp_select')) {
            return;
        }

        echo '<div class="options_group">';
        woocommerce_wp_select(array(
            'id' => self::PRODUCT_META_PLAN,
            'label' => __('MJB plugin license', 'modern-job-board'),
            'description' => __('When set, completing an order emails a signed Modern Job Board license key to the customer. Use this on your sales site, not for recruiter job-post products.', 'modern-job-board'),
            'desc_tip' => true,
            'options' => array(
                '' => __('— None (board monetization product) —', 'modern-job-board'),
                MJB_License::PLAN_PRO => __('Pro', 'modern-job-board'),
                MJB_License::PLAN_BUSINESS => __('Business', 'modern-job-board'),
                MJB_License::PLAN_COMPLETE => __('Complete Site (Business features)', 'modern-job-board'),
            ),
        ));
        echo '</div>';
    }

    /**
     * @param int $post_id
     */
    public static function save_product_fields($post_id)
    {
        $plan = isset($_POST[self::PRODUCT_META_PLAN])
            ? sanitize_key(wp_unslash($_POST[self::PRODUCT_META_PLAN]))
            : '';

        if ($plan !== '' && !in_array($plan, array(
            MJB_License::PLAN_PRO,
            MJB_License::PLAN_BUSINESS,
            MJB_License::PLAN_COMPLETE,
        ), true)) {
            $plan = '';
        }

        if ($plan === '') {
            delete_post_meta($post_id, self::PRODUCT_META_PLAN);
        } else {
            update_post_meta($post_id, self::PRODUCT_META_PLAN, $plan);
        }
    }

    /**
     * On paid order: issue keys for license products and email the buyer.
     *
     * @param int $order_id
     */
    public static function fulfill_order_licenses($order_id)
    {
        $order_id = (int) $order_id;
        if ($order_id <= 0 || !function_exists('wc_get_order')) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $existing = $order->get_meta(self::ORDER_META_KEYS);
        if (is_array($existing) && !empty($existing)) {
            return;
        }

        $issued = array();
        $expires = apply_filters('mjb_license_issue_expires', '00000000', $order_id);

        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $plan = (string) get_post_meta($product_id, self::PRODUCT_META_PLAN, true);
            if (!MJB_License::is_valid_plan($plan) || $plan === MJB_License::PLAN_FREE) {
                continue;
            }

            $qty = max(1, (int) $item->get_quantity());
            for ($i = 0; $i < $qty; $i++) {
                $key = self::issue_key($plan, $expires);
                if (is_wp_error($key)) {
                    continue;
                }
                $issued[] = array(
                    'plan' => $plan,
                    'key' => $key,
                    'product_id' => $product_id,
                );
            }
        }

        if (empty($issued)) {
            return;
        }

        $order->update_meta_data(self::ORDER_META_KEYS, $issued);
        $order->save();

        $to = $order->get_billing_email();
        foreach ($issued as $row) {
            self::email_license_key($to, $row['key'], $row['plan'], $order_id);
            $order->add_order_note(sprintf(
                /* translators: 1: plan, 2: key */
                __('MJB license issued (%1$s): %2$s', 'modern-job-board'),
                MJB_License::get_plan_label($row['plan']),
                $row['key']
            ));
        }

        do_action('mjb_license_keys_fulfilled', $order_id, $issued);
    }

    /**
     * Admin form: mint a key and optionally email it.
     */
    public static function handle_generate_key()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Unauthorized', 'modern-job-board'), 403);
        }

        check_admin_referer('mjb_generate_license_key');

        $plan = isset($_POST['mjb_gen_plan']) ? sanitize_key(wp_unslash($_POST['mjb_gen_plan'])) : MJB_License::PLAN_PRO;
        $expires = isset($_POST['mjb_gen_expires']) ? sanitize_text_field(wp_unslash($_POST['mjb_gen_expires'])) : '00000000';
        $email = isset($_POST['mjb_gen_email']) ? sanitize_email(wp_unslash($_POST['mjb_gen_email'])) : '';

        if (!in_array($plan, array(
            MJB_License::PLAN_PRO,
            MJB_License::PLAN_BUSINESS,
            MJB_License::PLAN_COMPLETE,
        ), true)) {
            $plan = MJB_License::PLAN_PRO;
        }

        $expires = preg_replace('/\D/', '', $expires);
        if (strlen($expires) !== 8) {
            $expires = '00000000';
        }

        $key = self::issue_key($plan, $expires);
        $redirect = admin_url('admin.php?page=modern-job-board&tab=settings#mjb-license');

        if (is_wp_error($key)) {
            set_transient('mjb_license_gen_notice', array(
                'type' => 'error',
                'message' => $key->get_error_message(),
            ), 60);
            wp_safe_redirect($redirect);
            exit;
        }

        $emailed = false;
        if (is_email($email)) {
            $emailed = self::email_license_key($email, $key, $plan, 0);
        }

        set_transient('mjb_license_gen_notice', array(
            'type' => 'success',
            'message' => $emailed
                ? sprintf(
                    /* translators: 1: key, 2: email */
                    __('License key generated and emailed to %2$s: %1$s', 'modern-job-board'),
                    $key,
                    $email
                )
                : sprintf(
                    /* translators: %s: key */
                    __('License key generated: %s', 'modern-job-board'),
                    $key
                ),
            'key' => $key,
        ), 120);

        wp_safe_redirect($redirect);
        exit;
    }

    /**
     * Admin notice after key generation.
     */
    public static function maybe_render_generate_notice()
    {
        $notice = get_transient('mjb_license_gen_notice');
        if (!is_array($notice) || empty($notice['message'])) {
            return;
        }
        delete_transient('mjb_license_gen_notice');

        $class = (!empty($notice['type']) && $notice['type'] === 'error') ? 'notice-error' : 'notice-success';
        echo '<div class="notice ' . esc_attr($class) . ' is-dismissible"><p>';
        echo esc_html($notice['message']);
        echo '</p></div>';
    }

    /**
     * Vendor form markup for Settings → License.
     *
     * @return string
     */
    public static function render_generate_key_form()
    {
        if (!current_user_can('manage_options')) {
            return '';
        }

        ob_start();
        ?>
        <div class="mjb-license-generate" style="margin-top:1.5em;padding-top:1em;border-top:1px solid #dcdcde">
            <h3><?php esc_html_e('Issue a license key (vendor)', 'modern-job-board'); ?></h3>
            <p class="description">
                <?php esc_html_e('For manual sales or support. Paste the key into the customer email, or send it automatically below. On a sales site with WooCommerce, assign “MJB plugin license” on the product instead.', 'modern-job-board'); ?>
            </p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="max-width:36rem">
                <input type="hidden" name="action" value="mjb_generate_license_key">
                <?php wp_nonce_field('mjb_generate_license_key'); ?>
                <p>
                    <label for="mjb_gen_plan"><strong><?php esc_html_e('Plan', 'modern-job-board'); ?></strong></label><br>
                    <select name="mjb_gen_plan" id="mjb_gen_plan">
                        <option value="<?php echo esc_attr(MJB_License::PLAN_PRO); ?>"><?php esc_html_e('Pro', 'modern-job-board'); ?></option>
                        <option value="<?php echo esc_attr(MJB_License::PLAN_BUSINESS); ?>"><?php esc_html_e('Business', 'modern-job-board'); ?></option>
                        <option value="<?php echo esc_attr(MJB_License::PLAN_COMPLETE); ?>"><?php esc_html_e('Complete Site', 'modern-job-board'); ?></option>
                    </select>
                </p>
                <p>
                    <label for="mjb_gen_expires"><strong><?php esc_html_e('Expiry (YYYYMMDD)', 'modern-job-board'); ?></strong></label><br>
                    <input type="text" name="mjb_gen_expires" id="mjb_gen_expires" value="00000000" class="regular-text" maxlength="8" pattern="\d{8}">
                    <span class="description"><?php esc_html_e('Use 00000000 for no expiry.', 'modern-job-board'); ?></span>
                </p>
                <p>
                    <label for="mjb_gen_email"><strong><?php esc_html_e('Email key to (optional)', 'modern-job-board'); ?></strong></label><br>
                    <input type="email" name="mjb_gen_email" id="mjb_gen_email" class="regular-text" placeholder="customer@example.com">
                </p>
                <p>
                    <button type="submit" class="button button-secondary"><?php esc_html_e('Generate key', 'modern-job-board'); ?></button>
                </p>
            </form>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}
