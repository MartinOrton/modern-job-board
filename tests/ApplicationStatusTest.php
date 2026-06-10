<?php

use PHPUnit\Framework\TestCase;

class ApplicationStatusTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['mjb_test_post_meta'] = array();
        $GLOBALS['mjb_test_post_status'] = array();
        $GLOBALS['mjb_test_post_types'] = array();
        $GLOBALS['mjb_test_post_authors'] = array();
        $GLOBALS['mjb_test_is_logged_in'] = false;
        $GLOBALS['mjb_test_current_user_id'] = 0;
        $GLOBALS['mjb_test_user_caps'] = array();
        $GLOBALS['mjb_test_status_updates'] = array();
    }

    public function test_get_status_defaults_to_new()
    {
        $this->assertSame('new', MJB_Application_Status::get_status(101));
    }

    public function test_update_status_persists_meta()
    {
        $this->assertTrue(MJB_Application_Status::update_status(55, 'shortlisted'));
        $this->assertSame('shortlisted', MJB_Application_Status::get_status(55));
        $this->assertSame('Shortlisted', MJB_Application_Status::get_label('shortlisted'));
    }

    public function test_user_can_manage_allows_job_owner()
    {
        $GLOBALS['mjb_test_is_logged_in'] = true;
        $GLOBALS['mjb_test_current_user_id'] = 9;
        $GLOBALS['mjb_test_post_status'][500] = 'publish';
        $GLOBALS['mjb_test_post_types'][500] = 'job_listing';
        $GLOBALS['mjb_test_post_authors'][500] = 9;
        $GLOBALS['mjb_test_post_meta'][77]['_job_applied_for'] = 500;

        $this->assertTrue(MJB_Application_Status::user_can_manage(77));
    }

    public function test_user_can_manage_denies_other_employers()
    {
        $GLOBALS['mjb_test_is_logged_in'] = true;
        $GLOBALS['mjb_test_current_user_id'] = 2;
        $GLOBALS['mjb_test_post_status'][500] = 'publish';
        $GLOBALS['mjb_test_post_types'][500] = 'job_listing';
        $GLOBALS['mjb_test_post_authors'][500] = 9;
        $GLOBALS['mjb_test_post_meta'][77]['_job_applied_for'] = 500;

        $this->assertFalse(MJB_Application_Status::user_can_manage(77));
    }
}