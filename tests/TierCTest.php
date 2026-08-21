<?php

use PHPUnit\Framework\TestCase;

class TierCTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['mjb_test_options'] = array();
        $GLOBALS['mjb_test_user_roles'] = array();
        $GLOBALS['mjb_test_user_caps'] = array();
        $GLOBALS['mjb_test_current_user_id'] = 0;
    }

    public function test_normalize_files_array_multi()
    {
        $files = MJB_Board_Polish::normalize_files_array(array(
            'name' => array('a.pdf', 'b.pdf'),
            'type' => array('application/pdf', 'application/pdf'),
            'tmp_name' => array('/tmp/a', '/tmp/b'),
            'error' => array(0, 0),
            'size' => array(10, 20),
        ));
        $this->assertCount(2, $files);
        $this->assertSame('a.pdf', $files[0]['name']);
        $this->assertSame('b.pdf', $files[1]['name']);
    }

    public function test_xml_tag_name_sanitizes()
    {
        $this->assertSame('_company_name', MJB_Board_Polish::xml_tag_name('_company_name'));
        $this->assertSame('m_123bad', MJB_Board_Polish::xml_tag_name('123bad'));
    }

    public function test_who_can_post_admin_mode()
    {
        $GLOBALS['mjb_test_options'][MJB_Board_Polish::OPTION_WHO_CAN_POST] = 'admin';
        $GLOBALS['mjb_test_user_caps'][5] = array('manage_options' => true);
        $this->assertTrue(MJB_Board_Polish::filter_can_post_job(false, 5));
        $GLOBALS['mjb_test_user_caps'][6] = array();
        $this->assertFalse(MJB_Board_Polish::filter_can_post_job(false, 6));
    }

    public function test_who_can_post_employer_mode()
    {
        $GLOBALS['mjb_test_options'][MJB_Board_Polish::OPTION_WHO_CAN_POST] = 'employer';
        $GLOBALS['mjb_test_user_roles'][8] = array('employer');
        $this->assertTrue(MJB_Board_Polish::filter_can_post_job(false, 8));
        $GLOBALS['mjb_test_user_roles'][9] = array('candidate');
        $this->assertFalse(MJB_Board_Polish::filter_can_post_job(false, 9));
    }

    public function test_hashed_filename_has_extension()
    {
        $name = MJB_Private_Uploads::hashed_filename('pdf');
        $this->assertStringEndsWith('.pdf', $name);
        $this->assertGreaterThan(20, strlen($name));
    }

    public function test_signed_path_roundtrip()
    {
        $signed = MJB_Private_Uploads::sign_path('mjb-private/resumes/x.pdf', 600);
        $this->assertTrue(MJB_Private_Uploads::verify_signed_path($signed['path'], $signed['exp'], $signed['sig']));
        $this->assertFalse(MJB_Private_Uploads::verify_signed_path($signed['path'], $signed['exp'] - 10000, $signed['sig']));
        $this->assertFalse(MJB_Private_Uploads::verify_signed_path($signed['path'], $signed['exp'], 'deadbeef'));
    }

    public function test_cron_hooks_list_nonempty()
    {
        $hooks = MJB_Board_Polish::cron_hooks();
        $this->assertArrayHasKey('mjb_daily_cron_event', $hooks);
    }
}
