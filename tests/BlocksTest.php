<?php

use PHPUnit\Framework\TestCase;

class BlocksTest extends TestCase
{
    public function test_build_shortcode_tag_adds_posts_per_page_for_job_listings()
    {
        $tag = MJB_Blocks::build_shortcode_tag('mjb_jobs', array('postsPerPage' => 5));

        $this->assertSame('[mjb_jobs posts_per_page="5"]', $tag);
    }

    public function test_build_shortcode_tag_defaults_posts_per_page_to_ten()
    {
        $tag = MJB_Blocks::build_shortcode_tag('mjb_jobs', array());

        $this->assertSame('[mjb_jobs posts_per_page="10"]', $tag);
    }

    public function test_build_shortcode_tag_passes_through_other_blocks()
    {
        $tag = MJB_Blocks::build_shortcode_tag('mjb_dashboard', array());

        $this->assertSame('[mjb_dashboard]', $tag);
    }
}