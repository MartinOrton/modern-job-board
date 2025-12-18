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
        if (isset($_POST['mjb_register_candidate']) && isset($_POST['mjb_candidate_nonce'])) {
            if (!wp_verify_nonce($_POST['mjb_candidate_nonce'], 'mjb_candidate_action')) {
                return;
            }

            $username = sanitize_user($_POST['mjb_username']);
            $email = sanitize_email($_POST['mjb_email']);
            $password = $_POST['mjb_password'];
            $first_name = sanitize_text_field($_POST['mjb_first_name']);
            $last_name = sanitize_text_field($_POST['mjb_last_name']);
            $headline = sanitize_text_field($_POST['mjb_headline']);

            // Validation
            if (empty($username) || empty($email) || empty($password)) {
                return;
            }

            if (username_exists($username) || email_exists($email)) {
                return;
            }

            // Create User
            $user_id = wp_create_user($username, $password, $email);

            if (!is_wp_error($user_id)) {
                // Set Role
                $user = new WP_User($user_id);
                $user->set_role('candidate');

                // Update User Data (Name)
                wp_update_user(array(
                    'ID' => $user_id,
                    'first_name' => $first_name,
                    'last_name' => $last_name
                ));

                // Save Meta
                update_user_meta($user_id, '_candidate_headline', $headline);

                // Auto Login
                wp_set_current_user($user_id);
                wp_set_auth_cookie($user_id);

                // Redirect to Home or Candidate Dashboard (TODO)
                wp_redirect(home_url('/'));
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
