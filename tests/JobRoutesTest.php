<?php

use PHPUnit\Framework\TestCase;

class JobRoutesTest extends TestCase
{
    public function test_build_path_creates_readable_segments()
    {
        $path = MJB_Job_Routes::build_path(array(
            'search_location' => 'remote',
            'search_category' => 'engineering',
            'search_type' => 'full-time',
            'search_keywords' => 'web developer',
            'page' => 2,
        ));

        $this->assertSame('in/remote/category/engineering/type/full-time/keyword/web-developer/page/2', $path);
    }

    public function test_parse_path_restores_filter_params()
    {
        $params = MJB_Job_Routes::parse_path('in/remote/category/engineering/type/full-time/keyword/web-developer/page/2/per-page/20');

        $this->assertSame('remote', $params['search_location']);
        $this->assertSame('engineering', $params['search_category']);
        $this->assertSame('full-time', $params['search_type']);
        $this->assertSame('web developer', $params['search_keywords']);
        $this->assertSame(2, $params['page']);
        $this->assertSame(20, $params['per_page']);
    }

    public function test_build_url_uses_jobs_base()
    {
        $url = MJB_Job_Routes::build_url(array(
            'search_location' => 'remote',
            'search_keywords' => 'developer',
        ));

        $this->assertSame('https://example.test/jobs/in/remote/keyword/developer/', $url);
    }

    public function test_build_rest_url_uses_search_endpoint()
    {
        $url = MJB_Job_Routes::build_url(array(
            'search_location' => 'remote',
            'page' => 2,
        ), array(
            'rest' => true,
            'per_page' => 20,
        ));

        $this->assertSame('https://example.test/wp-json/mjb/v1/jobs/search/in/remote/page/2/per-page/20/', $url);
    }

    public function test_build_path_normalizes_underscore_segments_to_hyphens()
    {
        $path = MJB_Job_Routes::build_path(array(
            'search_location' => 'san_francisco',
            'search_type' => 'full_time',
        ));

        $this->assertSame('in/san-francisco/type/full-time', $path);
    }

    public function test_build_path_supports_company_filter()
    {
        $path = MJB_Job_Routes::build_path(array(
            'search_company' => 'acme_digital',
        ));

        $this->assertSame('company/acme-digital', $path);
    }

    public function test_parse_path_restores_company_filter()
    {
        $params = MJB_Job_Routes::parse_path('company/acme-digital/page/2');

        $this->assertSame('acme-digital', $params['search_company']);
        $this->assertSame(2, $params['page']);
    }
}