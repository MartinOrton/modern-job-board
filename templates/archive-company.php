<?php
/**
 * The template for displaying the company archive.
 */

get_header();

$companies_query = $GLOBALS['wp_query'];
$company_count = intval($companies_query->found_posts);

MJB_Shortcodes::render_content_hero(
    __('Companies', 'modern-job-board'),
    sprintf(
        /* translators: %d: number of companies */
        _n(
            'Browse %d employer profile and their open roles.',
            'Browse %d employer profiles and their open roles.',
            $company_count,
            'modern-job-board'
        ),
        $company_count
    )
);
?>

<div class="mjb-container mjb-container--company-archive">
    <main class="site-main">
        <?php MJB_Shortcodes::render_company_loop($companies_query); ?>
        <?php
        MJB_Shortcodes::render_archive_pagination($companies_query);
        wp_reset_postdata();
        ?>
    </main>
</div>

<?php
get_footer();