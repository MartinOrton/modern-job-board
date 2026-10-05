<?php
/**
 * The template for displaying Single Job
 */

get_header();

while (have_posts()) {
    the_post();
    $hero_intro = MJB_Shortcodes::get_job_posted_by_html(get_the_ID());
    MJB_Shortcodes::render_content_hero(get_the_title(), $hero_intro);
}
rewind_posts();
?>

<div class="mjb-container mjb-container--listing mjb-container--single">
    <div class="mjb-content-area">
        <aside class="mjb-sidebar">
            <?php
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in render_job_search_form().
            echo MJB_Shortcodes::render_job_search_form(null, true);
            ?>
        </aside>
        <main class="site-main mjb-single-main">
            <?php while (have_posts()) :
                the_post();
                $job_id = get_the_ID();
                $method = get_post_meta($job_id, '_application_method', true);
                $app_url = get_post_meta($job_id, '_application_url', true);
                $whatsapp = get_post_meta($job_id, '_application_whatsapp', true);
                $is_external = ($method === 'external' && !empty($app_url));
                $is_whatsapp = ($method === 'whatsapp' && !empty($whatsapp));
                $is_filled = class_exists('MJB_Job_Ops') && MJB_Job_Ops::is_filled($job_id);
                $is_new = class_exists('MJB_Job_Ops') && MJB_Job_Ops::is_new($job_id);
                $is_saved = is_user_logged_in() && class_exists('MJB_Saved_Jobs')
                    ? MJB_Saved_Jobs::is_saved(get_current_user_id(), $job_id)
                    : false;
                $save_url = class_exists('MJB_Saved_Jobs')
                    ? (is_user_logged_in()
                        ? MJB_Saved_Jobs::get_toggle_url($job_id)
                        : MJB_Saved_Jobs::get_login_url_for_save($job_id))
                    : '#';
                if ($is_whatsapp && class_exists('MJB_Job_Ops')) {
                    $apply_url = MJB_Job_Ops::whatsapp_url(
                        $whatsapp,
                        sprintf(
                            /* translators: %s: job title */
                            __('Hi, I am interested in the %s role.', 'modern-job-board'),
                            get_the_title($job_id)
                        )
                    );
                } elseif ($is_external) {
                    $apply_url = $app_url;
                } else {
                    $apply_url = class_exists('MJB_Applications')
                        ? MJB_Applications::get_apply_url($job_id)
                        : '#';
                }
                $share_url = get_permalink($job_id);
                $share_title = get_the_title($job_id);
                $share_text = rawurlencode($share_title);
                $share_enc = rawurlencode($share_url);
                ?>
                <article id="post-<?php the_ID(); ?>" <?php post_class('mjb-single-job'); ?>>
                    <header class="entry-header">
                        <div class="mjb-job-card__badges mjb-job-card__badges--single">
                            <?php if ($is_new) : ?>
                                <span class="mjb-badge mjb-badge--new"><?php esc_html_e('New', 'modern-job-board'); ?></span>
                            <?php endif; ?>
                            <?php if ($is_filled) : ?>
                                <span class="mjb-badge mjb-badge--filled"><?php esc_html_e('Filled', 'modern-job-board'); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="mjb-job-meta mjb-job-meta--single">
                            <?php
                            MJB_Shortcodes::render_meta_pill('clock', get_the_term_list(get_the_ID(), 'job_type', '', ', '));
                            MJB_Shortcodes::render_meta_pill('map-pin', MJB_Location::render_job_location_term_list(get_the_ID()));
                            MJB_Shortcodes::render_meta_pill('tag', get_the_term_list(get_the_ID(), 'job_category', '', ', '));
                            echo MJB_Shortcodes::get_job_date_pills_html(get_the_ID()); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in helper.
                            ?>
                        </div>
                    </header>

                    <div class="entry-content">
                        <?php the_content(); ?>
                    </div>

                    <div class="mjb-application-area">
                        <?php
                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Notices::render().
                        echo MJB_Notices::render();
                        ?>

                        <div class="mjb-job-actions" role="group" aria-label="<?php esc_attr_e('Job actions', 'modern-job-board'); ?>">
                            <?php if ($is_filled) : ?>
                                <span class="btn btn-outline mjb-job-action mjb-job-action--apply is-disabled" aria-disabled="true">
                                    <span><?php esc_html_e('Position filled', 'modern-job-board'); ?></span>
                                </span>
                            <?php elseif ($is_external || $is_whatsapp) : ?>
                                <a href="<?php echo esc_url($apply_url); ?>"
                                   class="btn btn-primary mjb-job-action mjb-job-action--apply"
                                   target="_blank"
                                   rel="noopener noreferrer"><?php
                                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                                    echo MJB_Icons::render('file-pen', 16);
                                    ?><span><?php echo $is_whatsapp ? esc_html__('Apply on WhatsApp', 'modern-job-board') : esc_html__('Apply', 'modern-job-board'); ?></span></a>
                            <?php else : ?>
                                <a href="<?php echo esc_url($apply_url); ?>"
                                   class="btn btn-primary mjb-job-action mjb-job-action--apply"
                                   <?php if (is_user_logged_in()) : ?>data-mjb-inline="apply"<?php endif; ?>><?php
                                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                                    echo MJB_Icons::render('file-pen', 16);
                                    ?><span><?php esc_html_e('Apply', 'modern-job-board'); ?></span></a>
                            <?php endif; ?>

                            <a href="<?php echo esc_url($save_url); ?>"
                               class="btn btn-outline mjb-job-action mjb-job-action--save<?php echo $is_saved ? ' is-active' : ''; ?>"
                               <?php if (is_user_logged_in()) : ?>data-mjb-inline="save"<?php endif; ?>><?php
                                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                                echo MJB_Icons::render('bookmark', 16);
                                ?><span><?php echo $is_saved ? esc_html__('Saved', 'modern-job-board') : esc_html__('Save', 'modern-job-board'); ?></span></a>

                            <div class="mjb-job-action mjb-job-action--share" data-mjb-share>
                                <button type="button"
                                        class="btn btn-outline mjb-job-action__share-btn"
                                        data-mjb-share-toggle
                                        aria-expanded="false"
                                        aria-haspopup="true"
                                        aria-controls="mjb-share-menu-<?php echo esc_attr((string) $job_id); ?>"><?php
                                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
                                    echo MJB_Icons::render('share-2', 16);
                                    ?><span><?php esc_html_e('Share', 'modern-job-board'); ?></span></button>
                                <div id="mjb-share-menu-<?php echo esc_attr((string) $job_id); ?>"
                                     class="mjb-share-menu mjb-share-menu--icons"
                                     data-mjb-share-menu
                                     hidden
                                     role="menu">
                                    <a class="mjb-share-menu__icon"
                                       role="menuitem"
                                       href="<?php echo esc_url('https://www.linkedin.com/sharing/share-offsite/?url=' . $share_enc); ?>"
                                       target="_blank"
                                       rel="noopener noreferrer"
                                       title="<?php esc_attr_e('Share on LinkedIn', 'modern-job-board'); ?>"
                                       aria-label="<?php esc_attr_e('Share on LinkedIn', 'modern-job-board'); ?>"><?php
                                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                        echo MJB_Icons::render('linkedin', 18);
                                    ?></a>
                                    <a class="mjb-share-menu__icon"
                                       role="menuitem"
                                       href="<?php echo esc_url('https://twitter.com/intent/tweet?url=' . $share_enc . '&text=' . $share_text); ?>"
                                       target="_blank"
                                       rel="noopener noreferrer"
                                       title="<?php esc_attr_e('Share on X', 'modern-job-board'); ?>"
                                       aria-label="<?php esc_attr_e('Share on X', 'modern-job-board'); ?>"><?php
                                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                        echo MJB_Icons::render('twitter', 18);
                                    ?></a>
                                    <a class="mjb-share-menu__icon"
                                       role="menuitem"
                                       href="<?php echo esc_url('https://www.facebook.com/sharer/sharer.php?u=' . $share_enc); ?>"
                                       target="_blank"
                                       rel="noopener noreferrer"
                                       title="<?php esc_attr_e('Share on Facebook', 'modern-job-board'); ?>"
                                       aria-label="<?php esc_attr_e('Share on Facebook', 'modern-job-board'); ?>"><?php
                                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                        echo MJB_Icons::render('facebook', 18);
                                    ?></a>
                                    <a class="mjb-share-menu__icon"
                                       role="menuitem"
                                       href="<?php echo esc_url('mailto:?subject=' . rawurlencode($share_title) . '&body=' . $share_enc); ?>"
                                       title="<?php esc_attr_e('Share by email', 'modern-job-board'); ?>"
                                       aria-label="<?php esc_attr_e('Share by email', 'modern-job-board'); ?>"><?php
                                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                        echo MJB_Icons::render('mail', 18);
                                    ?></a>
                                    <button type="button"
                                            class="mjb-share-menu__icon"
                                            role="menuitem"
                                            data-mjb-share-copy
                                            data-url="<?php echo esc_url($share_url); ?>"
                                            data-copied-label="<?php esc_attr_e('Copied', 'modern-job-board'); ?>"
                                            title="<?php esc_attr_e('Copy link', 'modern-job-board'); ?>"
                                            aria-label="<?php esc_attr_e('Copy link', 'modern-job-board'); ?>"><?php
                                        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                                        echo MJB_Icons::render('link', 18);
                                    ?></button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php
                    do_action('mjb_single_job_after_content', $job_id);
                    ?>
                </article>
            <?php endwhile; ?>
        </main>
    </div>
</div>

<?php get_footer(); ?>
