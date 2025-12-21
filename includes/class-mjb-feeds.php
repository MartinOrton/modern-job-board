<?php
/**
 * Modern Job Board Feeds (XML)
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Feeds
{
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
     * Render Job Feed (XML).
     */
    public function render_job_feed()
    {
        header('Content-Type: application/xml; charset=UTF-8');
        echo '<?xml version="1.0" encoding="UTF-8"?>';
        ?>
        <rss version="2.0" xmlns:content="http://purl.org/rss/1.0/modules/content/"
            xmlns:wfw="http://wellformedweb.org/CommentAPI/" xmlns:dc="http://purl.org/dc/elements/1.1/"
            xmlns:atom="http://www.w3.org/2005/Atom" xmlns:sy="http://purl.org/rss/1.0/modules/syndication/"
            xmlns:slash="http://purl.org/rss/1.0/modules/slash/">
            <channel>
                <title><?php echo get_bloginfo_rss('name'); ?> - Job Listings</title>
                <atom:link href="<?php self_link(); ?>" rel="self" type="application/rss+xml" />
                <link><?php bloginfo_rss('url'); ?></link>
                <description><?php bloginfo_rss('description'); ?></description>
                <lastBuildDate><?php echo mysql2date('D, d M Y H:i:s +0000', get_lastpostmodified('GMT'), false); ?>
                </lastBuildDate>
                <language><?php bloginfo_rss('language'); ?></language>
                <?php
                $args = array(
                    'post_type' => 'job_listing',
                    'post_status' => 'publish',
                    'posts_per_page' => 100, // Limit for feed
                );
                $query = new WP_Query($args);

                if ($query->have_posts()):
                    while ($query->have_posts()):
                        $query->the_post();
                        $company_name = get_post_meta(get_the_ID(), '_company_name', true);
                        $location = get_the_term_list(get_the_ID(), 'job_location', '', ', ');
                        $type = get_the_term_list(get_the_ID(), 'job_type', '', ', ');
                        ?>
                        <item>
                            <title><?php the_title_rss(); ?></title>
                            <link><?php the_permalink_rss(); ?></link>
                            <guid isPermaLink="false"><?php the_guid(); ?></guid>
                            <pubDate><?php echo mysql2date('D, d M Y H:i:s +0000', get_post_time('Y-m-d H:i:s', true), false); ?>
                            </pubDate>
                            <dc:creator><![CDATA[<?php the_author(); ?>]]></dc:creator>
                            <description><![CDATA[<?php the_excerpt_rss(); ?>]]></description>
                            <content:encoded><![CDATA[<?php the_content_feed('rss2'); ?>]]></content:encoded>
                            <jobbranch:company><![CDATA[<?php echo esc_html($company_name); ?>]]></jobbranch:company>
                            <jobbranch:location><![CDATA[<?php echo strip_tags($location); ?>]]></jobbranch:location>
                            <jobbranch:type><![CDATA[<?php echo strip_tags($type); ?>]]></jobbranch:type>
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
