<?php

use PHPUnit\Framework\TestCase;

class FeedsTest extends TestCase
{
    public function test_build_feed_query_args_limits_results_and_orders_featured()
    {
        $args = MJB_Feeds::build_feed_query_args();

        $this->assertSame(100, $args['posts_per_page']);
        $this->assertSame('_featured', $args['meta_key']);
        $this->assertSame('DESC', $args['orderby']['meta_value_num']);
    }
}