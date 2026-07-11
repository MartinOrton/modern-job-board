<?php
/**
 * The template for displaying Job Archives
 */

get_header();

$filter_params = MJB_Search::get_request_filter_params();
$heading = MJB_Search::get_listing_page_heading($filter_params);
$jobs_query = $GLOBALS['wp_query'];
$per_page = max(1, intval($jobs_query->get('posts_per_page')));
if ($per_page < 1) {
    $per_page = 10;
}

MJB_Shortcodes::render_content_hero($heading['title'], $heading['intro']);
?>

<div class="mjb-container mjb-container--listing">
    <?php MJB_Shortcodes::render_audience_cards($filter_params); ?>
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
                <div id="mjb-jobs-list" class="mjb-jobs-list-wrap" data-posts-per-page="<?php echo esc_attr($per_page); ?>"<?php echo !empty($filter_params['search_company']) ? ' data-search-company="' . esc_attr($filter_params['search_company']) . '"' : ''; ?>>
                    <div id="mjb-jobs-results">
                        <?php MJB_Shortcodes::render_job_loop($jobs_query); ?>
                        <?php MJB_Shortcodes::render_pagination($jobs_query, $filter_params); ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
</div>

<?php get_footer(); ?>