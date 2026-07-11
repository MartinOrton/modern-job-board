<?php
/**
 * Modern Job Board Scheduled XML Import Backfill
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Import_Scheduler
{
    const OPTION_KEY = 'mjb_scheduled_import_feeds';
    const CRON_HOOK = 'mjb_scheduled_import_event';

    /**
     * Initialize scheduler hooks.
     */
    public static function init()
    {
        add_filter('cron_schedules', array(__CLASS__, 'register_schedules'));
        add_action('init', array(__CLASS__, 'schedule_events'));
        add_action(self::CRON_HOOK, array(__CLASS__, 'run_due_feeds'));
    }

    /**
     * Register an hourly cron schedule.
     *
     * @param array $schedules
     * @return array
     */
    public static function register_schedules($schedules)
    {
        $schedules['mjb_hourly'] = array(
            'interval' => HOUR_IN_SECONDS,
            'display' => __('Every Hour', 'modern-job-board'),
        );

        return $schedules;
    }

    /**
     * Ensure the import cron event is scheduled.
     */
    public static function schedule_events()
    {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + 120, 'mjb_hourly', self::CRON_HOOK);
        }
    }

    /**
     * Clear the import cron event.
     */
    public static function clear_events()
    {
        wp_clear_scheduled_hook(self::CRON_HOOK);
    }

    /**
     * Get all scheduled feeds.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function get_feeds()
    {
        $feeds = get_option(self::OPTION_KEY, array());

        return is_array($feeds) ? $feeds : array();
    }

    /**
     * Persist scheduled feeds.
     *
     * @param array $feeds
     */
    public static function save_feeds($feeds)
    {
        update_option(self::OPTION_KEY, array_values($feeds), false);
    }

    /**
     * Find a feed by ID.
     *
     * @param string $feed_id
     * @return array<string, mixed>|null
     */
    public static function get_feed($feed_id)
    {
        $feed_id = sanitize_key($feed_id);
        if ($feed_id === '') {
            return null;
        }

        foreach (self::get_feeds() as $feed) {
            if (($feed['id'] ?? '') === $feed_id) {
                return $feed;
            }
        }

        return null;
    }

    /**
     * Create or update a scheduled feed.
     *
     * @param array $data
     * @return string|WP_Error Feed ID on success.
     */
    public static function save_feed($data)
    {
        $name = isset($data['name']) ? sanitize_text_field($data['name']) : '';
        $url = isset($data['url']) ? esc_url_raw($data['url']) : '';
        $schedule = isset($data['schedule']) ? sanitize_key($data['schedule']) : 'daily';
        $enabled = !empty($data['enabled']);
        $author_id = isset($data['author_id']) ? intval($data['author_id']) : get_current_user_id();
        $feed_id = isset($data['id']) ? sanitize_key($data['id']) : '';

        if ($name === '') {
            return new WP_Error('mjb_schedule_name_required', __('Please provide a feed name.', 'modern-job-board'));
        }

        if ($url === '' || !wp_http_validate_url($url)) {
            return new WP_Error('mjb_schedule_invalid_url', __('Please provide a valid feed URL.', 'modern-job-board'));
        }

        if (!in_array($schedule, array('daily', 'weekly'), true)) {
            $schedule = 'daily';
        }

        if ($author_id < 1) {
            $author_id = 1;
        }

        $feeds = self::get_feeds();
        $existing_index = null;

        if ($feed_id !== '') {
            foreach ($feeds as $index => $feed) {
                if (($feed['id'] ?? '') === $feed_id) {
                    $existing_index = $index;
                    break;
                }
            }
        }

        if ($existing_index === null) {
            if ($feed_id === '') {
                $feed_id = self::generate_feed_id();
            }

            $feeds[] = array(
                'id' => $feed_id,
                'name' => $name,
                'url' => $url,
                'schedule' => $schedule,
                'enabled' => $enabled,
                'author_id' => $author_id,
                'last_run' => '',
                'last_status' => '',
                'last_imported' => 0,
                'last_skipped' => 0,
                'last_error' => '',
            );
        } else {
            $feeds[$existing_index]['name'] = $name;
            $feeds[$existing_index]['url'] = $url;
            $feeds[$existing_index]['schedule'] = $schedule;
            $feeds[$existing_index]['enabled'] = $enabled;
            $feeds[$existing_index]['author_id'] = $author_id;
        }

        self::save_feeds($feeds);

        return $feed_id;
    }

    /**
     * Delete a scheduled feed.
     *
     * @param string $feed_id
     * @return bool
     */
    public static function delete_feed($feed_id)
    {
        $feed_id = sanitize_key($feed_id);
        if ($feed_id === '') {
            return false;
        }

        $feeds = self::get_feeds();
        $updated = array();

        foreach ($feeds as $feed) {
            if (($feed['id'] ?? '') !== $feed_id) {
                $updated[] = $feed;
            }
        }

        if (count($updated) === count($feeds)) {
            return false;
        }

        self::save_feeds($updated);

        return true;
    }

    /**
     * Run all enabled feeds that are due.
     */
    public static function run_due_feeds()
    {
        foreach (self::get_feeds() as $feed) {
            if (empty($feed['enabled'])) {
                continue;
            }

            if (!self::is_feed_due($feed)) {
                continue;
            }

            self::run_feed($feed['id']);
        }
    }

    /**
     * Determine whether a feed should run now.
     *
     * @param array $feed
     * @return bool
     */
    public static function is_feed_due($feed)
    {
        $last_run = isset($feed['last_run']) ? (string) $feed['last_run'] : '';
        if ($last_run === '') {
            return true;
        }

        $last_timestamp = strtotime($last_run);
        if ($last_timestamp === false) {
            return true;
        }

        $interval = ($feed['schedule'] ?? 'daily') === 'weekly' ? WEEK_IN_SECONDS : DAY_IN_SECONDS;

        $now = current_time('timestamp');

        return ($now - $last_timestamp) >= $interval;
    }

    /**
     * Import jobs for a single scheduled feed.
     *
     * @param string $feed_id
     * @param bool   $force
     * @return array{imported:int, skipped:int}|WP_Error
     */
    public static function run_feed($feed_id, $force = false)
    {
        $feed = self::get_feed($feed_id);
        if ($feed === null) {
            return new WP_Error('mjb_schedule_not_found', __('Scheduled feed not found.', 'modern-job-board'));
        }

        if (!$force && empty($feed['enabled'])) {
            return new WP_Error('mjb_schedule_disabled', __('Scheduled feed is disabled.', 'modern-job-board'));
        }

        if (!$force && !self::is_feed_due($feed)) {
            return new WP_Error('mjb_schedule_not_due', __('Scheduled feed is not due yet.', 'modern-job-board'));
        }

        $author_id = intval($feed['author_id'] ?? 1);
        if ($author_id < 1) {
            $author_id = 1;
        }

        $result = MJB_Xml_Importer::import_from_url(
            $feed['url'],
            array('author_id' => $author_id)
        );

        if (is_wp_error($result)) {
            self::update_feed_status($feed_id, array(
                'last_run' => current_time('mysql'),
                'last_status' => 'error',
                'last_error' => $result->get_error_message(),
            ));
            self::notify_failure($feed, $result->get_error_message());
            return $result;
        }

        self::update_feed_status($feed_id, array(
            'last_run' => current_time('mysql'),
            'last_status' => 'success',
            'last_imported' => intval($result['imported']),
            'last_skipped' => intval($result['skipped']),
            'last_error' => '',
        ));

        return $result;
    }

    /**
     * Update stored status fields for a feed.
     *
     * @param string $feed_id
     * @param array  $fields
     */
    public static function update_feed_status($feed_id, $fields)
    {
        $feeds = self::get_feeds();

        foreach ($feeds as $index => $feed) {
            if (($feed['id'] ?? '') !== $feed_id) {
                continue;
            }

            foreach ($fields as $key => $value) {
                $feeds[$index][$key] = $value;
            }

            self::save_feeds($feeds);
            return;
        }
    }

    /**
     * Email the site admin when a scheduled import fails.
     *
     * @param array  $feed
     * @param string $message
     */
    public static function notify_failure($feed, $message)
    {
        $admin_email = get_option('admin_email');
        if (!is_email($admin_email)) {
            return;
        }

        $subject = sprintf(
            __('[%s] Scheduled job import failed', 'modern-job-board'),
            wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES)
        );

        $body = sprintf(
            __("Scheduled feed \"%1\$s\" failed.\n\nURL: %2\$s\nError: %3\$s", 'modern-job-board'),
            $feed['name'] ?? '',
            $feed['url'] ?? '',
            $message
        );

        wp_mail($admin_email, $subject, $body);
    }

    /**
     * Generate a unique feed identifier.
     *
     * @return string
     */
    private static function generate_feed_id()
    {
        return 'feed_' . substr(md5(uniqid((string) wp_rand(), true)), 0, 12);
    }
}