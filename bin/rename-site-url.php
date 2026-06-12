<?php
/**
 * Replace old Local site URLs in the WordPress database (handles serialized data).
 *
 * Usage: php bin/rename-site-url.php "C:\path\to\wordpress" "old-host" "new-host"
 */

if ($argc < 4) {
    fwrite(STDERR, "Usage: php bin/rename-site-url.php /path/to/wordpress old.host new.host\n");
    exit(1);
}

$wp_root  = rtrim($argv[1], "\\/");
$old_host = $argv[2];
$new_host = $argv[3];

if (!isset($_SERVER['REQUEST_METHOD'])) {
    $_SERVER['REQUEST_METHOD'] = 'CLI';
}

require $wp_root . '/wp-load.php';

if (!defined('ABSPATH')) {
    fwrite(STDERR, "WordPress failed to load.\n");
    exit(1);
}

/**
 * @param mixed $data Data to process.
 * @param array $pairs Replacement pairs [from => to].
 * @return mixed
 */
function mjb_recursive_url_replace($data, array $pairs) {
    if (is_string($data)) {
        if (is_serialized($data)) {
            $unserialized = @unserialize($data);
            if (false !== $unserialized) {
                $unserialized = mjb_recursive_url_replace($unserialized, $pairs);
                return serialize($unserialized);
            }
        }

        foreach ($pairs as $from => $to) {
            $data = str_replace($from, $to, $data);
        }

        return $data;
    }

    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $data[ $key ] = mjb_recursive_url_replace($value, $pairs);
        }
    }

    return $data;
}

global $wpdb;

$pairs = array(
    'https://' . $old_host => 'https://' . $new_host,
    'http://' . $old_host  => 'https://' . $new_host,
);

$tables = array(
    $wpdb->options,
    $wpdb->posts,
    $wpdb->postmeta,
    $wpdb->comments,
    $wpdb->commentmeta,
    $wpdb->terms,
    $wpdb->term_taxonomy,
    $wpdb->usermeta,
);

$updated_rows = 0;

foreach ($tables as $table) {
    $columns = $wpdb->get_col("SHOW COLUMNS FROM `{$table}`", 0);

    foreach ($columns as $column) {
        $like = '%' . $wpdb->esc_like($old_host) . '%';
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM `{$table}` WHERE `{$column}` LIKE %s",
                $like
            ),
            ARRAY_A
        );

        if (empty($rows)) {
            continue;
        }

        foreach ($rows as $row) {
            $original = $row[ $column ];
            $replaced = mjb_recursive_url_replace($original, $pairs);

            if ($replaced === $original) {
                continue;
            }

            $primary = array();
            if (isset($row['option_id'])) {
                $primary = array('option_id' => $row['option_id']);
            } elseif (isset($row['ID'])) {
                $primary = array('ID' => $row['ID']);
            } elseif (isset($row['meta_id'])) {
                $primary = array('meta_id' => $row['meta_id']);
            } elseif (isset($row['comment_ID'])) {
                $primary = array('comment_ID' => $row['comment_ID']);
            } elseif (isset($row['term_id'])) {
                $primary = array('term_id' => $row['term_id']);
            } elseif (isset($row['umeta_id'])) {
                $primary = array('umeta_id' => $row['umeta_id']);
            }

            if (empty($primary)) {
                continue;
            }

            $wpdb->update($table, array($column => $replaced), $primary);
            $updated_rows++;
        }
    }
}

update_option('siteurl', 'https://' . $new_host);
update_option('home', 'https://' . $new_host);

echo "Updated {$updated_rows} database values." . PHP_EOL;
echo 'Site URL: ' . home_url('/') . PHP_EOL;