<?php
/**
 * WPJB Tier C polish: completeness UI, filter chips, multi-file apply,
 * XML backup, social auto-post, path tokens, health check, who-can-post.
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Board_Polish
{
    const OPTION_WHO_CAN_POST = 'mjb_who_can_post';
    const OPTION_SOCIAL_X = 'mjb_social_post_x';
    const OPTION_SOCIAL_LI = 'mjb_social_post_linkedin';
    const OPTION_SOCIAL_FB = 'mjb_social_post_facebook';
    const OPTION_SOCIAL_WEBHOOK = 'mjb_social_post_webhook';
    const META_EXTRA_FILES = '_application_extra_files';
    const MAX_EXTRA_FILES = 3;

    /**
     * Bootstrap.
     */
    public static function init()
    {
        add_action('admin_init', array(__CLASS__, 'register_settings'));
        add_action('admin_menu', array(__CLASS__, 'admin_menu'), 64);
        add_action('mjb_after_job_search_form', array(__CLASS__, 'render_filter_chips'), 5, 1);
        add_action('mjb_candidate_dashboard_before_profile', array(__CLASS__, 'render_completeness_bar'), 10, 1);
        add_action('transition_post_status', array(__CLASS__, 'maybe_social_post'), 20, 3);
        add_action('admin_init', array(__CLASS__, 'handle_xml_backup_export'));
        add_filter('mjb_can_post_job', array(__CLASS__, 'filter_can_post_job'), 10, 2);
        add_action('mjb_application_form_fields', array(__CLASS__, 'render_extra_file_fields'), 15);
        add_action('mjb_application_submitted', array(__CLASS__, 'on_application_submitted'), 15, 1);
    }

    /**
     * @param int $application_id
     */
    public static function on_application_submitted($application_id)
    {
        self::save_extra_files((int) $application_id, array());
    }

    /**
     * Settings: who can post + social toggles.
     */
    public static function register_settings()
    {
        register_setting('mjb_settings_group', self::OPTION_WHO_CAN_POST, array(
            'type' => 'string',
            'default' => 'employer',
            'sanitize_callback' => function ($v) {
                $v = sanitize_key($v);
                return in_array($v, array('anyone', 'employer', 'admin'), true) ? $v : 'employer';
            },
        ));
        register_setting('mjb_settings_group', self::OPTION_SOCIAL_X, array(
            'type' => 'string',
            'default' => '0',
            'sanitize_callback' => array(__CLASS__, 'sanitize_bool_option'),
        ));
        register_setting('mjb_settings_group', self::OPTION_SOCIAL_LI, array(
            'type' => 'string',
            'default' => '0',
            'sanitize_callback' => array(__CLASS__, 'sanitize_bool_option'),
        ));
        register_setting('mjb_settings_group', self::OPTION_SOCIAL_FB, array(
            'type' => 'string',
            'default' => '0',
            'sanitize_callback' => array(__CLASS__, 'sanitize_bool_option'),
        ));
        register_setting('mjb_settings_group', self::OPTION_SOCIAL_WEBHOOK, array(
            'type' => 'string',
            'default' => '',
            'sanitize_callback' => 'esc_url_raw',
        ));

        add_settings_section(
            'mjb_posting_section',
            __('Job posting rules', 'modern-job-board'),
            function () {
                echo '<p>' . esc_html__('Control who may submit jobs from the front end.', 'modern-job-board') . '</p>';
            },
            'mjb-settings'
        );
        add_settings_field(
            self::OPTION_WHO_CAN_POST,
            __('Who can post jobs', 'modern-job-board'),
            function () {
                $v = get_option(self::OPTION_WHO_CAN_POST, 'employer');
                echo '<select name="' . esc_attr(self::OPTION_WHO_CAN_POST) . '">';
                foreach (array(
                    'anyone' => __('Anyone (logged-in users)', 'modern-job-board'),
                    'employer' => __('Recruiters only', 'modern-job-board'),
                    'admin' => __('Administrators only', 'modern-job-board'),
                ) as $key => $label) {
                    echo '<option value="' . esc_attr($key) . '" ' . selected($v, $key, false) . '>' . esc_html($label) . '</option>';
                }
                echo '</select>';
            },
            'mjb-settings',
            'mjb_posting_section'
        );

        add_settings_section(
            'mjb_social_section',
            __('Social auto-post', 'modern-job-board'),
            function () {
                echo '<p>' . esc_html__('When a job is published, open share intents (or POST a webhook). Full API posting needs third-party apps; this ships practical auto-share hooks.', 'modern-job-board') . '</p>';
            },
            'mjb-settings'
        );
        add_settings_field(
            self::OPTION_SOCIAL_WEBHOOK,
            __('Social webhook URL', 'modern-job-board'),
            function () {
                echo '<input type="url" class="large-text" name="' . esc_attr(self::OPTION_SOCIAL_WEBHOOK) . '" value="' . esc_attr(get_option(self::OPTION_SOCIAL_WEBHOOK, '')) . '" placeholder="https://">';
                echo '<p class="description">' . esc_html__('Optional: POST JSON {title,url,company,id} on publish (Zapier/Make/n8n).', 'modern-job-board') . '</p>';
            },
            'mjb-settings',
            'mjb_social_section'
        );
        foreach (array(
            self::OPTION_SOCIAL_X => __('Log X (Twitter) share payload', 'modern-job-board'),
            self::OPTION_SOCIAL_LI => __('Log LinkedIn share payload', 'modern-job-board'),
            self::OPTION_SOCIAL_FB => __('Log Facebook share payload', 'modern-job-board'),
        ) as $opt => $label) {
            add_settings_field(
                $opt,
                $label,
                function () use ($opt) {
                    echo '<label><input type="checkbox" name="' . esc_attr($opt) . '" value="1" ' . checked(get_option($opt, '0'), '1', false) . '> ';
                    echo esc_html__('Enable (fires mjb_social_share action + stores last payload for operators)', 'modern-job-board') . '</label>';
                },
                'mjb-settings',
                'mjb_social_section'
            );
        }
    }

    /**
     * @param mixed $v
     * @return string
     */
    public static function sanitize_bool_option($v)
    {
        return ($v === '1' || $v === 1 || $v === 'on' || $v === true) ? '1' : '0';
    }

    /**
     * C10: who can post filter.
     *
     * @param bool $can
     * @param int  $user_id
     * @return bool
     */
    public static function filter_can_post_job($can, $user_id = 0)
    {
        $user_id = $user_id ? (int) $user_id : get_current_user_id();
        $mode = get_option(self::OPTION_WHO_CAN_POST, 'employer');
        if ($mode === 'admin') {
            return user_can($user_id, 'manage_options');
        }
        if ($mode === 'anyone') {
            return $user_id > 0;
        }
        // employer (default)
        if (user_can($user_id, 'manage_options')) {
            return true;
        }
        $user = get_userdata($user_id);
        return $user && in_array('employer', (array) $user->roles, true);
    }

    /**
     * @param int $user_id
     * @return bool
     */
    public static function user_can_post_job($user_id = 0)
    {
        $user_id = $user_id ? (int) $user_id : get_current_user_id();
        return (bool) apply_filters('mjb_can_post_job', false, $user_id);
    }

    /**
     * C4: Active filter chips under search form.
     *
     * @param array $filter_params
     */
    public static function render_filter_chips($filter_params = array())
    {
        if (!is_array($filter_params)) {
            $filter_params = class_exists('MJB_Search')
                ? MJB_Search::get_request_filter_params()
                : array();
        }

        $chips = array();
        $map = array(
            'search_keywords' => __('Keywords', 'modern-job-board'),
            'search_location' => __('Location', 'modern-job-board'),
            'search_category' => __('Category', 'modern-job-board'),
            'search_type' => __('Type', 'modern-job-board'),
            'search_company' => __('Company', 'modern-job-board'),
        );

        foreach ($map as $key => $label) {
            if (empty($filter_params[$key])) {
                continue;
            }
            $value = (string) $filter_params[$key];
            $display = $value;
            if ($key === 'search_location' && class_exists('MJB_Search')) {
                $display = MJB_Search::get_term_label('job_location', $value) ?: $value;
            } elseif ($key === 'search_category' && class_exists('MJB_Search')) {
                $display = MJB_Search::get_term_label('job_category', $value) ?: $value;
            } elseif ($key === 'search_type' && class_exists('MJB_Search')) {
                $display = MJB_Search::get_term_label('job_type', $value) ?: $value;
            } elseif ($key === 'search_company' && class_exists('MJB_Search')) {
                $display = MJB_Search::get_company_label($value) ?: $value;
            }
            $remove_params = $filter_params;
            $remove_params[$key] = '';
            $url = class_exists('MJB_Job_Routes')
                ? MJB_Job_Routes::build_url(array_filter($remove_params))
                : remove_query_arg($key);
            $chips[] = array(
                'label' => $label,
                'value' => $display,
                'url' => $url,
            );
        }

        if (empty($chips)) {
            return;
        }

        echo '<div class="mjb-filter-chips" role="list" aria-label="' . esc_attr__('Active filters', 'modern-job-board') . '">';
        foreach ($chips as $chip) {
            echo '<a class="mjb-filter-chip" role="listitem" href="' . esc_url($chip['url']) . '">';
            echo '<span class="mjb-filter-chip__label">' . esc_html($chip['label']) . ':</span> ';
            echo '<span class="mjb-filter-chip__value">' . esc_html($chip['value']) . '</span> ';
            echo '<span class="mjb-filter-chip__remove" aria-hidden="true">×</span>';
            echo '<span class="screen-reader-text">' . esc_html(sprintf(__('Remove %s filter', 'modern-job-board'), $chip['label'])) . '</span>';
            echo '</a>';
        }
        if (class_exists('MJB_Job_Routes')) {
            echo '<a class="mjb-filter-chip mjb-filter-chip--clear" href="' . esc_url(MJB_Job_Routes::build_url()) . '">' . esc_html__('Clear all', 'modern-job-board') . '</a>';
        }
        echo '</div>';
    }

    /**
     * C3: Completeness progress bar.
     *
     * @param int $user_id
     */
    public static function render_completeness_bar($user_id = 0)
    {
        if (!class_exists('MJB_Applications')) {
            return;
        }
        $data = MJB_Applications::profile_completeness($user_id);
        $pct = isset($data['percent']) ? (int) $data['percent'] : 0;
        $missing = isset($data['missing']) ? (array) $data['missing'] : array();
        $labels = array(
            'account' => __('Account', 'modern-job-board'),
            'first_name' => __('First name', 'modern-job-board'),
            'last_name' => __('Last name', 'modern-job-board'),
            'phone' => __('Phone', 'modern-job-board'),
            'resume' => __('Resume', 'modern-job-board'),
        );

        echo '<div class="mjb-completeness" data-percent="' . esc_attr((string) $pct) . '">';
        echo '<div class="mjb-completeness__header">';
        echo '<strong>' . esc_html__('Profile completeness', 'modern-job-board') . '</strong> ';
        echo '<span class="mjb-completeness__pct">' . esc_html((string) $pct) . '%</span>';
        echo '</div>';
        echo '<div class="mjb-completeness__track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="' . esc_attr((string) $pct) . '">';
        echo '<div class="mjb-completeness__bar" style="width:' . esc_attr((string) $pct) . '%"></div>';
        echo '</div>';
        if (!empty($missing)) {
            $names = array();
            foreach ($missing as $m) {
                $names[] = isset($labels[$m]) ? $labels[$m] : $m;
            }
            echo '<p class="mjb-completeness__missing description">' . esc_html(
                sprintf(
                    /* translators: %s: comma-separated field names */
                    __('Still needed: %s', 'modern-job-board'),
                    implode(', ', $names)
                )
            ) . '</p>';
        } else {
            echo '<p class="mjb-completeness__done description">' . esc_html__('Your profile is complete for one-click apply.', 'modern-job-board') . '</p>';
        }
        echo '</div>';
    }

    /**
     * C5: Extra attachment inputs on application form.
     */
    public static function render_extra_file_fields()
    {
        echo '<p class="mjb-app-extra-files">';
        echo '<label for="mjb_application_files">' . esc_html__('Additional files (optional)', 'modern-job-board') . '</label><br>';
        echo '<input type="file" name="mjb_application_files[]" id="mjb_application_files" multiple accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document">';
        echo '<span class="description">' . esc_html(sprintf(
            /* translators: %d: max files */
            __('Up to %d extra PDF/DOC files (cover letter, certificates).', 'modern-job-board'),
            self::MAX_EXTRA_FILES
        )) . '</span>';
        echo '</p>';
    }

    /**
     * C5: Persist multi-file uploads after application create.
     *
     * @param int   $application_id
     * @param array $context
     */
    public static function save_extra_files($application_id, $context = array())
    {
        unset($context);
        $application_id = (int) $application_id;
        if ($application_id <= 0 || empty($_FILES['mjb_application_files'])) {
            return;
        }

        $files = self::normalize_files_array($_FILES['mjb_application_files']);
        $saved = array();
        $i = 0;
        foreach ($files as $file) {
            if ($i >= self::MAX_EXTRA_FILES) {
                break;
            }
            if (empty($file['name']) || (int) $file['error'] !== UPLOAD_ERR_OK) {
                continue;
            }
            if (!class_exists('MJB_Private_Uploads')) {
                break;
            }
            $uploaded = MJB_Private_Uploads::upload($file, MJB_Private_Uploads::TYPE_RESUME);
            if (is_wp_error($uploaded)) {
                continue;
            }
            $saved[] = array(
                'path' => !empty($uploaded['relative']) ? $uploaded['relative'] : $uploaded['file'],
                'name' => isset($file['name']) ? sanitize_file_name($file['name']) : '',
            );
            $i++;
        }
        if (!empty($saved)) {
            update_post_meta($application_id, self::META_EXTRA_FILES, $saved);
        }
    }

    /**
     * @param array $file_post $_FILES['field'] multi structure
     * @return array<int, array>
     */
    public static function normalize_files_array(array $file_post)
    {
        $out = array();
        if (!isset($file_post['name']) || !is_array($file_post['name'])) {
            // Single file shape.
            if (!empty($file_post['name'])) {
                $out[] = $file_post;
            }
            return $out;
        }
        foreach ($file_post['name'] as $i => $name) {
            $out[] = array(
                'name' => $name,
                'type' => isset($file_post['type'][$i]) ? $file_post['type'][$i] : '',
                'tmp_name' => isset($file_post['tmp_name'][$i]) ? $file_post['tmp_name'][$i] : '',
                'error' => isset($file_post['error'][$i]) ? $file_post['error'][$i] : UPLOAD_ERR_NO_FILE,
                'size' => isset($file_post['size'][$i]) ? $file_post['size'][$i] : 0,
            );
        }
        return $out;
    }

    /**
     * C7: Social share on publish.
     *
     * @param string  $new_status
     * @param string  $old_status
     * @param WP_Post $post
     */
    public static function maybe_social_post($new_status, $old_status, $post)
    {
        if (!$post || $post->post_type !== 'job_listing') {
            return;
        }
        if ($new_status !== 'publish' || $old_status === 'publish') {
            return;
        }

        $payload = array(
            'id' => (int) $post->ID,
            'title' => get_the_title($post),
            'url' => get_permalink($post),
            'company' => (string) get_post_meta($post->ID, '_company_name', true),
        );

        $webhook = get_option(self::OPTION_SOCIAL_WEBHOOK, '');
        if ($webhook) {
            wp_remote_post($webhook, array(
                'timeout' => 8,
                'blocking' => false,
                'headers' => array('Content-Type' => 'application/json'),
                'body' => wp_json_encode($payload),
            ));
        }

        $networks = array();
        if (get_option(self::OPTION_SOCIAL_X, '0') === '1') {
            $networks['x'] = 'https://twitter.com/intent/tweet?text=' . rawurlencode($payload['title'] . ' ' . $payload['url']);
        }
        if (get_option(self::OPTION_SOCIAL_LI, '0') === '1') {
            $networks['linkedin'] = 'https://www.linkedin.com/sharing/share-offsite/?url=' . rawurlencode($payload['url']);
        }
        if (get_option(self::OPTION_SOCIAL_FB, '0') === '1') {
            $networks['facebook'] = 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode($payload['url']);
        }

        if (!empty($networks)) {
            $payload['share_urls'] = $networks;
            update_post_meta($post->ID, '_mjb_social_share_urls', $networks);
            update_option('mjb_last_social_payload', $payload, false);
        }

        /**
         * @param array   $payload
         * @param WP_Post $post
         */
        do_action('mjb_social_share', $payload, $post);
    }

    /**
     * C6: Full board XML backup export handler.
     */
    public static function handle_xml_backup_export()
    {
        if (!isset($_POST['mjb_action']) || $_POST['mjb_action'] !== 'export_board_xml') {
            return;
        }
        if (!current_user_can('manage_options')) {
            return;
        }
        check_admin_referer('mjb_export_board_xml_nonce');

        $xml = self::build_board_xml_backup();
        $filename = 'mjb-board-backup-' . gmdate('Y-m-d-His') . '.xml';
        nocache_headers();
        header('Content-Type: application/xml; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- raw XML download
        echo $xml;
        exit;
    }

    /**
     * Build multi-entity XML backup.
     *
     * @param int $limit_per_type
     * @return string
     */
    public static function build_board_xml_backup($limit_per_type = 500)
    {
        $limit = max(1, min(2000, (int) $limit_per_type));
        $types = array(
            'job_listing' => 'jobs',
            'company' => 'companies',
            'job_application' => 'applications',
            'mjb_resume' => 'resumes',
        );

        $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $out .= '<mjbBoardBackup version="' . esc_attr(defined('MJB_VERSION') ? MJB_VERSION : '0') . '" generated="' . esc_attr(gmdate('c')) . '">' . "\n";

        foreach ($types as $post_type => $tag) {
            $out .= '  <' . $tag . '>' . "\n";
            $ids = get_posts(array(
                'post_type' => $post_type,
                'post_status' => array('publish', 'pending', 'draft', 'private', 'expired'),
                'posts_per_page' => $limit,
                'fields' => 'ids',
                'orderby' => 'ID',
                'order' => 'ASC',
            ));
            foreach ((array) $ids as $id) {
                $post = get_post($id);
                if (!$post) {
                    continue;
                }
                $out .= '    <item id="' . (int) $id . '">' . "\n";
                $out .= '      <title><![CDATA[' . $post->post_title . ']]></title>' . "\n";
                $out .= '      <status>' . esc_html($post->post_status) . '</status>' . "\n";
                $out .= '      <content><![CDATA[' . $post->post_content . ']]></content>' . "\n";
                $out .= '      <author>' . (int) $post->post_author . '</author>' . "\n";
                $out .= '      <date>' . esc_html($post->post_date_gmt) . '</date>' . "\n";
                $meta = get_post_meta($id);
                if (is_array($meta)) {
                    $out .= '      <meta>' . "\n";
                    foreach ($meta as $key => $values) {
                        if (strpos($key, '_') !== 0 && strpos($key, 'mjb') === false) {
                            // Prefer MJB/private meta; still export underscore meta for jobs.
                        }
                        if (!is_array($values)) {
                            continue;
                        }
                        foreach ($values as $val) {
                            // Skip huge binary-ish values.
                            if (is_string($val) && strlen($val) > 50000) {
                                continue;
                            }
                            $out .= '        <' . self::xml_tag_name($key) . '><![CDATA[' . (string) $val . ']]></' . self::xml_tag_name($key) . '>' . "\n";
                        }
                    }
                    $out .= '      </meta>' . "\n";
                }
                $out .= '    </item>' . "\n";
            }
            $out .= '  </' . $tag . '>' . "\n";
        }

        // Taxonomies snapshot.
        $out .= '  <taxonomies>' . "\n";
        foreach (array('job_category', 'job_type', 'job_location') as $tax) {
            if (!taxonomy_exists($tax)) {
                continue;
            }
            $terms = get_terms(array('taxonomy' => $tax, 'hide_empty' => false));
            if (is_wp_error($terms) || empty($terms)) {
                continue;
            }
            $out .= '    <taxonomy name="' . esc_attr($tax) . '">' . "\n";
            foreach ($terms as $term) {
                $out .= '      <term slug="' . esc_attr($term->slug) . '" id="' . (int) $term->term_id . '"><![CDATA[' . $term->name . ']]></term>' . "\n";
            }
            $out .= '    </taxonomy>' . "\n";
        }
        $out .= '  </taxonomies>' . "\n";
        $out .= '</mjbBoardBackup>';

        return $out;
    }

    /**
     * @param string $key
     * @return string
     */
    public static function xml_tag_name($key)
    {
        $tag = preg_replace('/[^a-zA-Z0-9_\-]/', '_', (string) $key);
        if ($tag === '' || preg_match('/^[0-9]/', $tag)) {
            $tag = 'm_' . $tag;
        }
        return $tag;
    }

    /**
     * C9: Health check admin.
     */
    public static function admin_menu()
    {
        add_submenu_page(
            'edit.php?post_type=job_listing',
            __('Health Check', 'modern-job-board'),
            __('Health Check', 'modern-job-board'),
            'manage_options',
            'mjb-health-check',
            array(__CLASS__, 'render_health_check')
        );
    }

    /**
     * Known cron hooks for the board.
     *
     * @return array<string, string>
     */
    public static function cron_hooks()
    {
        return array(
            'mjb_daily_cron_event' => __('Daily board (expiry, partner feeds)', 'modern-job-board'),
            'mjb_job_alerts_cron' => __('Job alert digests', 'modern-job-board'),
            'mjb_publish_scheduled_jobs' => __('Scheduled job go-live', 'modern-job-board'),
            'mjb_webhook_queue_cron' => __('Webhook queue', 'modern-job-board'),
        );
    }

    /**
     * Health status rows.
     *
     * @return array<int, array{hook:string,label:string,next:int|false,ok:bool}>
     */
    public static function health_rows()
    {
        $rows = array();
        foreach (self::cron_hooks() as $hook => $label) {
            $next = wp_next_scheduled($hook);
            $rows[] = array(
                'hook' => $hook,
                'label' => $label,
                'next' => $next,
                'ok' => (bool) $next,
            );
        }
        return $rows;
    }

    /**
     * Health Check UI.
     */
    public static function render_health_check()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        echo '<div class="wrap"><h1>' . esc_html__('Board Health Check', 'modern-job-board') . '</h1>';
        echo '<p>' . esc_html__('Verify cron schedules used by Modern Job Board. Missing events usually re-register on the next front-end or admin page load.', 'modern-job-board') . '</p>';
        echo '<table class="widefat striped"><thead><tr>';
        echo '<th>' . esc_html__('Event', 'modern-job-board') . '</th>';
        echo '<th>' . esc_html__('Hook', 'modern-job-board') . '</th>';
        echo '<th>' . esc_html__('Next run', 'modern-job-board') . '</th>';
        echo '<th>' . esc_html__('Status', 'modern-job-board') . '</th>';
        echo '</tr></thead><tbody>';
        foreach (self::health_rows() as $row) {
            $next_label = $row['next']
                ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), (int) $row['next'])
                : '—';
            $status = $row['ok']
                ? '<span style="color:#00a32a">' . esc_html__('Scheduled', 'modern-job-board') . '</span>'
                : '<span style="color:#d63638">' . esc_html__('Not scheduled', 'modern-job-board') . '</span>';
            echo '<tr>';
            echo '<td>' . esc_html($row['label']) . '</td>';
            echo '<td><code>' . esc_html($row['hook']) . '</code></td>';
            echo '<td>' . esc_html($next_label) . '</td>';
            echo '<td>' . wp_kses_post($status) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';

        echo '<h2 class="mjb-section-title">' . esc_html__('Environment', 'modern-job-board') . '</h2><ul class="ul-disc">';
        echo '<li>' . esc_html__('Plugin version:', 'modern-job-board') . ' <code>' . esc_html(defined('MJB_VERSION') ? MJB_VERSION : '?') . '</code></li>';
        echo '<li>' . esc_html__('WooCommerce:', 'modern-job-board') . ' ' . (class_exists('WooCommerce') ? esc_html__('active', 'modern-job-board') : esc_html__('not active', 'modern-job-board')) . '</li>';
        echo '<li>' . esc_html__('WP Cron disabled:', 'modern-job-board') . ' ' . (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON ? esc_html__('yes', 'modern-job-board') : esc_html__('no', 'modern-job-board')) . '</li>';
        if (class_exists('MJB_Private_Uploads')) {
            echo '<li>' . esc_html__('Private storage:', 'modern-job-board') . ' <code>mjb-private</code> / <code>mjb-brand</code></li>';
        }
        echo '</ul></div>';
    }
}
