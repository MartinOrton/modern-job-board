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
                        <p><?php _e('Application instructions go here.', 'modern-job-board'); ?></p>
                        <!-- Placeholder for application form or link -->
                        <button class="mjb-apply-button"><?php _e('Apply Now', 'modern-job-board'); ?></button>
                    </div>
                </article>
            <?php endwhile; ?>
        </main>
    </div>
</div>

<?php get_footer(); ?>