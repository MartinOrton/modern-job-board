<?php
/**
 * Flush WordPress rewrite rules for Modern Job Board routes.
 *
 * Usage: php bin/flush-rewrites.php /path/to/wordpress
 */

if ($argc < 2) {
    fwrite(STDERR, "Usage: php bin/flush-rewrites.php /path/to/wordpress\n");
    exit(1);
}

$wp_root = rtrim($argv[1], "\\/");

if (!isset($_SERVER['REQUEST_METHOD'])) {
    $_SERVER['REQUEST_METHOD'] = 'CLI';
}

require $wp_root . '/wp-load.php';

if (!defined('ABSPATH')) {
    fwrite(STDERR, "WordPress failed to load.\n");
    exit(1);
}

MJB_Job_Routes::register_rewrites();
MJB_Job_Permalinks::register_rewrites();
if (class_exists('MJB_Login')) {
    MJB_Login::register_rewrites();
}
if (class_exists('MJB_Pretty_Urls')) {
    MJB_Pretty_Urls::register_rewrites();
}
flush_rewrite_rules(false);
delete_option('mjb_routes_version');

echo "Rewrite rules flushed." . PHP_EOL;