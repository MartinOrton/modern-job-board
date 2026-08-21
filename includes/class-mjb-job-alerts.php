<?php
/**
 * Job seeker email alerts (#8).
 *
 * Candidates save a search (keywords/location/category/type) and receive
 * daily or weekly digests of matching new jobs.
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Job_Alerts
{
    const CPT = 'mjb_job_alert';
    const CRON_HOOK = 'mjb_job_alerts_cron';

    /**
     * Bootstrap.
     */
    public static function init()
    {
        add_action('init', array(__CLASS__, 'register_cpt'));
        add_action('init', array(__CLASS__, 'schedule_cron'));
        add_action(self::CRON_HOOK, array(__CLASS__, 'run_digests'));
        add_action('wp_ajax_mjb_save_job_alert', array(__CLASS__, 'ajax_save_alert'));
        add_action('wp_ajax_mjb_delete_job_alert', array(__CLASS__, 'ajax_delete_alert'));
        add_shortcode('mjb_job_alerts', array(__CLASS__, 'render_manage_shortcode'));
        add_action('mjb_after_job_search_form', array(__CLASS__, 'render_subscribe_to_search'), 10, 1);
        add_action('wp_footer', array(__CLASS__, 'print_subscribe_script'), 30);
    }

    /**
     * Job-alert CTA under the jobs filter form.
     *
     * UX: title + plain-language benefit → what matches → how often → primary action.
     *
     * @param array|null $filter_params
     */
    public static function render_subscribe_to_search($filter_params = null)
    {
        $params = is_array($filter_params) ? $filter_params : (class_exists('MJB_Search') ? MJB_Search::get_request_filter_params() : array());
        $summary = self::describe_filters($params);

        echo '<aside class="mjb-alert-subscribe" data-mjb-alert-subscribe aria-labelledby="mjb-alert-subscribe-title">';
        echo '<div class="mjb-alert-subscribe__head">';
        if (class_exists('MJB_Icons')) {
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped in MJB_Icons::render().
            echo '<span class="mjb-alert-subscribe__icon" aria-hidden="true">' . MJB_Icons::render('mail', 18) . '</span>';
        }
        echo '<div class="mjb-alert-subscribe__copy">';
        echo '<h4 id="mjb-alert-subscribe-title" class="mjb-alert-subscribe__title">' . esc_html__('Get email alerts', 'modern-job-board') . '</h4>';
        echo '<p class="mjb-alert-subscribe__hint">' . esc_html__('We will email you when new jobs match your filters.', 'modern-job-board') . '</p>';
        echo '</div>';
        echo '</div>';

        echo '<p class="mjb-alert-subscribe__match">';
        echo '<span class="mjb-alert-subscribe__match-label">' . esc_html__('Matching', 'modern-job-board') . '</span> ';
        echo '<span class="mjb-alert-subscribe__match-value">' . esc_html($summary) . '</span>';
        echo '</p>';

        if (!is_user_logged_in()) {
            // Frontend candidate login (not wp-login.php) so guests stay in the board UI.
            $redirect = (is_singular() || is_page()) ? get_permalink() : home_url(add_query_arg(array()));
            if (!$redirect) {
                $redirect = home_url('/jobs/');
            }
            if (class_exists('MJB_Login') && method_exists('MJB_Login', 'get_frontend_login_url')) {
                $login = MJB_Login::get_frontend_login_url('candidate', $redirect);
            } elseif (class_exists('MJB_Page_Resolver')) {
                $login = MJB_Page_Resolver::get_page_url(
                    'mjb_candidate_login',
                    'mjb_candidate_login_page_id',
                    array('redirect_to' => $redirect),
                    '/jobs/candidate-login/'
                );
            } else {
                $login = home_url('/jobs/candidate-login/');
                $login = add_query_arg('redirect_to', $redirect, $login);
            }
            echo '<a class="btn btn-outline btn-sm mjb-alert-subscribe__btn mjb-alert-subscribe__btn--login" href="' . esc_url($login) . '">';
            echo esc_html__('Sign in to create an alert', 'modern-job-board');
            echo '</a>';
            echo '</aside>';
            return;
        }

        $nonce = wp_create_nonce('mjb_search_nonce');
        $freq_id = 'mjb-alert-freq-' . (function_exists('wp_unique_id') ? wp_unique_id() : uniqid());
        $daily_label = __('Daily', 'modern-job-board');
        $weekly_label = __('Weekly', 'modern-job-board');

        echo '<div class="mjb-alert-subscribe__controls">';
        echo '<div class="mjb-alert-subscribe__freq-field">';
        echo '<label class="mjb-alert-subscribe__freq-label" id="' . esc_attr($freq_id) . '-label">' . esc_html__('Email me', 'modern-job-board') . '</label>';
        // Frequency reuses Filter Jobs autocomplete chrome (.mjb-ac / .mjb-ac__input / .mjb-ac__menu).
        echo '<div class="mjb-ac mjb-alert-subscribe__ac" data-mjb-freq-ac>';
        echo '<input type="hidden" class="mjb-ac__value" data-mjb-alert-freq value="daily">';
        echo '<button type="button" class="mjb-ac__input mjb-alert-subscribe__trigger" data-mjb-freq-toggle';
        echo ' aria-haspopup="listbox" aria-expanded="false" aria-controls="' . esc_attr($freq_id) . '"';
        echo ' aria-labelledby="' . esc_attr($freq_id) . '-label">';
        echo '<span data-mjb-freq-label>' . esc_html($daily_label) . '</span>';
        echo '</button>';
        echo '<div id="' . esc_attr($freq_id) . '" class="mjb-ac__menu" data-mjb-freq-menu hidden role="listbox">';
        echo '<button type="button" class="mjb-ac__item is-active" role="option" data-value="daily" data-label="' . esc_attr($daily_label) . '" aria-selected="true">' . esc_html($daily_label) . '</button>';
        echo '<button type="button" class="mjb-ac__item" role="option" data-value="weekly" data-label="' . esc_attr($weekly_label) . '" aria-selected="false">' . esc_html($weekly_label) . '</button>';
        echo '</div>';
        echo '</div>';
        echo '</div>';

        echo '<button type="button" class="btn btn-primary btn-sm mjb-alert-subscribe__btn" data-mjb-alert-save';
        foreach (array('search_keywords', 'search_location', 'search_category', 'search_type', 'search_company') as $key) {
            $val = isset($params[$key]) ? $params[$key] : '';
            echo ' data-' . esc_attr(str_replace('_', '-', $key)) . '="' . esc_attr($val) . '"';
        }
        echo ' data-security="' . esc_attr($nonce) . '">';
        echo esc_html__('Create alert', 'modern-job-board');
        echo '</button>';
        echo '</div>';

        echo '<p class="mjb-alert-subscribe__msg" data-mjb-alert-msg hidden role="status" aria-live="polite"></p>';
        echo '</aside>';
    }

    /**
     * Human-readable summary of the current filter set for the alert CTA.
     *
     * @param array $params
     * @return string
     */
    public static function describe_filters(array $params)
    {
        $parts = array();

        if (!empty($params['search_keywords'])) {
            $parts[] = sanitize_text_field($params['search_keywords']);
        }

        if (class_exists('MJB_Search')) {
            if (!empty($params['search_location'])) {
                $label = MJB_Search::get_term_label('job_location', $params['search_location']);
                if ($label !== '') {
                    $parts[] = $label;
                }
            }
            if (!empty($params['search_category'])) {
                $label = MJB_Search::get_term_label('job_category', $params['search_category']);
                if ($label !== '') {
                    $parts[] = $label;
                }
            }
            if (!empty($params['search_type'])) {
                $label = MJB_Search::get_term_label('job_type', $params['search_type']);
                if ($label !== '') {
                    $parts[] = $label;
                }
            }
            if (!empty($params['search_company'])) {
                $label = MJB_Search::get_company_label($params['search_company']);
                if ($label !== '') {
                    $parts[] = $label;
                }
            }
        }

        if (empty($parts)) {
            return __('All new jobs', 'modern-job-board');
        }

        return implode(' · ', $parts);
    }

    /**
     * Minimal JS for subscribe-to-search (no separate asset required).
     */
    public static function print_subscribe_script()
    {
        if (!is_user_logged_in()) {
            return;
        }
        ?>
        <script>
        (function () {
            function closeAllFreq(except) {
                document.querySelectorAll('[data-mjb-freq-ac].is-open').forEach(function (ac) {
                    if (except && ac === except) return;
                    ac.classList.remove('is-open');
                    var menu = ac.querySelector('[data-mjb-freq-menu]');
                    var toggle = ac.querySelector('[data-mjb-freq-toggle]');
                    if (menu) menu.hidden = true;
                    if (toggle) toggle.setAttribute('aria-expanded', 'false');
                });
            }

            document.addEventListener('click', function (e) {
                var toggle = e.target.closest('[data-mjb-freq-toggle]');
                if (toggle) {
                    e.preventDefault();
                    e.stopPropagation();
                    var ac = toggle.closest('[data-mjb-freq-ac]');
                    if (!ac) return;
                    var menu = ac.querySelector('[data-mjb-freq-menu]');
                    var open = !ac.classList.contains('is-open');
                    closeAllFreq(open ? ac : null);
                    ac.classList.toggle('is-open', open);
                    if (menu) menu.hidden = !open;
                    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                    return;
                }

                var item = e.target.closest('[data-mjb-freq-menu] .mjb-ac__item');
                if (item) {
                    e.preventDefault();
                    e.stopPropagation();
                    var ac = item.closest('[data-mjb-freq-ac]');
                    if (!ac) return;
                    var valueInput = ac.querySelector('[data-mjb-alert-freq]');
                    var labelEl = ac.querySelector('[data-mjb-freq-label]');
                    var val = item.getAttribute('data-value') || 'daily';
                    var label = item.getAttribute('data-label') || item.textContent || val;
                    if (valueInput) valueInput.value = val;
                    if (labelEl) labelEl.textContent = label;
                    ac.querySelectorAll('.mjb-ac__item').forEach(function (el) {
                        var on = el === item;
                        el.classList.toggle('is-active', on);
                        el.setAttribute('aria-selected', on ? 'true' : 'false');
                    });
                    closeAllFreq();
                    return;
                }

                if (!e.target.closest('[data-mjb-freq-ac]')) {
                    closeAllFreq();
                }

                var btn = e.target.closest('[data-mjb-alert-save]');
                if (!btn) return;
                e.preventDefault();
                var wrap = btn.closest('[data-mjb-alert-subscribe]');
                var freq = wrap ? wrap.querySelector('[data-mjb-alert-freq]') : null;
                var msg = wrap ? wrap.querySelector('[data-mjb-alert-msg]') : null;
                var body = new FormData();
                body.append('action', 'mjb_save_job_alert');
                body.append('security', btn.getAttribute('data-security') || '');
                body.append('frequency', freq ? (freq.value || 'daily') : 'daily');
                ['search-keywords','search-location','search-category','search-type','search-company'].forEach(function (k) {
                    var v = btn.getAttribute('data-' + k) || '';
                    body.append(k.replace(/-/g, '_'), v);
                });
                var defaultLabel = btn.getAttribute('data-default-label') || btn.textContent || 'Create alert';
                btn.setAttribute('data-default-label', defaultLabel.trim());
                btn.disabled = true;
                btn.classList.remove('is-success');
                if (msg) {
                    msg.hidden = true;
                    msg.classList.remove('is-error', 'is-success');
                    msg.textContent = '';
                }
                fetch((window.mjb_ajax && window.mjb_ajax.ajax_url) || (window.ajaxurl || '/wp-admin/admin-ajax.php'), {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: body
                }).then(function (r) { return r.json(); }).then(function (json) {
                    btn.disabled = false;
                    var ok = !!(json && json.success);
                    var text = (json && json.data && json.data.message)
                        ? json.data.message
                        : (ok ? 'Alert created.' : 'Could not create alert. Please try again.');
                    if (msg) {
                        msg.hidden = false;
                        msg.classList.toggle('is-success', ok);
                        msg.classList.toggle('is-error', !ok);
                        msg.textContent = text;
                    }
                    if (ok) {
                        btn.classList.add('is-success');
                        btn.textContent = 'Alert created';
                        setTimeout(function () {
                            btn.classList.remove('is-success');
                            btn.textContent = btn.getAttribute('data-default-label') || defaultLabel;
                        }, 3500);
                    }
                }).catch(function () {
                    btn.disabled = false;
                    if (msg) {
                        msg.hidden = false;
                        msg.classList.add('is-error');
                        msg.classList.remove('is-success');
                        msg.textContent = 'Could not create alert. Please try again.';
                    }
                });
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') closeAllFreq();
            });
        })();
        </script>
        <?php
    }

    /**
     * Register alert CPT (private).
     */
    public static function register_cpt()
    {
        register_post_type(self::CPT, array(
            'labels' => array(
                'name' => __('Job Alerts', 'modern-job-board'),
                'singular_name' => __('Job Alert', 'modern-job-board'),
            ),
            'public' => false,
            'show_ui' => false,
            'supports' => array('title', 'author'),
            'capability_type' => 'post',
        ));
    }

    /**
     * Schedule hourly digests (frequency checked per alert).
     */
    public static function schedule_cron()
    {
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'hourly', self::CRON_HOOK);
        }
    }

    /**
     * @param int $user_id
     * @return array<int, WP_Post>
     */
    public static function get_user_alerts($user_id)
    {
        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            return array();
        }
        return get_posts(array(
            'post_type' => self::CPT,
            'post_status' => 'publish',
            'author' => $user_id,
            'posts_per_page' => 50,
            'orderby' => 'date',
            'order' => 'DESC',
        ));
    }

    /**
     * Create or update an alert from filter params.
     *
     * @param int   $user_id
     * @param array $filters
     * @param string $frequency daily|weekly
     * @return int|WP_Error
     */
    public static function save_alert($user_id, array $filters, $frequency = 'daily')
    {
        $user_id = (int) $user_id;
        if ($user_id <= 0) {
            return new WP_Error('mjb_alert_auth', __('You must be logged in.', 'modern-job-board'));
        }

        if (class_exists('MJB_Memberships')) {
            $slot_ok = MJB_Memberships::can_create_alert($user_id);
            if (is_wp_error($slot_ok)) {
                return $slot_ok;
            }
        }

        $frequency = $frequency === 'weekly' ? 'weekly' : 'daily';
        $clean = array(
            'search_keywords' => isset($filters['search_keywords']) ? sanitize_text_field($filters['search_keywords']) : '',
            'search_location' => isset($filters['search_location']) ? sanitize_title($filters['search_location']) : '',
            'search_category' => isset($filters['search_category']) ? sanitize_title($filters['search_category']) : '',
            'search_type' => isset($filters['search_type']) ? sanitize_title($filters['search_type']) : '',
            'search_company' => isset($filters['search_company']) ? sanitize_title($filters['search_company']) : '',
        );

        $label_parts = array_filter(array(
            $clean['search_keywords'],
            $clean['search_location'],
            $clean['search_category'],
            $clean['search_type'],
        ));
        $title = !empty($label_parts)
            ? implode(' · ', $label_parts)
            : __('All new jobs', 'modern-job-board');

        $id = wp_insert_post(array(
            'post_type' => self::CPT,
            'post_status' => 'publish',
            'post_author' => $user_id,
            'post_title' => $title,
        ), true);

        if (is_wp_error($id)) {
            return $id;
        }

        update_post_meta($id, '_mjb_alert_filters', $clean);
        update_post_meta($id, '_mjb_alert_frequency', $frequency);
        update_post_meta($id, '_mjb_alert_last_sent', 0);

        return (int) $id;
    }

    /**
     * AJAX: save alert from current filters.
     */
    public static function ajax_save_alert()
    {
        check_ajax_referer('mjb_search_nonce', 'security');
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'login_required'), 401);
        }

        $filters = array(
            'search_keywords' => isset($_REQUEST['search_keywords']) ? wp_unslash($_REQUEST['search_keywords']) : '',
            'search_location' => isset($_REQUEST['search_location']) ? wp_unslash($_REQUEST['search_location']) : '',
            'search_category' => isset($_REQUEST['search_category']) ? wp_unslash($_REQUEST['search_category']) : '',
            'search_type' => isset($_REQUEST['search_type']) ? wp_unslash($_REQUEST['search_type']) : '',
            'search_company' => isset($_REQUEST['search_company']) ? wp_unslash($_REQUEST['search_company']) : '',
        );
        $frequency = isset($_REQUEST['frequency']) ? sanitize_key(wp_unslash($_REQUEST['frequency'])) : 'daily';

        $id = self::save_alert(get_current_user_id(), $filters, $frequency);
        if (is_wp_error($id)) {
            wp_send_json_error(array('message' => $id->get_error_message()), 400);
        }

        wp_send_json_success(array(
            'id' => $id,
            'message' => __('Alert created. We will email you when matching jobs are posted.', 'modern-job-board'),
        ));
    }

    /**
     * AJAX: delete own alert.
     */
    public static function ajax_delete_alert()
    {
        check_ajax_referer('mjb_search_nonce', 'security');
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'login_required'), 401);
        }
        $id = isset($_REQUEST['alert_id']) ? (int) $_REQUEST['alert_id'] : 0;
        $post = get_post($id);
        if (!$post || $post->post_type !== self::CPT || (int) $post->post_author !== get_current_user_id()) {
            wp_send_json_error(array('message' => 'not_found'), 404);
        }
        wp_trash_post($id);
        wp_send_json_success(array('deleted' => $id));
    }

    /**
     * Cron: send due digests.
     */
    public static function run_digests()
    {
        $alerts = get_posts(array(
            'post_type' => self::CPT,
            'post_status' => 'publish',
            'posts_per_page' => 100,
            'fields' => 'ids',
            'orderby' => 'ID',
            'order' => 'ASC',
        ));

        $now = time();
        foreach ($alerts as $alert_id) {
            $freq = get_post_meta($alert_id, '_mjb_alert_frequency', true);
            $last = (int) get_post_meta($alert_id, '_mjb_alert_last_sent', true);
            $interval = ($freq === 'weekly') ? WEEK_IN_SECONDS : DAY_IN_SECONDS;
            if ($last > 0 && ($now - $last) < $interval) {
                continue;
            }

            $since = $last > 0 ? $last : ($now - $interval);
            $filters = get_post_meta($alert_id, '_mjb_alert_filters', true);
            if (!is_array($filters)) {
                $filters = array();
            }

            $jobs = self::find_matching_jobs($filters, $since, 20);
            if (empty($jobs)) {
                update_post_meta($alert_id, '_mjb_alert_last_sent', $now);
                continue;
            }

            $author = get_userdata((int) get_post_field('post_author', $alert_id));
            if (!$author || !$author->user_email) {
                continue;
            }

            self::send_digest_email($author->user_email, $jobs, $filters);
            update_post_meta($alert_id, '_mjb_alert_last_sent', $now);
        }
    }

    /**
     * @param array $filters
     * @param int   $since_ts
     * @param int   $limit
     * @return array<int, WP_Post>
     */
    public static function find_matching_jobs(array $filters, $since_ts, $limit = 20)
    {
        $args = array(
            'post_type' => 'job_listing',
            'post_status' => 'publish',
            'posts_per_page' => max(1, min(50, (int) $limit)),
            'date_query' => array(
                array(
                    'after' => gmdate('Y-m-d H:i:s', (int) $since_ts),
                    'inclusive' => true,
                    'column' => 'post_date_gmt',
                ),
            ),
            'orderby' => 'date',
            'order' => 'DESC',
        );

        $tax_query = array();
        if (!empty($filters['search_location'])) {
            $tax_query[] = array(
                'taxonomy' => 'job_listing_location',
                'field' => 'slug',
                'terms' => sanitize_title($filters['search_location']),
            );
        }
        if (!empty($filters['search_category'])) {
            $tax_query[] = array(
                'taxonomy' => 'job_listing_category',
                'field' => 'slug',
                'terms' => sanitize_title($filters['search_category']),
            );
        }
        if (!empty($filters['search_type'])) {
            $tax_query[] = array(
                'taxonomy' => 'job_listing_type',
                'field' => 'slug',
                'terms' => sanitize_title($filters['search_type']),
            );
        }
        if (count($tax_query) > 1) {
            $tax_query['relation'] = 'AND';
        }
        if (!empty($tax_query)) {
            $args['tax_query'] = $tax_query;
        }
        if (!empty($filters['search_keywords'])) {
            $args['s'] = sanitize_text_field($filters['search_keywords']);
        }

        return get_posts($args);
    }

    /**
     * @param string   $to
     * @param WP_Post[] $jobs
     * @param array    $filters
     */
    public static function send_digest_email($to, array $jobs, array $filters)
    {
        $subject = sprintf(
            /* translators: %s: site name */
            __('[%s] New jobs matching your alert', 'modern-job-board'),
            wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES)
        );

        $lines = array();
        $lines[] = __('Here are new jobs matching your saved search:', 'modern-job-board');
        $lines[] = '';
        foreach ($jobs as $job) {
            $lines[] = '• ' . $job->post_title;
            $lines[] = '  ' . get_permalink($job);
            $lines[] = '';
        }
        $lines[] = __('Manage alerts from your candidate dashboard.', 'modern-job-board');

        $message = implode("\n", $lines);
        $subject = apply_filters('mjb_job_alert_email_subject', $subject, $jobs, $filters);
        $message = apply_filters('mjb_job_alert_email_message', $message, $jobs, $filters);

        wp_mail($to, $subject, $message);
    }

    /**
     * Manage alerts shortcode (candidate dashboard).
     *
     * @return string
     */
    public static function render_manage_shortcode()
    {
        if (!is_user_logged_in()) {
            return '<p class="mjb-message mjb-message--info">' . esc_html__('Log in to manage job alerts.', 'modern-job-board') . '</p>';
        }

        $alerts = self::get_user_alerts(get_current_user_id());
        $jobs_url = class_exists('MJB_Job_Routes') ? MJB_Job_Routes::build_url() : home_url('/');
        ob_start();
        echo '<div class="mjb-job-alerts">';
        echo '<h3 class="mjb-job-alerts__title">' . esc_html__('Your job alerts', 'modern-job-board') . '</h3>';
        echo '<p class="mjb-job-alerts__intro">' . esc_html__('Email digests for searches you have saved.', 'modern-job-board') . '</p>';
        if (empty($alerts)) {
            echo '<p class="mjb-job-alerts__empty">' . esc_html__('No alerts yet.', 'modern-job-board') . ' ';
            echo '<a href="' . esc_url($jobs_url) . '">' . esc_html__('Browse jobs and create one', 'modern-job-board') . '</a>.</p>';
        } else {
            echo '<ul class="mjb-job-alerts__list">';
            foreach ($alerts as $alert) {
                $freq = get_post_meta($alert->ID, '_mjb_alert_frequency', true);
                echo '<li class="mjb-job-alerts__item">';
                echo '<span class="mjb-job-alerts__name">' . esc_html($alert->post_title) . '</span>';
                echo '<span class="mjb-job-alerts__freq">' . esc_html($freq === 'weekly' ? __('Weekly emails', 'modern-job-board') : __('Daily emails', 'modern-job-board')) . '</span>';
                echo '</li>';
            }
            echo '</ul>';
        }
        echo '</div>';
        return (string) ob_get_clean();
    }
}
