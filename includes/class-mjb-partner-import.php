<?php
/**
 * Partner backfill / import marketplace hooks (#14).
 * Extends existing importer + scheduler with partner feed registry.
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Partner_Import
{
    const OPTION = 'mjb_partner_feeds';

    /**
     * Bootstrap.
     */
    public static function init()
    {
        add_action('admin_menu', array(__CLASS__, 'admin_menu'), 59);
        add_action('mjb_daily_cron_event', array(__CLASS__, 'run_partner_feeds'), 20);
        add_action('admin_post_mjb_save_partner_feed', array(__CLASS__, 'save_feed'));
    }

    /**
     * @return array<int, array{name:string,url:string,enabled:bool}>
     */
    public static function get_feeds()
    {
        $feeds = get_option(self::OPTION, array());
        return is_array($feeds) ? $feeds : array();
    }

    /**
     * Cron: pull enabled partner feeds via existing importer if available.
     */
    public static function run_partner_feeds()
    {
        if (!class_exists('MJB_Job_Importer') && !class_exists('MJB_XML_Importer')) {
            return;
        }
        foreach (self::get_feeds() as $feed) {
            if (empty($feed['enabled']) || empty($feed['url'])) {
                continue;
            }
            $url = esc_url_raw($feed['url']);
            if ($url === '') {
                continue;
            }
            /**
             * Partners can hook custom import logic.
             *
             * @param string $url
             * @param array  $feed
             */
            do_action('mjb_partner_import_feed', $url, $feed);

            // Best-effort: if XML importer exposes a static entrypoint, call it.
            if (class_exists('MJB_XML_Importer') && method_exists('MJB_XML_Importer', 'import_from_url')) {
                MJB_XML_Importer::import_from_url($url);
            }
        }
    }

    /**
     * Admin.
     */
    public static function admin_menu()
    {
        add_submenu_page(
            'edit.php?post_type=job_listing',
            __('Partner feeds', 'modern-job-board'),
            __('Partner feeds', 'modern-job-board'),
            'manage_options',
            'mjb-partner-feeds',
            array(__CLASS__, 'render_admin')
        );
    }

    /**
     * UI.
     */
    public static function render_admin()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        $feeds = self::get_feeds();
        echo '<div class="wrap"><h1>' . esc_html__('Partner import feeds', 'modern-job-board') . '</h1>';
        echo '<p>' . esc_html__('Register partner XML/JSON feed URLs. They run on the daily board cron.', 'modern-job-board') . '</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="mjb_save_partner_feed">';
        wp_nonce_field('mjb_save_partner_feed');
        echo '<table class="form-table"><tr><th>' . esc_html__('Name', 'modern-job-board') . '</th><td><input name="name" class="regular-text" required></td></tr>';
        echo '<tr><th>' . esc_html__('Feed URL', 'modern-job-board') . '</th><td><input type="url" name="url" class="large-text" required></td></tr>';
        echo '<tr><th></th><td><label><input type="checkbox" name="enabled" value="1" checked> ' . esc_html__('Enabled', 'modern-job-board') . '</label></td></tr></table>';
        submit_button(__('Add feed', 'modern-job-board'));
        echo '</form>';
        echo '<h2>' . esc_html__('Registered feeds', 'modern-job-board') . '</h2><ul>';
        if (empty($feeds)) {
            echo '<li>' . esc_html__('None yet.', 'modern-job-board') . '</li>';
        } else {
            foreach ($feeds as $f) {
                echo '<li><strong>' . esc_html($f['name']) . '</strong> — <code>' . esc_html($f['url']) . '</code> (' . (!empty($f['enabled']) ? 'on' : 'off') . ')</li>';
            }
        }
        echo '</ul></div>';
    }

    /**
     * Save feed.
     */
    public static function save_feed()
    {
        if (!current_user_can('manage_options') || !isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'mjb_save_partner_feed')) {
            wp_die(esc_html__('Unauthorized', 'modern-job-board'));
        }
        $feeds = self::get_feeds();
        $feeds[] = array(
            'name' => isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '',
            'url' => isset($_POST['url']) ? esc_url_raw(wp_unslash($_POST['url'])) : '',
            'enabled' => !empty($_POST['enabled']),
        );
        update_option(self::OPTION, $feeds, false);
        wp_safe_redirect(admin_url('edit.php?post_type=job_listing&page=mjb-partner-feeds'));
        exit;
    }
}
