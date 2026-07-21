<?php

use PHPUnit\Framework\TestCase;

class JobImporterCompanyTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['mjb_test_posts'] = array();
        $GLOBALS['mjb_test_post_meta'] = array();
        $GLOBALS['mjb_test_companies_by_title'] = array();
        $GLOBALS['mjb_test_inserted_posts'] = array();
        $GLOBALS['mjb_test_post_types'] = array();
        $GLOBALS['mjb_test_post_status'] = array();
        $GLOBALS['mjb_test_titles'] = array();
        $GLOBALS['mjb_test_next_post_id'] = 3000;
    }

    public function test_normalize_company_name_decodes_entities_and_collapses_space()
    {
        $this->assertSame(
            'Pixel & Ink',
            MJB_Job_Importer::normalize_company_name("  Pixel &amp;  Ink  ")
        );
    }

    public function test_company_name_key_is_case_insensitive()
    {
        $this->assertSame(
            MJB_Job_Importer::company_name_key('Acme Digital'),
            MJB_Job_Importer::company_name_key('acme digital')
        );
    }

    public function test_find_or_create_company_reuses_existing_by_name()
    {
        $first = MJB_Job_Importer::find_or_create_company('Acme Digital');
        $second = MJB_Job_Importer::find_or_create_company('acme digital');
        $entity = MJB_Job_Importer::find_or_create_company('Acme Digital');

        $this->assertGreaterThan(0, $first);
        $this->assertSame($first, $second);
        $this->assertSame($first, $entity);

        $company_inserts = array_filter(
            $GLOBALS['mjb_test_inserted_posts'],
            static function ($post) {
                return ($post['post_type'] ?? '') === 'company';
            }
        );

        $this->assertCount(1, $company_inserts);
    }

    public function test_find_or_create_company_matches_entity_encoded_legacy_title()
    {
        $legacy_id = wp_insert_post(array(
            'post_title' => 'Pixel &amp; Ink',
            'post_type' => 'company',
            'post_status' => 'publish',
        ));

        update_post_meta(
            $legacy_id,
            MJB_Job_Importer::COMPANY_NAME_KEY_META,
            MJB_Job_Importer::company_name_key('Pixel & Ink')
        );

        $found = MJB_Job_Importer::find_or_create_company('Pixel & Ink');

        $this->assertSame(intval($legacy_id), $found);
    }
}
