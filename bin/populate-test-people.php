<?php
/**
 * Seed local-test recruiters, companies, jobs, candidates, resumes, and applications.
 *
 * Idempotent: existing @mjb.test users and fixture applications are reused, not duplicated.
 *
 * Usage:
 *   php bin/populate-test-people.php "C:\path\to\wordpress"
 *
 * All fixture accounts use password: MjbTest-2026!
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

if ($argc < 2) {
    fwrite(STDERR, "Usage: php bin/populate-test-people.php /path/to/wordpress\n");
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

if (!defined('ABSPATH') || !class_exists('MJB_Job_Importer') || !class_exists('MJB_Resumes')) {
    fwrite(STDERR, "Modern Job Board is not active in this WordPress install.\n");
    exit(1);
}

const MJB_TEST_PASSWORD = 'MjbTest-2026!';
const MJB_TEST_FIXTURE_META = '_mjb_test_fixture';

mt_srand(20260914);

$locations = array(
    'Remote',
    'London',
    'San Francisco',
    'New York',
    'Manchester',
    'Austin',
    'Berlin',
    'Toronto',
    'Dublin',
    'Cape Town',
    'Pretoria',
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

$new_companies = array(
    array('name' => 'Helios Analytics', 'tagline' => 'Decisions from data', 'website' => 'https://helios-analytics.test', 'city' => 'London'),
    array('name' => 'Copperline Legal', 'tagline' => 'Employment law for growing teams', 'website' => 'https://copperline.test', 'city' => 'Manchester'),
    array('name' => 'Fathom Health', 'tagline' => 'Clinical software that clinicians finish', 'website' => 'https://fathom-health.test', 'city' => 'Cape Town'),
    array('name' => 'Nightowl Media', 'tagline' => 'Stories after dark', 'website' => 'https://nightowl.test', 'city' => 'Berlin'),
    array('name' => 'Kite Logistics', 'tagline' => 'Freight that lands on time', 'website' => 'https://kite-logistics.test', 'city' => 'Austin'),
    array('name' => 'Beacon Fintech', 'tagline' => 'Payments infrastructure for SMEs', 'website' => 'https://beacon-fintech.test', 'city' => 'Dublin'),
    array('name' => 'Redwood Climate', 'tagline' => 'Carbon accounting without the spreadsheet', 'website' => 'https://redwood-climate.test', 'city' => 'Portland'),
    array('name' => 'Orbit Robotics', 'tagline' => 'Warehouse robots that just work', 'website' => 'https://orbit-robotics.test', 'city' => 'San Francisco'),
);

$job_titles = array(
    'Frontend Engineer',
    'Backend Engineer',
    'Product Designer',
    'Account Executive',
    'People Partner',
    'Data Analyst',
    'Customer Success Manager',
    'Legal Counsel',
    'Recruiter',
    'Marketing Lead',
);

$candidates = array(
    array('first' => 'Amina', 'last' => 'Dlamini', 'headline' => 'Product designer · hiring tools', 'city' => 'Cape Town', 'apply' => 3),
    array('first' => 'Noah', 'last' => 'Bennett', 'headline' => 'Full-stack engineer (PHP/React)', 'city' => 'London', 'apply' => 2),
    array('first' => 'Priya', 'last' => 'Shah', 'headline' => 'Data analyst · SQL and Looker', 'city' => 'Manchester', 'apply' => 2),
    array('first' => 'Jonas', 'last' => 'Keller', 'headline' => 'Backend engineer · Python/Go', 'city' => 'Berlin', 'apply' => 3),
    array('first' => 'Sofia', 'last' => 'Martinez', 'headline' => 'Customer success lead', 'city' => 'Austin', 'apply' => 1),
    array('first' => 'Liam', 'last' => 'OConnor', 'headline' => 'Frontend engineer · TypeScript', 'city' => 'Dublin', 'apply' => 2),
    array('first' => 'Thandi', 'last' => 'Mokoena', 'headline' => 'People operations generalist', 'city' => 'Pretoria', 'apply' => 2),
    array('first' => 'Elena', 'last' => 'Rossi', 'headline' => 'Product manager · marketplaces', 'city' => 'Remote', 'apply' => 3),
    array('first' => 'Marcus', 'last' => 'Chen', 'headline' => 'DevOps / SRE', 'city' => 'San Francisco', 'apply' => 1),
    array('first' => 'Isla', 'last' => 'MacLeod', 'headline' => 'Content designer', 'city' => 'Edinburgh', 'apply' => 2),
    array('first' => 'Yusuf', 'last' => 'Rahman', 'headline' => 'Sales development representative', 'city' => 'New York', 'apply' => 2),
    array('first' => 'Hana', 'last' => 'Suzuki', 'headline' => 'QA engineer · Playwright', 'city' => 'Toronto', 'apply' => 1),
    array('first' => 'Omar', 'last' => 'Hassan', 'headline' => 'Solutions architect', 'city' => 'London', 'apply' => 2),
    array('first' => 'Claire', 'last' => 'Dubois', 'headline' => 'Marketing manager · B2B', 'city' => 'Remote', 'apply' => 1),
    array('first' => 'Sipho', 'last' => 'Nkosi', 'headline' => 'Mobile developer · Flutter', 'city' => 'Johannesburg', 'apply' => 2),
    array('first' => 'Maya', 'last' => 'Patel', 'headline' => 'UX researcher', 'city' => 'London', 'apply' => 0),
    array('first' => 'Daniel', 'last' => 'Wright', 'headline' => 'Finance analyst', 'city' => 'Manchester', 'apply' => 0),
    array('first' => 'Leila', 'last' => 'Farouk', 'headline' => 'Legal intern · employment law', 'city' => 'Dublin', 'apply' => 0),
);

$app_statuses = array('new', 'new', 'new', 'reviewed', 'reviewed', 'shortlisted', 'shortlisted', 'rejected', 'hired');

/**
 * @param string $text
 * @return string
 */
