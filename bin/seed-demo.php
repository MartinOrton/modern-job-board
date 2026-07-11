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

if (!isset($_SERVER['REQUEST_METHOD'])) {
    $_SERVER['REQUEST_METHOD'] = 'CLI';
}

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

if (!class_exists('MJB_Job_Importer')) {
    require_once dirname(__DIR__) . '/includes/class-mjb-job-importer.php';
}

if (!class_exists('MJB_Job_Permalinks')) {
    require_once dirname(__DIR__) . '/includes/class-mjb-job-permalinks.php';
}

require_once __DIR__ . '/demo-jobs-data.php';

$created_pages = MJB_Page_Wizard::create_missing_pages();
echo 'Pages: created ' . intval($created_pages['created']) . ', existing ' . intval($created_pages['existing']) . PHP_EOL;

$taxonomies = array(
    'job_location' => array(
        'Remote',
        'London',
        'San Francisco',
        'New York',
        'Manchester',
        'Austin',
        'Berlin',
        'Toronto',
        'Sydney',
        'Portland',
        'Dublin',
    ),
    'job_category' => array(
        'Engineering',
        'Design',
        'Marketing',
        'Sales',
        'Customer Success',
        'Data',
        'Product',
        'HR',
        'Legal',
        'Operations',
    ),
    'job_type' => array(
        'Full Time',
        'Contract',
        'Part Time',
    ),
);

foreach ($taxonomies as $taxonomy => $terms) {
    foreach ($terms as $term_name) {
        if (!term_exists($term_name, $taxonomy)) {
            wp_insert_term($term_name, $taxonomy);
        }
    }
}

/**
 * Import or refresh a demo job by stable external ID.
 *
 * @param array<string, mixed> $job
 * @return int Post ID on success, 0 on failure.
 */
function mjb_seed_demo_job($job) {
    $external_id = 'mjb-demo-' . sanitize_title($job['title']);
    $existing_id = MJB_Job_Importer::find_existing_by_external_id($external_id);

    if ($existing_id) {
        $updated = wp_update_post(
            array(
                'ID' => $existing_id,
                'post_title' => sanitize_text_field($job['title']),
                'post_content' => wp_kses_post($job['content']),
                'post_status' => 'publish',
            ),
            true
        );

        if (!$updated || is_wp_error($updated)) {
            return 0;
        }

        MJB_Job_Importer::assign_taxonomy_terms($existing_id, 'job_location', $job['location'] ?? '');
        MJB_Job_Importer::assign_taxonomy_terms($existing_id, 'job_type', $job['type'] ?? '');
        MJB_Job_Importer::assign_taxonomy_terms($existing_id, 'job_category', $job['category'] ?? '');

        $company_name = isset($job['company']) ? sanitize_text_field($job['company']) : '';
        if ($company_name !== '') {
            $company_id = MJB_Job_Importer::find_or_create_company($company_name);
            if ($company_id) {
                update_post_meta($existing_id, '_company_id', $company_id);
                update_post_meta($existing_id, '_company_name', $company_name);
            }
        }

        update_post_meta($existing_id, '_featured', !empty($job['featured']) ? '1' : '0');
        MJB_Job_Permalinks::sync_geo_meta($existing_id);

        return intval($existing_id);
    }

    return MJB_Job_Importer::import_job(
        array(
            'title' => $job['title'],
            'content' => $job['content'],
            'location' => $job['location'],
            'type' => $job['type'],
            'category' => $job['category'],
            'company' => $job['company'],
            'featured' => !empty($job['featured']),
            'external_id' => $external_id,
        ),
        array(
            'author_id' => 1,
            'skip_duplicates' => true,
        )
    );
}

$demo_jobs = mjb_get_demo_jobs_data();
$imported = 0;

foreach ($demo_jobs as $job) {
    if (mjb_seed_demo_job($job)) {
        $imported++;
    }
}

echo "Seeded {$imported} demo jobs (" . count($demo_jobs) . ' defined).' . PHP_EOL;
echo 'Demo URLs:' . PHP_EOL;
echo '  Jobs: ' . home_url('/jobs/') . PHP_EOL;
echo '  Post a job: ' . home_url('/post-a-job/') . PHP_EOL;
echo '  Employer dashboard: ' . home_url('/employer-dashboard/') . PHP_EOL;