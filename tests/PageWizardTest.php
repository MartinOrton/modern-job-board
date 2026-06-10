<?php

use PHPUnit\Framework\TestCase;

class PageWizardTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['mjb_test_options'] = array();
        $GLOBALS['mjb_test_posts'] = array();
        $GLOBALS['mjb_test_post_content'] = array();
        $GLOBALS['mjb_test_post_status'] = array();
        $GLOBALS['mjb_test_post_types'] = array();
        $GLOBALS['mjb_test_inserted_posts'] = array();
        $GLOBALS['mjb_test_next_post_id'] = 3000;
        $GLOBALS['mjb_test_permalinks'] = array();
    }

    public function test_get_page_definitions_includes_all_shortcodes()
    {
        $definitions = MJB_Page_Wizard::get_page_definitions();
        $shortcodes = array_column($definitions, 'shortcode');

        $this->assertContains('mjb_jobs', $shortcodes);
        $this->assertContains('mjb_dashboard', $shortcodes);
        $this->assertContains('mjb_job_form', $shortcodes);
        $this->assertContains('mjb_candidate_dashboard', $shortcodes);
        $this->assertContains('mjb_employer_registration', $shortcodes);
        $this->assertContains('mjb_candidate_registration', $shortcodes);
        $this->assertCount(6, $definitions);
    }

    public function test_create_missing_pages_creates_only_missing_pages()
    {
        $GLOBALS['mjb_test_posts'] = array(42);
        $GLOBALS['mjb_test_post_status'][42] = 'publish';
        $GLOBALS['mjb_test_post_types'][42] = 'page';
        $GLOBALS['mjb_test_post_content'][42] = '[mjb_jobs]';
        $GLOBALS['mjb_test_options']['mjb_jobs_page_id'] = 42;

        $result = MJB_Page_Wizard::create_missing_pages();

        $this->assertSame(1, $result['existing']);
        $this->assertSame(5, $result['created']);
        $this->assertSame(42, $GLOBALS['mjb_test_options']['mjb_jobs_page_id']);
        $this->assertArrayHasKey('mjb_employer_dashboard_page_id', $GLOBALS['mjb_test_options']);
    }

    public function test_has_missing_pages_detects_unconfigured_shortcode_pages()
    {
        $this->assertTrue(MJB_Page_Wizard::has_missing_pages());

        $GLOBALS['mjb_test_posts'] = array(90, 91, 92, 93, 94, 95);
        foreach ($GLOBALS['mjb_test_posts'] as $index => $page_id) {
            $GLOBALS['mjb_test_post_status'][$page_id] = 'publish';
            $GLOBALS['mjb_test_post_types'][$page_id] = 'page';
        }

        $definitions = MJB_Page_Wizard::get_page_definitions();
        foreach ($definitions as $index => $definition) {
            $page_id = $GLOBALS['mjb_test_posts'][$index];
            $GLOBALS['mjb_test_post_content'][$page_id] = '[' . $definition['shortcode'] . ']';
            $GLOBALS['mjb_test_options'][$definition['option_key']] = $page_id;
        }

        $this->assertFalse(MJB_Page_Wizard::has_missing_pages());
    }

    public function test_page_resolver_option_map_includes_jobs_page()
    {
        $map = MJB_Page_Resolver::get_option_map();

        $this->assertArrayHasKey('mjb_jobs', $map);
        $this->assertSame('mjb_jobs_page_id', $map['mjb_jobs']);
    }
}