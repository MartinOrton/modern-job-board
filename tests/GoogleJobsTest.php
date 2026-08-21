<?php

use PHPUnit\Framework\TestCase;

class GoogleJobsTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['mjb_test_options'] = array();
        $GLOBALS['mjb_test_post_meta'] = array();
        $GLOBALS['mjb_test_post_status'] = array();
        $GLOBALS['mjb_test_post_types'] = array();
        $GLOBALS['mjb_test_titles'] = array();
        $GLOBALS['mjb_test_post_content'] = array();
        $GLOBALS['mjb_test_dates'] = array();
        $GLOBALS['mjb_test_terms'] = array();
        $GLOBALS['mjb_test_permalinks'] = array();
    }

    public function test_validate_schema_flags_missing_title()
    {
        $issues = MJB_Google_Jobs::validate_schema(array(
            'description' => str_repeat('x', 80),
            'datePosted' => '2026-01-01',
            'hiringOrganization' => array('name' => 'Co'),
            'jobLocation' => array('address' => array()),
        ));
        $this->assertNotEmpty($issues['errors']);
    }

    public function test_sanitize_type_map_keeps_allowed_only()
    {
        $map = MJB_Google_Jobs::sanitize_type_map(array(
            'full-time' => 'FULL_TIME',
            'nope' => 'NOT_A_TYPE',
            '' => 'OTHER',
        ));
        $this->assertSame(array('full-time' => 'FULL_TIME'), $map);
    }

    public function test_sanitize_static_salary_unit()
    {
        $s = MJB_Google_Jobs::sanitize_static(array(
            'base_salary_currency' => 'zar',
            'base_salary_value' => '10000',
            'base_salary_unit' => 'MONTH',
            'job_location_type' => 'TELECOMMUTE',
        ));
        $this->assertSame('ZAR', $s['base_salary_currency']);
        $this->assertSame('TELECOMMUTE', $s['job_location_type']);
    }
}
