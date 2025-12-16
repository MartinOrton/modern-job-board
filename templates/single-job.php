<?php
/**
 * The template for displaying Single Job
 */

get_header(); ?>

<div class="mjb-container">
    <div class="mjb-content-area">
        <main class="site-main">
            <?php while (have_posts()):
                the_post(); ?>
                <article id="post-<?php the_ID(); ?>" <?php post_class('mjb-single-job'); ?>>
                    <header class="entry-header">
                        <h1 class="entry-title"><?php the_title(); ?></h1>
                        <div class="mjb-job-meta">
                            <span><?php echo get_the_term_list(get_the_ID(), 'job_type', '', ', '); ?></span>
                            <span><?php echo get_the_term_list(get_the_ID(), 'job_location', '', ', '); ?></span>
                            <span><?php echo get_post_meta(get_the_ID(), '_company_name', true); ?></span>
                        </div>
                    </header>

                    <div class="entry-content">
                        <?php the_content(); ?>
                    </div>

                    <div class="mjb-application-area">
                        <h3><?php _e('Apply for this job', 'modern-job-board'); ?></h3>
                        <?php
                        if (isset($_GET['application_submitted']) && $_GET['application_submitted'] == 'true') {
                            echo '<p class="mjb-success">' . __('Application submitted successfully!', 'modern-job-board') . '</p>';
                        } else {
                            ?>
                            <form method="post" action="" enctype="multipart/form-data" class="mjb-application-form">
                                <?php wp_nonce_field('mjb_submit_application', 'mjb_application_nonce'); ?>
                                <input type="hidden" name="job_id" value="<?php echo get_the_ID(); ?>">

                                <p>
                                    <label for="candidate_name"><?php _e('Full Name', 'modern-job-board'); ?></label>
                                    <input type="text" name="candidate_name" id="candidate_name" required>
                                </p>

                                <p>
                                    <label for="candidate_email"><?php _e('Email Address', 'modern-job-board'); ?></label>
                                    <input type="email" name="candidate_email" id="candidate_email" required>
                                </p>

                                <p>
                                    <label for="candidate_resume"><?php _e('Resume (PDF/Doc)', 'modern-job-board'); ?></label>
                                    <input type="file" name="candidate_resume" id="candidate_resume" accept=".pdf,.doc,.docx"
                                        required>
                                </p>

                                <p>
                                    <label
                                        for="candidate_message"><?php _e('Message / Cover Letter', 'modern-job-board'); ?></label>
                                    <textarea name="candidate_message" id="candidate_message" rows="5" required></textarea>
                                </p>

                                <p>
                                    <input type="submit" name="mjb_submit_application"
                                        value="<?php _e('Submit Application', 'modern-job-board'); ?>">
                                </p>
                            </form>
                        <?php } ?>
                    </div>
                </article>
            <?php endwhile; ?>
        </main>
    </div>
</div>

<?php get_footer(); ?>