<?php

use PHPUnit\Framework\TestCase;

class AdminTabsTest extends TestCase
{
    public function test_get_tabs_includes_core_sections()
    {
        $tabs = MJB_Admin_Tabs::get_tabs();

        $this->assertArrayHasKey('dashboard', $tabs);
        $this->assertArrayHasKey('jobs', $tabs);
        $this->assertArrayHasKey('applications', $tabs);
        $this->assertArrayHasKey('settings', $tabs);
        $this->assertArrayHasKey('tools', $tabs);
        $this->assertSame('dashboard', MJB_Admin_Tabs::get_default_tab());
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
}