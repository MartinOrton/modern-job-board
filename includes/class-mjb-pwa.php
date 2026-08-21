<?php
/**
 * Lightweight PWA support (#42) — web app manifest + basic service worker.
 */

if (!defined('ABSPATH')) {
    exit;
}

class MJB_Pwa
{
    const OPTION = 'mjb_pwa_enabled';

    /**
     * Bootstrap.
     */
    public static function init()
    {
        if (get_option(self::OPTION, '0') !== '1') {
            // Still register rewrite so enabling later works after flush.
        }
        add_action('init', array(__CLASS__, 'register_rewrite'));
        add_filter('query_vars', array(__CLASS__, 'query_vars'));
        add_action('template_redirect', array(__CLASS__, 'serve'), 0);
        add_action('wp_head', array(__CLASS__, 'manifest_link'), 5);
        add_action('admin_init', array(__CLASS__, 'register_setting'));
    }

    /**
     * @param array $vars
     * @return array
     */
    public static function query_vars($vars)
    {
        $vars[] = 'mjb_pwa';
        return $vars;
    }

    /**
     * Rewrites.
     */
    public static function register_rewrite()
    {
        add_rewrite_rule('^mjb-manifest\\.json$', 'index.php?mjb_pwa=manifest', 'top');
        add_rewrite_rule('^mjb-sw\\.js$', 'index.php?mjb_pwa=sw', 'top');
    }

    /**
     * Setting checkbox (registered into main settings via option).
     */
    public static function register_setting()
    {
        register_setting('mjb_settings_group', self::OPTION, array(
            'type' => 'string',
            'default' => '0',
            'sanitize_callback' => function ($v) {
                return $v === '1' || $v === 1 || $v === true || $v === 'on' ? '1' : '0';
            },
        ));
    }

    /**
     * Link tag when enabled.
     */
    public static function manifest_link()
    {
        if (get_option(self::OPTION, '0') !== '1') {
            return;
        }
        echo '<link rel="manifest" href="' . esc_url(home_url('/mjb-manifest.json')) . '">' . "\n";
        echo '<meta name="theme-color" content="#0f766e">' . "\n";
        echo '<script>if("serviceWorker" in navigator){navigator.serviceWorker.register(' . wp_json_encode(home_url('/mjb-sw.js')) . ').catch(function(){});}</script>' . "\n";
    }

    /**
     * Serve manifest / SW.
     */
    public static function serve()
    {
        $mode = get_query_var('mjb_pwa', '');
        if ($mode === 'manifest') {
            nocache_headers();
            header('Content-Type: application/manifest+json; charset=UTF-8');
            $name = get_bloginfo('name');
            echo wp_json_encode(array(
                'name' => $name,
                'short_name' => wp_trim_words($name, 3, ''),
                'start_url' => home_url('/jobs/'),
                'display' => 'standalone',
                'background_color' => '#ffffff',
                'theme_color' => '#0f766e',
                'icons' => array(),
            ));
            exit;
        }
        if ($mode === 'sw') {
            nocache_headers();
            header('Content-Type: application/javascript; charset=UTF-8');
            header('Service-Worker-Allowed: /');
            // Minimal offline shell cache for jobs listing assets.
            echo "const CACHE='mjb-pwa-v1';\n";
            echo "self.addEventListener('install',e=>{e.waitUntil(caches.open(CACHE).then(c=>c.addAll(['/jobs/']).catch(()=>{})));self.skipWaiting();});\n";
            echo "self.addEventListener('fetch',e=>{if(e.request.method!=='GET')return;e.respondWith(caches.match(e.request).then(r=>r||fetch(e.request)));});\n";
            exit;
        }
    }
}
