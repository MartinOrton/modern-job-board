<?php
/**
 * Gutenberg block registrations for Modern Job Board shortcodes.
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Blocks
{
    /**
     * Block definitions mapped to shortcodes.
     *
     * @var array<string, array<string, mixed>>
     */
    private static $blocks = array(
        'job-listings' => array(
            'shortcode' => 'mjb_jobs',
            'title' => 'Job Listings',
            'description' => 'Searchable job listings with AJAX filters.',
            'attributes' => array(
                'postsPerPage' => array(
                    'type' => 'number',
                    'default' => 10,
                ),
            ),
        ),
        'job-form' => array(
            'shortcode' => 'mjb_job_form',
            'title' => 'Job Submission Form',
            'description' => 'Frontend form for employers to post jobs.',
            'attributes' => array(),
        ),
        'employer-dashboard' => array(
            'shortcode' => 'mjb_dashboard',
            'title' => 'Employer Dashboard',
            'description' => 'Employer dashboard for jobs and applications.',
            'attributes' => array(),
        ),
        'employer-registration' => array(
            'shortcode' => 'mjb_employer_registration',
            'title' => 'Employer Registration',
            'description' => 'Registration form for employer accounts.',
            'attributes' => array(),
        ),
        'candidate-registration' => array(
            'shortcode' => 'mjb_candidate_registration',
            'title' => 'Candidate Registration',
            'description' => 'Registration form for candidate accounts.',
            'attributes' => array(),
        ),
        'candidate-dashboard' => array(
            'shortcode' => 'mjb_candidate_dashboard',
            'title' => 'Candidate Dashboard',
            'description' => 'Candidate profile, resume, and applications.',
            'attributes' => array(),
        ),
    );

    /**
     * Initialize block hooks.
     */
    public static function init()
    {
        add_action('init', array(__CLASS__, 'register_blocks'));
        add_filter('block_categories_all', array(__CLASS__, 'register_block_category'), 10, 2);
    }

    /**
     * Register the Modern Job Board block category.
     *
     * @param array<int, array<string, string>> $categories
     * @param mixed                               $context
     * @return array<int, array<string, string>>
     */
    public static function register_block_category($categories, $context)
    {
        unset($context);

        $categories[] = array(
            'slug' => 'modern-job-board',
            'title' => __('Modern Job Board', 'modern-job-board'),
            'icon' => 'portfolio',
        );

        return $categories;
    }

    /**
     * Register dynamic blocks for each shortcode.
     */
    public static function register_blocks()
    {
        if (!function_exists('register_block_type')) {
            return;
        }

        foreach (self::$blocks as $slug => $block) {
            register_block_type(
                'modern-job-board/' . $slug,
                array(
                    'api_version' => 3,
                    'title' => __($block['title'], 'modern-job-board'),
                    'category' => 'modern-job-board',
                    'icon' => 'portfolio',
                    'description' => __($block['description'], 'modern-job-board'),
                    'attributes' => $block['attributes'],
                    'supports' => array(
                        'html' => false,
                    ),
                    'render_callback' => static function ($attributes) use ($block) {
                        return self::render_block($block['shortcode'], $attributes);
                    },
                )
            );
        }
    }

    /**
     * Render a block via its underlying shortcode.
     *
     * @param string               $shortcode
     * @param array<string, mixed> $attributes
     * @return string
     */
    public static function render_block($shortcode, $attributes)
    {
        return do_shortcode(self::build_shortcode_tag($shortcode, $attributes));
    }

    /**
     * Build the shortcode tag used by a block render callback.
     *
     * @param string               $shortcode
     * @param array<string, mixed> $attributes
     * @return string
     */
    public static function build_shortcode_tag($shortcode, $attributes)
    {
        $attribute_string = '';

        if ($shortcode === 'mjb_jobs') {
            $posts_per_page = isset($attributes['postsPerPage']) ? max(1, intval($attributes['postsPerPage'])) : 10;
            $attribute_string = ' posts_per_page="' . $posts_per_page . '"';
        }

        return '[' . $shortcode . $attribute_string . ']';
    }
}