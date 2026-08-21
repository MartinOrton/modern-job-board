<?php
/**
 * External job ingestion: Broadbean-style webhook + empty-list backfill (WPJB Tier B3–B4).
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Ingestion
{
    const OPTION_WEBHOOK_SECRET = 'mjb_ingestion_webhook_secret';
    const OPTION_BACKFILL_ENABLED = 'mjb_backfill_when_empty';
    const OPTION_BACKFILL_MESSAGE = 'mjb_backfill_message';
    const OPTION_BACKFILL_URL = 'mjb_backfill_partner_url';
    const META_SOURCE = '_mjb_ingestion_source';
    const META_EXTERNAL = '_mjb_ingestion_external_id';

    /**
     * Bootstrap.
     */
    public static function init()
    {
        add_action('admin_init', array(__CLASS__, 'register_settings'));
        add_action('rest_api_init', array(__CLASS__, 'register_rest'));
        add_filter('mjb_job_list_empty_html', array(__CLASS__, 'filter_empty_list'), 10, 2);
        add_action('admin_menu', array(__CLASS__, 'admin_menu'), 60);
    }

    /**
     * Settings.
     */
    public static function register_settings()
    {
        register_setting('mjb_settings_group', self::OPTION_WEBHOOK_SECRET, array(
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_text_field',
        ));
        register_setting('mjb_settings_group', self::OPTION_BACKFILL_ENABLED, array(
            'type' => 'string',
            'default' => '0',
            'sanitize_callback' => function ($v) {
                return ($v === '1' || $v === 1 || $v === 'on') ? '1' : '0';
            },
        ));
        register_setting('mjb_settings_group', self::OPTION_BACKFILL_MESSAGE, array(
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'sanitize_textarea_field',
        ));
        register_setting('mjb_settings_group', self::OPTION_BACKFILL_URL, array(
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'esc_url_raw',
        ));

        add_settings_section(
            'mjb_ingestion_section',
            __('Job ingestion & backfill', 'modern-job-board'),
            function () {
                echo '<p>' . esc_html__('REST webhook for agency ATS feeds (Broadbean-style JSON) and empty-list density messaging.', 'modern-job-board') . '</p>';
            },
            'mjb-settings'
        );

        add_settings_field(
            self::OPTION_WEBHOOK_SECRET,
            __('Ingestion webhook secret', 'modern-job-board'),
            function () {
                $v = get_option(self::OPTION_WEBHOOK_SECRET, '');
                echo '<input type="text" class="regular-text" name="' . esc_attr(self::OPTION_WEBHOOK_SECRET) . '" value="' . esc_attr($v) . '" autocomplete="off">';
                $url = rest_url('mjb/v1/ingest/job');
                echo '<p class="description">' . esc_html__('POST JSON to', 'modern-job-board') . ' <code>' . esc_html($url) . '</code> ';
                echo esc_html__('with header X-MJB-Ingest-Secret or body.secret.', 'modern-job-board') . '</p>';
            },
            'mjb-settings',
            'mjb_ingestion_section'
        );
        add_settings_field(
            self::OPTION_BACKFILL_ENABLED,
            __('Empty list backfill', 'modern-job-board'),
            function () {
                echo '<label><input type="checkbox" name="' . esc_attr(self::OPTION_BACKFILL_ENABLED) . '" value="1" ' . checked(get_option(self::OPTION_BACKFILL_ENABLED, '0'), '1', false) . '> ';
                echo esc_html__('When no jobs match a search, show partner/backfill CTA (ZipRecruiter-style density)', 'modern-job-board') . '</label>';
            },
            'mjb-settings',
            'mjb_ingestion_section'
        );
        add_settings_field(
            self::OPTION_BACKFILL_MESSAGE,
            __('Backfill message', 'modern-job-board'),
            function () {
                $v = get_option(self::OPTION_BACKFILL_MESSAGE, '');
                if ($v === '') {
                    $v = __('No local matches yet. Browse partner listings or try a broader search.', 'modern-job-board');
                }
                echo '<textarea name="' . esc_attr(self::OPTION_BACKFILL_MESSAGE) . '" class="large-text" rows="2">' . esc_textarea($v) . '</textarea>';
            },
            'mjb-settings',
            'mjb_ingestion_section'
        );
        add_settings_field(
            self::OPTION_BACKFILL_URL,
            __('Backfill partner URL', 'modern-job-board'),
            function () {
                $v = get_option(self::OPTION_BACKFILL_URL, '');
                echo '<input type="url" class="large-text" name="' . esc_attr(self::OPTION_BACKFILL_URL) . '" value="' . esc_attr($v) . '" placeholder="https://">';
                echo '<p class="description">' . esc_html__('Optional external jobs URL shown as a CTA on empty results.', 'modern-job-board') . '</p>';
            },
            'mjb-settings',
            'mjb_ingestion_section'
        );
    }

    /**
     * REST route for job ingest.
     */
    public static function register_rest()
    {
        register_rest_route('mjb/v1', '/ingest/job', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'rest_ingest_job'),
            'permission_callback' => array(__CLASS__, 'rest_permission'),
        ));
        register_rest_route('mjb/v1', '/ingest/batch', array(
            'methods' => 'POST',
            'callback' => array(__CLASS__, 'rest_ingest_batch'),
            'permission_callback' => array(__CLASS__, 'rest_permission'),
        ));
    }

    /**
     * @param WP_REST_Request $request
     * @return bool|WP_Error
     */
    public static function rest_permission($request)
    {
        $secret = (string) get_option(self::OPTION_WEBHOOK_SECRET, '');
        if ($secret === '') {
            return new WP_Error('mjb_ingest_disabled', __('Ingestion webhook secret is not configured.', 'modern-job-board'), array('status' => 403));
        }
        $provided = $request->get_header('x-mjb-ingest-secret');
        if (!$provided) {
            $provided = $request->get_param('secret');
        }
        if (!is_string($provided) || !hash_equals($secret, $provided)) {
            return new WP_Error('mjb_ingest_auth', __('Invalid ingestion secret.', 'modern-job-board'), array('status' => 401));
        }
        return true;
    }

    /**
     * Normalize a single job payload (Indeed/Broadbean-ish keys).
     *
     * @param array $raw
     * @return array|WP_Error
     */
    public static function normalize_job_payload(array $raw)
    {
        $title = '';
        foreach (array('title', 'job_title', 'jobTitle', 'position') as $k) {
            if (!empty($raw[$k])) {
                $title = sanitize_text_field($raw[$k]);
                break;
            }
        }
        if ($title === '') {
            return new WP_Error('mjb_ingest_title', __('Job title is required.', 'modern-job-board'));
        }

        $description = '';
        foreach (array('description', 'body', 'job_description', 'jobDescription') as $k) {
            if (!empty($raw[$k])) {
                $description = wp_kses_post($raw[$k]);
                break;
            }
        }

        $company = '';
        foreach (array('company', 'company_name', 'companyName', 'advertiser') as $k) {
            if (!empty($raw[$k])) {
                $company = sanitize_text_field($raw[$k]);
                break;
            }
        }

        $location = '';
        foreach (array('location', 'city', 'job_location') as $k) {
            if (!empty($raw[$k])) {
                $location = sanitize_text_field($raw[$k]);
                break;
            }
        }

        $external_id = '';
        foreach (array('external_id', 'id', 'reference', 'job_reference', 'requisition_id') as $k) {
            if (!empty($raw[$k])) {
                $external_id = sanitize_text_field((string) $raw[$k]);
                break;
            }
        }

        $apply_url = '';
        foreach (array('apply_url', 'application_url', 'url', 'link') as $k) {
            if (!empty($raw[$k])) {
                $apply_url = esc_url_raw($raw[$k]);
                break;
            }
        }

        $source = isset($raw['source']) ? sanitize_key($raw['source']) : 'webhook';
        $status = isset($raw['status']) ? sanitize_key($raw['status']) : 'publish';
        if (!in_array($status, array('publish', 'draft', 'pending'), true)) {
            $status = 'publish';
        }

        return array(
            'title' => $title,
            'description' => $description,
            'company' => $company,
            'location' => $location,
            'external_id' => $external_id,
            'apply_url' => $apply_url,
            'source' => $source,
            'status' => $status,
            'job_type' => isset($raw['job_type']) ? sanitize_text_field($raw['job_type']) : (isset($raw['employmentType']) ? sanitize_text_field($raw['employmentType']) : ''),
            'featured' => !empty($raw['featured']),
        );
    }

    /**
     * Create or update a job from normalized data.
     *
     * @param array $data
     * @return array{id:int,created:bool}|WP_Error
     */
    public static function upsert_job(array $data)
    {
        $existing_id = 0;
        if (!empty($data['external_id'])) {
            $found = get_posts(array(
                'post_type' => 'job_listing',
                'post_status' => array('publish', 'draft', 'pending', 'private', 'expired'),
                'posts_per_page' => 1,
                'fields' => 'ids',
                'meta_query' => array(
                    array(
                        'key' => self::META_EXTERNAL,
                        'value' => $data['external_id'],
                    ),
                ),
            ));
            if (!empty($found)) {
                $existing_id = (int) $found[0];
            } elseif (class_exists('MJB_Job_Importer')) {
                $found2 = get_posts(array(
                    'post_type' => 'job_listing',
                    'post_status' => array('publish', 'draft', 'pending', 'private', 'expired'),
                    'posts_per_page' => 1,
                    'fields' => 'ids',
                    'meta_query' => array(
                        array(
                            'key' => MJB_Job_Importer::EXTERNAL_ID_META,
                            'value' => $data['external_id'],
                        ),
                    ),
                ));
                if (!empty($found2)) {
                    $existing_id = (int) $found2[0];
                }
            }
        }

        $postarr = array(
            'post_type' => 'job_listing',
            'post_title' => $data['title'],
            'post_content' => $data['description'],
            'post_status' => $data['status'],
        );

        if ($existing_id > 0) {
            $postarr['ID'] = $existing_id;
            $result = wp_update_post($postarr, true);
            $created = false;
        } else {
            $result = wp_insert_post($postarr, true);
            $created = true;
        }

        if (is_wp_error($result)) {
            return $result;
        }

        $job_id = (int) $result;
        if (!empty($data['company'])) {
            update_post_meta($job_id, '_company_name', $data['company']);
            if (class_exists('MJB_Job_Importer') && method_exists('MJB_Job_Importer', 'find_company_by_name')) {
                $cid = MJB_Job_Importer::find_company_by_name($data['company']);
                if ($cid) {
                    update_post_meta($job_id, '_company_id', $cid);
                }
            }
        }
        if (!empty($data['apply_url'])) {
            update_post_meta($job_id, '_application_url', $data['apply_url']);
            update_post_meta($job_id, '_application_method', 'url');
        }
        if (!empty($data['external_id'])) {
            update_post_meta($job_id, self::META_EXTERNAL, $data['external_id']);
            if (class_exists('MJB_Job_Importer')) {
                update_post_meta($job_id, MJB_Job_Importer::EXTERNAL_ID_META, $data['external_id']);
            }
        }
        update_post_meta($job_id, self::META_SOURCE, $data['source']);
        update_post_meta($job_id, '_featured', !empty($data['featured']) ? '1' : '0');

        if (!empty($data['location']) && taxonomy_exists('job_location')) {
            wp_set_object_terms($job_id, $data['location'], 'job_location', false);
        }
        if (!empty($data['job_type']) && taxonomy_exists('job_type')) {
            wp_set_object_terms($job_id, $data['job_type'], 'job_type', false);
        }

        /**
         * After a job is ingested via webhook.
         *
         * @param int   $job_id
         * @param array $data
         * @param bool  $created
         */
        do_action('mjb_job_ingested', $job_id, $data, $created);

        return array('id' => $job_id, 'created' => $created);
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public static function rest_ingest_job($request)
    {
        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = $request->get_params();
        }
        if (isset($params['job']) && is_array($params['job'])) {
            $params = $params['job'];
        }
        $normalized = self::normalize_job_payload(is_array($params) ? $params : array());
        if (is_wp_error($normalized)) {
            return $normalized;
        }
        $result = self::upsert_job($normalized);
        if (is_wp_error($result)) {
            return $result;
        }
        return rest_ensure_response(array(
            'success' => true,
            'id' => $result['id'],
            'created' => $result['created'],
        ));
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public static function rest_ingest_batch($request)
    {
        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = $request->get_params();
        }
        $jobs = array();
        if (isset($params['jobs']) && is_array($params['jobs'])) {
            $jobs = $params['jobs'];
        } elseif (isset($params[0]) && is_array($params[0])) {
            $jobs = $params;
        }
        if (empty($jobs)) {
            return new WP_Error('mjb_ingest_batch', __('Provide a jobs array.', 'modern-job-board'), array('status' => 400));
        }

        $results = array();
        $i = 0;
        foreach ($jobs as $job) {
            if ($i >= 50) {
                break;
            }
            if (!is_array($job)) {
                continue;
            }
            $normalized = self::normalize_job_payload($job);
            if (is_wp_error($normalized)) {
                $results[] = array('error' => $normalized->get_error_message());
                continue;
            }
            $up = self::upsert_job($normalized);
            if (is_wp_error($up)) {
                $results[] = array('error' => $up->get_error_message());
            } else {
                $results[] = $up;
            }
            $i++;
        }

        return rest_ensure_response(array('success' => true, 'results' => $results));
    }

    /**
     * ZipRecruiter-style empty list augmentation.
     *
     * @param string   $html
     * @param WP_Query $jobs
     * @return string
     */
    public static function filter_empty_list($html, $jobs = null)
    {
        unset($jobs);
        if (get_option(self::OPTION_BACKFILL_ENABLED, '0') !== '1') {
            return $html;
        }

        $msg = get_option(self::OPTION_BACKFILL_MESSAGE, '');
        if ($msg === '') {
            $msg = __('No local matches yet. Browse partner listings or try a broader search.', 'modern-job-board');
        }
        $url = get_option(self::OPTION_BACKFILL_URL, '');

        $out = '<div class="mjb-backfill mjb-job-list-empty">';
        $out .= '<p class="mjb-backfill__msg">' . esc_html($msg) . '</p>';
        if ($url) {
            $out .= '<p class="mjb-backfill__cta"><a class="btn btn-primary" href="' . esc_url($url) . '" rel="noopener noreferrer" target="_blank">';
            $out .= esc_html__('View more jobs', 'modern-job-board');
            $out .= '</a></p>';
        }

        // Surface a few recent partner-imported jobs if any.
        $partner = get_posts(array(
            'post_type' => 'job_listing',
            'post_status' => 'publish',
            'posts_per_page' => 5,
            'fields' => 'ids',
            'meta_query' => array(
                array(
                    'key' => self::META_SOURCE,
                    'compare' => 'EXISTS',
                ),
            ),
        ));
        if (!empty($partner)) {
            $out .= '<ul class="mjb-backfill__list">';
            foreach ($partner as $pid) {
                $out .= '<li><a href="' . esc_url(get_permalink($pid)) . '">' . esc_html(get_the_title($pid)) . '</a></li>';
            }
            $out .= '</ul>';
        }

        $out .= '</div>';

        /**
         * Filter empty-list backfill HTML.
         *
         * @param string $out
         * @param string $html Original empty HTML.
         */
        return apply_filters('mjb_backfill_empty_html', $out, $html);
    }

    /**
     * Tools submenu deep-link.
     */
    public static function admin_menu()
    {
        add_submenu_page(
            'edit.php?post_type=job_listing',
            __('Ingestion', 'modern-job-board'),
            __('Ingestion', 'modern-job-board'),
            'manage_options',
            'mjb-ingestion',
            array(__CLASS__, 'render_admin')
        );
    }

    /**
     * Admin help page.
     */
    public static function render_admin()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        $secret = get_option(self::OPTION_WEBHOOK_SECRET, '');
        $url = rest_url('mjb/v1/ingest/job');
        echo '<div class="wrap"><h1>' . esc_html__('Job ingestion', 'modern-job-board') . '</h1>';
        echo '<p>' . esc_html__('Agency / ATS feeds can POST jobs to the REST webhook. Partner XML feeds continue under Partner feeds. Empty-list backfill is configured in Settings.', 'modern-job-board') . '</p>';
        echo '<h2>' . esc_html__('Webhook', 'modern-job-board') . '</h2>';
        echo '<p><code>' . esc_html($url) . '</code></p>';
        echo '<p>' . esc_html__('Secret configured:', 'modern-job-board') . ' <strong>' . ($secret !== '' ? esc_html__('yes', 'modern-job-board') : esc_html__('no', 'modern-job-board')) . '</strong></p>';
        $example = implode("\n", array(
            'POST /wp-json/mjb/v1/ingest/job',
            'X-MJB-Ingest-Secret: your-secret',
            'Content-Type: application/json',
            '',
            '{',
            '  "title": "Senior Engineer",',
            '  "company": "Acme",',
            '  "location": "Cape Town",',
            '  "description": "Job body",',
            '  "external_id": "ATS-123",',
            '  "apply_url": "https://example.com/apply",',
            '  "source": "broadbean"',
            '}',
        ));
        echo '<pre class="mjb-admin-pre">';
        echo esc_html($example);
        echo '</pre>';
        echo '<p><a class="button" href="' . esc_url(admin_url('edit.php?post_type=job_listing&page=mjb-partner-feeds')) . '">' . esc_html__('Partner XML feeds', 'modern-job-board') . '</a> ';
        echo '<a class="button" href="' . esc_url(admin_url('edit.php?post_type=job_listing&page=mjb-settings')) . '">' . esc_html__('Settings', 'modern-job-board') . '</a></p>';
        echo '</div>';
    }
}
