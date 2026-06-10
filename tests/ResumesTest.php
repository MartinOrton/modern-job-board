<?php

use PHPUnit\Framework\TestCase;

class ResumesTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['mjb_test_user_meta'] = array();
        $GLOBALS['mjb_test_timestamp'] = 1700000000;
        $GLOBALS['mjb_test_options'] = array();
    }

    public function test_employer_has_cv_access_when_pass_not_expired()
    {
        $GLOBALS['mjb_test_user_meta'][5]['_mjb_cv_access_expires'] = $GLOBALS['mjb_test_timestamp'] + 3600;

        $this->assertTrue(MJB_Resumes::employer_has_cv_access(5, 99));
    }

    public function test_employer_has_cv_access_when_application_unlocked()
    {
        $GLOBALS['mjb_test_user_meta'][5]['_mjb_unlocked_applications'] = array(99, 100);

        $this->assertTrue(MJB_Resumes::employer_has_cv_access(5, 99));
        $this->assertFalse(MJB_Resumes::employer_has_cv_access(5, 101));
    }

    public function test_employer_has_cv_access_false_without_pass_or_unlock()
    {
        $this->assertFalse(MJB_Resumes::employer_has_cv_access(5, 99));
    }
}