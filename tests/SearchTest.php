<?php

use PHPUnit\Framework\TestCase;

class SearchTest extends TestCase
{
    public function test_sanitize_filter_params_trims_unknown_keys()
    {
        $params = MJB_Search::sanitize_filter_params(array(
            'search_keywords' => 'developer',
            'search_location' => 'london',
            'search_category' => '',
            'search_type' => 'full-time',
            'ignored' => 'value',
        ));

        $this->assertSame('developer', $params['search_keywords']);
        $this->assertSame('london', $params['search_location']);
        $this->assertSame('', $params['search_category']);
        $this->assertSame('full-time', $params['search_type']);
        $this->assertArrayNotHasKey('ignored', $params);
    }

    public function test_build_query_args_adds_keyword_search()
    {
        $args = MJB_Search::build_query_args(array(
            'search_keywords' => 'engineer',
        ));

        $this->assertSame('job_listing', $args['post_type']);
        $this->assertSame('engineer', $args['s']);
        $this->assertArrayNotHasKey('tax_query', $args);
    }

    public function test_build_query_args_adds_taxonomy_filters()
    {
        $args = MJB_Search::build_query_args(array(
            'search_location' => 'remote',
            'search_category' => 'engineering',
            'search_type' => 'contract',
        ));

        $clauses = array_values(array_filter($args['tax_query'], 'is_array'));
        $this->assertCount(3, $clauses);
        $this->assertSame('AND', $args['tax_query']['relation']);
        $this->assertSame('job_location', $args['tax_query'][0]['taxonomy']);
        $this->assertSame('remote', $args['tax_query'][0]['terms']);
    }

    public function test_build_query_args_respects_base_args_override()
    {
        $args = MJB_Search::build_query_args(
            array('search_keywords' => 'designer'),
            array('posts_per_page' => 25)
        );

        $this->assertSame(25, $args['posts_per_page']);
        $this->assertSame('designer', $args['s']);
    }

    public function test_build_query_args_applies_featured_ordering()
    {
        $args = MJB_Search::build_query_args(array());

        $this->assertArrayNotHasKey('meta_key', $args);
        $this->assertSame('DESC', $args['orderby']['mjb_featured_clause']);
        $this->assertSame('DESC', $args['orderby']['date']);
        $this->assertSame('DESC', $args['orderby']['ID']);

        // Featured pair lives in a named OR-group. Hide-filled (when enabled) AND-wraps it.
        $featured = $args['meta_query'];
        if (isset($featured['relation']) && $featured['relation'] === 'AND' && isset($featured[0]) && is_array($featured[0])) {
            $featured = $featured[0];
        }
        $this->assertSame('OR', $featured['relation']);
        $this->assertSame('NUMERIC', $featured['mjb_featured_clause']['type']);
        $this->assertSame('EXISTS', $featured['mjb_featured_clause']['compare']);
        $this->assertSame('NOT EXISTS', $featured['mjb_featured_missing']['compare']);
    }

    public function test_build_query_args_supports_pagination()
    {
        $args = MJB_Search::build_query_args(array('page' => 3));

        $this->assertSame(3, $args['paged']);
    }

    public function test_build_query_args_defaults_pagination_to_first_page()
    {
        $args = MJB_Search::build_query_args(array());

        $this->assertSame(1, $args['paged']);
    }

    public function test_sanitize_filter_params_reads_mjb_page_for_ajax()
    {
        $params = MJB_Search::sanitize_filter_params(array(
            'mjb_page' => 4,
        ));

        $this->assertSame(4, $params['page']);
    }

    public function test_map_employment_type_for_schema()
    {
        $this->assertSame('FULL_TIME', MJB_Search::map_employment_type_for_schema('full-time'));
        $this->assertSame('CONTRACTOR', MJB_Search::map_employment_type_for_schema('contract'));
    }

    public function test_normalize_slug_converts_underscores_to_hyphens()
    {
        $this->assertSame('san-francisco', MJB_Search::normalize_slug('san_francisco'));
        $this->assertSame('full-time', MJB_Search::normalize_slug('full_time'));
    }

    public function test_sanitize_filter_params_normalizes_taxonomy_slugs()
    {
        $params = MJB_Search::sanitize_filter_params(array(
            'search_location' => 'san_francisco',
            'search_category' => 'web_development',
            'search_type' => 'full_time',
        ));

        $this->assertSame('san-francisco', $params['search_location']);
        $this->assertSame('web-development', $params['search_category']);
        $this->assertSame('full-time', $params['search_type']);
    }

    public function test_get_listing_page_heading_uses_jobs_at_company_copy()
    {
        $heading = MJB_Search::get_listing_page_heading(array(
            'search_company' => 'acme-digital',
        ));

        $this->assertSame('Jobs at Acme Digital', $heading['title']);
        $this->assertSame('', $heading['intro']);
    }

    public function test_get_listing_page_heading_uses_jobs_in_location_copy()
    {
        $heading = MJB_Search::get_listing_page_heading(array(
            'search_location' => 'london',
        ));

        $this->assertSame('Jobs in London, England, United Kingdom', $heading['title']);
        $this->assertSame('', $heading['intro']);
    }

    public function test_should_show_audience_cards_only_on_unfiltered_home()
    {
        $this->assertTrue(MJB_Shortcodes::should_show_audience_cards(array()));
        $this->assertFalse(MJB_Shortcodes::should_show_audience_cards(array(
            'search_location' => 'london',
        )));
        $this->assertFalse(MJB_Shortcodes::should_show_audience_cards(array(
            'page' => 2,
        )));
    }
}