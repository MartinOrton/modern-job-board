<?php

use PHPUnit\Framework\TestCase;

class AdminResumesTest extends TestCase
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
        $GLOBALS['mjb_test_user_meta'] = array();
        $GLOBALS['mjb_test_user_emails'] = array();
        $GLOBALS['mjb_test_user_display'] = array();
        $GLOBALS['mjb_test_user_roles'] = array();
        $GLOBALS['mjb_test_user_caps'] = array();
        $GLOBALS['mjb_test_current_user_id'] = 1;
        $GLOBALS['mjb_test_timestamp'] = 1750000000;
        $_REQUEST = array();
        MJB_Admin_Resumes::reset_catalog();
    }

    public function test_sanitize_view_and_order()
    {
        $this->assertSame('missing_file', MJB_Admin_Resumes::sanitize_view('missing_file'));
        $this->assertSame('pdf', MJB_Admin_Resumes::sanitize_view('pdf'));
        $this->assertSame('word', MJB_Admin_Resumes::sanitize_view('word'));
        $this->assertSame('all', MJB_Admin_Resumes::sanitize_view('not-a-view'));
        $this->assertSame(20, MJB_Admin_Resumes::sanitize_per_page(7));
        $this->assertSame(50, MJB_Admin_Resumes::sanitize_per_page(50));
        $this->assertSame('file', MJB_Admin_Resumes::sanitize_orderby('file'));
        $this->assertSame('posted', MJB_Admin_Resumes::sanitize_orderby('nope'));
        $this->assertSame('asc', MJB_Admin_Resumes::sanitize_order('ASC'));
        $this->assertSame('publish', MJB_Admin_Resumes::sanitize_action('publish'));
        $this->assertSame('', MJB_Admin_Resumes::sanitize_action('explode'));
        $this->assertSame('pdf', MJB_Admin_Resumes::sanitize_ext('PDF'));
        $this->assertSame('', MJB_Admin_Resumes::sanitize_ext('exe'));
    }

    public function test_get_state_honours_requested_per_page_and_ext()
    {
        $_REQUEST['mjb_per'] = '10';
        $_REQUEST['mjb_ext'] = 'docx';
        $state = MJB_Admin_Resumes::get_state(1);
        $this->assertSame(10, $state['per']);
        $this->assertSame('docx', $state['ext']);
        $this->assertSame('all', $state['view']);
    }

    public function test_row_matches_view_for_file_and_status()
    {
        $now = 1750000000;
        $GLOBALS['mjb_test_post_types'][701] = 'mjb_resume';
        $GLOBALS['mjb_test_post_types'][702] = 'mjb_resume';
        $GLOBALS['mjb_test_post_status'][701] = 'publish';
        $GLOBALS['mjb_test_post_status'][702] = 'draft';
        $GLOBALS['mjb_test_titles'][701] = 'amina-cv.pdf - Amina Dlamini';
        $GLOBALS['mjb_test_titles'][702] = 'maya.docx - Maya Patel';
        $GLOBALS['mjb_test_post_meta'][701]['_candidate_user_id'] = 16;
        $GLOBALS['mjb_test_post_meta'][701]['_resume_file_relative'] = 'resumes/2026/09/amina-cv.pdf';
        $GLOBALS['mjb_test_post_meta'][702]['_candidate_user_id'] = 17;
        $GLOBALS['mjb_test_user_display'][16] = 'Amina Dlamini';
        $GLOBALS['mjb_test_user_emails'][16] = 'amina@mjb.test';
        $GLOBALS['mjb_test_user_display'][17] = 'Maya Patel';

        $pdf = MJB_Admin_Resumes::build_row(701, $now);
        $draft = MJB_Admin_Resumes::build_row(702, $now);

        $this->assertTrue($pdf['has_file']);
        $this->assertSame('pdf', $pdf['ext']);
        $this->assertSame('Amina Dlamini', $pdf['candidate']);
        $this->assertTrue(MJB_Admin_Resumes::row_matches_view($pdf, 'pdf'));
        $this->assertFalse(MJB_Admin_Resumes::row_matches_view($pdf, 'missing_file'));
        $this->assertTrue(MJB_Admin_Resumes::row_matches_view($draft, 'missing_file'));
        $this->assertTrue(MJB_Admin_Resumes::row_matches_view($draft, 'draft'));
        $this->assertTrue(MJB_Admin_Resumes::row_matches_view($draft, 'word'));
        $this->assertFalse(MJB_Admin_Resumes::row_matches_view($pdf, 'word'));
    }

    public function test_search_matches_candidate_email_and_file()
    {
        $now = 1750000000;
        $GLOBALS['mjb_test_post_types'][711] = 'mjb_resume';
        $GLOBALS['mjb_test_post_status'][711] = 'publish';
        $GLOBALS['mjb_test_titles'][711] = 'priya-shah.pdf - Priya Shah';
        $GLOBALS['mjb_test_post_meta'][711]['_candidate_user_id'] = 21;
        $GLOBALS['mjb_test_post_meta'][711]['_resume_file_path'] = '/resumes/priya-shah.pdf';
        $GLOBALS['mjb_test_user_display'][21] = 'Priya Shah';
        $GLOBALS['mjb_test_user_emails'][21] = 'priya@mjb.test';

        $row = MJB_Admin_Resumes::build_row(711, $now);
        $state = array('view' => 'all', 'q' => 'priya', 'ext' => '');
        $this->assertTrue(MJB_Admin_Resumes::row_matches_state($row, $state));
        $state['q'] = 'mjb.test';
        $this->assertTrue(MJB_Admin_Resumes::row_matches_state($row, $state));
        $state['q'] = 'priya-shah.pdf';
        $this->assertTrue(MJB_Admin_Resumes::row_matches_state($row, $state));
        $state['q'] = 'zzzz';
        $this->assertFalse(MJB_Admin_Resumes::row_matches_state($row, $state));
        $state['q'] = '';
        $state['ext'] = 'docx';
        $this->assertFalse(MJB_Admin_Resumes::row_matches_state($row, $state));
        $state['ext'] = 'pdf';
        $this->assertTrue(MJB_Admin_Resumes::row_matches_state($row, $state));
    }

    public function test_run_action_publishes_draft()
    {
        $GLOBALS['mjb_test_post_types'][721] = 'mjb_resume';
        $GLOBALS['mjb_test_post_status'][721] = 'draft';
        $GLOBALS['mjb_test_titles'][721] = 'casey.pdf - Casey Rivera';
        $GLOBALS['mjb_test_user_caps'][1]['edit_post'] = true;

        $this->assertTrue(MJB_Admin_Resumes::run_action('publish', 721));
        $this->assertSame('publish', get_post_status(721));
    }

    public function test_render_outputs_resumes_console()
    {
        $GLOBALS['mjb_test_post_types'][731] = 'mjb_resume';
        $GLOBALS['mjb_test_post_types'][732] = 'mjb_resume';
        $GLOBALS['mjb_test_post_status'][731] = 'publish';
        $GLOBALS['mjb_test_post_status'][732] = 'draft';
        $GLOBALS['mjb_test_titles'][731] = 'naledi.pdf - Naledi Mokoena';
        $GLOBALS['mjb_test_titles'][732] = 'james.docx - James Okonkwo';
        $GLOBALS['mjb_test_post_meta'][731]['_candidate_user_id'] = 31;
        $GLOBALS['mjb_test_post_meta'][731]['_resume_file_relative'] = 'resumes/2026/09/naledi.pdf';
        $GLOBALS['mjb_test_post_meta'][732]['_candidate_user_id'] = 32;
        $GLOBALS['mjb_test_user_display'][31] = 'Naledi Mokoena';
        $GLOBALS['mjb_test_user_emails'][31] = 'naledi@mjb.test';
        $GLOBALS['mjb_test_user_display'][32] = 'James Okonkwo';

        ob_start();
        MJB_Admin_Resumes::render(1);
        $html = ob_get_clean();

        $this->assertStringContainsString('mjb-tab-panel--resumes', $html);
        $this->assertStringContainsString('mjb-jobs-table', $html);
        $this->assertStringContainsString('All resumes', $html);
        $this->assertStringContainsString('Naledi Mokoena', $html);
        $this->assertStringContainsString('James Okonkwo', $html);
        $this->assertStringContainsString('id="mjb-resumes-q"', $html);
        $this->assertStringContainsString('id="mjb-resumes-density"', $html);
        $this->assertStringContainsString('Comfortable', $html);
        $this->assertStringContainsString('mjb-resumes-ac', $html);
        $this->assertStringContainsString('id="mjb-resumes-per-btn"', $html);
        $this->assertStringContainsString('mjb-jobs-bulkbar', $html);
        $this->assertStringContainsString('Missing file', $html);
        $this->assertStringContainsString('data-view="all"', $html);
        $this->assertStringContainsString('data-view="missing_file"', $html);
        $this->assertMatchesRegularExpression(
            '/<span class="mjb-view-pill"[^>]*data-view="all"[^>]*aria-selected="true"/',
            $html
        );
        $this->assertDoesNotMatchRegularExpression('/<button[^>]*data-view="all"/', $html);
        $this->assertDoesNotMatchRegularExpression('/<a[^>]*data-view="all"/', $html);
        $this->assertMatchesRegularExpression('/<button[^>]*data-view="missing_file"/', $html);
        $this->assertStringNotContainsString('Open Full List', $html);
    }

    public function test_empty_view_pills_are_hidden_except_all_and_current()
    {
        $this->assertTrue(MJB_Admin_Resumes::should_show_view_pill('all', 0, 'all'));
        $this->assertTrue(MJB_Admin_Resumes::should_show_view_pill('draft', 0, 'draft'));
        $this->assertTrue(MJB_Admin_Resumes::should_show_view_pill('pdf', 1, 'all'));
        $this->assertFalse(MJB_Admin_Resumes::should_show_view_pill('draft', 0, 'all'));
        $this->assertFalse(MJB_Admin_Resumes::should_show_view_pill('word', 0, 'pdf'));
    }

    public function test_sort_rows_by_file_and_status()
    {
        $rows = array(
            array('candidate' => 'A', 'status' => 'draft', 'filename' => 'zebra.pdf', 'posted_ts' => 10),
            array('candidate' => 'B', 'status' => 'publish', 'filename' => 'alpha.pdf', 'posted_ts' => 20),
        );
        $sorted = MJB_Admin_Resumes::sort_rows($rows, 'file', 'asc');
        $this->assertSame('B', $sorted[0]['candidate']);
        $sorted = MJB_Admin_Resumes::sort_rows($rows, 'status', 'asc');
        $this->assertSame('B', $sorted[0]['candidate']);
    }

    public function test_file_basename_and_ext()
    {
        $this->assertSame('amina-cv.pdf', MJB_Admin_Resumes::file_basename('', 'resumes/2026/09/amina-cv.pdf', ''));
        $this->assertSame('pdf', MJB_Admin_Resumes::file_ext('amina-cv.pdf'));
        $this->assertSame('docx', MJB_Admin_Resumes::file_ext('notes.docx'));
        $this->assertSame('', MJB_Admin_Resumes::file_ext('notes.txt'));
    }

    public function test_file_column_title_does_not_wrap()
    {
        $css = file_get_contents(dirname(__DIR__) . '/assets/css/mjb-admin-jobs.css');
        $this->assertNotFalse($css);
        $stripped = preg_replace('/\/\*.*?\*\//s', '', $css);

        $this->assertMatchesRegularExpression(
            '/\.mjb-resumes \.mjb-jobs-col-for \.mjb-jobs-title\s*\{[^}]*white-space:\s*nowrap/s',
            $stripped
        );
        $this->assertMatchesRegularExpression(
            '/\.mjb-resumes \.mjb-jobs-col-for \.mjb-jobs-title\s*\{[^}]*text-overflow:\s*ellipsis/s',
            $stripped
        );
    }
}
