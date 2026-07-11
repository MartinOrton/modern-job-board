<?php

use PHPUnit\Framework\TestCase;

class LocationTest extends TestCase
{
    public function test_format_location_slug_returns_full_geo_label()
    {
        $formatted = MJB_Location::format_location_slug('san-francisco', 'San Francisco');

        $this->assertSame('San Francisco, California, United States', $formatted);
    }

    public function test_format_location_slug_returns_remote_only()
    {
        $this->assertSame('Remote', MJB_Location::format_location_slug('remote', 'Remote'));
    }

    public function test_format_location_slug_falls_back_to_city_only()
    {
        $formatted = MJB_Location::format_location_slug('brighton', 'Brighton');

        $this->assertSame('Brighton', $formatted);
    }

    public function test_format_job_location_uses_geo_map_for_london()
    {
        $GLOBALS['mjb_test_terms'][42]['job_location'] = array('London');

        $this->assertSame('London, England, United Kingdom', MJB_Location::format_job_location(42));
    }

    public function test_get_job_schema_address_returns_structured_parts()
    {
        $GLOBALS['mjb_test_terms'][42]['job_location'] = array('London');

        $address = MJB_Location::get_job_schema_address(42);

        $this->assertSame('London', $address['locality']);
        $this->assertSame('England', $address['region']);
        $this->assertSame('United Kingdom', $address['country']);
        $this->assertSame('London, England, United Kingdom', $address['formatted']);
    }
}