function mjb_test_pdf($text)
{
    $safe = preg_replace('/[^\x20-\x7E]/', ' ', (string) $text);
    $safe = str_replace(array('\\', '(', ')'), array('\\\\', '\\(', '\\)'), $safe);
    $stream = "BT /F1 16 Tf 72 720 Td ({$safe}) Tj T* /F1 11 Tf (Modern Job Board test resume) Tj ET\n";
    $len = strlen($stream);

    $o1 = "<< /Type /Catalog /Pages 2 0 R >>";
    $o2 = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
    $o3 = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>";
    $o4 = "<< /Length {$len} >>\nstream\n{$stream}endstream";
    $o5 = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";

    $parts = array($o1, $o2, $o3, $o4, $o5);
    $offsets = array();
    $body = '';
    $pos = 9; // strlen("%PDF-1.1\n")
    foreach ($parts as $i => $obj) {
        $offsets[$i] = $pos;
        $chunk = ($i + 1) . " 0 obj\n{$obj}\nendobj\n";
        $body .= $chunk;
        $pos += strlen($chunk);
    }
    $xref_pos = $pos;
    $xref = "xref\n0 6\n0000000000 65535 f \n";
    foreach ($offsets as $off) {
        $xref .= sprintf("%010d 00000 n \n", $off);
    }
    $trailer = "trailer<< /Size 6 /Root 1 0 R >>\nstartxref\n{$xref_pos}\n%%EOF\n";

    return "%PDF-1.1\n" . $body . $xref . $trailer;
}

/**
 * @param string $login
 * @param string $email
 * @param string $display
 * @param string $first
 * @param string $last
 * @param string $role
 * @return int
 */
