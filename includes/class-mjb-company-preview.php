<?php
/**
 * Company hover preview (#18).
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Company_Preview
{
    /**
     * Bootstrap.
     */
    public static function init()
    {
        add_action('wp_ajax_mjb_company_preview', array(__CLASS__, 'ajax_preview'));
        add_action('wp_ajax_nopriv_mjb_company_preview', array(__CLASS__, 'ajax_preview'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue'), 30);
    }

    /**
     * Front script for hover cards on job listings.
     */
    public static function enqueue()
    {
        if (is_admin()) {
            return;
        }
        wp_enqueue_script(
            'mjb-company-preview',
            MJB_URL . 'assets/js/mjb-company-preview.js',
            array('jquery'),
            defined('MJB_VERSION') ? MJB_VERSION : '1.0',
            true
        );
        wp_localize_script('mjb-company-preview', 'mjbCompanyPreview', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mjb_company_preview'),
            'icons' => array(
                'website' => MJB_Icons::render('globe', 18),
                'linkedin' => MJB_Icons::render('linkedin', 18),
                'twitter' => MJB_Icons::render('twitter', 18),
            ),
            'i18n' => array(
                'website' => __('Website', 'modern-job-board'),
                'linkedin' => __('LinkedIn', 'modern-job-board'),
                'twitter' => __('X', 'modern-job-board'),
            ),
        ));
    }

    /**
     * AJAX: company motto, site, social.
     */
    public static function ajax_preview()
    {
        check_ajax_referer('mjb_company_preview', 'security');
        $company_id = isset($_REQUEST['company_id']) ? (int) $_REQUEST['company_id'] : 0;
        if ($company_id <= 0) {
            // Resolve from job.
            $job_id = isset($_REQUEST['job_id']) ? (int) $_REQUEST['job_id'] : 0;
            if ($job_id > 0) {
                $company_id = (int) get_post_meta($job_id, '_company_id', true);
            }
        }
        $company = $company_id > 0 ? get_post($company_id) : null;
        if (!$company || $company->post_type !== 'company' || $company->post_status !== 'publish') {
            $job_id = isset($_REQUEST['job_id']) ? (int) $_REQUEST['job_id'] : 0;
            $name = $job_id > 0 ? (string) get_post_meta($job_id, '_company_name', true) : '';
            if ($name === '') {
                wp_send_json_error(array('message' => 'not_found'), 404);
            }
            wp_send_json_success(array(
                'id' => 0,
                'name' => $name,
                'motto' => '',
                'website' => '',
                'twitter' => '',
                'linkedin' => '',
                'logo' => '',
                'url' => '',
            ));
        }

        $motto = get_post_meta($company_id, '_company_tagline', true);
        if ($motto === '') {
            $motto = get_post_meta($company_id, '_company_motto', true);
        }
        if ($motto === '' && $company->post_excerpt) {
            $motto = $company->post_excerpt;
        }

        $website = get_post_meta($company_id, '_company_website', true);
        $twitter = get_post_meta($company_id, '_company_twitter', true);
        $linkedin = get_post_meta($company_id, '_company_linkedin', true);
        $logo = get_the_post_thumbnail_url($company_id, 'thumbnail');
        if (!$logo) {
            $logo = (string) get_post_meta($company_id, '_company_logo_url', true);
        }

        wp_send_json_success(array(
            'id' => $company_id,
            'name' => $company->post_title,
            'motto' => $motto ? wp_strip_all_tags($motto) : '',
            'website' => $website ? esc_url_raw($website) : '',
            'twitter' => $twitter ? esc_url_raw($twitter) : '',
            'linkedin' => $linkedin ? esc_url_raw($linkedin) : '',
            'logo' => $logo ? esc_url_raw($logo) : '',
            'url' => get_permalink($company_id),
        ));
    }
}
