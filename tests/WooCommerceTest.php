<?php

use PHPUnit\Framework\TestCase;

class WooCommerceTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['mjb_test_post_meta'] = array();
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
}