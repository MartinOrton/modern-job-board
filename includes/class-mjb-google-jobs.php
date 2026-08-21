<?php
/**
 * Google for Jobs / JobPosting schema mapper + admin preview (WPJB parity A1).
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Google_Jobs
{
    const OPTION_TYPE_MAP = 'mjb_gjobs_type_map';
    const OPTION_STATIC = 'mjb_gjobs_static';

    /**
     * Bootstrap.
     */
    public static function init()
    {
        add_action('admin_init', array(__CLASS__, 'register_settings'));
        add_action('add_meta_boxes', array(__CLASS__, 'meta_box'));
        add_filter('mjb_job_schema', array(__CLASS__, 'filter_schema'), 10, 2);
    }

    /**
     * Google employmentType values.
     *
     * @return array<string, string>
     */
    public static function google_employment_types()
    {
        return array(
            'FULL_TIME' => __('Full time', 'modern-job-board'),
            'PART_TIME' => __('Part time', 'modern-job-board'),
            'CONTRACTOR' => __('Contractor', 'modern-job-board'),
            'TEMPORARY' => __('Temporary', 'modern-job-board'),
            'INTERN' => __('Intern', 'modern-job-board'),
            'VOLUNTEER' => __('Volunteer', 'modern-job-board'),
            'PER_DIEM' => __('Per diem', 'modern-job-board'),
            'OTHER' => __('Other', 'modern-job-board'),
        );
    }

    /**
     * Register settings under Integrations.
     */
    public static function register_settings()
    {
        register_setting('mjb_settings_group', self::OPTION_TYPE_MAP, array(
            'type' => 'array',
            'sanitize_callback' => array(__CLASS__, 'sanitize_type_map'),
            'default' => array(),
        ));
        register_setting('mjb_settings_group', self::OPTION_STATIC, array(
            'type' => 'array',
            'sanitize_callback' => array(__CLASS__, 'sanitize_static'),
            'default' => array(),
        ));

        add_settings_field(
            'mjb_gjobs_type_map',
            __('Google Jobs type map', 'modern-job-board'),
            array(__CLASS__, 'render_type_map_field'),
            'mjb-settings',
            'mjb_integrations_section'
        );
        add_settings_field(
            'mjb_gjobs_static',
            __('Google Jobs static fields', 'modern-job-board'),
            array(__CLASS__, 'render_static_field'),
            'mjb-settings',
            'mjb_integrations_section'
        );
    }

    /**
     * @param mixed $input
     * @return array
     */
    public static function sanitize_type_map($input)
    {
        $out = array();
        $allowed = array_keys(self::google_employment_types());
        if (!is_array($input)) {
            return $out;
        }
        foreach ($input as $slug => $gtype) {
            $slug = sanitize_title((string) $slug);
            // Google employmentType tokens are UPPER_SNAKE (e.g. FULL_TIME).
            // sanitize_key() lowercases — normalize back before allowlist check.
            $gtype = strtoupper(preg_replace('/[^A-Za-z0-9_]/', '', (string) $gtype));
            if ($slug === '' || !in_array($gtype, $allowed, true)) {
                continue;
            }
            $out[$slug] = $gtype;
        }
        return $out;
    }

    /**
     * @param mixed $input
     * @return array
     */
    public static function sanitize_static($input)
    {
        $out = array(
            'base_salary_currency' => '',
            'base_salary_value' => '',
            'base_salary_unit' => 'MONTH',
            'hiring_organization_same_as' => '',
            'job_location_type' => '',
        );
        if (!is_array($input)) {
            return $out;
        }
        $out['base_salary_currency'] = strtoupper(sanitize_text_field($input['base_salary_currency'] ?? ''));
        $out['base_salary_value'] = sanitize_text_field($input['base_salary_value'] ?? '');
        $unit = strtoupper(sanitize_text_field($input['base_salary_unit'] ?? 'MONTH'));
        $out['base_salary_unit'] = in_array($unit, array('HOUR', 'DAY', 'WEEK', 'MONTH', 'YEAR'), true) ? $unit : 'MONTH';
        $out['hiring_organization_same_as'] = esc_url_raw($input['hiring_organization_same_as'] ?? '');
        $jlt = sanitize_text_field($input['job_location_type'] ?? '');
        $out['job_location_type'] = $jlt === 'TELECOMMUTE' ? 'TELECOMMUTE' : '';
        return $out;
    }

    /**
     * Settings UI: map each job_type term → Google employmentType.
     */
    public static function render_type_map_field()
    {
        $map = get_option(self::OPTION_TYPE_MAP, array());
        if (!is_array($map)) {
            $map = array();
        }
        $terms = get_terms(array('taxonomy' => 'job_type', 'hide_empty' => false));
        $gtypes = self::google_employment_types();

        echo '<p class="description">' . esc_html__('Map each job type to a Google for Jobs employmentType. Unmapped types fall back to built-in name matching, then OTHER.', 'modern-job-board') . '</p>';
        if (is_wp_error($terms) || empty($terms)) {
            echo '<p>' . esc_html__('No job types yet. Create job types first.', 'modern-job-board') . '</p>';
            return;
        }
        echo '<table class="widefat striped" style="max-width:36rem"><thead><tr><th>' . esc_html__('Job type', 'modern-job-board') . '</th><th>' . esc_html__('Google type', 'modern-job-board') . '</th></tr></thead><tbody>';
        foreach ($terms as $term) {
            $current = isset($map[$term->slug]) ? $map[$term->slug] : '';
            echo '<tr><td>' . esc_html($term->name) . '</td><td>';
            echo '<select name="' . esc_attr(self::OPTION_TYPE_MAP) . '[' . esc_attr($term->slug) . ']">';
            echo '<option value="">' . esc_html__('— Auto —', 'modern-job-board') . '</option>';
            foreach ($gtypes as $key => $label) {
                echo '<option value="' . esc_attr($key) . '" ' . selected($current, $key, false) . '>' . esc_html($label) . ' (' . esc_html($key) . ')</option>';
            }
            echo '</select></td></tr>';
        }
        echo '</tbody></table>';
    }

    /**
     * Static schema fields applied to all jobs when set.
     */
    public static function render_static_field()
    {
        $s = self::get_static();
        ?>
        <p class="description"><?php esc_html_e('Optional values applied to every JobPosting when set (leave blank to omit).', 'modern-job-board'); ?></p>
        <p>
            <label><?php esc_html_e('Base salary currency (ISO)', 'modern-job-board'); ?><br>
                <input type="text" class="small-text" name="<?php echo esc_attr(self::OPTION_STATIC); ?>[base_salary_currency]" value="<?php echo esc_attr($s['base_salary_currency']); ?>" placeholder="USD">
            </label>
        </p>
        <p>
            <label><?php esc_html_e('Base salary value', 'modern-job-board'); ?><br>
                <input type="text" class="small-text" name="<?php echo esc_attr(self::OPTION_STATIC); ?>[base_salary_value]" value="<?php echo esc_attr($s['base_salary_value']); ?>" placeholder="50000">
            </label>
        </p>
        <p>
            <label><?php esc_html_e('Salary unit', 'modern-job-board'); ?><br>
                <select name="<?php echo esc_attr(self::OPTION_STATIC); ?>[base_salary_unit]">
                    <?php foreach (array('HOUR', 'DAY', 'WEEK', 'MONTH', 'YEAR') as $u) : ?>
                        <option value="<?php echo esc_attr($u); ?>" <?php selected($s['base_salary_unit'], $u); ?>><?php echo esc_html($u); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </p>
        <p>
            <label><?php esc_html_e('Hiring organization sameAs URL', 'modern-job-board'); ?><br>
                <input type="url" class="regular-text" name="<?php echo esc_attr(self::OPTION_STATIC); ?>[hiring_organization_same_as]" value="<?php echo esc_attr($s['hiring_organization_same_as']); ?>" placeholder="https://">
            </label>
        </p>
        <p>
            <label>
                <input type="checkbox" name="<?php echo esc_attr(self::OPTION_STATIC); ?>[job_location_type]" value="TELECOMMUTE" <?php checked($s['job_location_type'], 'TELECOMMUTE'); ?>>
                <?php esc_html_e('Mark all jobs as TELECOMMUTE (remote) in schema', 'modern-job-board'); ?>
            </label>
        </p>
        <?php
    }

    /**
     * @return array
     */
    public static function get_static()
    {
        $s = get_option(self::OPTION_STATIC, array());
        return self::sanitize_static(is_array($s) ? $s : array());
    }

    /**
     * Job editor preview meta box.
     */
    public static function meta_box()
    {
        add_meta_box(
            'mjb_google_jobs_preview',
            __('Google for Jobs', 'modern-job-board'),
            array(__CLASS__, 'render_meta_box'),
            'job_listing',
            'side',
            'default'
        );
    }

    /**
     * @param WP_Post $post
     */
    public static function render_meta_box($post)
    {
        $schema = self::build_schema($post->ID);
        $issues = self::validate_schema($schema);
        echo '<p class="description">' . esc_html__('Live JobPosting preview for this listing.', 'modern-job-board') . '</p>';
        if (!empty($issues['errors'])) {
            echo '<p><strong style="color:#b32d2e">' . esc_html__('Errors', 'modern-job-board') . '</strong></p><ul style="margin:0 0 8px 1.1em">';
            foreach ($issues['errors'] as $e) {
                echo '<li style="color:#b32d2e">' . esc_html($e) . '</li>';
            }
            echo '</ul>';
        }
        if (!empty($issues['warnings'])) {
            echo '<p><strong style="color:#b26b00">' . esc_html__('Warnings', 'modern-job-board') . '</strong></p><ul style="margin:0 0 8px 1.1em">';
            foreach ($issues['warnings'] as $w) {
                echo '<li style="color:#b26b00">' . esc_html($w) . '</li>';
            }
            echo '</ul>';
        }
        if (empty($issues['errors']) && empty($issues['warnings'])) {
            echo '<p style="color:#008a20">' . esc_html__('No schema issues detected.', 'modern-job-board') . '</p>';
        }
        echo '<details><summary>' . esc_html__('JSON-LD', 'modern-job-board') . '</summary>';
        echo '<pre style="max-height:220px;overflow:auto;font-size:11px;white-space:pre-wrap">' . esc_html(wp_json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . '</pre>';
        echo '</details>';
        $validate = 'https://search.google.com/test/rich-results?url=' . rawurlencode(get_permalink($post->ID));
        echo '<p><a class="button" href="' . esc_url($validate) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Validate URL', 'modern-job-board') . '</a></p>';
    }

    /**
     * Build JobPosting array for a job.
     *
     * @param int $post_id
     * @return array
     */
    public static function build_schema($post_id)
    {
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'job_listing') {
            return array();
        }

        $job_title = get_the_title($post_id);
        $job_description = wp_strip_all_tags($post->post_content);
        $date_posted = get_the_date('c', $post_id);
        $expires = get_post_meta($post_id, '_job_expires', true);
        $company_name = get_post_meta($post_id, '_company_name', true);
        if (!$company_name) {
            $company_name = get_bloginfo('name');
        }

        $address = class_exists('MJB_Location')
            ? MJB_Location::get_job_schema_address($post_id)
            : array('formatted' => '', 'locality' => '', 'region' => '', 'country' => '');
        $location_name = $address['formatted'];

        $employment_type = self::resolve_employment_type($post_id);

        $postal_address = array(
            '@type' => 'PostalAddress',
            'addressLocality' => $address['locality'] !== '' ? $address['locality'] : $location_name,
        );
        if ($address['region'] !== '') {
            $postal_address['addressRegion'] = $address['region'];
        }
        if ($address['country'] !== '') {
            $postal_address['addressCountry'] = $address['country'];
        }

        $static = self::get_static();
        $same_as = $static['hiring_organization_same_as'] !== '' ? $static['hiring_organization_same_as'] : get_bloginfo('url');

        $schema = array(
            '@context' => 'https://schema.org/',
            '@type' => 'JobPosting',
            'title' => $job_title,
            'description' => $job_description,
            'datePosted' => $date_posted,
            'identifier' => array(
                '@type' => 'PropertyValue',
                'name' => get_bloginfo('name'),
                'value' => (string) $post_id,
            ),
            'hiringOrganization' => array(
                '@type' => 'Organization',
                'name' => $company_name,
                'sameAs' => $same_as,
            ),
            'jobLocation' => array(
                '@type' => 'Place',
                'address' => $postal_address,
            ),
            'directApply' => true,
            'url' => get_permalink($post_id),
        );

        if ($expires) {
            $schema['validThrough'] = date('c', strtotime($expires));
        }
        if ($employment_type) {
            $schema['employmentType'] = $employment_type;
        }
        if ($static['job_location_type'] === 'TELECOMMUTE') {
            $schema['jobLocationType'] = 'TELECOMMUTE';
        }
        if ($static['base_salary_currency'] !== '' && $static['base_salary_value'] !== '') {
            $schema['baseSalary'] = array(
                '@type' => 'MonetaryAmount',
                'currency' => $static['base_salary_currency'],
                'value' => array(
                    '@type' => 'QuantitativeValue',
                    'value' => $static['base_salary_value'],
                    'unitText' => $static['base_salary_unit'],
                ),
            );
        }

        if (get_post_meta($post_id, '_job_filled', true)) {
            // Filled jobs should not be pushed as open roles.
            $schema['mjbFilled'] = true;
        }

        return apply_filters('mjb_google_jobs_schema', $schema, $post_id);
    }

    /**
     * @param int $post_id
     * @return string
     */
    public static function resolve_employment_type($post_id)
    {
        $map = get_option(self::OPTION_TYPE_MAP, array());
        if (!is_array($map)) {
            $map = array();
        }
        $type_terms = get_the_terms($post_id, 'job_type');
        if ($type_terms && !is_wp_error($type_terms)) {
            $slug = $type_terms[0]->slug;
            if (!empty($map[$slug])) {
                return $map[$slug];
            }
            if (class_exists('MJB_Search')) {
                return MJB_Search::map_employment_type_for_schema($type_terms[0]->name);
            }
        }
        return 'OTHER';
    }

    /**
     * @param array $schema
     * @return array{errors: string[], warnings: string[]}
     */
    public static function validate_schema(array $schema)
    {
        $errors = array();
        $warnings = array();

        foreach (array('title', 'description', 'datePosted', 'hiringOrganization', 'jobLocation') as $req) {
            if (empty($schema[$req])) {
                $errors[] = sprintf(
                    /* translators: %s: schema property name */
                    __('Missing required field: %s', 'modern-job-board'),
                    $req
                );
            }
        }
        if (empty($schema['employmentType'])) {
            $warnings[] = __('employmentType not set (recommended).', 'modern-job-board');
        }
        if (empty($schema['validThrough'])) {
            $warnings[] = __('validThrough / expiration not set (recommended).', 'modern-job-board');
        }
        if (!empty($schema['mjbFilled'])) {
            $warnings[] = __('Job is marked filled — consider unpublishing or omitting from Google Jobs.', 'modern-job-board');
        }
        $desc = isset($schema['description']) ? (string) $schema['description'] : '';
        if (strlen($desc) < 50) {
            $warnings[] = __('Description is very short for Google Jobs.', 'modern-job-board');
        }

        return array('errors' => $errors, 'warnings' => $warnings);
    }

    /**
     * Replace core schema builder output when filtered from CPT.
     *
     * @param array $schema
     * @param int   $post_id
     * @return array
     */
    public static function filter_schema($schema, $post_id)
    {
        $built = self::build_schema($post_id);
        if (!empty($built['mjbFilled'])) {
            unset($built['mjbFilled']);
        }
        return !empty($built) ? $built : $schema;
    }
}
