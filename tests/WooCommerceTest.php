<?php

use PHPUnit\Framework\TestCase;

class WooCommerceTest extends TestCase
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
    }

    public function test_is_order_processed_returns_false_by_default()
    {
        $this->assertFalse(MJB_WooCommerce::is_order_processed(9001));
    }

    public function test_is_order_processed_returns_true_when_flag_set()
    {
        update_post_meta(9001, '_mjb_benefits_processed', 'yes');

        $this->assertTrue(MJB_WooCommerce::is_order_processed(9001));
    }

    public function test_user_can_purchase_job_requires_ownership()
    {
        $GLOBALS['mjb_test_is_logged_in'] = true;
        $GLOBALS['mjb_test_current_user_id'] = 7;
        $GLOBALS['mjb_test_post_status'][100] = 'publish';
        $GLOBALS['mjb_test_post_types'][100] = 'job_listing';
        $GLOBALS['mjb_test_post_authors'][100] = 7;

        $this->assertTrue(MJB_WooCommerce::user_can_purchase_job(100));

        $GLOBALS['mjb_test_post_authors'][100] = 9;
        $this->assertFalse(MJB_WooCommerce::user_can_purchase_job(100));
    }

    public function test_user_can_unlock_application_requires_job_owner()
    {
        $GLOBALS['mjb_test_is_logged_in'] = true;
        $GLOBALS['mjb_test_current_user_id'] = 3;
        $GLOBALS['mjb_test_post_meta'][200]['_job_applied_for'] = 100;
        $GLOBALS['mjb_test_post_status'][100] = 'publish';
        $GLOBALS['mjb_test_post_types'][100] = 'job_listing';
        $GLOBALS['mjb_test_post_authors'][100] = 3;

        $this->assertTrue(MJB_WooCommerce::user_can_unlock_application(200));

        $GLOBALS['mjb_test_post_authors'][100] = 8;
        $this->assertFalse(MJB_WooCommerce::user_can_unlock_application(200));
    }
}