function mjb_test_user($login, $email, $display, $first, $last, $role)
{
    $existing = get_user_by('login', $login);
    if (!$existing) {
        $existing = get_user_by('email', $email);
    }
    if ($existing) {
        $user_id = (int) $existing->ID;
        if (!in_array($role, (array) $existing->roles, true)) {
            $user = new WP_User($user_id);
            $user->set_role($role);
        }
        update_user_meta($user_id, MJB_TEST_FIXTURE_META, '1');
        return $user_id;
    }

    $user_id = wp_insert_user(array(
        'user_login' => $login,
        'user_email' => $email,
        'user_pass' => MJB_TEST_PASSWORD,
        'display_name' => $display,
        'first_name' => $first,
        'last_name' => $last,
        'role' => $role,
    ));
    if (!$user_id || is_wp_error($user_id)) {
        return 0;
    }
    $user_id = (int) $user_id;
    update_user_meta($user_id, MJB_TEST_FIXTURE_META, '1');

    return $user_id;
}

/**
 * @param string $company_name
 * @param int    $user_id
 * @param array  $extra
 * @return int
 */
function mjb_test_company($company_name, $user_id, $extra = array())
{
    $company_id = MJB_Job_Importer::find_or_create_company($company_name, array('author_id' => $user_id));
    if (!$company_id) {
        return 0;
    }
    $company_id = (int) $company_id;
    wp_update_post(array(
        'ID' => $company_id,
        'post_author' => $user_id,
        'post_status' => 'publish',
        'post_content' => isset($extra['about']) ? $extra['about'] : $company_name . ' is a local test employer on Modern Job Board.',
    ));
    if (!empty($extra['tagline'])) {
        update_post_meta($company_id, '_company_tagline', sanitize_text_field($extra['tagline']));
    }
    if (!empty($extra['website'])) {
        update_post_meta($company_id, '_company_website', esc_url_raw($extra['website']));
    }
    update_post_meta($company_id, '_company_contact_name', sanitize_text_field($extra['contact'] ?? ''));
    update_post_meta($company_id, '_company_email', sanitize_email($extra['email'] ?? ''));
    update_post_meta($company_id, '_employer_user_id', $user_id);
    update_post_meta($company_id, MJB_TEST_FIXTURE_META, '1');
    update_user_meta($user_id, '_company_name', $company_name);
    update_user_meta($user_id, '_employer_company_id', $company_id);

    return $company_id;
}

/**
 * @param int    $user_id
 * @param string $display
 * @return int
 */
function mjb_test_resume($user_id, $display)
{
    $existing = (int) get_user_meta($user_id, '_candidate_resume_id', true);
    if ($existing && get_post_type($existing) === 'mjb_resume') {
        return $existing;
    }

    MJB_Private_Uploads::ensure_directories();
    $dest_dir = trailingslashit(MJB_Private_Uploads::tier_root_dir(MJB_Private_Uploads::TIER_PRIVATE))
        . 'resumes/' . gmdate('Y') . '/' . gmdate('m');
    if (!file_exists($dest_dir)) {
        wp_mkdir_p($dest_dir);
    }
    $filename = 'mjb-cv-' . $user_id . '-' . wp_generate_password(12, false, false) . '.pdf';
    $pdf_path = trailingslashit($dest_dir) . $filename;
    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
    if (file_put_contents($pdf_path, mjb_test_pdf($display . ' — Curriculum Vitae')) === false) {
        fwrite(STDERR, "Could not write resume PDF for user {$user_id}\n");
        return 0;
    }

    $uploaded = array(
        'file' => $pdf_path,
        'relative' => MJB_Private_Uploads::absolute_to_relative($pdf_path),
        'url' => '',
    );
    $resume_id = MJB_Resumes::create_resume_post($user_id, $uploaded, sanitize_title($display) . '-cv.pdf');
    if (is_wp_error($resume_id) || !$resume_id) {
        return 0;
    }
    update_post_meta((int) $resume_id, MJB_TEST_FIXTURE_META, '1');

    return (int) $resume_id;
}

/**
 * @param int    $job_id
 * @param int    $user_id
 * @param string $status
 * @return int
 */
