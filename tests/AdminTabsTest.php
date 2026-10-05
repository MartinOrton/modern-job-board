<?php

use PHPUnit\Framework\TestCase;

if (!function_exists('settings_errors')) {
    function settings_errors($setting = '', $sanitize = false, $hide_on_update = false)
    {
        unset($setting, $sanitize, $hide_on_update);
    }
}

if (!function_exists('settings_fields')) {
    function settings_fields($option_group)
    {
        echo '<input type="hidden" name="option_page" value="' . esc_attr((string) $option_group) . '" />';
    }
}

if (!function_exists('do_settings_fields')) {
    function do_settings_fields($page, $section)
    {
        unset($page, $section);
    }
}

if (!function_exists('do_settings_sections')) {
    function do_settings_sections($page)
    {
        unset($page);
    }
}

if (!function_exists('submit_button')) {
    function submit_button($text = null, $type = 'primary', $name = 'submit', $wrap = true, $other_attributes = null)
    {
        unset($other_attributes);
        $value = $text ? (string) $text : 'Save Changes';
        $html = '<input type="submit" name="' . esc_attr((string) $name) . '" class="button button-' . esc_attr((string) $type) . '" value="' . esc_attr($value) . '" />';
        echo $wrap ? '<p class="submit">' . $html . '</p>' : $html;
    }
}

class AdminTabsTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['mjb_test_current_user_id'] = 0;
        $GLOBALS['mjb_test_user_caps'] = array();
        $GLOBALS['wp_settings_sections'] = array();
        $_REQUEST = array();
    }
    public function test_get_tabs_includes_core_sections()
    {
        $tabs = MJB_Admin_Tabs::get_tabs();

        $this->assertArrayHasKey('dashboard', $tabs);
        $this->assertArrayHasKey('jobs', $tabs);
        $this->assertArrayHasKey('applications', $tabs);
        $this->assertArrayHasKey('settings', $tabs);
        $this->assertArrayHasKey('tools', $tabs);
        $this->assertSame('dashboard', MJB_Admin_Tabs::get_default_tab());
        $this->assertSame('gauge', $tabs['dashboard']['icon']);
        $this->assertSame('primary', $tabs['dashboard']['group']);
        $this->assertSame('config', $tabs['settings']['group']);
        $this->assertSame('config', $tabs['tools']['group']);
    }

    public function test_dashboard_skeleton_matches_recruiter_overview()
    {
        $admin = MJB_Admin_Tabs::get_tab_skeleton_html('dashboard');
        $recruiter = MJB_Dashboard::get_tab_skeleton_html('overview');

        $this->assertSame($recruiter, $admin);
        $this->assertStringContainsString('mjb-skeleton-dashboard', $admin);
        $this->assertStringContainsString('mjb-skeleton-stat', $admin);
        $this->assertStringContainsString('mjb-skeleton-chart', $admin);
    }

    public function test_jobs_skeleton_matches_recruiter_list()
    {
        $admin = MJB_Admin_Tabs::get_tab_skeleton_html('jobs');
        $recruiter = MJB_Dashboard::get_tab_skeleton_html('jobs');

        $this->assertSame($recruiter, $admin);
        $this->assertStringContainsString('mjb-skeleton-row', $admin);
        $this->assertStringContainsString('mjb-skeleton-toolbar', $admin);
    }

    public function test_sanitize_tab_falls_back_to_dashboard()
    {
        $this->assertSame('dashboard', MJB_Admin_Tabs::sanitize_tab(''));
        $this->assertSame('dashboard', MJB_Admin_Tabs::sanitize_tab('not-a-real-tab'));
        $this->assertSame('settings', MJB_Admin_Tabs::sanitize_tab('settings'));
    }

    public function test_is_valid_tab()
    {
        $this->assertTrue(MJB_Admin_Tabs::is_valid_tab('custom-fields'));
        $this->assertFalse(MJB_Admin_Tabs::is_valid_tab('invalid'));
    }

    public function test_paged_tabs_flagged_correctly()
    {
        $tabs = MJB_Admin_Tabs::get_tabs();

        $this->assertTrue($tabs['jobs']['paged']);
        $this->assertTrue($tabs['applications']['paged']);
        $this->assertFalse($tabs['dashboard']['paged']);
        $this->assertFalse($tabs['setup']['paged']);
    }

    public function test_post_type_tab_map_covers_mjb_cpts()
    {
        $map = MJB_Admin_Tabs::get_post_type_tab_map();

        $this->assertSame('jobs', $map['job_listing']);
        $this->assertSame('applications', $map['job_application']);
        $this->assertSame('companies', $map['company']);
        $this->assertSame('resumes', $map['mjb_resume']);
    }

    public function test_get_tab_menu_slug()
    {
        $this->assertSame('modern-job-board', MJB_Admin_Tabs::get_tab_menu_slug('dashboard'));
        $this->assertSame(
            'admin.php?page=modern-job-board&tab=jobs',
            MJB_Admin_Tabs::get_tab_menu_slug('jobs')
        );
    }

    public function test_get_tab_url_includes_page_and_tab()
    {
        $url = MJB_Admin_Tabs::get_tab_url('settings');

        $this->assertStringContainsString('page=modern-job-board', $url);
        $this->assertStringContainsString('tab=settings', $url);
    }

    public function test_settings_section_slug()
    {
        $this->assertSame('license', MJB_Admin_Tabs::settings_section_slug('mjb_license_section'));
        $this->assertSame('board_mode', MJB_Admin_Tabs::settings_section_slug('mjb_board_mode_section'));
        $this->assertSame('listing', MJB_Admin_Tabs::settings_section_slug('mjb_listing_section'));
    }

    public function test_get_tab_url_supports_settings_subtab()
    {
        $url = MJB_Admin_Tabs::get_tab_url('settings', array('settings_tab' => 'monetization'));

        $this->assertStringContainsString('tab=settings', $url);
        $this->assertStringContainsString('settings_tab=monetization', $url);
    }

    public function test_active_admin_subtab_is_not_a_link()
    {
        ob_start();
        MJB_Admin_Tabs::render_admin_subtab(array(
            'active' => true,
            'label' => 'License',
            'class' => 'mjb-settings-subtab mjb-admin-subtab',
            'id' => 'mjb-settings-tab-license',
            'data_attr' => 'data-settings-tab',
            'data_value' => 'license',
            'aria_controls' => 'mjb-settings-panel-license',
        ));
        $active = ob_get_clean();

        $this->assertMatchesRegularExpression('/^<span class="mjb-settings-subtab mjb-admin-subtab is-active"/', $active);
        $this->assertStringContainsString('data-settings-tab="license"', $active);
        $this->assertStringContainsString('aria-selected="true"', $active);
        $this->assertStringNotContainsString('<a ', $active);
        $this->assertStringNotContainsString('<button', $active);
        $this->assertStringNotContainsString('href=', $active);

        ob_start();
        MJB_Admin_Tabs::render_admin_subtab(array(
            'active' => false,
            'label' => 'Listings',
            'class' => 'mjb-settings-subtab mjb-admin-subtab',
            'id' => 'mjb-settings-tab-listing',
            'data_attr' => 'data-settings-tab',
            'data_value' => 'listing',
        ));
        $idle = ob_get_clean();

        $this->assertMatchesRegularExpression('/^<button type="button" class="mjb-settings-subtab mjb-admin-subtab"/', $idle);
        $this->assertStringContainsString('aria-selected="false"', $idle);
        $this->assertStringNotContainsString('is-active', $idle);
        $this->assertStringNotContainsString('href=', $idle);
    }

    public function test_admin_menu_icon_uses_bundled_white_svg()
    {
        $icon = MJB_Admin::get_admin_menu_icon();
        $prefix = 'data:image/svg+xml;base64,';

        $this->assertStringStartsWith($prefix, $icon);
        $decoded = base64_decode(substr($icon, strlen($prefix)), true);
        $this->assertIsString($decoded);
        $this->assertStringContainsString('viewBox="0 0 249 249"', $decoded);
        $this->assertStringContainsString('fill="#fff"', $decoded);
    }

    public function test_settings_tab_uses_jobs_console_chrome()
    {
        $this->asAdmin();
        $this->seedSettingsSections();

        $html = MJB_Admin_Tabs::render_tab('settings');

        $this->assertStringContainsString('mjb-tab-panel--settings', $html);
        $this->assertStringContainsString('mjb-jobs', $html);
        $this->assertStringContainsString('mjb-settings', $html);
        $this->assertStringContainsString('class="mjb-sec mjb-jobs__sec"', $html);
        $this->assertStringContainsString('>Settings</h2>', $html);
        $this->assertStringContainsString('class="mjb-jobs-views mjb-settings-subtabs"', $html);
        $this->assertStringContainsString('class="mjb-panel mjb-jobs-panel mjb-settings-panel"', $html);
        $this->assertStringContainsString('class="mjb-jobs-foot"', $html);
        $this->assertStringContainsString('Save Settings', $html);
        $this->assertStringContainsString('id="mjb-settings-form"', $html);
        $this->assertStringNotContainsString('mjb-section-title', $html);
        $this->assertStringNotContainsString('mjb-admin-subtabs', $html);
    }

    public function test_settings_subtabs_are_jobs_view_pills()
    {
        $this->asAdmin();
        $this->seedSettingsSections();
        $_REQUEST['settings_tab'] = 'listing';

        $html = MJB_Admin_Tabs::render_tab('settings');

        $this->assertMatchesRegularExpression(
            '/<span class="mjb-view-pill mjb-settings-subtab[^"]*is-active"[^>]*data-settings-tab="listing"/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/<button type="button" class="mjb-view-pill mjb-settings-subtab"[^>]*data-settings-tab="license"/',
            $html
        );
        $this->assertStringContainsString('aria-selected="true"', $html);
        $this->assertStringContainsString('mjb-settings-section is-active', $html);
        $this->assertStringContainsString('id="mjb-settings-panel-listing"', $html);
        $this->assertStringContainsString('class="mjb-panel__head"', $html);
        $this->assertStringContainsString('Listing Settings', $html);
    }

    public function test_settings_panel_css_matches_jobs_chrome()
    {
        $css = file_get_contents(dirname(__DIR__) . '/assets/css/mjb-admin-jobs.css');
        $this->assertNotFalse($css);
        $stripped = preg_replace('/\/\*.*?\*\//s', '', $css);

        $this->assertMatchesRegularExpression(
            '/\.mjb-settings \.mjb-jobs-panel\s*\{[^}]*overflow:\s*visible/s',
            $stripped
        );
        $this->assertMatchesRegularExpression(
            '/\.mjb-settings \.mjb-settings-form \.form-table\s*\{[^}]*border:\s*0/s',
            $stripped
        );
        $this->assertMatchesRegularExpression(
            '/\.mjb-settings \.mjb-view-pill:hover:not\(\[aria-selected="true"\]\)\s*\{[^}]*background:\s*#fff/s',
            $stripped
        );
        $this->assertDoesNotMatchRegularExpression(
            '/\.mjb-settings \.mjb-view-pill:hover:not\(\[aria-selected="true"\]\)\s*\{[^}]*translateY/s',
            $stripped
        );
    }

    public function test_settings_spacing_matches_jobs_rhythm()
    {
        $css = file_get_contents(dirname(__DIR__) . '/assets/css/mjb-admin-jobs.css');
        $this->assertNotFalse($css);
        $stripped = preg_replace('/\/\*.*?\*\//s', '', $css);

        $this->assertMatchesRegularExpression(
            '/\.mjb-settings \.mjb-jobs-views\s*\{[^}]*margin-bottom:\s*14px/s',
            $stripped,
            'Pills-to-panel gap must match Jobs toolbar-to-panel (14px).'
        );
        $this->assertMatchesRegularExpression(
            '/\.mjb-settings \.mjb-panel__head\s*\{[^}]*padding:\s*16px 20px/s',
            $stripped
        );
        $this->assertMatchesRegularExpression(
            '/\.mjb-settings \.mjb-settings-section__intro\s*\{[^}]*padding:\s*16px 20px/s',
            $stripped
        );
        $this->assertMatchesRegularExpression(
            '/\.mjb-settings \.mjb-settings-form \.form-table th,\s*\.mjb-settings \.mjb-settings-form \.form-table td\s*\{[^}]*padding:\s*20px/s',
            $stripped
        );
        $this->assertMatchesRegularExpression(
            '/\.mjb-settings \.mjb-settings-form \.form-table tr:first-child th,\s*\.mjb-settings \.mjb-settings-form \.form-table tr:first-child td\s*\{[^}]*border-top:\s*0/s',
            $stripped,
            'First field row must not double the panel-head divider.'
        );
        $this->assertMatchesRegularExpression(
            '/\.mjb-settings \.mjb-jobs-foot p\.submit\s*\{[^}]*margin:\s*0/s',
            $stripped
        );
        $this->assertMatchesRegularExpression(
            '/\.mjb-settings \.mjb-jobs-foot p\.submit\s*\{[^}]*padding:\s*0/s',
            $stripped
        );
        $this->assertMatchesRegularExpression(
            '/\.mjb-settings \.mjb-jobs-foot\s*\{[^}]*padding:\s*16px 20px/s',
            $stripped
        );
    }

    public function test_settings_selects_match_jobs_filter_chrome()
    {
        $css = file_get_contents(dirname(__DIR__) . '/assets/css/mjb-admin-jobs.css');
        $this->assertNotFalse($css);
        $stripped = preg_replace('/\/\*.*?\*\//s', '', $css);

        $this->assertMatchesRegularExpression(
            '/\.mjb-settings \.mjb-settings-form select\s*\{[^}]*border:\s*1px solid var\(--mjb-line\)/s',
            $stripped
        );
        $this->assertMatchesRegularExpression(
            '/\.mjb-settings \.mjb-settings-form select:hover\s*\{[^}]*border-color:\s*var\(--mjb-teal-dark\)/s',
            $stripped
        );
        $this->assertDoesNotMatchRegularExpression(
            '/\.mjb-settings \.mjb-settings-form select:hover\s*\{[^}]*box-shadow:\s*0 /s',
            $stripped
        );
        $this->assertDoesNotMatchRegularExpression(
            '/\.mjb-settings \.mjb-settings-form select:focus\s*\{[^}]*box-shadow:\s*0 0 0 3px/s',
            $stripped
        );
        $this->assertMatchesRegularExpression(
            '/\.mjb-settings \.mjb-settings-form input\[type="text"\]:focus[^}]*border-color:\s*var\(--mjb-teal-dark\)/s',
            $stripped
        );
        $this->assertDoesNotMatchRegularExpression(
            '/\.mjb-settings \.mjb-settings-form input\[type="text"\]:focus[^}]*box-shadow:\s*0 0 0 3px/s',
            $stripped
        );
        $this->assertMatchesRegularExpression(
            '/\.mjb-settings \.mjb-jobs-filter__btn\[aria-expanded="true"\]\s*\{[^}]*border-color:\s*var\(--mjb-teal-dark\)/s',
            $stripped
        );
        $this->assertMatchesRegularExpression(
            '/\.mjb-settings \.mjb-settings-select \.mjb-jobs-filter__btn:hover\s*\{[^}]*border-color:\s*var\(--mjb-teal-dark\)/s',
            $stripped
        );
        $this->assertMatchesRegularExpression(
            '/\.mjb-settings \.mjb-jobs-menu button:hover[^}]*background(?:-color)?:\s*var\(--mjb-teal-tint-2\)/s',
            $stripped
        );

        $js = file_get_contents(dirname(__DIR__) . '/assets/js/mjb-admin-settings.js');
        $this->assertNotFalse($js);
        $this->assertStringContainsString('mjb-settings-select', $js);
        $this->assertStringContainsString('mjb-jobs-menu', $js);
        $this->assertStringContainsString('mjb-jobs-filter__btn', $js);
    }

    public function test_settings_map_dropdowns_stack_above_later_rows()
    {
        $css = file_get_contents(dirname(__DIR__) . '/assets/css/mjb-admin-jobs.css');
        $this->assertNotFalse($css);
        $stripped = preg_replace('/\/\*.*?\*\//s', '', $css);

        $this->assertMatchesRegularExpression(
            '/\.mjb-settings table\.widefat\s*\{[^}]*overflow:\s*visible/s',
            $stripped,
            'Nested Integrations map table must not clip open menus.'
        );
        $this->assertMatchesRegularExpression(
            '/\.mjb-settings \.mjb-settings-select\.is-open\s*\{[^}]*z-index:\s*50/s',
            $stripped
        );
        $this->assertMatchesRegularExpression(
            '/\.mjb-settings \.mjb-settings-map tbody tr\.is-open\s*\{[^}]*z-index:\s*40/s',
            $stripped
        );

        $js = file_get_contents(dirname(__DIR__) . '/assets/js/mjb-admin-settings.js');
        $this->assertNotFalse($js);
        $this->assertStringContainsString("classList.toggle('is-open'", $js);

        $php = file_get_contents(dirname(__DIR__) . '/includes/class-mjb-google-jobs.php');
        $this->assertNotFalse($php);
        $this->assertStringContainsString('mjb-settings-map', $php);
    }

    public function test_settings_field_stack_has_even_gaps()
    {
        $css = file_get_contents(dirname(__DIR__) . '/assets/css/mjb-admin-jobs.css');
        $this->assertNotFalse($css);
        $stripped = preg_replace('/\/\*.*?\*\//s', '', $css);

        $this->assertMatchesRegularExpression(
            '/\.mjb-settings-stack\s*\{[^}]*gap:\s*16px/s',
            $stripped
        );
        $this->assertMatchesRegularExpression(
            '/\.mjb-settings-field\s*\{[^}]*gap:\s*6px/s',
            $stripped
        );
        $this->assertMatchesRegularExpression(
            '/\.mjb-settings \.mjb-purchase-cta\s*\{[^}]*gap:\s*8px/s',
            $stripped
        );
        $this->assertMatchesRegularExpression(
            '/\.mjb-settings-check\s*\{[^}]*gap:\s*8px/s',
            $stripped
        );
        $this->assertMatchesRegularExpression(
            '/\.mjb-settings \.mjb-settings-map td\s*\{[^}]*padding:\s*12px 16px/s',
            $stripped
        );
        $this->assertMatchesRegularExpression(
            '/\.mjb-settings \.mjb-settings-form \.form-table th\s*\{[^}]*padding-right:\s*28px/s',
            $stripped
        );

        $php = file_get_contents(dirname(__DIR__) . '/includes/class-mjb-google-jobs.php');
        $this->assertNotFalse($php);
        $this->assertStringContainsString('mjb-settings-stack', $php);
        $this->assertStringContainsString('mjb-settings-field', $php);
        $this->assertStringContainsString('mjb-settings-check', $php);
    }

    /**
     * @return void
     */
    private function asAdmin()
    {
        $GLOBALS['mjb_test_current_user_id'] = 1;
        $GLOBALS['mjb_test_user_caps'][1]['manage_options'] = true;
    }

    /**
     * @return void
     */
    private function seedSettingsSections()
    {
        $GLOBALS['wp_settings_sections'] = array(
            'mjb-settings' => array(
                'mjb_license_section' => array(
                    'id' => 'mjb_license_section',
                    'title' => 'License & plan',
                    'callback' => null,
                ),
                'mjb_listing_section' => array(
                    'id' => 'mjb_listing_section',
                    'title' => 'Listing Settings',
                    'callback' => null,
                ),
            ),
        );
    }
}