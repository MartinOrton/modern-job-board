<?php
/**
 * Single company profile — same layout as the Jobs search board,
 * scoped to this employer's listings only.
 */

get_header();

$company_id = get_queried_object_id();
$company_slug = get_post_field('post_name', $company_id);

$filter_params = MJB_Search::get_request_filter_params();
// Always lock filters to the company being viewed.
$filter_params['search_company'] = MJB_Search::normalize_slug($company_slug);

$heading = MJB_Search::get_listing_page_heading($filter_params);

$per_page = 10;
$args = MJB_Search::build_query_args(
    $filter_params,
    array(
        'posts_per_page' => $per_page,
    )
);
$jobs_query = new WP_Query($args);

MJB_Shortcodes::render_content_hero($heading['title'], $heading['intro']);
?>

<div class="mjb-container mjb-container--listing mjb-container--company">
    <div id="mjb-jobs-board" class="mjb-jobs-board">
        <?php MJB_Shortcodes::render_jobs_loader(); ?>
        <div class="mjb-content-area">
            <aside class="mjb-sidebar">
                <?php
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in render_job_search_form().
                echo MJB_Shortcodes::render_job_search_form($filter_params, true);
                ?>
            </aside>

            <main class="site-main">
                <div
                    id="mjb-jobs-list"
                    class="mjb-jobs-list-wrap"
                    data-posts-per-page="<?php echo esc_attr((string) $per_page); ?>"
                    data-search-company="<?php echo esc_attr($filter_params['search_company']); ?>"
                >
                    <div id="mjb-jobs-results">
                        <?php MJB_Shortcodes::render_job_loop($jobs_query); ?>
                        <?php MJB_Shortcodes::render_pagination($jobs_query, $filter_params); ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
</div>

<?php
wp_reset_postdata();
get_footer();
