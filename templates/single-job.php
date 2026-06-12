<?php
/**
 * The template for displaying Single Job
 */

get_header(); ?>

<div class="mjb-container mjb-container--single">
    <div class="mjb-content-area mjb-content-area--single">
        <main class="site-main mjb-single-main">
            <?php while (have_posts()):
                the_post(); ?>
                <article id="post-<?php the_ID(); ?>" <?php post_class('mjb-single-job'); ?>>
                    <header class="entry-header">
                        <h1 class="entry-title"><?php the_title(); ?></h1>
                        <div class="mjb-job-meta">
                            <span><?php echo get_the_term_list(get_the_ID(), 'job_type', '', ', '); ?></span>
                            <span><?php echo get_the_term_list(get_the_ID(), 'job_location', '', ', '); ?></span>
                            <?php
                            $company_name = get_post_meta(get_the_ID(), '_company_name', true);
                            $company_id = get_post_meta(get_the_ID(), '_company_id', true);

                            if ($company_id && get_post($company_id)) {
                                echo '<p class="company-name"><strong>' . __('Company:', 'modern-job-board') . '</strong> <a href="' . get_permalink($company_id) . '">' . esc_html(get_the_title($company_id)) . '</a></p>';
                            } elseif ($company_name) {
                                echo '<p class="company-name"><strong>' . __('Company:', 'modern-job-board') . '</strong> ' . esc_html($company_name) . '</p>';
                            }
                            ?>
                            <p class="posted-date"><strong><?php _e('Posted:', 'modern-job-board'); ?></strong>
                                <?php echo get_the_date(); ?></p>
                            <p class="job-category"><strong><?php _e('Category:', 'modern-job-board'); ?></strong>
                                <?php echo get_the_term_list(get_the_ID(), 'job_category', '', ', '); ?></p>
                        </div>
                    </header>

                    <div class="entry-content">
                        <?php the_content(); ?>
                    </div>

                    <div class="mjb-application-area">
                        <h3><?php _e('Apply for this job', 'modern-job-board'); ?></h3>
                        <?php
                        $method = get_post_meta(get_the_ID(), '_application_method', true);
                        $app_url = get_post_meta(get_the_ID(), '_application_url', true);

                        if ($method === 'external' && !empty($app_url)) {
                            echo '<a href="' . esc_url($app_url) . '" target="_blank" class="button mjb-apply-button">' . __('Apply for this job', 'modern-job-board') . '</a>';
                        } else {
                            // Internal Application Form
                            echo MJB_Notices::render();

                            if (empty($_GET['mjb_notice']) || strpos(sanitize_key(wp_unslash($_GET['mjb_notice'])), 'success_') !== 0) {
                                $current_user = wp_get_current_user();
                                $candidate_name = $current_user->exists() ? $current_user->first_name . ' ' . $current_user->last_name : '';
                                $candidate_email = $current_user->exists() ? $current_user->user_email : '';

                                $resume_label = '';
                                if ($current_user->exists()) {
                                    $resume_id = get_user_meta($current_user->ID, '_candidate_resume_id', true);
                                    if ($resume_id) {
                                        $resume_path = MJB_Resumes::get_resume_post_file_path($resume_id);
                                        if ($resume_path) {
                                            $resume_label = basename($resume_path);
                                        }
                                    }
                                }
                                ?>
                                <form method="post" action="" enctype="multipart/form-data" class="mjb-application-form">
                                    <?php wp_nonce_field('mjb_submit_application', 'mjb_application_nonce'); ?>
                                    <input type="hidden" name="job_id" value="<?php echo get_the_ID(); ?>">
                                    <div class="mjb-hp-field" aria-hidden="true">
                                        <label for="mjb_hp_website"><?php _e('Website', 'modern-job-board'); ?></label>
                                        <input type="text" name="<?php echo esc_attr(MJB_Application_Guard::HONEYPOT_FIELD); ?>" id="mjb_hp_website" tabindex="-1" autocomplete="off">
                                    </div>

                                    <p>
                                        <label for="candidate_name"><?php _e('Full Name', 'modern-job-board'); ?></label>
                                        <input type="text" name="candidate_name" id="candidate_name"
                                            value="<?php echo esc_attr(trim($candidate_name)); ?>" required>
                                    </p>

                                    <p>
                                        <label for="candidate_email"><?php _e('Email Address', 'modern-job-board'); ?></label>
                                        <input type="email" name="candidate_email" id="candidate_email"
                                            value="<?php echo esc_attr($candidate_email); ?>" required>
                                    </p>

                                    <p>
                                        <label for="candidate_resume"><?php _e('Resume (PDF/Doc)', 'modern-job-board'); ?></label>
                                        <?php if ($resume_label): ?>
                                        <div class="mjb-profile-resume-option">
                                            <label>
                                                <input type="checkbox" name="mjb_use_profile_resume" id="mjb_use_profile_resume"
                                                    value="1">
                                                <?php printf(__('Attach my profile resume: <strong>%s</strong>', 'modern-job-board'), esc_html($resume_label)); ?>
                                            </label>
                                        </div>
                                        <div id="mjb-upload-resume-container">
                                            <input type="file" name="candidate_resume" id="candidate_resume" accept=".pdf,.doc,.docx"
                                                required>
                                            <span class="description mjb-upload-hint"><?php _e('Or upload a different one:', 'modern-job-board'); ?></span>
                                        </div>
                                        <script>
                                            document.getElementById('mjb_use_profile_resume').addEventListener('change', function () {
                                                var uploadInput = document.getElementById('candidate_resume');
                                                var container = document.getElementById('mjb-upload-resume-container');
                                                if (this.checked) {
                                                    uploadInput.removeAttribute('required');
                                                    container.classList.add('mjb-is-hidden');
                                                } else {
                                                    uploadInput.setAttribute('required', 'required');
                                                    container.classList.remove('mjb-is-hidden');
                                                }
                                            });
                                        </script>
                                    <?php else: ?>
                                        <input type="file" name="candidate_resume" id="candidate_resume" accept=".pdf,.doc,.docx"
                                            required>
                                    <?php endif; ?>
                                    </p>

                                    <p>
                                        <label
                                            for="candidate_message"><?php _e('Message / Cover Letter', 'modern-job-board'); ?></label>
                                        <textarea name="candidate_message" id="candidate_message" rows="5" required></textarea>
                                    </p>

                                    <?php
                                    global $mjb_custom_fields;
                                    if (isset($mjb_custom_fields)) {
                                        $fields = $mjb_custom_fields->get_fields('application');
                                        foreach ($fields as $field) {
                                            echo '<p>';
                                            echo '<label for="mjb_app_field_' . esc_attr($field['key']) . '">' . esc_html($field['label']) . '</label>';

                                            $field_name = 'mjb_app_field_' . $field['key'];
                                            $required = !empty($field['required']) ? 'required' : '';

                                            if ($field['type'] === 'text' || $field['type'] === 'number') {
                                                echo '<input type="' . esc_attr($field['type']) . '" name="' . esc_attr($field_name) . '" id="' . esc_attr($field_name) . '" ' . $required . '>';
                                            } elseif ($field['type'] === 'textarea') {
                                                echo '<textarea name="' . esc_attr($field_name) . '" id="' . esc_attr($field_name) . '" ' . $required . '></textarea>';
                                            } elseif ($field['type'] === 'select') {
                                                echo '<select name="' . esc_attr($field_name) . '" id="' . esc_attr($field_name) . '" ' . $required . '>';
                                                $options = explode(',', $field['options']);
                                                foreach ($options as $opt) {
                                                    $opt = trim($opt);
                                                    echo '<option value="' . esc_attr($opt) . '">' . esc_html($opt) . '</option>';
                                                }
                                                echo '</select>';
                                            } elseif ($field['type'] === 'checkbox') {
                                                echo '<input type="checkbox" name="' . esc_attr($field_name) . '" id="' . esc_attr($field_name) . '" value="1" ' . $required . '>';
                                            }

                                            echo '</p>';
                                        }
                                    }
                                    ?>

                                    <?php if (MJB_Recaptcha::is_enabled()) : ?>
                                    <p>
                                        <div class="g-recaptcha" data-sitekey="<?php echo esc_attr(MJB_Recaptcha::get_site_key()); ?>"></div>
                                    </p>
                                    <?php endif; ?>

                                    <p>
                                        <input type="submit" name="mjb_submit_application"
                                            value="<?php _e('Submit Application', 'modern-job-board'); ?>">
                                    </p>
                                </form>
                                <?php
                            }
                        }
                        ?>
                    </div>
                </article>
            <?php endwhile; ?>
        </main>
    </div>
</div>

<?php get_footer(); ?>