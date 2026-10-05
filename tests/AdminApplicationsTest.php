<?php

use PHPUnit\Framework\TestCase;

class AdminApplicationsTest extends TestCase
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
        $GLOBALS['mjb_test_user_caps'] = array();
        $_REQUEST = array();
        MJB_Admin_Applications::reset_catalog();
    }

    public function test_sanitize_view_and_order()
    {
        $this->assertSame('new', MJB_Admin_Applications::sanitize_view('new'));
        $this->assertSame('no_resume', MJB_Admin_Applications::sanitize_view('no_resume'));
        $this->assertSame('shortlisted', MJB_Admin_Applications::sanitize_view('shortlisted'));
        $this->assertSame('all', MJB_Admin_Applications::sanitize_view('not-a-view'));
        $this->assertSame(20, MJB_Admin_Applications::sanitize_per_page(7));
        $this->assertSame(50, MJB_Admin_Applications::sanitize_per_page(50));
        $this->assertSame('job', MJB_Admin_Applications::sanitize_orderby('job'));
        $this->assertSame('posted', MJB_Admin_Applications::sanitize_orderby('nope'));
        $this->assertSame('asc', MJB_Admin_Applications::sanitize_order('ASC'));
        $this->assertSame('reviewed', MJB_Admin_Applications::sanitize_action('reviewed'));
        $this->assertSame('', MJB_Admin_Applications::sanitize_action('explode'));
    }

    public function test_get_state_honours_requested_per_page_and_job()
    {
        $_REQUEST['mjb_per'] = '10';
        $_REQUEST['mjb_job'] = '88';
        $state = MJB_Admin_Applications::get_state(1);
        $this->assertSame(10, $state['per']);
        $this->assertSame(88, $state['job']);
        $this->assertSame('all', $state['view']);
    }

    public function test_row_matches_view_for_status_and_resume()
    {
        $now = 1750000000;
        $GLOBALS['mjb_test_post_types'][401] = 'job_application';
        $GLOBALS['mjb_test_post_types'][402] = 'job_application';
        $GLOBALS['mjb_test_post_status'][401] = 'publish';
        $GLOBALS['mjb_test_post_status'][402] = 'publish';
        $GLOBALS['mjb_test_titles'][401] = 'Ada Lovelace';
        $GLOBALS['mjb_test_titles'][402] = 'Alan Turing';
        $GLOBALS['mjb_test_post_meta'][401]['_candidate_name'] = 'Ada Lovelace';
        $GLOBALS['mjb_test_post_meta'][401]['_mjb_application_status'] = 'new';
        $GLOBALS['mjb_test_post_meta'][402]['_candidate_name'] = 'Alan Turing';
        $GLOBALS['mjb_test_post_meta'][402]['_mjb_application_status'] = 'shortlisted';
        $GLOBALS['mjb_test_post_meta'][402]['_candidate_resume_path'] = '/resumes/alan.pdf';

        $new = MJB_Admin_Applications::build_row(401, $now);
        $shortlisted = MJB_Admin_Applications::build_row(402, $now);

        $this->assertTrue(MJB_Admin_Applications::row_matches_view($new, 'new'));
        $this->assertTrue(MJB_Admin_Applications::row_matches_view($new, 'no_resume'));
        $this->assertFalse(MJB_Admin_Applications::row_matches_view($new, 'shortlisted'));
        $this->assertTrue(MJB_Admin_Applications::row_matches_view($shortlisted, 'shortlisted'));
        $this->assertFalse(MJB_Admin_Applications::row_matches_view($shortlisted, 'no_resume'));
        $this->assertTrue($shortlisted['has_resume']);
        $this->assertFalse($new['has_resume']);
    }

    public function test_search_matches_candidate_email_and_job()
    {
        $now = 1750000000;
        $GLOBALS['mjb_test_post_types'][411] = 'job_application';
        $GLOBALS['mjb_test_post_types'][501] = 'job_listing';
        $GLOBALS['mjb_test_post_status'][411] = 'publish';
        $GLOBALS['mjb_test_post_status'][501] = 'publish';
        $GLOBALS['mjb_test_titles'][411] = 'Priya Shah';
        $GLOBALS['mjb_test_titles'][501] = 'Senior Designer';
        $GLOBALS['mjb_test_post_meta'][411]['_candidate_name'] = 'Priya Shah';
        $GLOBALS['mjb_test_post_meta'][411]['_candidate_email'] = 'priya@mjb.test';
        $GLOBALS['mjb_test_post_meta'][411]['_job_applied_for'] = 501;
        $GLOBALS['mjb_test_post_meta'][501]['_company_name'] = 'Harbor Health';

        $row = MJB_Admin_Applications::build_row(411, $now);
        $state = array('view' => 'all', 'q' => 'priya', 'job' => 0);
        $this->assertTrue(MJB_Admin_Applications::row_matches_state($row, $state));
        $state['q'] = 'mjb.test';
        $this->assertTrue(MJB_Admin_Applications::row_matches_state($row, $state));
        $state['q'] = 'designer';
        $this->assertTrue(MJB_Admin_Applications::row_matches_state($row, $state));
        $state['q'] = 'harbor';
        $this->assertTrue(MJB_Admin_Applications::row_matches_state($row, $state));
        $state['q'] = 'zzzz';
        $this->assertFalse(MJB_Admin_Applications::row_matches_state($row, $state));
        $state['q'] = '';
        $state['job'] = 999;
        $this->assertFalse(MJB_Admin_Applications::row_matches_state($row, $state));
        $state['job'] = 501;
        $this->assertTrue(MJB_Admin_Applications::row_matches_state($row, $state));
        $this->assertSame('Senior Designer', $row['job_title']);
    }

    public function test_run_action_updates_status()
    {
        $GLOBALS['mjb_test_post_types'][421] = 'job_application';
        $GLOBALS['mjb_test_post_status'][421] = 'publish';
        $GLOBALS['mjb_test_titles'][421] = 'Casey Rivera';

        $this->assertTrue(MJB_Admin_Applications::run_action('shortlisted', 421));
        $this->assertSame('shortlisted', MJB_Application_Status::get_status(421));
    }

    public function test_render_outputs_applications_console()
    {
        $GLOBALS['mjb_test_post_types'][431] = 'job_application';
        $GLOBALS['mjb_test_post_types'][432] = 'job_application';
        $GLOBALS['mjb_test_post_types'][601] = 'job_listing';
        $GLOBALS['mjb_test_post_status'][431] = 'publish';
        $GLOBALS['mjb_test_post_status'][432] = 'publish';
        $GLOBALS['mjb_test_post_status'][601] = 'publish';
        $GLOBALS['mjb_test_titles'][431] = 'Naledi Mokoena';
        $GLOBALS['mjb_test_titles'][432] = 'James Okonkwo';
        $GLOBALS['mjb_test_titles'][601] = 'Product Designer';
        $GLOBALS['mjb_test_post_meta'][431]['_candidate_name'] = 'Naledi Mokoena';
        $GLOBALS['mjb_test_post_meta'][431]['_candidate_email'] = 'naledi@mjb.test';
        $GLOBALS['mjb_test_post_meta'][431]['_job_applied_for'] = 601;
        $GLOBALS['mjb_test_post_meta'][431]['_mjb_application_status'] = 'new';
        $GLOBALS['mjb_test_post_meta'][432]['_candidate_name'] = 'James Okonkwo';
        $GLOBALS['mjb_test_post_meta'][432]['_mjb_application_status'] = 'reviewed';
        $GLOBALS['mjb_test_post_meta'][432]['_candidate_resume_id'] = 77;

        ob_start();
        MJB_Admin_Applications::render(1);
        $html = ob_get_clean();

        $this->assertStringContainsString('mjb-tab-panel--applications', $html);
        $this->assertStringContainsString('mjb-jobs-table', $html);
        $this->assertStringContainsString('All applications', $html);
        $this->assertStringContainsString('Naledi Mokoena', $html);
        $this->assertStringContainsString('James Okonkwo', $html);
        $this->assertStringContainsString('Product Designer', $html);
        $this->assertStringContainsString('id="mjb-applications-q"', $html);
        $this->assertStringContainsString('id="mjb-applications-density"', $html);
        $this->assertStringContainsString('Comfortable', $html);
        $this->assertStringContainsString('mjb-applications-ac', $html);
        $this->assertStringContainsString('id="mjb-applications-per-btn"', $html);
        $this->assertStringContainsString('mjb-jobs-bulkbar', $html);
        $this->assertStringContainsString('No resume', $html);
        $this->assertStringContainsString('data-view="all"', $html);
        $this->assertStringContainsString('data-view="new"', $html);
        $this->assertStringContainsString('data-view="reviewed"', $html);
        $this->assertMatchesRegularExpression(
            '/<span class="mjb-view-pill"[^>]*data-view="all"[^>]*aria-selected="true"/',
            $html
        );
        $this->assertDoesNotMatchRegularExpression('/<button[^>]*data-view="all"/', $html);
        $this->assertDoesNotMatchRegularExpression('/<a[^>]*data-view="all"/', $html);
        $this->assertMatchesRegularExpression('/<button[^>]*data-view="new"/', $html);
        $this->assertStringNotContainsString('data-view="hired"', $html);
        $this->assertStringNotContainsString('Open Full List', $html);
    }

    public function test_empty_view_pills_are_hidden_except_all_and_current()
    {
        $this->assertTrue(MJB_Admin_Applications::should_show_view_pill('all', 0, 'all'));
        $this->assertTrue(MJB_Admin_Applications::should_show_view_pill('hired', 0, 'hired'));
        $this->assertTrue(MJB_Admin_Applications::should_show_view_pill('new', 1, 'all'));
        $this->assertFalse(MJB_Admin_Applications::should_show_view_pill('hired', 0, 'all'));
        $this->assertFalse(MJB_Admin_Applications::should_show_view_pill('no_resume', 0, 'reviewed'));
    }

    public function test_sort_rows_by_job_and_status()
    {
        $rows = array(
            array('candidate' => 'A', 'status' => 'new', 'job_title' => 'Zebra', 'posted_ts' => 10),
            array('candidate' => 'B', 'status' => 'hired', 'job_title' => 'Alpha', 'posted_ts' => 20),
        );
        $sorted = MJB_Admin_Applications::sort_rows($rows, 'job', 'asc');
        $this->assertSame('B', $sorted[0]['candidate']);
        $sorted = MJB_Admin_Applications::sort_rows($rows, 'status', 'asc');
        $this->assertSame('A', $sorted[0]['candidate']);
    }
}
