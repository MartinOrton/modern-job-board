<?php

use PHPUnit\Framework\TestCase;

class FeedsTest extends TestCase
{
    public function test_build_feed_query_args_limits_results_and_orders_featured()
    {
        $args = MJB_Feeds::build_feed_query_args();

        $this->assertSame(100, $args['posts_per_page']);
        $this->assertArrayNotHasKey('meta_key', $args);
        $this->assertSame('DESC', $args['orderby']['mjb_featured_clause']);
    }
}