function mjb_test_application($job_id, $user_id, $status)
{
    $user = get_userdata($user_id);
    if (!$user) {
        return 0;
    }
    $email = $user->user_email;
    if (class_exists('MJB_Application_Guard') && MJB_Application_Guard::has_duplicate_application($job_id, $email)) {
        return 0;
    }

    $resume_post_id = (int) get_user_meta($user_id, '_candidate_resume_id', true);
    $copied = $resume_post_id ? MJB_Resumes::copy_profile_resume_for_application($resume_post_id) : new WP_Error('no_resume');
    if (is_wp_error($copied)) {
        fwrite(STDERR, 'Application resume copy failed for ' . $email . ': ' . $copied->get_error_message() . PHP_EOL);
        return 0;
    }

    $name = $user->display_name;
    $title = sprintf('Application for %s by %s', get_the_title($job_id), $name);
    $message = sprintf(
        "Hello,\n\nI am applying for %s. Please find my CV attached. This is local test data for Modern Job Board.\n\n%s\n%s",
        get_the_title($job_id),
        $name,
        (string) get_user_meta($user_id, '_candidate_headline', true)
    );

    $application_id = wp_insert_post(array(
        'post_title' => $title,
        'post_content' => $message,
        'post_type' => 'job_application',
        'post_status' => 'publish',
        'post_author' => $user_id,
    ), true);
    if (!$application_id || is_wp_error($application_id)) {
        return 0;
    }
    $application_id = (int) $application_id;
    update_post_meta($application_id, '_job_applied_for', $job_id);
    update_post_meta($application_id, '_candidate_name', $name);
    update_post_meta($application_id, '_candidate_email', $email);
    update_post_meta($application_id, '_candidate_user_id', $user_id);
    update_post_meta($application_id, '_candidate_phone', (string) get_user_meta($user_id, '_candidate_phone', true));
    update_post_meta($application_id, '_candidate_resume_path', $copied['path']);
    if (!empty($copied['relative'])) {
        update_post_meta($application_id, '_candidate_resume_relative', $copied['relative']);
    }
    if ($resume_post_id) {
        update_post_meta($application_id, '_candidate_resume_id', $resume_post_id);
    }
    update_post_meta($application_id, MJB_TEST_FIXTURE_META, '1');
    if (class_exists('MJB_Application_Status')) {
        MJB_Application_Status::update_status($application_id, $status);
    }

    return $application_id;
}

/**
 * @param string $company_name
 * @param string $title
 * @return string
 */
function mjb_test_job_content($company_name, $title)
{
    $company_name = esc_html($company_name);
    $title = esc_html($title);

    return '<h3>About the role</h3>'
        . "<p>{$company_name} is hiring a {$title}. This listing is local test data for Modern Job Board.</p>"
        . '<h3>What you will do</h3><ul>'
        . '<li>Ship work with a small cross-functional team.</li>'
        . '<li>Talk to customers and turn feedback into the next iteration.</li>'
        . '<li>Keep documentation and hiring pipelines honest.</li>'
        . '</ul>'
        . '<h3>Requirements</h3><ul>'
        . '<li>Relevant experience for the role.</li>'
        . '<li>Clear written communication.</li>'
        . '<li>Comfortable remote or hybrid.</li>'
        . '</ul>';
}

$stats = array(
    'recruiters_new' => 0,
    'recruiters_linked' => 0,
    'companies_new' => 0,
    'jobs_new' => 0,
    'candidates_new' => 0,
    'resumes_new' => 0,
    'applications_new' => 0,
);

// --- Recruiters for existing companies that lack an employer user. ---
$existing_companies = get_posts(array(
    'post_type' => 'company',
    'post_status' => array('publish', 'pending'),
    'posts_per_page' => -1,
    'orderby' => 'title',
    'order' => 'ASC',
));

