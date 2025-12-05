<?php
/**
 * The template for displaying Job Archives
 */

get_header(); ?>

<div class="mjb-container">
    <header class="page-header">
        <h1 class="page-title"><?php post_type_archive_title(); ?></h1>
    </header>

    <div class="mjb-content-area">
        <main class="site-main">
            <?php if (have_posts()): ?>
                <div class="mjb-job-list">
                    <?php while (have_posts()):
                        the_post(); ?>
                        <div class="mjb-job-item">
                            <h3><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
                            <div class="mjb-job-meta">
                                <span><?php echo get_the_term_list(get_the_ID(), 'job_type', '', ', '); ?></span>
                                <span><?php echo get_the_term_list(get_the_ID(), 'job_location', '', ', '); ?></span>
                                <span><?php echo get_post_meta(get_the_ID(), '_company_name', true); ?></span>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
                <?php the_posts_pagination(); ?>
            <?php else: ?>
                <p><?php _e('No jobs found.', 'modern-job-board'); ?></p>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php get_footer(); ?>