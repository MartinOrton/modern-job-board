<?php
/**
 * Media-rich listings (#19) — gallery + video URL on job posts.
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Media_Listings
{
    /**
     * Bootstrap.
     */
    public static function init()
    {
        add_action('add_meta_boxes', array(__CLASS__, 'meta_box'));
        add_action('save_post_job_listing', array(__CLASS__, 'save'), 20, 2);
        add_action('mjb_single_job_after_content', array(__CLASS__, 'render_media'));
        add_filter('the_content', array(__CLASS__, 'append_to_content'), 12);
    }

    /**
     * Meta box.
     */
    public static function meta_box()
    {
        add_meta_box(
            'mjb_job_media',
            __('Job media', 'modern-job-board'),
            array(__CLASS__, 'render_meta_box'),
            'job_listing',
            'normal',
            'default'
        );
    }

    /**
     * @param WP_Post $post
     */
    public static function render_meta_box($post)
    {
        wp_nonce_field('mjb_job_media', 'mjb_job_media_nonce');
        $gallery = get_post_meta($post->ID, '_mjb_gallery_ids', true);
        $video = get_post_meta($post->ID, '_mjb_video_url', true);
        echo '<p><label for="mjb_gallery_ids">' . esc_html__('Gallery attachment IDs (comma-separated)', 'modern-job-board') . '</label><br>';
        echo '<input type="text" class="large-text" name="mjb_gallery_ids" id="mjb_gallery_ids" value="' . esc_attr($gallery) . '"></p>';
        echo '<p><label for="mjb_video_url">' . esc_html__('Video URL (YouTube/Vimeo)', 'modern-job-board') . '</label><br>';
        echo '<input type="url" class="large-text" name="mjb_video_url" id="mjb_video_url" value="' . esc_attr($video) . '"></p>';
    }

    /**
     * @param int     $post_id
     * @param WP_Post $post
     */
    public static function save($post_id, $post)
    {
        if (!isset($_POST['mjb_job_media_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['mjb_job_media_nonce'])), 'mjb_job_media')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        if (isset($_POST['mjb_gallery_ids'])) {
            $raw = sanitize_text_field(wp_unslash($_POST['mjb_gallery_ids']));
            $ids = array_filter(array_map('absint', preg_split('/[\s,]+/', $raw)));
            update_post_meta($post_id, '_mjb_gallery_ids', implode(',', $ids));
        }
        if (isset($_POST['mjb_video_url'])) {
            update_post_meta($post_id, '_mjb_video_url', esc_url_raw(wp_unslash($_POST['mjb_video_url'])));
        }
    }

    /**
     * Render media block.
     *
     * @param int $job_id
     */
    public static function render_media($job_id = 0)
    {
        $job_id = $job_id ?: get_the_ID();
        $gallery = get_post_meta($job_id, '_mjb_gallery_ids', true);
        $video = get_post_meta($job_id, '_mjb_video_url', true);
        if (!$gallery && !$video) {
            return;
        }
        echo '<div class="mjb-job-media">';
        if ($video) {
            $embed = wp_oembed_get(esc_url_raw($video));
            if ($embed) {
                // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_oembed_get returns trusted embed HTML.
                echo '<div class="mjb-job-media__video">' . $embed . '</div>';
            }
        }
        if ($gallery) {
            $ids = array_filter(array_map('absint', explode(',', $gallery)));
            if ($ids) {
                echo '<div class="mjb-job-media__gallery">';
                foreach ($ids as $id) {
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attachment image markup from core.
                    echo wp_get_attachment_image($id, 'medium');
                }
                echo '</div>';
            }
        }
        echo '</div>';
    }

    /**
     * Append on single job when theme does not fire custom action.
     *
     * @param string $content
     * @return string
     */
    public static function append_to_content($content)
    {
        if (!is_singular('job_listing') || !in_the_loop() || !is_main_query()) {
            return $content;
        }
        ob_start();
        self::render_media(get_the_ID());
        return $content . (string) ob_get_clean();
    }
}
