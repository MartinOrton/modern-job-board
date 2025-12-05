<?php
/**
 * Modern Job Board Template Loader
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Template_Loader
{

    /**
     * Initialize Template Loader.
     */
    public function init()
    {
        add_filter('template_include', array($this, 'template_loader'));
    }

    /**
     * Load plugin templates.
     *
     * @param string $template Path to the template.
     * @return string Path to the template.
     */
    public function template_loader($template)
    {
        if (is_post_type_archive('job_listing') || is_tax(array('job_type', 'job_category', 'job_location'))) {
            $theme_files = array('archive-job_listing.php', 'archive-job.php');
            $exists_in_theme = locate_template($theme_files, false);
            if ($exists_in_theme != '') {
                return $exists_in_theme;
            } else {
                return MJB_PATH . 'templates/archive-job.php';
            }
        } elseif (is_singular('job_listing')) {
            $theme_files = array('single-job_listing.php', 'single-job.php');
            $exists_in_theme = locate_template($theme_files, false);
            if ($exists_in_theme != '') {
                return $exists_in_theme;
            } else {
                return MJB_PATH . 'templates/single-job.php';
            }
        }

        return $template;
    }
}
