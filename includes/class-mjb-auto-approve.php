<?php
/**
 * Employer auto-approve rules (#20).
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Auto_Approve
{
    const OPTION = 'mjb_auto_approve_trusted_employers';

    /**
     * Bootstrap.
     */
    public static function init()
    {
        add_filter('wp_insert_post_data', array(__CLASS__, 'maybe_auto_publish'), 20, 2);
        add_action('transition_post_status', array(__CLASS__, 'remember_approved_employer'), 10, 3);
    }

    /**
     * When option enabled: if employer already has a published job, new jobs auto-publish.
     *
     * @param array $data
     * @param array $postarr
     * @return array
     */
    public static function maybe_auto_publish($data, $postarr)
    {
        if (!isset($data['post_type']) || $data['post_type'] !== 'job_listing') {
            return $data;
        }
        if (!self::is_enabled()) {
            return $data;
        }
        // Only real submissions. Never promote WP "Add New" placeholders (auto-draft / "Auto Draft").
        if (!in_array($data['post_status'], array('pending', 'draft'), true)) {
            return $data;
        }
        $title = isset($data['post_title']) ? trim(wp_strip_all_tags((string) $data['post_title'])) : '';
        if ($title === '' || strcasecmp($title, 'Auto Draft') === 0) {
            return $data;
        }

        $author = isset($data['post_author']) ? (int) $data['post_author'] : get_current_user_id();
        if ($author <= 0) {
            return $data;
        }

        if (self::employer_is_trusted($author)) {
            $data['post_status'] = 'publish';
        }

        return $data;
    }

    /**
     * @return bool
     */
    public static function is_enabled()
    {
        return get_option(self::OPTION, '1') === '1';
    }

    /**
     * Trusted = has at least one published listing (or admin).
     *
     * @param int $user_id
     * @return bool
     */
    public static function employer_is_trusted($user_id)
    {
        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            return false;
        }
        if (user_can($user_id, 'manage_options')) {
            return true;
        }
        $flag = get_user_meta($user_id, '_mjb_trusted_employer', true);
        if ($flag === '1') {
            return true;
        }
        $published = get_posts(array(
            'post_type' => 'job_listing',
            'post_status' => 'publish',
            'author' => $user_id,
            'posts_per_page' => 1,
            'fields' => 'ids',
        ));
        return !empty($published);
    }

    /**
     * Mark employer trusted after first approved publish.
     *
     * @param string  $new
     * @param string  $old
     * @param WP_Post $post
     */
    public static function remember_approved_employer($new, $old, $post)
    {
        if (!$post || $post->post_type !== 'job_listing') {
            return;
        }
        if ($new === 'publish' && $old !== 'publish') {
            update_user_meta((int) $post->post_author, '_mjb_trusted_employer', '1');
        }
    }
}
