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
        $company = get_post($company_id);
        if (!$company || $company->post_type !== 'company' || $company->post_status !== 'publish') {
            wp_send_json_error(array('message' => 'not_found'), 404);
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
