<?php

use PHPUnit\Framework\TestCase;

class DemoCompaniesTest extends TestCase
{
    public function test_demo_companies_cover_every_demo_job_employer()
    {
        require_once dirname(__DIR__) . '/bin/demo-jobs-data.php';

        $jobs = mjb_get_demo_jobs_data();
        $this->assertNotEmpty($jobs);

        $from_jobs = array();
        foreach ($jobs as $job) {
            $from_jobs[(string) $job['company']] = true;
        }

        $companies = mjb_get_demo_companies_data();
        $this->assertEqualsCanonicalizing(array_keys($from_jobs), array_keys($companies));

        foreach ($companies as $name => $profile) {
            $this->assertArrayHasKey('tagline', $profile, $name);
            $this->assertNotSame('', $profile['tagline'], $name);
            $this->assertArrayHasKey('website', $profile, $name);
            $this->assertStringStartsWith('https://', $profile['website'], $name);
            $this->assertArrayHasKey('linkedin', $profile, $name);
            $this->assertArrayHasKey('twitter', $profile, $name);
            $this->assertArrayHasKey('about', $profile, $name);
            $this->assertNotSame('', $profile['about'], $name);
        }
    }
}
