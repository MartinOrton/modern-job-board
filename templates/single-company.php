<?php
/**
 * The template for displaying single company.
 */

get_header(); ?>

<div class="mjb-content-area">
    <main class="site-main">
        <?php
        while (have_posts()):
            the_post();
            ?>
            <article id="post-<?php the_ID(); ?>" <?php post_class('mjb-company'); ?>>
                <header class="entry-header">
                    <h1 class="entry-title"><?php the_title(); ?></h1>
                </header>
                <div class="entry-content">
                    <?php the_content(); ?>
                </div>
            </article>

            <div class="mjb-company-jobs">
                <h3><?php printf(__('Jobs at %s', 'modern-job-board'), get_the_title()); ?></h3>
                <?php
                $jobs = new WP_Query(array(
                    'post_type' => 'job_listing',
                    'post_status' => 'publish',
                    'meta_query' => array(
                        array(
                            'key' => '_company_id',
                            'value' => get_the_ID(),
                        ),
                    ),
                ));

                if ($jobs->have_posts()):
                    echo '<div class="mjb-job-list">';
                    while ($jobs->have_posts()):
                        $jobs->the_post();
                        // Reuse simple list style
                        echo '<div class="mjb-job-item">';
                        echo '<h4><a href="' . get_permalink() . '">' . get_the_title() . '</a></h4>';
                        echo '<div class="mjb-job-meta">';
                        echo '<span>' . get_the_term_list(get_the_ID(), 'job_type', '', ', ') . '</span>';
                        echo '<span>' . get_the_term_list(get_the_ID(), 'job_location', '', ', ') . '</span>';
                        echo '</div>';
                        echo '</div>';
                    endwhile;
                    echo '</div>';
                    wp_reset_postdata();
                else:
                    echo '<p>' . __('No active job listings.', 'modern-job-board') . '</p>';
                endif;
                ?>
            </div>

            <?php
        endwhile; // End of the loop.
        ?>
    </main>
</div>

<?php
get_footer();
