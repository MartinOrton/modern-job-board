<?php

use PHPUnit\Framework\TestCase;

class AdminJobsTest extends TestCase
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
        $_REQUEST = array();
        MJB_Admin_Jobs::reset_catalog();
    }

    public function test_sanitize_view_maps_dashboard_and_mock_aliases()
    {
        $this->assertSame('zero_views', MJB_Admin_Jobs::sanitize_view('noviews'));
        $this->assertSame('zero_views', MJB_Admin_Jobs::sanitize_view('zero_views'));
        $this->assertSame('expiring', MJB_Admin_Jobs::sanitize_view('expiring'));
        $this->assertSame('all', MJB_Admin_Jobs::sanitize_view('not-a-view'));
        $this->assertSame('attention', MJB_Admin_Jobs::sanitize_view('attention'));
    }

    public function test_sanitize_per_page_and_order()
    {
        $this->assertSame(20, MJB_Admin_Jobs::sanitize_per_page(7));
        $this->assertSame(50, MJB_Admin_Jobs::sanitize_per_page(50));
        $this->assertSame('views', MJB_Admin_Jobs::sanitize_orderby('views'));
        $this->assertSame('posted', MJB_Admin_Jobs::sanitize_orderby('nope'));
        $this->assertSame('asc', MJB_Admin_Jobs::sanitize_order('ASC'));
        $this->assertSame('desc', MJB_Admin_Jobs::sanitize_order('sideways'));
    }

    public function test_extend_expiry_adds_listing_duration_from_now_when_past()
    {
        $GLOBALS['mjb_test_options']['mjb_listing_duration'] = 30;
        $GLOBALS['mjb_test_post_meta'][11]['_job_expires'] = '2020-01-01';

        $next = MJB_Admin_Jobs::extend_expiry(11);

        $this->assertNotSame('', $next);
        $this->assertSame($next, $GLOBALS['mjb_test_post_meta'][11]['_job_expires']);
        $expected = gmdate('Y-m-d', 1750000000 + (30 * DAY_IN_SECONDS));
        $this->assertSame($expected, $next);
    }

    public function test_row_matches_view_for_attention_and_filled()
    {
        $now = 1750000000;
        $GLOBALS['mjb_test_posts'] = array(11, 12, 13);
        $GLOBALS['mjb_test_post_types'][11] = 'job_listing';
        $GLOBALS['mjb_test_post_types'][12] = 'job_listing';
        $GLOBALS['mjb_test_post_types'][13] = 'job_listing';
        $GLOBALS['mjb_test_post_status'][11] = 'pending';
        $GLOBALS['mjb_test_post_status'][12] = 'publish';
        $GLOBALS['mjb_test_post_status'][13] = 'publish';
        $GLOBALS['mjb_test_titles'][11] = 'Pending Role';
        $GLOBALS['mjb_test_titles'][12] = 'Filled Role';
        $GLOBALS['mjb_test_titles'][13] = 'Live Role';
        $GLOBALS['mjb_test_post_meta'][12][MJB_Job_Ops::META_FILLED] = '1';
        $GLOBALS['mjb_test_post_meta'][13][MJB_Analytics::VIEW_COUNT_META] = 4;
        $GLOBALS['mjb_test_post_meta'][13]['_job_expires'] = gmdate('Y-m-d', $now + (2 * DAY_IN_SECONDS));

        $pending = MJB_Admin_Jobs::build_row(11, array(), $now, $now + (7 * DAY_IN_SECONDS));
        $filled = MJB_Admin_Jobs::build_row(12, array(), $now, $now + (7 * DAY_IN_SECONDS));
        $live = MJB_Admin_Jobs::build_row(13, array(), $now, $now + (7 * DAY_IN_SECONDS));

        $this->assertTrue(MJB_Admin_Jobs::row_matches_view($pending, 'attention'));
        $this->assertTrue(MJB_Admin_Jobs::row_matches_view($pending, 'pending'));
        $this->assertTrue(MJB_Admin_Jobs::row_matches_view($filled, 'filled'));
        $this->assertFalse(MJB_Admin_Jobs::row_matches_view($filled, 'published'));
        $this->assertTrue(MJB_Admin_Jobs::row_matches_view($live, 'expiring'));
        $this->assertTrue($live['expiring']);
    }

    public function test_search_matches_title_company_and_location()
    {
        $now = 1750000000;
        $GLOBALS['mjb_test_posts'] = array(21);
        $GLOBALS['mjb_test_post_types'][21] = 'job_listing';
        $GLOBALS['mjb_test_post_status'][21] = 'publish';
        $GLOBALS['mjb_test_titles'][21] = 'Accounts Clerk';
        $GLOBALS['mjb_test_post_meta'][21]['_company_name'] = 'Cora ONeil';
        $GLOBALS['mjb_test_terms'][21]['job_location'] = array('Pretoria');
        $GLOBALS['mjb_test_post_meta'][21][MJB_Analytics::VIEW_COUNT_META] = 2;

        $row = MJB_Admin_Jobs::build_row(21, array(), $now, $now + (7 * DAY_IN_SECONDS));
        $state = array(
            'view' => 'all',
            'q' => 'pretoria',
            'company' => 0,
            'category' => '',
            'type' => '',
        );

        $this->assertTrue(MJB_Admin_Jobs::row_matches_state($row, $state));
        $state['q'] = 'cora';
        $this->assertTrue(MJB_Admin_Jobs::row_matches_state($row, $state));
        $state['q'] = 'developer';
        $this->assertFalse(MJB_Admin_Jobs::row_matches_state($row, $state));
    }

    public function test_duplicate_job_creates_draft_copy()
    {
        $GLOBALS['mjb_test_post_types'][31] = 'job_listing';
        $GLOBALS['mjb_test_post_status'][31] = 'publish';
        $GLOBALS['mjb_test_titles'][31] = 'Senior Developer';
        $GLOBALS['mjb_test_post_content'][31] = 'Build things';
        $GLOBALS['mjb_test_post_meta'][31]['_company_name'] = '4Mation';
        $GLOBALS['mjb_test_post_meta'][31][MJB_Analytics::VIEW_COUNT_META] = 11;
        $GLOBALS['mjb_test_post_meta'][31]['_featured'] = 1;
        $GLOBALS['mjb_test_terms'][31]['job_type'] = array('Full time');

        $new_id = MJB_Admin_Jobs::duplicate_job(31);

        $this->assertIsInt($new_id);
        $this->assertGreaterThan(0, $new_id);
        $this->assertSame('draft', $GLOBALS['mjb_test_post_status'][$new_id]);
        $this->assertSame('Senior Developer (Copy)', $GLOBALS['mjb_test_titles'][$new_id]);
        $this->assertSame('4Mation', $GLOBALS['mjb_test_post_meta'][$new_id]['_company_name']);
        $this->assertArrayNotHasKey(MJB_Analytics::VIEW_COUNT_META, $GLOBALS['mjb_test_post_meta'][$new_id] ?? array());
        $this->assertArrayNotHasKey('_featured', $GLOBALS['mjb_test_post_meta'][$new_id] ?? array());
        $this->assertSame(array('Full time'), $GLOBALS['mjb_test_terms'][$new_id]['job_type']);
    }

    public function test_publish_job_respects_free_plan_cap()
    {
        $GLOBALS['mjb_test_options'][MJB_License::OPTION_PLAN] = MJB_License::PLAN_FREE;
        $GLOBALS['mjb_test_post_counts']['job_listing'] = (object) array('publish' => MJB_License::FREE_ACTIVE_JOB_LIMIT);
        $GLOBALS['mjb_test_post_types'][41] = 'job_listing';
        $GLOBALS['mjb_test_post_status'][41] = 'pending';

        $result = MJB_Admin_Jobs::publish_job(41);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('pending', $GLOBALS['mjb_test_post_status'][41]);
    }

    public function test_run_action_features_and_fills()
    {
        $GLOBALS['mjb_test_post_types'][51] = 'job_listing';
        $GLOBALS['mjb_test_post_status'][51] = 'publish';

        $this->assertTrue(MJB_Admin_Jobs::run_action('feature', 51));
        $this->assertSame(1, $GLOBALS['mjb_test_post_meta'][51]['_featured']);

        $this->assertTrue(MJB_Admin_Jobs::run_action('fill', 51));
        $this->assertTrue(MJB_Job_Ops::is_filled(51));

        $this->assertTrue(MJB_Admin_Jobs::run_action('unfill', 51));
        $this->assertFalse(MJB_Job_Ops::is_filled(51));
    }

    public function test_render_outputs_jobs_console()
    {
        $GLOBALS['mjb_test_posts'] = array(61, 62);
        $GLOBALS['mjb_test_post_types'][61] = 'job_listing';
        $GLOBALS['mjb_test_post_types'][62] = 'job_listing';
        $GLOBALS['mjb_test_post_status'][61] = 'publish';
        $GLOBALS['mjb_test_post_status'][62] = 'pending';
        $GLOBALS['mjb_test_titles'][61] = 'Lead Engineer';
        $GLOBALS['mjb_test_titles'][62] = 'Office Manager';
        $GLOBALS['mjb_test_post_meta'][61][MJB_Analytics::VIEW_COUNT_META] = 8;
        $GLOBALS['mjb_test_post_meta'][61]['_company_name'] = '4Mation Digital';
        $GLOBALS['mjb_test_terms'][61]['job_location'] = array('London');
        $GLOBALS['mjb_test_terms'][61]['job_type'] = array('Full time');
        $GLOBALS['mjb_test_post_meta'][62]['_company_name'] = 'Cora';

        ob_start();
        MJB_Admin_Jobs::render(1);
        $html = ob_get_clean();

        $this->assertStringContainsString('mjb-tab-panel--jobs', $html);
        $this->assertStringContainsString('mjb-jobs-table', $html);
        $this->assertStringContainsString('Needs attention', $html);
        $this->assertStringContainsString('Lead Engineer', $html);
        $this->assertStringContainsString('mjb-chip--loc', $html);
        $this->assertStringContainsString('London', $html);
        $this->assertStringContainsString('Full time', $html);
        $this->assertStringContainsString('Submitted by employer', $html);
        $this->assertStringContainsString('mjb-jobs-bulkbar', $html);
        $this->assertStringContainsString('mjb-jobs-density', $html);
        $this->assertStringContainsString('Comfortable', $html);
        $this->assertStringContainsString('Compact', $html);
        $this->assertStringContainsString('mjb-jobs-ac', $html);
        $this->assertStringContainsString('aria-controls="mjb-jobs-ac-list"', $html);
        $this->assertStringContainsString('role="combobox"', $html);
        $this->assertStringContainsString('id="mjb-jobs-per-btn"', $html);
        $this->assertStringContainsString('id="mjb-jobs-per-menu"', $html);
        $this->assertStringContainsString('data-per="5"', $html);
        $this->assertStringContainsString('data-per="10"', $html);
        $this->assertStringContainsString('data-per="50"', $html);
        $this->assertStringContainsString('data-per="100"', $html);
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
        $this->assertStringNotContainsString('data-view="expired"', $html);
        $this->assertStringNotContainsString('data-view="filled"', $html);
        $this->assertStringNotContainsString('data-view="expiring"', $html);
        $apps_th = strpos($html, 'data-key="apps"');
        $views_th = strpos($html, 'data-key="views"');
        $this->assertNotFalse($apps_th);
        $this->assertNotFalse($views_th);
        $this->assertLessThan($views_th, $apps_th, 'Applications column should sit before Views.');
    }

    public function test_empty_view_pills_are_hidden_except_all_and_current()
    {
        $this->assertTrue(MJB_Admin_Jobs::should_show_view_pill('all', 0, 'all'));
        $this->assertTrue(MJB_Admin_Jobs::should_show_view_pill('draft', 0, 'draft'));
        $this->assertTrue(MJB_Admin_Jobs::should_show_view_pill('pending', 2, 'all'));
        $this->assertFalse(MJB_Admin_Jobs::should_show_view_pill('draft', 0, 'all'));
        $this->assertFalse(MJB_Admin_Jobs::should_show_view_pill('expired', 0, 'published'));
    }

    public function test_get_state_honours_requested_per_page()
    {
        $_REQUEST['mjb_per'] = '50';
        $state = MJB_Admin_Jobs::get_state(1);
        $this->assertSame(50, $state['per']);
    }

    public function test_jobs_button_hover_is_flat_white_with_dark_border()
    {
        $css = file_get_contents(dirname(__DIR__) . '/assets/css/mjb-admin-jobs.css');
        $this->assertNotFalse($css);
        $stripped = preg_replace('/\/\*.*?\*\//s', '', $css);

        $this->assertMatchesRegularExpression(
            '/\.mjb-jobs-filter__btn:hover\s*,\s*\.mjb-jobs-per-btn:hover\s*\{[^}]*background:\s*#fff/s',
            $stripped
        );
        $this->assertMatchesRegularExpression(
            '/\.mjb-jobs-filter__btn:hover\s*,\s*\.mjb-jobs-per-btn:hover\s*\{[^}]*border-color:\s*var\(--mjb-teal-dark\)/s',
            $stripped
        );
        $this->assertDoesNotMatchRegularExpression(
            '/\.mjb-view-pill:hover:not\(\[aria-selected="true"\]\)\s*\{[^}]*translateY/s',
            $stripped
        );
        $this->assertDoesNotMatchRegularExpression(
            '/\.mjb-jobs-filter__btn:hover\s*,\s*\.mjb-jobs-per-btn:hover\s*\{[^}]*box-shadow:\s*0 /s',
            $stripped
        );
        $this->assertMatchesRegularExpression(
            '/\.mjb-jobs-ac__item\.is-active\s*\{[^}]*background(?:-color)?:\s*var\(--mjb-teal-tint-2\)/s',
            $stripped
        );
        $this->assertDoesNotMatchRegularExpression(
            '/\.mjb-jobs \.mjb-jobs-menu button:hover[^}]*border-color:\s*var\(--mjb-teal-dark\)/s',
            $stripped
        );
        $this->assertMatchesRegularExpression(
            '/\.mjb-jobs-menu button\.is-danger:hover[^}]*background:\s*var\(--mjb-red-bg\)/s',
            $stripped
        );
    }

    public function test_jobs_panel_css_does_not_clip_footer_dropdown()
    {
        $css = file_get_contents(dirname(__DIR__) . '/assets/css/mjb-admin-jobs.css');
        $this->assertNotFalse($css);
        $stripped = preg_replace('/\/\*.*?\*\//s', '', $css);
        $this->assertMatchesRegularExpression(
            '/\.mjb-jobs-panel\s*\{[^}]*overflow:\s*visible/s',
            $stripped,
            'Jobs panel must override .mjb-panel overflow:hidden so the per-page menu can open.'
        );
    }

    public function test_suggest_returns_jobs_companies_and_locations()
    {
        $now = 1750000000;
        $GLOBALS['mjb_test_timestamp'] = $now;
        $GLOBALS['mjb_test_posts'] = array(71, 72);
        $GLOBALS['mjb_test_post_types'][71] = 'job_listing';
        $GLOBALS['mjb_test_post_types'][72] = 'job_listing';
        $GLOBALS['mjb_test_post_status'][71] = 'publish';
        $GLOBALS['mjb_test_post_status'][72] = 'publish';
        $GLOBALS['mjb_test_titles'][71] = 'Accounts Clerk';
        $GLOBALS['mjb_test_titles'][72] = 'Senior Developer';
        $GLOBALS['mjb_test_post_meta'][71]['_company_name'] = 'Cora ONeil';
        $GLOBALS['mjb_test_post_meta'][72]['_company_name'] = '4Mation Digital';
        $GLOBALS['mjb_test_terms'][71]['job_location'] = array('Pretoria');
        $GLOBALS['mjb_test_terms'][72]['job_location'] = array('Cape Town');
        $GLOBALS['mjb_test_dates'][71] = $now - 100;
        $GLOBALS['mjb_test_dates'][72] = $now;

        $empty = MJB_Admin_Jobs::suggest('', 12);
        $this->assertNotEmpty($empty);
        $this->assertSame('job', $empty[0]['kind']);
        $this->assertSame('Senior Developer', $empty[0]['label']);

        $by_company = MJB_Admin_Jobs::suggest('cora', 12);
        $kinds = array_column($by_company, 'kind');
        $values = array_column($by_company, 'value');
        $this->assertContains('job', $kinds);
        $this->assertContains('company', $kinds);
        $this->assertContains('Accounts Clerk', $values);
        $this->assertContains('Cora ONeil', $values);

        $by_location = MJB_Admin_Jobs::suggest('cape', 12);
        $loc_kinds = array_column($by_location, 'kind');
        $loc_values = array_column($by_location, 'value');
        $this->assertContains('location', $loc_kinds);
        $this->assertTrue(
            in_array('Cape Town', $loc_values, true) || (bool) array_filter($loc_values, static function ($value) {
                return stripos((string) $value, 'Cape') !== false;
            })
        );

        $this->assertSame(array(), MJB_Admin_Jobs::suggest('zzzz-no-such-listing', 12));
        $this->assertSame(3, MJB_Admin_Jobs::match_rank('Cape Town', 'cape town'));
        $this->assertSame(2, MJB_Admin_Jobs::match_rank('Cape Town', 'cape'));
        $this->assertSame(0, MJB_Admin_Jobs::match_rank('', 'cape'));
    }

    public function test_sort_rows_by_views()
    {
        $rows = array(
            array('title' => 'A', 'list_status' => 'publish', 'views' => 2, 'applications' => 0, 'posted_ts' => 10),
            array('title' => 'B', 'list_status' => 'publish', 'views' => 9, 'applications' => 1, 'posted_ts' => 20),
        );
        $sorted = MJB_Admin_Jobs::sort_rows($rows, 'views', 'desc');
        $this->assertSame('B', $sorted[0]['title']);
        $sorted = MJB_Admin_Jobs::sort_rows($rows, 'views', 'asc');
        $this->assertSame('A', $sorted[0]['title']);
    }
}
