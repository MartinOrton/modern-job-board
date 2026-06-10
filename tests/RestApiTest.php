<?php

use PHPUnit\Framework\TestCase;

class MJB_Mock_REST_Request
{
    private $params;
    private $route;

    public function __construct(array $params, $route = '/mjb/v1/jobs')
    {
        $this->params = $params;
        $this->route = $route;
    }

    public function get_param($key)
    {
        return $this->params[$key] ?? null;
    }

    public function get_route()
    {
        return $this->route;
    }
}

class RestApiTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['mjb_test_post_meta'] = array();
        $GLOBALS['mjb_test_post_status'] = array();
        $GLOBALS['mjb_test_post_types'] = array();
        $GLOBALS['mjb_test_titles'] = array();
        $GLOBALS['mjb_test_permalinks'] = array();
        $GLOBALS['mjb_test_excerpts'] = array();
        $GLOBALS['mjb_test_terms'] = array();
    }

    public function test_build_query_args_from_search_path()
    {
        $request = new MJB_Mock_REST_Request(array(
            'search_path' => 'in/remote/keyword/developer/page/2/per-page/20',
        ), '/mjb/v1/jobs/search/in/remote/keyword/developer/page/2/per-page/20');

        $args = MJB_REST_API::build_query_args_from_request($request);

        $this->assertSame('developer', $args['s']);
        $this->assertSame(2, $args['paged']);
        $this->assertSame(20, $args['posts_per_page']);
        $this->assertSame('remote', $args['tax_query'][0]['terms']);
    }

    public function test_build_query_args_from_request_applies_filters_and_caps_per_page()
    {
        $request = new MJB_Mock_REST_Request(array(
            'search_keywords' => 'developer',
            'search_location' => 'remote',
            'page' => 2,
            'per_page' => 500,
        ));

        $args = MJB_REST_API::build_query_args_from_request($request);

        $this->assertSame('developer', $args['s']);
        $this->assertSame(2, $args['paged']);
        $this->assertSame(100, $args['posts_per_page']);
        $this->assertSame('remote', $args['tax_query'][0]['terms']);
    }

    public function test_format_job_for_api_returns_expected_fields()
    {
        $GLOBALS['mjb_test_post_status'][42] = 'publish';
        $GLOBALS['mjb_test_post_types'][42] = 'job_listing';
        $GLOBALS['mjb_test_titles'][42] = 'Backend Engineer';
        $GLOBALS['mjb_test_permalinks'][42] = 'https://example.test/jobs/backend-engineer/';
        $GLOBALS['mjb_test_excerpts'][42] = 'Build APIs';
        $GLOBALS['mjb_test_post_meta'][42]['_company_name'] = 'Acme Corp';
        $GLOBALS['mjb_test_post_meta'][42]['_featured'] = 1;
        $GLOBALS['mjb_test_terms'][42]['job_location'] = array('London');
        $GLOBALS['mjb_test_terms'][42]['job_type'] = array('Full-time');
        $GLOBALS['mjb_test_terms'][42]['job_category'] = array('Engineering');

        $job = MJB_REST_API::format_job_for_api(42);

        $this->assertSame(42, $job['id']);
        $this->assertSame('Backend Engineer', $job['title']);
        $this->assertTrue($job['featured']);
        $this->assertSame('Acme Corp', $job['company']);
        $this->assertSame('London', $job['location']);
    }
}