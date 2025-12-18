<?php
/**
 * Modern Job Board Employer Registration
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Employer_Registration
{

    /**
     * Initialize Registration.
     */
    public function init()
    {
        add_shortcode('mjb_employer_registration', array($this, 'output_registration_form'));
        add_action('init', array($this, 'handle_registration'));
    }

    /**
     * Handle Registration Logic.
     */
    public function handle_registration()
    {
        if (isset($_POST['mjb_register_employer']) && isset($_POST['mjb_registration_nonce'])) {
            if (!wp_verify_nonce($_POST['mjb_registration_nonce'], 'mjb_register_action')) {
                return;
            }

            $username = sanitize_user($_POST['mjb_username']);
            $email = sanitize_email($_POST['mjb_email']);
            $password = $_POST['mjb_password'];
            $company_name = sanitize_text_field($_POST['mjb_company_name']);
            $phone = sanitize_text_field($_POST['mjb_phone']);

            // Validation
            if (empty($username) || empty($email) || empty($password)) {
                // Add error handling logic here (e.g., store in session/transient)
                return;
            }

            if (username_exists($username) || email_exists($email)) {
                // Add error handling logic here
                return;
            }

            // Create User
            $user_id = wp_create_user($username, $password, $email);

            if (!is_wp_error($user_id)) {
                // Set Role
                $user = new WP_User($user_id);
                $user->set_role('employer');

                // Save Meta
                update_user_meta($user_id, '_company_name', $company_name);
                update_user_meta($user_id, '_phone_number', $phone);

                // Auto Login
                wp_set_current_user($user_id);
                wp_set_auth_cookie($user_id);

                // Redirect to Dashboard (or home if dashboard not set)
                // Assuming /job-dashboard/ exists or homepage
                wp_redirect(home_url('/job-dashboard/'));
                exit;
            }
        }
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
            <form method="post" action="" class="mjb-form">
                <?php wp_nonce_field('mjb_register_action', 'mjb_registration_nonce'); ?>

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
                    <label for="mjb_company_name"><?php _e('Company Name', 'modern-job-board'); ?></label>
                    <input type="text" name="mjb_company_name" id="mjb_company_name">
                </p>

                <p>
                    <label for="mjb_phone"><?php _e('Phone Number', 'modern-job-board'); ?></label>
                    <input type="text" name="mjb_phone" id="mjb_phone">
                </p>

                <p>
                    <input type="submit" name="mjb_register_employer"
                        value="<?php _e('Register as Employer', 'modern-job-board'); ?>">
                </p>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }
}