foreach ($existing_companies as $company) {
    $company_id = (int) $company->ID;
    $linked = (int) get_post_meta($company_id, '_employer_user_id', true);
    if ($linked && get_userdata($linked)) {
        continue;
    }
    $slug = sanitize_title($company->post_title);
    $login = 'recruiter.' . $slug;
    $email = $login . '@mjb.test';
    $display = $company->post_title . ' Recruiter';
    $user_id = mjb_test_user($login, $email, $display, 'Recruiter', $company->post_title, 'employer');
    if (!$user_id) {
        continue;
    }
    update_post_meta($company_id, '_employer_user_id', $user_id);
    update_post_meta($company_id, '_company_contact_name', $display);
    if ((string) get_post_meta($company_id, '_company_email', true) === '') {
        update_post_meta($company_id, '_company_email', $email);
    }
    update_user_meta($user_id, '_company_name', $company->post_title);
    update_user_meta($user_id, '_employer_company_id', $company_id);
    $stats['recruiters_linked']++;
}

// --- New companies + recruiters + jobs. ---
foreach ($new_companies as $row) {
    $slug = sanitize_title($row['name']);
    $login = 'recruiter.' . $slug;
    $email = $login . '@mjb.test';
    $first = 'Hiring';
    $last = preg_replace('/[^A-Za-z]/', '', $row['name']);
    $display = $first . ' ' . $last;
    $user_id = mjb_test_user($login, $email, $display, $first, $last, 'employer');
    if (!$user_id) {
        continue;
    }
    $stats['recruiters_new']++;

    $existed = (int) MJB_Job_Importer::find_company_by_name($row['name']);
    $company_id = mjb_test_company($row['name'], $user_id, array(
        'tagline' => $row['tagline'],
        'website' => $row['website'],
        'contact' => $display,
        'email' => $email,
        'about' => $row['name'] . ' — ' . $row['tagline'] . '. Local test employer.',
    ));
    if (!$company_id) {
        continue;
    }
    if (!$existed) {
        $stats['companies_new']++;
    }

    $job_count = 3;
    for ($i = 0; $i < $job_count; $i++) {
        $title = $job_titles[($i + strlen($slug)) % count($job_titles)];
        $external_id = 'mjb-people-' . $company_id . '-' . sanitize_title($title) . '-' . ($i + 1);
        $status = 'publish';
        if ($i === 2 && $slug === 'helios-analytics') {
            $status = 'pending';
        }
        if ($i === 2 && $slug === 'copperline-legal') {
            $status = 'draft';
        }
        $post_id = MJB_Job_Importer::import_job(
            array(
                'title' => $title,
                'content' => mjb_test_job_content($row['name'], $title),
                'location' => $row['city'],
                'type' => $types[$i % count($types)],
                'category' => $categories[$i % count($categories)],
                'company' => $row['name'],
                'featured' => $i === 0 ? 1 : 0,
                'external_id' => $external_id,
            ),
            array(
                'author_id' => $user_id,
                'post_status' => $status,
                'skip_duplicates' => true,
            )
        );
        if (!$post_id) {
            continue;
        }
        update_post_meta($post_id, '_company_id', $company_id);
        update_post_meta($post_id, '_company_name', $row['name']);
        update_post_meta($post_id, '_application_method', 'internal');
        update_post_meta($post_id, '_job_expires', gmdate('Y-m-d', time() + ((30 + ($i * 15)) * DAY_IN_SECONDS)));
        if (class_exists('MJB_Analytics')) {
            update_post_meta($post_id, MJB_Analytics::VIEW_COUNT_META, mt_rand(0, 48));
        }
        update_post_meta($post_id, MJB_TEST_FIXTURE_META, '1');
        $stats['jobs_new']++;
    }
}

