<?php
/**
 * Modern Job Board Webhook Retry Queue
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Webhook_Queue
{
    const OPTION_KEY = 'mjb_webhook_queue';
    const CRON_HOOK = 'mjb_webhook_queue_event';
    const MAX_RETRIES = 5;
    const MAX_QUEUE_SIZE = 100;

    /**
     * Initialize queue processing hooks.
     */
    public static function init()
    {
        add_filter('cron_schedules', array(__CLASS__, 'register_schedules'));
        add_action('init', array(__CLASS__, 'schedule_events'));
        add_action(self::CRON_HOOK, array(__CLASS__, 'process_queue'));
    }

    /**
     * Register a five-minute cron schedule.
     *
     * @param array $schedules
     * @return array
     */
    public static function register_schedules($schedules)
    {
        $schedules['mjb_five_minutes'] = array(
            'interval' => 300,
            'display' => __('Every Five Minutes', 'modern-job-board'),
        );

        return $schedules;
    }

    /**
     * Ensure the queue cron event is scheduled.
     */
    public static function schedule_events()
    {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + 60, 'mjb_five_minutes', self::CRON_HOOK);
        }
    }

    /**
     * Attempt a blocking webhook delivery.
     *
     * @param string $event
     * @param string $url
     * @param string $body
     * @param array  $headers
     * @param int    $attempt
     * @return bool
     */
    public static function deliver($event, $url, $body, $headers, $attempt = 0)
    {
        if (self::attempt_delivery($url, $body, $headers)) {
            do_action('mjb_webhook_delivered', $event, $url, $attempt);
            return true;
        }

        self::enqueue($event, $url, $body, $headers, $attempt + 1);
        return false;
    }

    /**
     * Perform a blocking webhook HTTP request.
     *
     * @param string $url
     * @param string $body
     * @param array  $headers
     * @return bool
     */
    public static function attempt_delivery($url, $body, $headers)
    {
        $response = wp_remote_post($url, array(
            'timeout' => 8,
            'blocking' => true,
            'headers' => $headers,
            'body' => $body,
        ));

        return self::is_successful_response($response);
    }

    /**
     * Whether a remote response is considered successful.
     *
     * @param array|WP_Error $response
     * @return bool
     */
    public static function is_successful_response($response)
    {
        if (is_wp_error($response)) {
            return false;
        }

        $code = intval(wp_remote_retrieve_response_code($response));
        return $code >= 200 && $code < 300;
    }

    /**
     * Queue a failed delivery for retry.
     *
     * @param string $event
     * @param string $url
     * @param string $body
     * @param array  $headers
     * @param int    $attempt
     */
    public static function enqueue($event, $url, $body, $headers, $attempt = 1)
    {
        if ($attempt > self::MAX_RETRIES) {
            do_action('mjb_webhook_delivery_failed', $event, $url, $attempt);
            return;
        }

        $queue = self::get_queue();
        $queue[] = array(
            'event' => sanitize_key($event),
            'url' => esc_url_raw($url),
            'body' => $body,
            'headers' => $headers,
            'attempt' => intval($attempt),
            'next_retry' => time() + self::get_backoff_seconds($attempt),
        );

        if (count($queue) > self::MAX_QUEUE_SIZE) {
            $queue = array_slice($queue, -1 * self::MAX_QUEUE_SIZE);
        }

        update_option(self::OPTION_KEY, $queue, false);
    }

    /**
     * Process due webhook retries.
     */
    public static function process_queue()
    {
        $queue = self::get_queue();
        if (empty($queue)) {
            return;
        }

        $remaining = array();
        $now = time();

        foreach ($queue as $item) {
            if (empty($item['url']) || empty($item['body'])) {
                continue;
            }

            if (intval($item['next_retry']) > $now) {
                $remaining[] = $item;
                continue;
            }

            $event = isset($item['event']) ? $item['event'] : '';
            $headers = is_array($item['headers']) ? $item['headers'] : array();
            $attempt = intval($item['attempt']);

            if (self::attempt_delivery($item['url'], $item['body'], $headers)) {
                do_action('mjb_webhook_delivered', $event, $item['url'], $attempt);
                continue;
            }

            $next_attempt = $attempt + 1;
            if ($next_attempt > self::MAX_RETRIES) {
                do_action('mjb_webhook_delivery_failed', $event, $item['url'], $next_attempt);
                continue;
            }

            $item['attempt'] = $next_attempt;
            $item['next_retry'] = time() + self::get_backoff_seconds($next_attempt);
            $remaining[] = $item;
        }

        update_option(self::OPTION_KEY, array_values($remaining), false);
    }

    /**
     * Return the current queue items.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function get_queue()
    {
        $queue = get_option(self::OPTION_KEY, array());
        return is_array($queue) ? $queue : array();
    }

    /**
     * Count items waiting for retry.
     *
     * @return int
     */
    public static function get_pending_count()
    {
        return count(self::get_queue());
    }

    /**
     * Exponential backoff in seconds.
     *
     * @param int $attempt
     * @return int
     */
    public static function get_backoff_seconds($attempt)
    {
        $steps = array(60, 300, 900, 3600, 21600);
        $index = max(0, min(intval($attempt) - 1, count($steps) - 1));

        return $steps[$index];
    }
}