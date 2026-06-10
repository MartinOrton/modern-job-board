<?php
/**
 * Modern Job Board Feeds (XML)
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Feeds
{
    const FEED_LIMIT = 100;

    /**
     * Initialize Feeds.
     */
    public function init()
    {
        add_action('init', array($this, 'add_feed_rule'));
        add_action('do_feed_job_listings', array($this, 'render_job_feed'));
    }

    /**
     * Add Feed Rule.
     */
    public function add_feed_rule()
    {
        add_feed('job-listings', array($this, 'render_job_feed'));
    }

    /**
     * Build feed query args.
     *
     * @return array
     */
    public static function build_feed_query_args()
    {
        return MJB_Search::build_query_args(array(), array(
            'posts_per_page' => self::FEED_LIMIT,
        ));
    }

    /**
     * Render Job Feed (XML).
     */
    public function render_job_feed()
    {
        header('Content-Type: application/xml; charset=UTF-8');
        echo '<?xml version="1.0" encoding="UTF-8"?>';
        ?>
        <rss version="2.0"
            xmlns:content="http://purl.org/rss/1.0/modules/content/"
            xmlns:dc="http://purl.org/dc/elements/1.1/"
            xmlns:atom="http://www.w3.org/2005/Atom"
            xmlns:mjb="https://github.com/MartinOrton/modern-job-board/ns/feed/1.0">
            <channel>
                <title><?php echo get_bloginfo_rss('name'); ?> - Job Listings</title>
                <atom:link href="<?php self_link(); ?>" rel="self" type="application/rss+xml" />
                <link><?php bloginfo_rss('url'); ?></link>
                <description><?php bloginfo_rss('description'); ?></description>
                <lastBuildDate><?php echo mysql2date('D, d M Y H:i:s +0000', get_lastpostmodified('GMT'), false); ?>
                </lastBuildDate>
                <language><?php bloginfo_rss('language'); ?></language>
                <?php
                $query = new WP_Query(self::build_feed_query_args());

                if ($query->have_posts()) :
                    while ($query->have_posts()) :
                        $query->the_post();
                        $post_id = get_the_ID();
                        $company_name = get_post_meta($post_id, '_company_name', true);
                        $location_terms = wp_get_post_terms($post_id, 'job_location', array('fields' => 'names'));
                        $type_terms = wp_get_post_terms($post_id, 'job_type', array('fields' => 'names'));
                        $location = !empty($location_terms) ? $location_terms[0] : '';
                        $type = !empty($type_terms) ? $type_terms[0] : '';
                        $application_url = get_permalink($post_id);
                        ?>
                        <item>
                            <title><?php the_title_rss(); ?></title>
                            <link><?php the_permalink_rss(); ?></link>
                            <guid isPermaLink="true"><?php the_permalink_rss(); ?></guid>
                            <pubDate><?php echo mysql2date('D, d M Y H:i:s +0000', get_post_time('Y-m-d H:i:s', true), false); ?>
                            </pubDate>
                            <dc:creator><![CDATA[<?php the_author(); ?>]]></dc:creator>
                            <description><![CDATA[<?php the_excerpt_rss(); ?>]]></description>
                            <content:encoded><![CDATA[<?php the_content_feed('rss2'); ?>]]></content:encoded>
                            <mjb:company><![CDATA[<?php echo esc_html($company_name); ?>]]></mjb:company>
                            <mjb:location><![CDATA[<?php echo esc_html($location); ?>]]></mjb:location>
                            <mjb:jobType><![CDATA[<?php echo esc_html($type); ?>]]></mjb:jobType>
                            <mjb:applyUrl><![CDATA[<?php echo esc_url($application_url); ?>]]></mjb:applyUrl>
                            <mjb:featured><?php echo get_post_meta($post_id, '_featured', true) ? '1' : '0'; ?></mjb:featured>
                        </item>
                        <?php
                    endwhile;
                    wp_reset_postdata();
                endif;
                ?>
            </channel>
        </rss>
        <?php
    }
}