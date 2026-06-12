<?php
/**
 * Seed demo content for a local WordPress install.
 *
 * Usage: php bin/seed-demo.php "C:\path\to\wordpress"
 */

if ($argc < 2) {
    fwrite(STDERR, "Usage: php bin/seed-demo.php /path/to/wordpress\n");
    exit(1);
}

$wp_root = rtrim($argv[1], "\\/");

if (!is_file($wp_root . '/wp-load.php')) {
    fwrite(STDERR, "Could not find wp-load.php in {$wp_root}\n");
    exit(1);
}

require $wp_root . '/wp-load.php';

if (!defined('ABSPATH')) {
    fwrite(STDERR, "WordPress failed to load.\n");
    exit(1);
}

if (!class_exists('MJB_Page_Wizard')) {
    fwrite(STDERR, "Modern Job Board is not active in this WordPress install.\n");
    exit(1);
}

require_once dirname(__DIR__) . '/includes/class-mjb-page-wizard.php';
require_once dirname(__DIR__) . '/includes/class-mjb-job-importer.php';

$created_pages = MJB_Page_Wizard::create_missing_pages();
echo 'Pages: created ' . intval($created_pages['created']) . ', existing ' . intval($created_pages['existing']) . PHP_EOL;

$taxonomies = array(
    'job_location' => array('Remote', 'London', 'San Francisco'),
    'job_category' => array('Engineering', 'Design', 'Marketing'),
    'job_type' => array('Full Time', 'Contract', 'Part Time'),
);

foreach ($taxonomies as $taxonomy => $terms) {
    foreach ($terms as $term_name) {
        if (!term_exists($term_name, $taxonomy)) {
            wp_insert_term($term_name, $taxonomy);
        }
    }
}

$demo_jobs = array(
    array(
        'title' => 'Senior WordPress Developer',
        'description' => 'Build and maintain custom WordPress plugins and themes for agency clients. Experience with REST APIs, WooCommerce, and PHPCS required.',
        'location' => 'Remote',
        'type' => 'Full Time',
        'category' => 'Engineering',
        'company' => 'Acme Digital',
        'featured' => 1,
    ),
    array(
        'title' => 'Product Designer',
        'description' => 'Lead UX for a B2B hiring platform. You will own flows for employers, candidates, and admin analytics.',
        'location' => 'London',
        'type' => 'Full Time',
        'category' => 'Design',
        'company' => 'Northline Studio',
        'featured' => 0,
    ),
    array(
        'title' => 'Growth Marketing Manager',
        'description' => 'Own acquisition for a WordPress job board plugin. SEO, content, and partner campaigns.',
        'location' => 'San Francisco',
        'type' => 'Contract',
        'category' => 'Marketing',
        'company' => 'Launchpad Labs',
        'featured' => 0,
    ),
);

$imported = 0;

foreach ($demo_jobs as $job) {
    $post_id = MJB_Job_Importer::import_job(
        array(
            'title' => $job['title'],
            'description' => $job['description'],
            'location' => $job['location'],
            'type' => $job['type'],
            'category' => $job['category'],
            'company' => $job['company'],
            'featured' => !empty($job['featured']),
            'external_id' => 'mjb-demo-' . sanitize_title($job['title']),
        ),
        array(
            'author_id' => 1,
            'skip_duplicates' => true,
        )
    );

    if ($post_id) {
        $imported++;
    }
}

echo "Imported {$imported} demo jobs." . PHP_EOL;
echo 'Demo URLs:' . PHP_EOL;
echo '  Jobs: ' . home_url('/jobs/') . PHP_EOL;
echo '  Post a job: ' . home_url('/post-a-job/') . PHP_EOL;
echo '  Employer dashboard: ' . home_url('/employer-dashboard/') . PHP_EOL;