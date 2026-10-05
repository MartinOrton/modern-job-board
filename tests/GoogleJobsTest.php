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
        $blank = MJB_Google_Jobs::sanitize_static(array('base_salary_currency' => 'nope'));
        $this->assertSame('', $blank['base_salary_currency']);
    }

    public function test_currencies_include_flags_for_common_codes()
    {
        $list = MJB_Google_Jobs::currencies();
        $this->assertArrayHasKey('USD', $list);
        $this->assertArrayHasKey('EUR', $list);
        $this->assertArrayHasKey('GBP', $list);
        $this->assertArrayHasKey('ZAR', $list);
        $this->assertSame('us', $list['USD']['flag']);
        $this->assertSame('eu', $list['EUR']['flag']);
        $this->assertSame('gb', $list['GBP']['flag']);
        $this->assertSame('za', $list['ZAR']['flag']);
        $this->assertNotSame('', $list['USD']['name']);
        $this->assertStringContainsString('United States', $list['USD']['country']);
        $this->assertStringContainsString('South Africa', $list['ZAR']['country']);
    }

    public function test_suggest_currencies_matches_iso_and_country()
    {
        $by_iso = MJB_Google_Jobs::suggest_currencies('usd', 12);
        $this->assertNotEmpty($by_iso);
        $this->assertSame('USD', $by_iso[0]['value']);
        $this->assertSame('us', $by_iso[0]['flag']);

        $by_country = MJB_Google_Jobs::suggest_currencies('south africa', 12);
        $this->assertNotEmpty($by_country);
        $this->assertSame('ZAR', $by_country[0]['value']);

        $by_name = MJB_Google_Jobs::suggest_currencies('pound', 12);
        $codes = array_column($by_name, 'value');
        $this->assertContains('GBP', $codes);

        $empty = MJB_Google_Jobs::suggest_currencies('', 4);
        $this->assertCount(4, $empty);
        $this->assertSame('USD', $empty[0]['value']);
    }

    public function test_currencies_cover_active_iso_4217()
    {
        $list = MJB_Google_Jobs::currencies();
        $this->assertGreaterThanOrEqual(150, count($list), 'Integrations currency search should list active ISO 4217 codes, not a short popular subset.');

        foreach (array('ARS', 'BDT', 'BWP', 'CLP', 'COP', 'ETB', 'GEL', 'MAD', 'PEN', 'RSD', 'RUB', 'TZS', 'UAH', 'UGX', 'XAF', 'XOF', 'XCD') as $code) {
            $this->assertArrayHasKey($code, $list, "Missing ISO 4217 code {$code}");
        }

        foreach ($list as $code => $meta) {
            $this->assertMatchesRegularExpression('/^[A-Z]{3}$/', $code);
            $this->assertNotSame('', $meta['name']);
            $this->assertMatchesRegularExpression('/^[a-z]{2}$/', $meta['flag']);
            $this->assertArrayHasKey('country', $meta);
            $this->assertNotSame('', $meta['country']);
        }

        $botswana = MJB_Google_Jobs::suggest_currencies('botswana', 12);
        $this->assertNotEmpty($botswana);
        $this->assertSame('BWP', $botswana[0]['value']);

        $ukraine = MJB_Google_Jobs::suggest_currencies('ukraine', 12);
        $this->assertNotEmpty($ukraine);
        $this->assertSame('UAH', $ukraine[0]['value']);

        $france = MJB_Google_Jobs::suggest_currencies('france', 12);
        $this->assertNotEmpty($france);
        $this->assertSame('EUR', $france[0]['value']);
    }

    public function test_static_field_renders_currency_autocomplete()
    {
        ob_start();
        MJB_Google_Jobs::render_static_field();
        $html = ob_get_clean();
        $this->assertStringContainsString('id="mjb-gjobs-currency-ac"', $html);
        $this->assertStringContainsString('mjb-currency-ac', $html);
        $this->assertStringContainsString('class="mjb-ac__input"', $html);
        $this->assertStringContainsString('name="mjb_gjobs_static[base_salary_currency]"', $html);
        $this->assertStringContainsString('Search ISO code or country', $html);
        $this->assertStringNotContainsString('id="mjb_gjobs_currency" class="mjb-currency-select"', $html);

        $js = file_get_contents(dirname(__DIR__) . '/assets/js/mjb-admin-settings.js');
        $this->assertNotFalse($js);
        $this->assertStringContainsString('mjb_admin_currency_suggest', $js);
        $this->assertStringContainsString('mjb-currency-flag', $js);
        $this->assertStringContainsString('flagcdn.com', $js);
    }

    public function test_currency_autocomplete_uses_settings_dropdown_chrome()
    {
        ob_start();
        MJB_Google_Jobs::render_static_field();
        $html = ob_get_clean();

        $this->assertStringContainsString('mjb-jobs-filter', $html);
        $this->assertStringContainsString('mjb-settings-select', $html);
        $this->assertStringContainsString('class="mjb-jobs-menu"', $html);
        $this->assertStringNotContainsString('mjb-jobs-search', $html);
        $this->assertStringNotContainsString('mjb-jobs-ac', $html);

        $js = file_get_contents(dirname(__DIR__) . '/assets/js/mjb-admin-settings.js');
        $this->assertNotFalse($js);
        $this->assertStringContainsString('#mjb-gjobs-currency-ac .mjb-jobs-menu button', $js);
        $this->assertStringContainsString('document.createTextNode', $js);
        $this->assertStringNotContainsString('mjb-jobs-ac__item', $js);
        $this->assertStringNotContainsString('mjb-jobs-ac__hint', $js);

        $css = file_get_contents(dirname(__DIR__) . '/assets/css/mjb-admin-jobs.css');
        $this->assertNotFalse($css);
        $this->assertMatchesRegularExpression('/\.mjb-settings\s+\.mjb-currency-ac\s*\{[^}]*flex:\s*none/s', $css);
        $this->assertMatchesRegularExpression('/\.mjb-currency-ac\s+\.mjb-jobs-menu\s+button\s*\{[^}]*flex-direction:\s*row/s', $css);
    }

    public function test_static_field_sameas_matches_settings_inputs()
    {
        ob_start();
        MJB_Google_Jobs::render_static_field();
        $html = ob_get_clean();

        $this->assertStringContainsString('id="mjb_gjobs_sameas"', $html);
        $this->assertStringContainsString('type="url"', $html);
        $this->assertStringContainsString('name="mjb_gjobs_static[hiring_organization_same_as]"', $html);
        $this->assertStringContainsString('Hiring organization website', $html);
        $this->assertStringNotContainsString('Hiring organization sameAs URL', $html);
        $this->assertStringNotContainsString('class="regular-text"', $html);

        $css = file_get_contents(dirname(__DIR__) . '/assets/css/mjb-admin-jobs.css');
        $this->assertNotFalse($css);
        $this->assertMatchesRegularExpression(
            '/\.mjb-settings-field\s*>\s*input\s*\{[^}]*min-width:\s*0/s',
            $css
        );
    }
}
