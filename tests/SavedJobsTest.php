<?php

use PHPUnit\Framework\TestCase;

class SavedJobsTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['mjb_test_user_meta'] = array();
        $GLOBALS['mjb_test_posts'] = array();
        $GLOBALS['mjb_test_post_status'] = array();
        $GLOBALS['mjb_test_post_types'] = array();
        $GLOBALS['mjb_test_titles'] = array();
        $GLOBALS['mjb_test_permalinks'] = array();
        $GLOBALS['mjb_test_current_user_id'] = 0;
        $GLOBALS['mjb_test_is_logged_in'] = false;
    }

    public function test_toggle_saves_and_unsaves()
    {
        $this->assertFalse(MJB_Saved_Jobs::is_saved(7, 42));
        $this->assertTrue(MJB_Saved_Jobs::toggle(7, 42));
        $this->assertTrue(MJB_Saved_Jobs::is_saved(7, 42));
        $this->assertSame(array(42), MJB_Saved_Jobs::get_ids(7));

        $this->assertFalse(MJB_Saved_Jobs::toggle(7, 42));
        $this->assertFalse(MJB_Saved_Jobs::is_saved(7, 42));
        $this->assertSame(array(), MJB_Saved_Jobs::get_ids(7));
    }

    public function test_remove_and_set_ids()
    {
        MJB_Saved_Jobs::set_ids(3, array(10, 11, 12));
        $this->assertTrue(MJB_Saved_Jobs::remove(3, 11));
        $this->assertSame(array(10, 12), MJB_Saved_Jobs::get_ids(3));
        $this->assertFalse(MJB_Saved_Jobs::remove(3, 99));
    }

    public function test_prune_drops_unpublished_and_non_jobs()
    {
        $GLOBALS['mjb_test_posts'] = array(1, 2, 3);
        $GLOBALS['mjb_test_post_types'][1] = 'job_listing';
        $GLOBALS['mjb_test_post_types'][2] = 'job_listing';
        $GLOBALS['mjb_test_post_types'][3] = 'page';
        $GLOBALS['mjb_test_post_status'][1] = 'publish';
        $GLOBALS['mjb_test_post_status'][2] = 'draft';
        $GLOBALS['mjb_test_post_status'][3] = 'publish';

        MJB_Saved_Jobs::set_ids(5, array(1, 2, 3, 99));
        $valid = MJB_Saved_Jobs::prune(5);

        $this->assertSame(array(1), $valid);
        $this->assertSame(array(1), MJB_Saved_Jobs::get_ids(5));
    }

    public function test_get_jobs_returns_published_newest_first()
    {
        $GLOBALS['mjb_test_posts'] = array(10, 20);
        $GLOBALS['mjb_test_post_types'][10] = 'job_listing';
        $GLOBALS['mjb_test_post_types'][20] = 'job_listing';
        $GLOBALS['mjb_test_post_status'][10] = 'publish';
        $GLOBALS['mjb_test_post_status'][20] = 'publish';
        $GLOBALS['mjb_test_titles'][10] = 'First saved';
        $GLOBALS['mjb_test_titles'][20] = 'Second saved';

        // Append order: 10 then 20 → newest first is 20.
        MJB_Saved_Jobs::set_ids(8, array(10, 20));
        $jobs = MJB_Saved_Jobs::get_jobs(8);

        $this->assertCount(2, $jobs);
        $this->assertSame(20, (int) $jobs[0]->ID);
        $this->assertSame(10, (int) $jobs[1]->ID);
    }

    public function test_toggle_url_includes_job_and_optional_redirect()
    {
        $url = MJB_Saved_Jobs::get_toggle_url(15, 'https://example.test/jobs/candidate-dashboard/');
        $this->assertStringContainsString('15', $url);
        $this->assertStringContainsString('redirect_to', $url);
        $this->assertStringContainsString('_wpnonce', $url);
    }
}
