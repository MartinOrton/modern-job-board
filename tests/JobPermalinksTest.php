<?php

use PHPUnit\Framework\TestCase;

class JobPermalinksTest extends TestCase
{
    public function test_map_location_slug_to_geo_returns_known_segments()
    {
        $geo = MJB_Job_Permalinks::map_location_slug_to_geo('San Francisco');

        $this->assertSame('us', $geo['country']);
        $this->assertSame('california', $geo['state']);
        $this->assertSame('san-francisco', $geo['city']);
    }

    public function test_map_location_slug_to_geo_falls_back_for_unknown_locations()
    {
        $geo = MJB_Job_Permalinks::map_location_slug_to_geo('Brighton');

        $this->assertSame('global', $geo['country']);
        $this->assertSame('region', $geo['state']);
        $this->assertSame('brighton', $geo['city']);
    }

    public function test_map_location_slug_to_geo_avoids_duplicate_remote_segments()
    {
        $geo = MJB_Job_Permalinks::map_location_slug_to_geo('remote');

        $this->assertSame('global', $geo['country']);
        $this->assertSame('remote', $geo['state']);
        $this->assertSame('worldwide', $geo['city']);
    }

    public function test_build_job_url_uses_geo_segments_and_slug()
    {
        // Permalinks derive from the live job_location term, not cached meta.
        $GLOBALS['mjb_test_terms'][42]['job_location'] = array('london');

        $url = MJB_Job_Permalinks::build_job_url((object) array(
            'ID' => 42,
            'post_type' => 'job_listing',
            'post_name' => 'senior-wordpress-developer',
        ));

        $this->assertSame(
            'https://example.test/job/uk/england/london/senior-wordpress-developer/',
            $url
        );
    }

    public function test_get_job_geo_prefers_live_location_term_over_stale_meta()
    {
        $GLOBALS['mjb_test_post_meta'][55] = array(
            '_mjb_job_country' => 'uk',
            '_mjb_job_state' => 'england',
            '_mjb_job_city' => 'london',
        );
        $GLOBALS['mjb_test_terms'][55]['job_location'] = array('san-francisco');

        $geo = MJB_Job_Permalinks::get_job_geo(55);

        $this->assertSame('us', $geo['country']);
        $this->assertSame('california', $geo['state']);
        $this->assertSame('san-francisco', $geo['city']);
    }
}