<?php
/**
 * Merge duplicate company posts on a WordPress install.
 *
 * Usage:
 *   php bin/dedupe-companies.php "C:\path\to\wp\root"
 *
 * Dry-run (report only, no deletes):
 *   php bin/dedupe-companies.php "C:\path\to\wp\root" --dry-run
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only.\n");
    exit(1);
}

$wp_root = isset($argv[1]) ? rtrim($argv[1], "\\/") : '';
$dry_run = in_array('--dry-run', $argv, true);

if ($wp_root === '' || !is_file($wp_root . '/wp-load.php')) {
    fwrite(STDERR, "Usage: php bin/dedupe-companies.php <wp-root> [--dry-run]\n");
    exit(1);
}

require $wp_root . '/wp-load.php';

if (!class_exists('MJB_Job_Importer')) {
    fwrite(STDERR, "Modern Job Board plugin is not active.\n");
    exit(1);
}

$result = MJB_Job_Importer::dedupe_companies(array(
    'delete' => !$dry_run,
));

echo "Company name groups: {$result['groups']}\n";
echo "Duplicates merged:   {$result['merged']}\n";
echo "Duplicates deleted:  {$result['deleted']}\n";
echo "Companies kept:      " . count($result['kept']) . "\n";

if ($dry_run) {
    echo "(dry-run: no posts deleted)\n";
}

exit(0);
