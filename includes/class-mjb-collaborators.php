<?php
/**
 * Collaborator invites (#15) — invite board staff without full WP admin.
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Collaborators
{
    const ROLE = 'mjb_collaborator';
    const META_TOKEN = '_mjb_collab_invite_token';
    const META_EXPIRES = '_mjb_collab_invite_expires';

    /**
     * Bootstrap.
     */
    public static function init()
    {
        add_action('init', array(__CLASS__, 'register_role'));
        add_action('admin_post_mjb_invite_collaborator', array(__CLASS__, 'handle_invite'));
        add_action('admin_menu', array(__CLASS__, 'register_admin_page'), 60);
        add_action('template_redirect', array(__CLASS__, 'handle_accept'));
        add_filter('map_meta_cap', array(__CLASS__, 'map_meta_cap'), 10, 4);
    }

    /**
     * Role: manage listings & applications, not full admin.
     */
    public static function register_role()
    {
        if (get_role(self::ROLE)) {
            return;
        }
        add_role(
            self::ROLE,
            __('Board Collaborator', 'modern-job-board'),
            array(
                'read' => true,
                'edit_posts' => true,
                'edit_published_posts' => true,
                'publish_posts' => true,
                'delete_posts' => false,
                'upload_files' => true,
                'mjb_manage_board' => true,
            )
        );
    }

    /**
     * Grant collaborators job_listing caps via map_meta_cap for owners/admins only partially —
     * collaborators get edit on all job_listing if they have mjb_manage_board.
     *
     * @param string[] $caps
     * @param string   $cap
     * @param int      $user_id
     * @param array    $args
     * @return string[]
     */
    public static function map_meta_cap($caps, $cap, $user_id, $args)
    {
        $user = get_userdata($user_id);
        if (!$user || !in_array(self::ROLE, (array) $user->roles, true)) {
            return $caps;
        }

        $job_caps = array(
            'edit_post', 'delete_post', 'read_post', 'edit_job_listing',
            'edit_job_listings', 'edit_others_job_listings', 'publish_job_listings',
            'read_private_job_listings', 'delete_job_listings',
        );
        if (in_array($cap, $job_caps, true) || strpos($cap, 'job_listing') !== false) {
            return array('mjb_manage_board');
        }
        return $caps;
    }

    /**
     * Admin submenu under MJB.
     */
    public static function register_admin_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        add_submenu_page(
            'edit.php?post_type=job_listing',
            __('Collaborators', 'modern-job-board'),
            __('Collaborators', 'modern-job-board'),
            'manage_options',
            'mjb-collaborators',
            array(__CLASS__, 'render_admin_page')
        );
    }

    /**
     * Admin UI.
     */
    public static function render_admin_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        $users = get_users(array('role' => self::ROLE));
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Board collaborators', 'modern-job-board'); ?></h1>
            <p><?php esc_html_e('Invite teammates to manage job listings and applications without giving them full WordPress admin access.', 'modern-job-board'); ?></p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="mjb_invite_collaborator">
                <?php wp_nonce_field('mjb_invite_collaborator'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="mjb_collab_email"><?php esc_html_e('Email', 'modern-job-board'); ?></label></th>
                        <td><input type="email" class="regular-text" name="email" id="mjb_collab_email" required></td>
                    </tr>
                </table>
                <?php submit_button(__('Send invite', 'modern-job-board')); ?>
            </form>
            <h2><?php esc_html_e('Current collaborators', 'modern-job-board'); ?></h2>
            <ul>
                <?php if (empty($users)) : ?>
                    <li><?php esc_html_e('None yet.', 'modern-job-board'); ?></li>
                <?php else : ?>
                    <?php foreach ($users as $u) : ?>
                        <li><?php echo esc_html($u->user_email . ' — ' . $u->display_name); ?></li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>
        <?php
    }

    /**
     * Send invite email with accept link.
     */
    public static function handle_invite()
    {
        if (!current_user_can('manage_options') || !isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'mjb_invite_collaborator')) {
            wp_die(esc_html__('Unauthorized', 'modern-job-board'));
        }

        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        if (!is_email($email)) {
            wp_safe_redirect(add_query_arg('mjb_collab', 'invalid', admin_url('edit.php?post_type=job_listing&page=mjb-collaborators')));
            exit;
        }

        $token = bin2hex(random_bytes(16));
        $user = get_user_by('email', $email);
        if ($user) {
            $user->add_role(self::ROLE);
            update_user_meta($user->ID, self::META_TOKEN, '');
            $subject = sprintf(__('[%s] You are a board collaborator', 'modern-job-board'), wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES));
            $message = sprintf(__("You now have collaborator access on %s.\n\nSign in: %s", 'modern-job-board'), home_url('/'), wp_login_url());
            wp_mail($email, $subject, $message);
        } else {
            set_transient('mjb_collab_invite_' . $token, array(
                'email' => $email,
                'expires' => time() + 7 * DAY_IN_SECONDS,
            ), 7 * DAY_IN_SECONDS);
            $accept = home_url('/?mjb_collab_accept=' . rawurlencode($token));
            $subject = sprintf(__('[%s] Collaborator invitation', 'modern-job-board'), wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES));
            $message = sprintf(
                __("You have been invited as a board collaborator.\n\nAccept: %s\n\nThis link expires in 7 days.", 'modern-job-board'),
                $accept
            );
            wp_mail($email, $subject, $message);
        }

        wp_safe_redirect(add_query_arg('mjb_collab', 'sent', admin_url('edit.php?post_type=job_listing&page=mjb-collaborators')));
        exit;
    }

    /**
     * Accept invite: create user + assign role.
     */
    public static function handle_accept()
    {
        if (empty($_GET['mjb_collab_accept'])) {
            return;
        }
        $token = sanitize_text_field(wp_unslash($_GET['mjb_collab_accept']));
        $data = get_transient('mjb_collab_invite_' . $token);
        if (!is_array($data) || empty($data['email']) || empty($data['expires']) || time() > (int) $data['expires']) {
            wp_die(esc_html__('This invite is invalid or expired.', 'modern-job-board'));
        }

        $email = sanitize_email($data['email']);
        $user = get_user_by('email', $email);
        if (!$user) {
            $login = sanitize_user(current(explode('@', $email)), true);
            if ($login === '' || username_exists($login)) {
                $login = 'collab_' . wp_generate_password(6, false, false);
            }
            $password = wp_generate_password(16, true);
            $user_id = wp_create_user($login, $password, $email);
            if (is_wp_error($user_id)) {
                wp_die(esc_html($user_id->get_error_message()));
            }
            $user = get_userdata($user_id);
            wp_new_user_notification($user_id, null, 'user');
        }
        $user->add_role(self::ROLE);
        delete_transient('mjb_collab_invite_' . $token);

        wp_safe_redirect(wp_login_url());
        exit;
    }
}