// --- Candidates + CVs. ---
$candidate_ids = array();
foreach ($candidates as $index => $person) {
    $n = $index + 1;
    $login = sprintf('candidate.%02d', $n);
    $email = $login . '@mjb.test';
    $display = $person['first'] . ' ' . $person['last'];
    $existed = (bool) get_user_by('login', $login);
    $user_id = mjb_test_user($login, $email, $display, $person['first'], $person['last'], 'candidate');
    if (!$user_id) {
        continue;
    }
    if (!$existed) {
        $stats['candidates_new']++;
    }
    update_user_meta($user_id, '_candidate_headline', $person['headline']);
    update_user_meta($user_id, '_candidate_phone', '+27 82 000 00' . sprintf('%02d', $n));
    $had_resume = (int) get_user_meta($user_id, '_candidate_resume_id', true);
    $resume_id = mjb_test_resume($user_id, $display);
    if ($resume_id && !$had_resume) {
        $stats['resumes_new']++;
    }
    $candidate_ids[] = array(
        'id' => $user_id,
        'apply' => (int) $person['apply'],
    );
}

// --- Applications with copied CVs. ---
$jobs = get_posts(array(
    'post_type' => 'job_listing',
    'post_status' => 'publish',
    'posts_per_page' => 80,
    'orderby' => 'date',
    'order' => 'DESC',
    'fields' => 'ids',
));
$jobs = array_map('intval', (array) $jobs);
if ($jobs) {
    $status_i = 0;
    foreach ($candidate_ids as $row) {
        $need = max(0, (int) $row['apply']);
        if ($need === 0 || !$jobs) {
            continue;
        }
        $picks = array();
        $offset = $row['id'] % count($jobs);
        for ($i = 0; $i < $need; $i++) {
            $picks[] = $jobs[($offset + ($i * 7)) % count($jobs)];
        }
        $picks = array_values(array_unique($picks));
        foreach ($picks as $job_id) {
            $status = $app_statuses[$status_i % count($app_statuses)];
            $status_i++;
            $app_id = mjb_test_application($job_id, $row['id'], $status);
            if ($app_id) {
                $stats['applications_new']++;
            }
        }
    }
}

$counts = array(
    'companies' => intval(wp_count_posts('company')->publish),
    'jobs' => intval(wp_count_posts('job_listing')->publish),
    'applications' => intval(wp_count_posts('job_application')->publish),
    'resumes' => intval(wp_count_posts('mjb_resume')->publish),
    'employers' => count(get_users(array('role' => 'employer', 'fields' => 'ID', 'number' => 200))),
    'candidates' => count(get_users(array('role' => 'candidate', 'fields' => 'ID', 'number' => 200))),
);

echo "Fixture password for all @mjb.test accounts: " . MJB_TEST_PASSWORD . PHP_EOL;
echo "Created/linked this run:" . PHP_EOL;
echo "  recruiters linked to existing companies: {$stats['recruiters_linked']}" . PHP_EOL;
echo "  recruiters created: {$stats['recruiters_new']}" . PHP_EOL;
echo "  companies created: {$stats['companies_new']}" . PHP_EOL;
echo "  jobs created: {$stats['jobs_new']}" . PHP_EOL;
echo "  candidates created: {$stats['candidates_new']}" . PHP_EOL;
echo "  resumes created: {$stats['resumes_new']}" . PHP_EOL;
echo "  applications created: {$stats['applications_new']}" . PHP_EOL;
echo "Board totals:" . PHP_EOL;
echo "  companies {$counts['companies']}, jobs {$counts['jobs']}, applications {$counts['applications']}, resumes {$counts['resumes']}" . PHP_EOL;
echo "  recruiters {$counts['employers']}, candidates {$counts['candidates']}" . PHP_EOL;
echo "Logins:" . PHP_EOL;
echo "  Recruiter example: recruiter.helios-analytics@mjb.test" . PHP_EOL;
echo "  Candidate example: candidate.01@mjb.test  (Amina Dlamini, has applications + CV)" . PHP_EOL;
echo "  Candidate with CV only: candidate.16@mjb.test  (Maya Patel)" . PHP_EOL;
echo 'Admin applications: ' . admin_url('admin.php?page=modern-job-board&tab=applications') . PHP_EOL;
echo 'Admin resumes:      ' . admin_url('admin.php?page=modern-job-board&tab=resumes') . PHP_EOL;

exit(0);
