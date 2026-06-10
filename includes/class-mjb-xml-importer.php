<?php
/**
 * Modern Job Board XML / RSS Job Importer
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Xml_Importer
{
    const MJB_NS = 'https://github.com/MartinOrton/modern-job-board/ns/feed/1.0';

    /**
     * Parse an XML/RSS string into normalized job rows.
     *
     * @param string $xml_string
     * @return array<int, array<string, mixed>>|WP_Error
     */
    public static function parse_xml_string($xml_string)
    {
        if ($xml_string === '') {
            return new WP_Error('mjb_empty_xml', __('XML feed is empty.', 'modern-job-board'));
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xml_string, 'SimpleXMLElement', LIBXML_NOCDATA);
        if ($xml === false) {
            return new WP_Error('mjb_invalid_xml', __('Unable to parse XML feed.', 'modern-job-board'));
        }

        $items = array();
        if (isset($xml->channel->item)) {
            foreach ($xml->channel->item as $item) {
                $parsed = self::parse_rss_item($item);
                if (!empty($parsed['title'])) {
                    $items[] = $parsed;
                }
            }
        } elseif (isset($xml->item)) {
            foreach ($xml->item as $item) {
                $parsed = self::parse_rss_item($item);
                if (!empty($parsed['title'])) {
                    $items[] = $parsed;
                }
            }
        }

        if (empty($items)) {
            return new WP_Error('mjb_no_items', __('No job items found in XML feed.', 'modern-job-board'));
        }

        return $items;
    }

    /**
     * Import jobs from parsed rows.
     *
     * @param array $jobs
     * @param array $args
     * @return array{imported:int, skipped:int}
     */
    public static function import_jobs($jobs, $args = array())
    {
        $imported = 0;
        $skipped = 0;

        foreach ($jobs as $job) {
            $post_id = MJB_Job_Importer::import_job($job, $args);
            if ($post_id) {
                $imported++;
            } else {
                $skipped++;
            }
        }

        return array(
            'imported' => $imported,
            'skipped' => $skipped,
        );
    }

    /**
     * Fetch a remote feed URL and import jobs.
     *
     * @param string $url
     * @param array  $args
     * @return array{imported:int, skipped:int}|WP_Error
     */
    public static function import_from_url($url, $args = array())
    {
        $url = esc_url_raw($url);
        if ($url === '' || !wp_http_validate_url($url)) {
            return new WP_Error('mjb_invalid_url', __('Please provide a valid feed URL.', 'modern-job-board'));
        }

        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'redirection' => 3,
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $code = intval(wp_remote_retrieve_response_code($response));
        if ($code < 200 || $code >= 300) {
            return new WP_Error('mjb_feed_fetch_failed', __('Unable to fetch XML feed.', 'modern-job-board'));
        }

        $body = wp_remote_retrieve_body($response);
        $parsed = self::parse_xml_string($body);
        if (is_wp_error($parsed)) {
            return $parsed;
        }

        return self::import_jobs($parsed, $args);
    }

    /**
     * Parse a single RSS item into normalized job data.
     *
     * @param SimpleXMLElement $item
     * @return array<string, mixed>
     */
    public static function parse_rss_item($item)
    {
        $namespaces = $item->getNamespaces(true);
        $content_ns = isset($namespaces['content']) ? $item->children($namespaces['content']) : null;
        $mjb_ns = isset($namespaces['mjb']) ? $item->children($namespaces['mjb']) : $item->children(self::MJB_NS);

        $title = isset($item->title) ? sanitize_text_field((string) $item->title) : '';
        $description = isset($item->description) ? (string) $item->description : '';
        $content = ($content_ns && isset($content_ns->encoded)) ? (string) $content_ns->encoded : $description;

        $link = isset($item->link) ? esc_url_raw((string) $item->link) : '';
        $guid = isset($item->guid) ? sanitize_text_field((string) $item->guid) : '';
        $external_id = $guid !== '' ? $guid : $link;

        $company = ($mjb_ns && isset($mjb_ns->company)) ? (string) $mjb_ns->company : '';
        $location = ($mjb_ns && isset($mjb_ns->location)) ? (string) $mjb_ns->location : '';
        $type = ($mjb_ns && isset($mjb_ns->jobType)) ? (string) $mjb_ns->jobType : '';
        $featured = ($mjb_ns && isset($mjb_ns->featured)) ? ((string) $mjb_ns->featured === '1') : false;

        if ($company === '' && isset($item->author)) {
            $company = (string) $item->author;
        }

        return array(
            'title' => $title,
            'description' => $description,
            'content' => $content,
            'company' => sanitize_text_field($company),
            'location' => sanitize_text_field($location),
            'type' => sanitize_text_field($type),
            'featured' => $featured,
            'external_id' => sanitize_text_field($external_id),
            'source_url' => $link,
        );
    }
}