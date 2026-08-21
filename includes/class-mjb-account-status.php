<?php
/**
 * Employer / candidate account approval status (Niceboard-style pending verification).
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Account_Status
{
    const META_KEY = '_mjb_account_status';
    const STATUS_APPROVED = 'approved';
    const STATUS_PENDING  = 'pending';

    /**
     * Initialize hooks.
     */
    public static function init()
    {
        add_filter('wp_authenticate_user', array(__CLASS__, 'block_pending_login'), 10, 2);
        add_filter('manage_users_columns', array(__CLASS__, 'users_column'));
        add_filter('manage_users_custom_column', array(__CLASS__, 'users_column_content'), 10, 3);
        add_filter('user_row_actions', array(__CLASS__, 'user_row_actions'), 10, 2);
        add_action('admin_init', array(__CLASS__, 'handle_admin_approval'));
        add_action('show_user_profile', array(__CLASS__, 'render_profile_field'));
        add_action('edit_user_profile', array(__CLASS__, 'render_profile_field'));
        add_action('personal_options_update', array(__CLASS__, 'save_profile_field'));
        add_action('edit_user_profile_update', array(__CLASS__, 'save_profile_field'));
    }

    /**
     * Whether employer signup requires admin approval.
     *
     * @return bool
     */
    public static function require_employer_approval()
    {
        return (bool) get_option('mjb_require_employer_approval', 0);
    }

    /**
     * Whether candidate signup requires admin approval.
     *
     * @return bool
     */
    public static function require_candidate_approval()
    {
        return (bool) get_option('mjb_require_candidate_approval', 0);
    }

    /**
     * @param int $user_id
     * @return string approved|pending|empty
     */
    public static function get_status($user_id)
    {
        $status = get_user_meta(intval($user_id), self::META_KEY, true);
        if ($status === self::STATUS_PENDING || $status === self::STATUS_APPROVED) {
            return $status;
        }
        return self::STATUS_APPROVED;
    }

    /**
     * @param int $user_id
     * @return bool
     */
    public static function is_pending($user_id)
    {
        return self::get_status($user_id) === self::STATUS_PENDING;
    }

    /**
     * @param int    $user_id
     * @param string $status
     */
    public static function set_status($user_id, $status)
    {
        $user_id = intval($user_id);
        $status = $status === self::STATUS_PENDING ? self::STATUS_PENDING : self::STATUS_APPROVED;
        update_user_meta($user_id, self::META_KEY, $status);
    }

    /**
     * Mark newly registered board account as pending or approved.
     *
     * @param int  $user_id
     * @param bool $requires_approval
     * @return string The status applied.
     */
    public static function set_for_new_registration($user_id, $requires_approval)
    {
        $status = $requires_approval ? self::STATUS_PENDING : self::STATUS_APPROVED;
        self::set_status($user_id, $status);
        return $status;
    }

    /**
     * Approve a pending account (and related company if employer).
     *
     * @param int $user_id
     * @return bool
     */
    public static function approve($user_id)
    {
        $user_id = intval($user_id);
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }

        $was_pending = self::is_pending($user_id);
        self::set_status($user_id, self::STATUS_APPROVED);

        if (in_array('employer', (array) $user->roles, true)) {
            $company_id = intval(get_user_meta($user_id, '_employer_company_id', true));
            if ($company_id) {
                $company = get_post($company_id);
                if ($company && $company->post_type === 'company' && $company->post_status === 'pending') {
                    wp_update_post(array(
                        'ID' => $company_id,
                        'post_status' => 'publish',
                    ));
                }
            }
        }

        if ($was_pending && class_exists('MJB_Emails')) {
            global $mjb_emails;
            if ($mjb_emails instanceof MJB_Emails) {
                $mjb_emails->send_account_approved_notification($user_id);
            }
        }

        /**
         * Fires after an MJB account is approved.
         *
         * @param int $user_id
         */
        do_action('mjb_account_approved', $user_id);

        return true;
    }

    /**
     * Block login for pending board accounts.
     *
     * @param WP_User|WP_Error $user
     * @param string           $password
     * @return WP_User|WP_Error
     */
    public static function block_pending_login($user, $password)
    {
        unset($password);

        if (is_wp_error($user) || !($user instanceof WP_User)) {
            return $user;
        }

        if (user_can($user, 'manage_options')) {
            return $user;
        }

        $roles = (array) $user->roles;
        if (!in_array('employer', $roles, true) && !in_array('candidate', $roles, true)) {
            return $user;
        }

        if (self::is_pending($user->ID)) {
            return new WP_Error(
                'mjb_account_pending',
                __('Your account is pending approval. You will receive an email once it is approved.', 'modern-job-board')
            );
        }

        return $user;
    }

    /**
     * @param array $columns
     * @return array
     */
    public static function users_column($columns)
    {
        $columns['mjb_account_status'] = __('Board status', 'modern-job-board');
        return $columns;
    }

    /**
     * @param string $output
     * @param string $column_name
     * @param int    $user_id
     * @return string
     */
    public static function users_column_content($output, $column_name, $user_id)
    {
        if ($column_name !== 'mjb_account_status') {
            return $output;
        }

        $user = get_userdata($user_id);
        if (!$user) {
            return '—';
        }

        $roles = (array) $user->roles;
        if (!in_array('employer', $roles, true) && !in_array('candidate', $roles, true)) {
            return '—';
        }

        if (self::is_pending($user_id)) {
            return '<span style="color:#b45309;font-weight:600;">' . esc_html__('Pending', 'modern-job-board') . '</span>';
        }

        return esc_html__('Approved', 'modern-job-board');
    }

    /**
     * @param array   $actions
     * @param WP_User $user
     * @return array
     */
    public static function user_row_actions($actions, $user)
    {
        if (!current_user_can('edit_users') || !($user instanceof WP_User)) {
            return $actions;
        }

        $roles = (array) $user->roles;
        if (!in_array('employer', $roles, true) && !in_array('candidate', $roles, true)) {
            return $actions;
        }

        if (!self::is_pending($user->ID)) {
            return $actions;
        }

        $url = wp_nonce_url(
            add_query_arg(
                array(
                    'mjb_approve_account' => 1,
                    'user_id' => $user->ID,
                ),
                admin_url('users.php')
            ),
            'mjb_approve_account_' . $user->ID
        );

        $actions['mjb_approve'] = '<a href="' . esc_url($url) . '">' . esc_html__('Approve board account', 'modern-job-board') . '</a>';

        return $actions;
    }

    /**
     * Handle approve action from Users list.
     */
    public static function handle_admin_approval()
    {
        if (empty($_GET['mjb_approve_account']) || empty($_GET['user_id'])) {
            return;
        }

        if (!current_user_can('edit_users')) {
            return;
        }

        $user_id = intval($_GET['user_id']);
        if (!$user_id || !isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'mjb_approve_account_' . $user_id)) {
            return;
        }

        self::approve($user_id);

        wp_safe_redirect(add_query_arg('mjb_account_approved', '1', admin_url('users.php')));
        exit;
    }

    /**
     * @param WP_User $user
     */
    public static function render_profile_field($user)
    {
        if (!current_user_can('edit_users')) {
            return;
        }

        $roles = (array) $user->roles;
        if (!in_array('employer', $roles, true) && !in_array('candidate', $roles, true)) {
            return;
        }

        $status = self::get_status($user->ID);
        ?>
        <h2><?php esc_html_e('Job board account', 'modern-job-board'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th><label for="mjb_account_status"><?php esc_html_e('Account status', 'modern-job-board'); ?></label></th>
                <td>
                    <select name="mjb_account_status" id="mjb_account_status">
                        <option value="<?php echo esc_attr(self::STATUS_APPROVED); ?>" <?php selected($status, self::STATUS_APPROVED); ?>>
                            <?php esc_html_e('Approved', 'modern-job-board'); ?>
                        </option>
                        <option value="<?php echo esc_attr(self::STATUS_PENDING); ?>" <?php selected($status, self::STATUS_PENDING); ?>>
                            <?php esc_html_e('Pending approval', 'modern-job-board'); ?>
                        </option>
                    </select>
                    <p class="description"><?php esc_html_e('Pending users cannot log in until approved.', 'modern-job-board'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * @param int $user_id
     */
    public static function save_profile_field($user_id)
    {
        if (!current_user_can('edit_users')) {
            return;
        }

        if (!isset($_POST['mjb_account_status'])) {
            return;
        }

        check_admin_referer('update-user_' . $user_id);

        $new = sanitize_key(wp_unslash($_POST['mjb_account_status']));
        $old = self::get_status($user_id);

        if ($new === self::STATUS_APPROVED && $old === self::STATUS_PENDING) {
            self::approve($user_id);
            return;
        }

        self::set_status($user_id, $new);
    }
}
