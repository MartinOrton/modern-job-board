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
}