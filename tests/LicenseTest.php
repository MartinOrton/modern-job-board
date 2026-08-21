<?php

use PHPUnit\Framework\TestCase;

class LicenseTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['mjb_test_options'] = array();
        $GLOBALS['mjb_test_post_counts'] = array();
        $GLOBALS['mjb_test_post_types'] = array();
        $GLOBALS['mjb_test_post_status'] = array();

        // Drop any constant override from a previous process (cannot undefine).
        // Tests that need a forced plan set the option instead.
    }

    public function test_default_plan_is_free()
    {
        $this->assertSame(MJB_License::PLAN_FREE, MJB_License::get_plan());
        $this->assertFalse(MJB_License::is_pro());
        $this->assertFalse(MJB_License::is_business());
    }

    public function test_free_plan_feature_gates()
    {
        $GLOBALS['mjb_test_options'][MJB_License::OPTION_PLAN] = MJB_License::PLAN_FREE;

        $this->assertFalse(MJB_License::can('unlimited_jobs'));
        $this->assertFalse(MJB_License::can('woocommerce'));
        $this->assertFalse(MJB_License::can('custom_fields'));
        $this->assertFalse(MJB_License::can('tools'));
        $this->assertFalse(MJB_License::can('rest_api'));
        $this->assertFalse(MJB_License::can('xml_feed'));
        $this->assertFalse(MJB_License::can('webhooks'));
    }

    public function test_pro_plan_unlocks_pro_features_not_business()
    {
        $GLOBALS['mjb_test_options'][MJB_License::OPTION_PLAN] = MJB_License::PLAN_PRO;

        $this->assertTrue(MJB_License::is_pro());
        $this->assertFalse(MJB_License::is_business());
        $this->assertTrue(MJB_License::can('unlimited_jobs'));
        $this->assertTrue(MJB_License::can('woocommerce'));
        $this->assertTrue(MJB_License::can('custom_fields'));
        $this->assertTrue(MJB_License::can('tools'));
        $this->assertFalse(MJB_License::can('rest_api'));
        $this->assertFalse(MJB_License::can('xml_feed'));
        $this->assertFalse(MJB_License::can('webhooks'));
    }

    public function test_business_plan_unlocks_all_gated_features()
    {
        $GLOBALS['mjb_test_options'][MJB_License::OPTION_PLAN] = MJB_License::PLAN_BUSINESS;

        $this->assertTrue(MJB_License::is_business());
        $this->assertTrue(MJB_License::can('woocommerce'));
        $this->assertTrue(MJB_License::can('rest_api'));
        $this->assertTrue(MJB_License::can('xml_feed'));
        $this->assertTrue(MJB_License::can('webhooks'));
    }

    public function test_complete_site_matches_business_features()
    {
        $GLOBALS['mjb_test_options'][MJB_License::OPTION_PLAN] = MJB_License::PLAN_COMPLETE;

        $this->assertTrue(MJB_License::is_business());
        $this->assertTrue(MJB_License::can('rest_api'));
        $this->assertTrue(MJB_License::can('webhooks'));
    }

    public function test_free_job_cap_blocks_when_at_limit()
    {
        $GLOBALS['mjb_test_options'][MJB_License::OPTION_PLAN] = MJB_License::PLAN_FREE;
        $GLOBALS['mjb_test_post_counts']['job_listing'] = (object) array('publish' => 10);

        $this->assertSame(10, MJB_License::count_active_jobs());
        $this->assertFalse(MJB_License::can_publish_job(0));
    }

    public function test_free_job_cap_allows_under_limit()
    {
        $GLOBALS['mjb_test_options'][MJB_License::OPTION_PLAN] = MJB_License::PLAN_FREE;
        $GLOBALS['mjb_test_post_counts']['job_listing'] = (object) array('publish' => 9);

        $this->assertTrue(MJB_License::can_publish_job(0));
    }

    public function test_free_job_cap_allows_updating_existing_published_job()
    {
        $GLOBALS['mjb_test_options'][MJB_License::OPTION_PLAN] = MJB_License::PLAN_FREE;
        $GLOBALS['mjb_test_post_counts']['job_listing'] = (object) array('publish' => 10);
        $GLOBALS['mjb_test_post_types'][55] = 'job_listing';
        $GLOBALS['mjb_test_post_status'][55] = 'publish';

        $this->assertTrue(MJB_License::can_publish_job(55));
    }

    public function test_pro_ignores_job_cap()
    {
        $GLOBALS['mjb_test_options'][MJB_License::OPTION_PLAN] = MJB_License::PLAN_PRO;
        $GLOBALS['mjb_test_post_counts']['job_listing'] = (object) array('publish' => 100);

        $this->assertTrue(MJB_License::can_publish_job(0));
    }

    public function test_generate_and_activate_valid_key()
    {
        $key = MJB_License::generate_key(MJB_License::PLAN_PRO, '00000000');
        $this->assertIsString($key);
        $this->assertStringStartsWith('MJB-PRO-', $key);

        $result = MJB_License::activate_key($key);
        $this->assertTrue($result);
        $this->assertSame(MJB_License::PLAN_PRO, MJB_License::get_plan());
        $this->assertSame(strtoupper($key), MJB_License::get_key());
    }

    public function test_activate_business_key()
    {
        $key = MJB_License::generate_key(MJB_License::PLAN_BUSINESS, '00000000');
        $this->assertTrue(MJB_License::activate_key($key));
        $this->assertSame(MJB_License::PLAN_BUSINESS, MJB_License::get_plan());
        $this->assertTrue(MJB_License::can('webhooks'));
    }

    public function test_invalid_key_rejected()
    {
        $result = MJB_License::activate_key('MJB-PRO-00000000-DEADBEEF');
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame(MJB_License::PLAN_FREE, MJB_License::get_plan());
    }

    public function test_expired_key_rejected()
    {
        $key = MJB_License::generate_key(MJB_License::PLAN_PRO, '20200101');
        $result = MJB_License::activate_key($key);
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('mjb_license_expired', $result->get_error_code());
    }

    public function test_clear_license_via_empty_key()
    {
        $key = MJB_License::generate_key(MJB_License::PLAN_PRO, '00000000');
        MJB_License::activate_key($key);
        $this->assertSame(MJB_License::PLAN_PRO, MJB_License::get_plan());

        $this->assertTrue(MJB_License::activate_key(''));
        $this->assertSame(MJB_License::PLAN_FREE, MJB_License::get_plan());
        $this->assertSame('', MJB_License::get_key());
    }

    public function test_guard_insert_forces_pending_when_over_cap()
    {
        $GLOBALS['mjb_test_options'][MJB_License::OPTION_PLAN] = MJB_License::PLAN_FREE;
        $GLOBALS['mjb_test_post_counts']['job_listing'] = (object) array('publish' => 10);

        $data = array(
            'post_type' => 'job_listing',
            'post_status' => 'publish',
            'post_title' => 'Extra job',
        );
        $out = MJB_License::guard_insert_job_data($data, array());
        $this->assertSame('pending', $out['post_status']);
    }

    public function test_guard_insert_allows_publish_under_cap()
    {
        $GLOBALS['mjb_test_options'][MJB_License::OPTION_PLAN] = MJB_License::PLAN_FREE;
        $GLOBALS['mjb_test_post_counts']['job_listing'] = (object) array('publish' => 3);

        $data = array(
            'post_type' => 'job_listing',
            'post_status' => 'publish',
        );
        $out = MJB_License::guard_insert_job_data($data, array());
        $this->assertSame('publish', $out['post_status']);
    }

    public function test_plan_labels()
    {
        $this->assertSame('Free', MJB_License::get_plan_label(MJB_License::PLAN_FREE));
        $this->assertSame('Pro', MJB_License::get_plan_label(MJB_License::PLAN_PRO));
        $this->assertSame('Business', MJB_License::get_plan_label(MJB_License::PLAN_BUSINESS));
        $this->assertSame('Complete Site', MJB_License::get_plan_label(MJB_License::PLAN_COMPLETE));
    }
}
