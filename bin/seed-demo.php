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

/**
 * Fill company profile fields used by the listing hover preview.
 *
 * @param string               $name
 * @param array<string,string> $profile
 * @return array{id:int,logo:bool}
 */
function mjb_seed_demo_company($name, $profile) {
    $company_id = MJB_Job_Importer::find_or_create_company($name);
    if (!$company_id) {
        return array('id' => 0, 'logo' => false);
    }

    wp_update_post(
        array(
            'ID' => $company_id,
            'post_excerpt' => sanitize_text_field($profile['tagline']),
            'post_content' => wp_kses_post('<p>' . esc_html($profile['about']) . '</p>'),
            'post_status' => 'publish',
        )
    );

    update_post_meta($company_id, '_company_tagline', sanitize_text_field($profile['tagline']));
    update_post_meta($company_id, '_company_website', esc_url_raw($profile['website']));
    update_post_meta($company_id, '_company_linkedin', esc_url_raw($profile['linkedin']));
    update_post_meta($company_id, '_company_twitter', esc_url_raw($profile['twitter']));

    $logo = mjb_seed_demo_company_logo($company_id, $name);

    return array('id' => (int) $company_id, 'logo' => $logo);
}

/**
 * Attach an initials PNG as the company featured image when missing.
 *
 * @param int    $company_id
 * @param string $name
 * @return bool
 */
function mjb_seed_demo_company_logo($company_id, $name) {
    if (has_post_thumbnail($company_id)) {
        $thumb_id = (int) get_post_thumbnail_id($company_id);
        $url = $thumb_id ? wp_get_attachment_image_url($thumb_id, 'thumbnail') : '';
        if ($url) {
            update_post_meta($company_id, '_company_logo_url', esc_url_raw($url));
        }
        return true;
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    if (!function_exists('imagecreatetruecolor')) {
        return false;
    }

    $initials = class_exists('MJB_Shortcodes')
        ? MJB_Shortcodes::get_company_initials($name)
        : strtoupper(substr(preg_replace('/[^A-Za-z0-9]+/', '', $name), 0, 2));

    $size = 160;
    $im = imagecreatetruecolor($size, $size);
    if (!$im) {
        return false;
    }

    $bg = imagecolorallocate($im, 11, 95, 88);
    $fg = imagecolorallocate($im, 255, 255, 255);
    imagefilledrectangle($im, 0, 0, $size, $size, $bg);

    $font = '';
    foreach (array(
        'C:\\Windows\\Fonts\\segoeui.ttf',
        'C:\\Windows\\Fonts\\arial.ttf',
        '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
    ) as $candidate) {
        if (is_file($candidate)) {
            $font = $candidate;
            break;
        }
    }

    if ($font !== '' && function_exists('imagettftext')) {
        $box = imagettfbbox(52, 0, $font, $initials);
        $text_w = abs($box[2] - $box[0]);
        $text_h = abs($box[7] - $box[1]);
        $x = (int) (($size - $text_w) / 2) - (int) $box[0];
        $y = (int) (($size + $text_h) / 2) - (int) $box[1];
        imagettftext($im, 52, 0, $x, $y, $fg, $font, $initials);
    } else {
        $fw = imagefontwidth(5) * strlen($initials);
        $fh = imagefontheight(5);
        imagestring($im, 5, (int) (($size - $fw) / 2), (int) (($size - $fh) / 2), $initials, $fg);
    }

    $tmp = wp_tempnam('mjb-demo-logo.png');
    if (!$tmp) {
        imagedestroy($im);
        return false;
    }

    imagepng($im, $tmp);
    imagedestroy($im);

    $bits = wp_upload_bits(sanitize_title($name) . '-logo.png', null, file_get_contents($tmp));
    @unlink($tmp);
    if (!empty($bits['error']) || empty($bits['file'])) {
        return false;
    }

    $attachment_id = wp_insert_attachment(
        array(
            'post_mime_type' => 'image/png',
            'post_title' => $name . ' logo',
            'post_content' => '',
            'post_status' => 'inherit',
        ),
        $bits['file'],
        $company_id
    );
    if (!$attachment_id || is_wp_error($attachment_id)) {
        return false;
    }

    $meta = wp_generate_attachment_metadata($attachment_id, $bits['file']);
    if (is_array($meta)) {
        wp_update_attachment_metadata($attachment_id, $meta);
    }
    update_post_meta($attachment_id, '_mjb_demo_logo', '1');
    set_post_thumbnail($company_id, $attachment_id);

    $url = wp_get_attachment_image_url($attachment_id, 'thumbnail');
    if ($url) {
        update_post_meta($company_id, '_company_logo_url', esc_url_raw($url));
    }

    return true;
}

$demo_jobs = mjb_get_demo_jobs_data();
$imported = 0;

foreach ($demo_jobs as $job) {
    if (mjb_seed_demo_job($job)) {
        $imported++;
    }
}

echo "Seeded {$imported} demo jobs (" . count($demo_jobs) . ' defined).' . PHP_EOL;

$companies = mjb_get_demo_companies_data();
$company_count = 0;
$logo_count = 0;
foreach ($companies as $name => $profile) {
    $result = mjb_seed_demo_company($name, $profile);
    if (!empty($result['id'])) {
        $company_count++;
    }
    if (!empty($result['logo'])) {
        $logo_count++;
    }
}

echo "Seeded {$company_count} demo companies ({$logo_count} logos)." . PHP_EOL;
echo 'Demo URLs:' . PHP_EOL;
echo '  Jobs: ' . home_url('/jobs/') . PHP_EOL;
echo '  Post a job: ' . home_url('/post-a-job/') . PHP_EOL;
echo '  Recruiter dashboard: ' . home_url('/jobs/recruiter-dashboard/') . PHP_EOL;