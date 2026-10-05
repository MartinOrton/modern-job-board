<?php

use PHPUnit\Framework\TestCase;

class AdminCompaniesTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['mjb_test_options'] = array();
        $GLOBALS['mjb_test_posts'] = array();
        $GLOBALS['mjb_test_post_types'] = array();
        $GLOBALS['mjb_test_post_status'] = array();
        $GLOBALS['mjb_test_post_meta'] = array();
        $GLOBALS['mjb_test_titles'] = array();
        $GLOBALS['mjb_test_dates'] = array();
        $GLOBALS['mjb_test_terms'] = array();
        $GLOBALS['mjb_test_permalinks'] = array();
        $GLOBALS['mjb_test_user_meta'] = array();
        $GLOBALS['mjb_test_timestamp'] = 1750000000;
        $GLOBALS['mjb_test_db_results'] = array();
        $GLOBALS['mjb_test_companies_by_title'] = array();
        $_REQUEST = array();
        MJB_Admin_Companies::reset_catalog();
    }

    public function test_sanitize_view_and_order()
    {
        $this->assertSame('pending', MJB_Admin_Companies::sanitize_view('pending'));
        $this->assertSame('no_jobs', MJB_Admin_Companies::sanitize_view('no_jobs'));
        $this->assertSame('all', MJB_Admin_Companies::sanitize_view('not-a-view'));
        $this->assertSame(20, MJB_Admin_Companies::sanitize_per_page(7));
        $this->assertSame(50, MJB_Admin_Companies::sanitize_per_page(50));
        $this->assertSame('jobs', MJB_Admin_Companies::sanitize_orderby('jobs'));
        $this->assertSame('posted', MJB_Admin_Companies::sanitize_orderby('nope'));
        $this->assertSame('asc', MJB_Admin_Companies::sanitize_order('ASC'));
    }

    public function test_get_state_honours_requested_per_page()
    {
        $_REQUEST['mjb_per'] = '10';
        $state = MJB_Admin_Companies::get_state(1);
        $this->assertSame(10, $state['per']);
        $this->assertSame('all', $state['view']);
    }

    public function test_row_matches_view_for_pending_and_no_jobs()
    {
        $now = 1750000000;
        $GLOBALS['mjb_test_post_types'][201] = 'company';
        $GLOBALS['mjb_test_post_types'][202] = 'company';
        $GLOBALS['mjb_test_post_status'][201] = 'pending';
        $GLOBALS['mjb_test_post_status'][202] = 'publish';
        $GLOBALS['mjb_test_titles'][201] = 'Pending Co';
        $GLOBALS['mjb_test_titles'][202] = 'Live Co';

        $pending = MJB_Admin_Companies::build_row(201, array(), $now);
        $live = MJB_Admin_Companies::build_row(202, array(), $now);
        $with_jobs = MJB_Admin_Companies::build_row(202, array(
            202 => array('total' => 3, 'published' => 2, 'location' => 'Cape Town'),
        ), $now);

        $this->assertTrue(MJB_Admin_Companies::row_matches_view($pending, 'attention'));
        $this->assertTrue(MJB_Admin_Companies::row_matches_view($pending, 'pending'));
        $this->assertTrue(MJB_Admin_Companies::row_matches_view($live, 'no_jobs'));
        $this->assertTrue(MJB_Admin_Companies::row_matches_view($live, 'published'));
        $this->assertFalse(MJB_Admin_Companies::row_matches_view($live, 'has_jobs'));
        $this->assertTrue(MJB_Admin_Companies::row_matches_view($with_jobs, 'has_jobs'));
        $this->assertSame('Cape Town', $with_jobs['location']);
        $this->assertSame(2, $with_jobs['jobs_published']);
    }

    public function test_search_matches_name_tagline_and_location()
    {
        $now = 1750000000;
        $GLOBALS['mjb_test_post_types'][211] = 'company';
        $GLOBALS['mjb_test_post_status'][211] = 'publish';
        $GLOBALS['mjb_test_titles'][211] = 'Harbor Health';
        $GLOBALS['mjb_test_post_meta'][211]['_company_tagline'] = 'Care first';
        $GLOBALS['mjb_test_post_meta'][211]['_company_email'] = 'hi@harbor.test';

        $row = MJB_Admin_Companies::build_row(211, array(
            211 => array('total' => 1, 'published' => 1, 'location' => 'Dublin, Leinster, Ireland'),
        ), $now);

        $state = array('view' => 'all', 'q' => 'harbor', 'location' => '');
        $this->assertTrue(MJB_Admin_Companies::row_matches_state($row, $state));
        $state['q'] = 'care';
        $this->assertTrue(MJB_Admin_Companies::row_matches_state($row, $state));
        $state['q'] = 'dublin';
        $this->assertTrue(MJB_Admin_Companies::row_matches_state($row, $state));
        $state['q'] = 'zzzz';
        $this->assertFalse(MJB_Admin_Companies::row_matches_state($row, $state));
        $state['q'] = '';
        $state['location'] = 'London';
        $this->assertFalse(MJB_Admin_Companies::row_matches_state($row, $state));
    }

    public function test_run_action_approves_pending_company()
    {
        $GLOBALS['mjb_test_post_types'][221] = 'company';
        $GLOBALS['mjb_test_post_status'][221] = 'pending';
        $GLOBALS['mjb_test_titles'][221] = 'New Employer';

        $this->assertTrue(MJB_Admin_Companies::run_action('approve', 221));
        $this->assertSame('publish', $GLOBALS['mjb_test_post_status'][221]);
    }

    public function test_render_outputs_companies_console()
    {
        $GLOBALS['mjb_test_post_types'][231] = 'company';
        $GLOBALS['mjb_test_post_types'][232] = 'company';
        $GLOBALS['mjb_test_post_status'][231] = 'publish';
        $GLOBALS['mjb_test_post_status'][232] = 'pending';
        $GLOBALS['mjb_test_titles'][231] = 'Velocity Motors';
        $GLOBALS['mjb_test_titles'][232] = 'Summit Education';
        $GLOBALS['mjb_test_post_meta'][231]['_company_tagline'] = 'Go further';
        $GLOBALS['mjb_test_post_meta'][231]['_company_website'] = 'https://www.velocity.test';
        $GLOBALS['mjb_test_posts'] = array(301);
        $GLOBALS['mjb_test_post_types'][301] = 'job_listing';
        $GLOBALS['mjb_test_post_status'][301] = 'publish';
        $GLOBALS['mjb_test_post_meta'][301]['_company_id'] = 231;

        ob_start();
        MJB_Admin_Companies::render(1);
        $html = ob_get_clean();

        $this->assertStringContainsString('mjb-tab-panel--companies', $html);
        $this->assertStringContainsString('mjb-jobs-table', $html);
        $this->assertStringContainsString('All companies', $html);
        $this->assertStringContainsString('Needs attention', $html);
        $this->assertStringContainsString('Velocity Motors', $html);
        $this->assertStringContainsString('Summit Education', $html);
        $this->assertStringContainsString('id="mjb-companies-q"', $html);
        $this->assertStringContainsString('id="mjb-companies-density"', $html);
        $this->assertStringContainsString('Comfortable', $html);
        $this->assertStringContainsString('mjb-companies-ac', $html);
        $this->assertStringContainsString('id="mjb-companies-per-btn"', $html);
        $this->assertStringContainsString('mjb-jobs-bulkbar', $html);
        $this->assertStringContainsString('Submitted by employer', $html);
        $this->assertStringContainsString('data-view="all"', $html);
        $this->assertStringContainsString('data-view="pending"', $html);
        $this->assertMatchesRegularExpression(
            '/<span class="mjb-view-pill"[^>]*data-view="all"[^>]*aria-selected="true"/',
            $html
        );
        $this->assertDoesNotMatchRegularExpression('/<button[^>]*data-view="all"/', $html);
        $this->assertDoesNotMatchRegularExpression('/<a[^>]*data-view="all"/', $html);
        $this->assertMatchesRegularExpression('/<button[^>]*data-view="pending"/', $html);
        $this->assertStringNotContainsString('data-view="draft"', $html);
    }

    public function test_empty_view_pills_are_hidden_except_all_and_current()
    {
        $this->assertTrue(MJB_Admin_Companies::should_show_view_pill('all', 0, 'all'));
        $this->assertTrue(MJB_Admin_Companies::should_show_view_pill('draft', 0, 'draft'));
        $this->assertTrue(MJB_Admin_Companies::should_show_view_pill('pending', 1, 'all'));
        $this->assertFalse(MJB_Admin_Companies::should_show_view_pill('draft', 0, 'all'));
        $this->assertFalse(MJB_Admin_Companies::should_show_view_pill('no_jobs', 0, 'published'));
    }

    public function test_sort_rows_by_jobs()
    {
        $rows = array(
            array('title' => 'A', 'list_status' => 'publish', 'jobs_published' => 1, 'posted_ts' => 10),
            array('title' => 'B', 'list_status' => 'publish', 'jobs_published' => 8, 'posted_ts' => 20),
        );
        $sorted = MJB_Admin_Companies::sort_rows($rows, 'jobs', 'desc');
        $this->assertSame('B', $sorted[0]['title']);
        $sorted = MJB_Admin_Companies::sort_rows($rows, 'jobs', 'asc');
        $this->assertSame('A', $sorted[0]['title']);
    }
}
