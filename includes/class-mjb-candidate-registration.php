<?php
/**
 * Modern Job Board Candidate Registration
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Candidate_Registration
{

    /**
     * Initialize Registration.
     */
    public function init()
    {
        add_shortcode('mjb_candidate_registration', array($this, 'output_registration_form'));
        add_action('init', array($this, 'handle_registration'));
    }

    /**
     * Handle Registration Logic.
     */
    public function handle_registration()
    {
        if (!isset($_POST['mjb_register_candidate']) || !isset($_POST['mjb_candidate_nonce'])) {
            return;
        }

        $redirect_url = wp_get_referer() ? wp_get_referer() : home_url('/');

        if (!wp_verify_nonce($_POST['mjb_candidate_nonce'], 'mjb_candidate_action')) {
            MJB_Notices::redirect($redirect_url, 'error_security');
        }

        $username = isset($_POST['mjb_username']) ? sanitize_user($_POST['mjb_username']) : '';
        $email = isset($_POST['mjb_email']) ? sanitize_email($_POST['mjb_email']) : '';
        $password = isset($_POST['mjb_password']) ? $_POST['mjb_password'] : '';
        $first_name = isset($_POST['mjb_first_name']) ? sanitize_text_field($_POST['mjb_first_name']) : '';
        $last_name = isset($_POST['mjb_last_name']) ? sanitize_text_field($_POST['mjb_last_name']) : '';
        $headline = isset($_POST['mjb_headline']) ? sanitize_text_field($_POST['mjb_headline']) : '';

        if (empty($username) || empty($email) || empty($password) || empty($first_name) || empty($last_name)) {
            MJB_Notices::redirect($redirect_url, 'error_missing_fields');
        }

        if (username_exists($username)) {
            MJB_Notices::redirect($redirect_url, 'error_username_exists');
        }

        if (email_exists($email)) {
            MJB_Notices::redirect($redirect_url, 'error_email_exists');
        }

        $user_id = wp_create_user($username, $password, $email);

        if (is_wp_error($user_id)) {
            MJB_Notices::redirect($redirect_url, 'error_registration_failed');
        }

        $user = new WP_User($user_id);
        $user->set_role('candidate');

        wp_update_user(array(
            'ID' => $user_id,
            'first_name' => $first_name,
            'last_name' => $last_name
        ));

        update_user_meta($user_id, '_candidate_headline', $headline);

        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id);

        MJB_Notices::redirect(home_url('/'), 'success_candidate_registered');
    }

    /**
     * Output Registration Form.
     */
    public function output_registration_form($atts)
    {
        if (is_user_logged_in()) {
            return '<p>' . __('You are already logged in.', 'modern-job-board') . '</p>';
        }

        ob_start();
        ?>
        <div class="mjb-registration-form-container">
            <?php echo MJB_Notices::render(); ?>
            <form method="post" action="" class="mjb-form">
                <?php wp_nonce_field('mjb_candidate_action', 'mjb_candidate_nonce'); ?>

                <p>
                    <label for="mjb_username"><?php _e('Username', 'modern-job-board'); ?></label>
                    <input type="text" name="mjb_username" id="mjb_username" required>
                </p>

                <p>
                    <label for="mjb_email"><?php _e('Email Address', 'modern-job-board'); ?></label>
                    <input type="email" name="mjb_email" id="mjb_email" required>
                </p>

                <p>
                    <label for="mjb_password"><?php _e('Password', 'modern-job-board'); ?></label>
                    <input type="password" name="mjb_password" id="mjb_password" required>
                </p>

                <p>
                    <label for="mjb_first_name"><?php _e('First Name', 'modern-job-board'); ?></label>
                    <input type="text" name="mjb_first_name" id="mjb_first_name" required>
                </p>

                <p>
                    <label for="mjb_last_name"><?php _e('Last Name', 'modern-job-board'); ?></label>
                    <input type="text" name="mjb_last_name" id="mjb_last_name" required>
                </p>

                <p>
                    <label for="mjb_headline"><?php _e('Professional Headline', 'modern-job-board'); ?></label>
                    <input type="text" name="mjb_headline" id="mjb_headline"
                        placeholder="<?php esc_attr_e('e.g. Senior Web Developer', 'modern-job-board'); ?>">
                </p>

                <p>
                    <input type="submit" name="mjb_register_candidate"
                        value="<?php _e('Register as Candidate', 'modern-job-board'); ?>">
                </p>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
}