<?php
/**
 * Modern Job Board Outbound Webhooks
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Webhooks
{
    /**
     * Initialize webhook listeners.
     */
    public static function init()
    {
        add_action('mjb_application_submitted', array(__CLASS__, 'on_application_submitted'), 20, 1);
        add_action('mjb_application_status_updated', array(__CLASS__, 'on_application_status_updated'), 20, 3);
        add_action('mjb_job_submitted', array(__CLASS__, 'on_job_submitted'), 20, 1);
    }

    /**
     * Configured webhook endpoint URLs.
     *
     * @return array<int, string>
     */
    public static function get_urls()
    {
        $raw = (string) get_option('mjb_webhook_urls', '');
        $urls = preg_split('/\r\n|\r|\n/', $raw) ?: array();

        return array_values(array_filter(array_map('esc_url_raw', array_map('trim', $urls))));
    }

    /**
     * Dispatch a webhook payload to configured endpoints.
     *
     * @param string $event
     * @param array  $payload
     */
    public static function dispatch($event, $payload)
    {
        $urls = self::get_urls();
        if (empty($urls)) {
            return;
        }

        $event = sanitize_key($event);
        $body = wp_json_encode(array(
            'event' => $event,
            'timestamp' => current_time('timestamp'),
            'site' => home_url('/'),
            'data' => $payload,
        ));

        if (!$body) {
            return;
        }

        $headers = array(
            'Content-Type' => 'application/json; charset=utf-8',
            'User-Agent' => 'Modern-Job-Board/' . MJB_VERSION,
        );

        $secret = (string) get_option('mjb_webhook_secret', '');
        if ($secret !== '') {
            $headers['X-MJB-Signature'] = hash_hmac('sha256', $body, $secret);
        }

        $body = apply_filters('mjb_webhook_payload', $body, $event, $payload);

        foreach ($urls as $url) {
            do_action('mjb_before_webhook_dispatch', $event, $url, $payload);

            wp_remote_post($url, array(
                'timeout' => 5,
                'blocking' => false,
                'headers' => $headers,
                'body' => $body,
            ));
        }
    }

    /**
     * Send webhook for a new application.
     *
     * @param int $application_id
     */
    public static function on_application_submitted($application_id)
    {
        self::dispatch('application.submitted', MJB_REST_API_V2::format_application_for_api($application_id));
    }

    /**
     * Send webhook for an application status change.
     *
     * @param int    $application_id
     * @param string $old_status
     * @param string $new_status
     */
    public static function on_application_status_updated($application_id, $old_status, $new_status)
    {
        $payload = MJB_REST_API_V2::format_application_for_api($application_id);
        $payload['previous_status'] = $old_status;
        $payload['status'] = $new_status;
        $payload['status_label'] = MJB_Application_Status::get_label($new_status);

        self::dispatch('application.status_updated', $payload);
    }

    /**
     * Send webhook for a submitted job.
     *
     * @param int $job_id
     */
    public static function on_job_submitted($job_id)
    {
        self::dispatch('job.submitted', MJB_REST_API::format_job_for_api($job_id));
    }
}