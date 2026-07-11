<?php
/**
 * The template for displaying single company.
 */

get_header();

while (have_posts()) {
    the_post();
    $company_id = get_the_ID();
    $job_count = MJB_Shortcodes::get_company_job_count($company_id);

    MJB_Shortcodes::render_content_hero(
        get_the_title(),
        sprintf(
            /* translators: %d: number of open jobs */
            _n(
                '%d open role at this company.',
                '%d open roles at this company.',
                $job_count,
                'modern-job-board'
            ),
            $job_count
        )
    );
}
rewind_posts();
?>

<div class="mjb-container mjb-container--company">
    <div class="mjb-content-area mjb-content-area--company">
        <main class="site-main">
            <?php
            while (have_posts()) :
                the_post();
                $company_id = get_the_ID();
                $jobs_url = MJB_Job_Routes::build_url(array(
                    'search_company' => get_post_field('post_name', $company_id),
                ));
                $excerpt = MJB_Shortcodes::get_company_card_excerpt($company_id);
                ?>
                <article id="post-<?php the_ID(); ?>" <?php post_class('mjb-company-profile'); ?>>
                    <div class="mjb-company-profile__header">
                        <?php MJB_Shortcodes::render_company_avatar($company_id, 'lg'); ?>
                        <div class="mjb-company-profile__summary">
                            <h2 class="mjb-company-profile__name"><?php the_title(); ?></h2>
                            <p class="mjb-company-profile__meta">
                                <?php
                                $job_count = MJB_Shortcodes::get_company_job_count($company_id);
                                echo esc_html(sprintf(
                                    _n('%d open job', '%d open jobs', $job_count, 'modern-job-board'),
                                    $job_count
                                ));
                                ?>
                            </p>
                            <?php if ($job_count > 0) : ?>
                                <a class="mjb-btn mjb-company-profile__cta" href="<?php echo esc_url($jobs_url); ?>">
                                    <?php esc_html_e('View open jobs', 'modern-job-board'); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($excerpt !== '' || get_the_content() !== '') : ?>
                        <div class="entry-content mjb-company-profile__content">
                            <?php
                            if (get_the_content() !== '') {
                                the_content();
                            } else {
                                echo '<p>' . esc_html($excerpt) . '</p>';
                            }
                            ?>
                        </div>
                    <?php endif; ?>
                </article>

                <section class="mjb-company-jobs">
                    <header class="mjb-company-jobs__header">
                        <h2><?php printf(esc_html__('Jobs at %s', 'modern-job-board'), esc_html(get_the_title())); ?></h2>
                    </header>
                    <?php
                    $jobs = new WP_Query(array(
                        'post_type' => 'job_listing',
                        'post_status' => 'publish',
                        'posts_per_page' => -1,
                        'meta_query' => array(
                            array(
                                'key' => '_company_id',
                                'value' => $company_id,
                            ),
                        ),
                    ));

                    MJB_Shortcodes::render_job_loop($jobs);
                    wp_reset_postdata();
                    ?>
                </section>
                <?php
            endwhile;
            ?>
        </main>
    </div>
</div>

<?php
get_footer();