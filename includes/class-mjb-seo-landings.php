<?php
/**
 * Auto SEO landing polish (#29) — stronger title/meta for category/city/company archives.
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Seo_Landings
{
    /**
     * Bootstrap.
     */
    public static function init()
    {
        add_filter('document_title_parts', array(__CLASS__, 'title_parts'), 20);
        add_action('wp_head', array(__CLASS__, 'meta_description'), 1);
        add_filter('get_the_archive_description', array(__CLASS__, 'archive_description'), 20);
    }

    /**
     * @param array $parts
     * @return array
     */
    public static function title_parts($parts)
    {
        if (is_tax('job_listing_category')) {
            $term = get_queried_object();
            if ($term && !is_wp_error($term)) {
                $parts['title'] = sprintf(
                    /* translators: %s: category name */
                    __('%s jobs', 'modern-job-board'),
                    $term->name
                );
            }
        } elseif (is_tax('job_listing_location')) {
            $term = get_queried_object();
            if ($term && !is_wp_error($term)) {
                $parts['title'] = sprintf(
                    /* translators: %s: location */
                    __('Jobs in %s', 'modern-job-board'),
                    $term->name
                );
            }
        } elseif (is_singular('company')) {
            $parts['title'] = sprintf(
                /* translators: %s: company name */
                __('Jobs at %s', 'modern-job-board'),
                get_the_title()
            );
        } elseif (is_post_type_archive('job_listing')) {
            $parts['title'] = __('Browse jobs', 'modern-job-board');
        }
        return $parts;
    }

    /**
     * Meta description for landings.
     */
    public static function meta_description()
    {
        $desc = '';
        if (is_tax('job_listing_category') || is_tax('job_listing_location') || is_tax('job_listing_type')) {
            $term = get_queried_object();
            if ($term && !is_wp_error($term)) {
                $desc = $term->description
                    ? wp_strip_all_tags($term->description)
                    : sprintf(
                        /* translators: 1: term name 2: site name */
                        __('Find %1$s opportunities on %2$s. Apply online today.', 'modern-job-board'),
                        $term->name,
                        get_bloginfo('name')
                    );
            }
        } elseif (is_singular('company')) {
            $desc = sprintf(
                /* translators: 1: company 2: site */
                __('Explore open roles at %1$s on %2$s.', 'modern-job-board'),
                get_the_title(),
                get_bloginfo('name')
            );
        } elseif (is_singular('job_listing')) {
            $company = get_post_meta(get_the_ID(), '_company_name', true);
            $desc = sprintf(
                /* translators: 1: job title 2: company */
                __('%1$s%2$s — apply on %3$s.', 'modern-job-board'),
                get_the_title(),
                $company ? ' at ' . $company : '',
                get_bloginfo('name')
            );
        }

        $desc = apply_filters('mjb_seo_meta_description', $desc);
        if ($desc === '') {
            return;
        }
        $desc = wp_trim_words(wp_strip_all_tags($desc), 40, '…');
        echo '<meta name="description" content="' . esc_attr($desc) . '">' . "\n";
    }

    /**
     * @param string $description
     * @return string
     */
    public static function archive_description($description)
    {
        if ($description !== '') {
            return $description;
        }
        if (is_tax('job_listing_category') || is_tax('job_listing_location')) {
            $term = get_queried_object();
            if ($term && !is_wp_error($term)) {
                return '<p>' . esc_html(sprintf(
                    __('Browse the latest openings for %s.', 'modern-job-board'),
                    $term->name
                )) . '</p>';
            }
        }
        return $description;
    }
}
