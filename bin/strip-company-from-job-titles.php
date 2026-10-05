<?php
/**
 * Remove company names from job titles (they already show under the title).
 *
 * Usage: php bin/strip-company-from-job-titles.php "C:\path\to\wordpress"
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

if ($argc < 2) {
    fwrite(STDERR, "Usage: php bin/strip-company-from-job-titles.php /path/to/wordpress\n");
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

$ids = get_posts(array(
    'post_type' => 'job_listing',
    'post_status' => 'any',
    'posts_per_page' => -1,
    'fields' => 'ids',
));

$updated = 0;
$skipped = 0;

foreach ((array) $ids as $job_id) {
    $job_id = (int) $job_id;
    $title = (string) get_the_title($job_id);
    $company_id = (int) get_post_meta($job_id, '_company_id', true);
    $company = (string) get_post_meta($job_id, '_company_name', true);
    if ($company === '' && $company_id) {
        $company = (string) get_the_title($company_id);
    }
    if ($title === '' || $company === '') {
        $skipped++;
        continue;
    }

    $clean = $title;
    $quoted = preg_quote($company, '/');

    $clean = preg_replace('/\s*[—–-]\s*' . $quoted . '\s*$/u', '', $clean);
    $clean = preg_replace('/\s*\(\s*' . $quoted . '\s*#(\d+)\s*\)\s*$/u', ' #$1', $clean);
    $clean = preg_replace('/\s*\(\s*' . $quoted . '\s*\)\s*$/u', '', $clean);
    $clean = preg_replace('/\s+#1\s*$/', '', $clean);
    $clean = trim((string) $clean);

    if ($clean === '' || $clean === $title) {
        $skipped++;
        continue;
    }

    $result = wp_update_post(array(
        'ID' => $job_id,
        'post_title' => $clean,
    ), true);

    if ($result && !is_wp_error($result)) {
        $updated++;
        echo $title . '  ->  ' . $clean . PHP_EOL;
    } else {
        $skipped++;
    }
}

echo "Updated {$updated} job titles ({$skipped} unchanged)." . PHP_EOL;
exit(0);
