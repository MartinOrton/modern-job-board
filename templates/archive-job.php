<?php
/**
 * The template for displaying Job Archives
 */

get_header(); ?>

<div class="mjb-container">
    <header class="mjb-page-header">
        <h1 class="mjb-page-title"><?php post_type_archive_title(); ?></h1>
        <p class="mjb-page-intro"><?php esc_html_e('Browse open roles and filter by location, category, or keywords.', 'modern-job-board'); ?></p>
    </header>

    <div class="mjb-content-area">
        <aside class="mjb-sidebar">
            <form action="<?php echo esc_url(MJB_Job_Routes::build_url()); ?>" method="GET"
                class="mjb-search-form mjb-search-panel">
                <h3><?php _e('Filter Jobs', 'modern-job-board'); ?></h3>

                <p>
                    <label for="search_keywords"><?php _e('Keywords', 'modern-job-board'); ?></label>
                    <input type="text" name="search_keywords" id="search_keywords"
                        value="<?php echo isset($_GET['search_keywords']) ? esc_attr($_GET['search_keywords']) : ''; ?>">
                </p>

                <p>
                    <label for="search_location"><?php _e('Location', 'modern-job-board'); ?></label>
                    <?php
                    $archive_filters = MJB_Search::get_request_filter_params();
                    echo MJB_Search::render_location_dropdown($archive_filters['search_location']);
                    ?>
                </p>

                <p>
                    <label for="search_category"><?php _e('Category', 'modern-job-board'); ?></label>
                    <select name="search_category" id="search_category">
                        <option value=""><?php _e('All Categories', 'modern-job-board'); ?></option>
                        <?php
                        $categories = get_terms(array('taxonomy' => 'job_category', 'hide_empty' => false));
                        foreach ($categories as $category) {
                            $selected = isset($_GET['search_category']) && $_GET['search_category'] == $category->slug ? 'selected' : '';
                            echo '<option value="' . esc_attr($category->slug) . '" ' . $selected . '>' . esc_html($category->name) . '</option>';
                        }
                        ?>
                    </select>
                </p>

                <p>
                    <input type="submit" value="<?php _e('Search', 'modern-job-board'); ?>">
                    <a href="<?php echo get_post_type_archive_link('job_listing'); ?>"
                        class="mjb-reset-button"><?php _e('Reset', 'modern-job-board'); ?></a>
                </p>
            </form>
        </aside>

        <main class="site-main">
            <?php if (have_posts()): ?>
                <div id="mjb-jobs-list">
                    <?php MJB_Shortcodes::render_job_loop($GLOBALS['wp_query']); ?>
                </div>
                <?php the_posts_pagination(); ?>
            <?php else: ?>
                <p><?php _e('No jobs found.', 'modern-job-board'); ?></p>
            <?php endif; ?>
        </main>
    </div>
</div>

<?php get_footer(); ?>