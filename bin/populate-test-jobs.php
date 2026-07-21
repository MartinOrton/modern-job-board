<?php
/**
 * Add randomised extra demo jobs for local testing.
 *
 * Leaves some companies at 1 job; others get 2–8 total.
 *
 * Usage:
 *   php bin/populate-test-jobs.php "C:\path\to\wordpress"
 *   php bin/populate-test-jobs.php "C:\path\to\wordpress" --seed=42
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

if ($argc < 2) {
    fwrite(STDERR, "Usage: php bin/populate-test-jobs.php /path/to/wordpress [--seed=N]\n");
    exit(1);
}

$wp_root = rtrim($argv[1], "\\/");
$seed = null;
foreach (array_slice($argv, 2) as $arg) {
    if (preg_match('/^--seed=(\d+)$/', $arg, $m)) {
        $seed = intval($m[1]);
    }
}

if (!isset($_SERVER['REQUEST_METHOD'])) {
    $_SERVER['REQUEST_METHOD'] = 'CLI';
}

if (!is_file($wp_root . '/wp-load.php')) {
    fwrite(STDERR, "Could not find wp-load.php in {$wp_root}\n");
    exit(1);
}

require $wp_root . '/wp-load.php';

if (!class_exists('MJB_Job_Importer')) {
    fwrite(STDERR, "Modern Job Board is not active.\n");
    exit(1);
}

if ($seed !== null) {
    mt_srand($seed);
}

$titles = array(
    'Frontend Engineer',
    'Backend Engineer',
    'Full Stack Developer',
    'DevOps Engineer',
    'QA Engineer',
    'Product Manager',
    'Product Designer',
    'UX Researcher',
    'Marketing Specialist',
    'Content Strategist',
    'Account Executive',
    'Customer Success Manager',
    'Data Analyst',
    'Data Engineer',
    'People Operations Lead',
    'Office Manager',
    'Finance Analyst',
    'Legal Counsel',
    'Support Specialist',
    'Technical Writer',
    'Mobile Developer',
    'Security Engineer',
    'Solutions Architect',
    'Scrum Master',
    'Recruiter',
    'Brand Designer',
    'Community Manager',
    'Sales Development Representative',
    'Implementation Specialist',
    'Site Reliability Engineer',
);

$locations = array(
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
);

$types = array('Full Time', 'Contract', 'Part Time');
$categories = array(
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
);

/**
 * Weighted random target job count for a company.
 * ~35% stay at 1, rest get more.
 *
 * @return int
 */
function mjb_random_target_job_count()
{
    $roll = mt_rand(1, 100);

    if ($roll <= 35) {
        return 1;
    }
    if ($roll <= 55) {
        return 2;
    }
    if ($roll <= 72) {
        return 3;
    }
    if ($roll <= 85) {
        return 4;
    }
    if ($roll <= 93) {
        return 5;
    }
    if ($roll <= 97) {
        return 6;
    }

    return mt_rand(7, 8);
}

/**
 * @param string $company_name
 * @param string $title
 * @return string
 */
function mjb_build_test_job_content($company_name, $title)
{
    $company_name = esc_html($company_name);
    $title = esc_html($title);

    return '<h3>About the role</h3>'
        . "<p>{$company_name} is hiring a {$title} for a local testing dataset. "
        . 'This listing was generated automatically to exercise filters, company cards, and pagination.</p>'
        . '<h3>What you will do</h3>'
        . '<ul>'
        . '<li>Collaborate with a small cross-functional team on product delivery.</li>'
        . '<li>Own clear outcomes and communicate progress weekly.</li>'
        . '<li>Improve processes and documentation as the team scales.</li>'
        . '</ul>'
        . '<h3>Requirements</h3>'
        . '<ul>'
        . '<li>Relevant experience for the role level.</li>'
        . '<li>Strong written communication.</li>'
        . '<li>Comfortable in a remote or hybrid environment.</li>'
        . '</ul>'
        . '<h3>Benefits</h3>'
        . '<ul>'
        . '<li>Competitive pay band for the market.</li>'
        . '<li>Flexible working hours.</li>'
        . '<li>Learning stipend and equipment budget.</li>'
        . '</ul>';
}

$companies = get_posts(array(
    'post_type' => 'company',
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'orderby' => 'title',
    'order' => 'ASC',
));

if (empty($companies)) {
    fwrite(STDERR, "No published companies found. Run seed-demo.php first.\n");
    exit(1);
}

$created = 0;
$skipped = 0;
$summary = array();

foreach ($companies as $company) {
    $company_id = intval($company->ID);
    $company_name = $company->post_title;
    $current = MJB_Job_Importer::count_jobs_for_company($company_id);
    // Prefer published-only count for display targets.
    $current_published = class_exists('MJB_Shortcodes')
        ? MJB_Shortcodes::get_company_job_count($company_id)
        : $current;

    $target = mjb_random_target_job_count();
    // Never shrink existing listings.
    if ($target < $current_published) {
        $target = $current_published;
    }

    $needed = max(0, $target - $current_published);
    $made = 0;

    for ($i = 0; $i < $needed; $i++) {
        $title = $titles[mt_rand(0, count($titles) - 1)];
        $suffix = $current_published + $i + 1;
        // Unique title + external id so re-runs can still add more if targets rise.
        $unique_title = sprintf('%s (%s #%d)', $title, $company_name, $suffix);
        $external_id = 'mjb-test-' . $company_id . '-' . sanitize_title($title) . '-' . $suffix . '-' . mt_rand(1000, 9999);

        $post_id = MJB_Job_Importer::import_job(
            array(
                'title' => $unique_title,
                'content' => mjb_build_test_job_content($company_name, $title),
                'location' => $locations[mt_rand(0, count($locations) - 1)],
                'type' => $types[mt_rand(0, count($types) - 1)],
                'category' => $categories[mt_rand(0, count($categories) - 1)],
                'company' => $company_name,
                'featured' => mt_rand(1, 100) <= 12 ? 1 : 0,
                'external_id' => $external_id,
            ),
            array(
                'author_id' => 1,
                'post_status' => 'publish',
                'skip_duplicates' => true,
            )
        );

        if ($post_id) {
            // Ensure company link even if name matching was fuzzy.
            update_post_meta($post_id, '_company_id', $company_id);
            update_post_meta($post_id, '_company_name', MJB_Job_Importer::normalize_company_name($company_name));
            $created++;
            $made++;
        } else {
            $skipped++;
        }
    }

    $final = class_exists('MJB_Shortcodes')
        ? MJB_Shortcodes::get_company_job_count($company_id)
        : ($current_published + $made);

    $summary[] = sprintf(
        '%2d jobs | %s (added %d)',
        $final,
        $company_name,
        $made
    );
}

// Sort summary by job count desc for readability.
usort($summary, static function ($a, $b) {
    return intval($b) <=> intval($a);
});

$total_jobs = intval(wp_count_posts('job_listing')->publish);

echo "Created {$created} test jobs" . ($skipped ? " ({$skipped} skipped)" : '') . ".\n";
echo "Published jobs total: {$total_jobs}\n";
echo "Per company:\n";
foreach ($summary as $line) {
    echo "  {$line}\n";
}
echo 'Jobs board: ' . home_url('/jobs/') . "\n";
echo 'Companies:  ' . home_url('/company/') . "\n";

exit(0);
