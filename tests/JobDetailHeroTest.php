<?php

use PHPUnit\Framework\TestCase;

class JobDetailHeroTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['mjb_test_post_meta'] = array();
        $GLOBALS['mjb_test_posts'] = array();
        $GLOBALS['mjb_test_titles'] = array();
        $GLOBALS['mjb_test_post_status'] = array();
        $GLOBALS['mjb_test_post_types'] = array();
    }

    public function test_calendar_check_2_and_x_2_icons_render()
    {
        $check = MJB_Icons::render('calendar-check-2');
        $expire = MJB_Icons::render('calendar-x-2');

        $this->assertStringContainsString('mjb-icon--calendar-check-2', $check);
        $this->assertStringContainsString('<svg', $check);
        $this->assertStringContainsString('mjb-icon--calendar-x-2', $expire);
        $this->assertStringContainsString('<svg', $expire);
    }

    public function test_posted_by_html_is_empty_without_company()
    {
        $this->assertSame('', MJB_Shortcodes::get_job_posted_by_html(42));
    }

    public function test_posted_by_html_links_company_name_to_company_jobs()
    {
        $GLOBALS['mjb_test_post_meta'][42]['_company_name'] = 'Pixel Ink';
        $GLOBALS['mjb_test_post_meta'][42]['_company_id'] = 0;

        $html = MJB_Shortcodes::get_job_posted_by_html(42);

        $this->assertStringContainsString('Posted by', $html);
        $this->assertStringContainsString('Pixel Ink', $html);
        $this->assertStringContainsString('<a ', $html);
        $this->assertStringContainsString('href=', $html);
        $this->assertStringContainsString('company', $html);
    }

    public function test_job_date_pills_include_posted_and_optional_expiry()
    {
        $GLOBALS['mjb_test_dates'][42] = 'June 10, 2026';
        $html = MJB_Shortcodes::get_job_date_pills_html(42);

        $this->assertStringContainsString('mjb-icon--calendar-check-2', $html);
        $this->assertStringContainsString('Posted:', $html);
        $this->assertStringContainsString('June 10, 2026', $html);
        $this->assertStringNotContainsString('Expiry:', $html);

        $GLOBALS['mjb_test_post_meta'][42]['_job_expires'] = '2026-12-31';
        $html = MJB_Shortcodes::get_job_date_pills_html(42);
        $this->assertStringContainsString('mjb-icon--calendar-x-2', $html);
        $this->assertStringContainsString('Expiry:', $html);
    }

    public function test_job_listing_cards_omit_description()
    {
        $php = file_get_contents(dirname(__DIR__) . '/includes/class-mjb-shortcodes.php');
        $this->assertNotFalse($php);

        $loop_start = strpos($php, 'function render_job_loop');
        $this->assertNotFalse($loop_start);
        $loop = substr($php, $loop_start, 3500);

        $this->assertStringContainsString('mjb-job-card__title', $loop);
        $this->assertStringNotContainsString('mjb-job-card__excerpt', $loop);
        $this->assertStringNotContainsString('get_job_card_excerpt', $loop);

        $archive = file_get_contents(dirname(__DIR__) . '/templates/archive-job.php');
        $company = file_get_contents(dirname(__DIR__) . '/templates/single-company.php');
        $this->assertNotFalse($archive);
        $this->assertNotFalse($company);
        $this->assertStringContainsString('MJB_Shortcodes::render_job_loop', $archive);
        $this->assertStringContainsString('MJB_Shortcodes::render_job_loop', $company);
    }

    public function test_job_card_company_wires_preview_and_info_icon()
    {
        $GLOBALS['mjb_test_post_meta'][42]['_company_name'] = 'Pixel Ink';
        $GLOBALS['mjb_test_post_meta'][42]['_company_id'] = 7;
        $GLOBALS['mjb_test_post_status'][7] = 'publish';
        $GLOBALS['mjb_test_post_types'][7] = 'company';
        $GLOBALS['mjb_test_titles'][7] = 'Pixel Ink';

        ob_start();
        MJB_Shortcodes::render_job_card_company(42);
        $html = ob_get_clean();

        $this->assertStringContainsString('data-job-id="42"', $html);
        $this->assertStringContainsString('data-mjb-company-id="7"', $html);
        $this->assertStringContainsString('mjb-job-card__company--link', $html);
        $this->assertStringContainsString('href=', $html);
        $this->assertStringContainsString('Pixel Ink', $html);
        $this->assertStringContainsString('mjb-job-card__company-info', $html);
        $this->assertStringContainsString('mjb-icon--info', $html);
        $this->assertMatchesRegularExpression(
            '/<a[^>]*mjb-job-card__company[^>]*>.*Pixel Ink.*mjb-icon--info.*<\/a>/s',
            $html
        );
        $this->assertStringNotContainsString('<button type="button" class="mjb-job-card__company-info"', $html);
        $this->assertStringNotContainsString('<div class="mjb-job-card__company"', $html);

        $php = file_get_contents(dirname(__DIR__) . '/includes/class-mjb-shortcodes.php');
        $this->assertNotFalse($php);
        $loop_start = strpos($php, 'function render_job_loop');
        $this->assertNotFalse($loop_start);
        $loop = substr($php, $loop_start, 2500);
        $this->assertStringContainsString('data-job-id="', $loop);

        $js = file_get_contents(dirname(__DIR__) . '/assets/js/mjb-company-preview.js');
        $this->assertNotFalse($js);
        $this->assertStringContainsString("attr('data-mjb-company-id')", $js);
        $this->assertStringContainsString("attr('data-job-id')", $js);
        $this->assertStringContainsString('.fail(', $js);

        $css = file_get_contents(dirname(__DIR__) . '/assets/css/mjb-style.css');
        $this->assertNotFalse($css);
        $this->assertStringContainsString('.mjb-job-card__company-info', $css);
        $this->assertMatchesRegularExpression(
            '/\.mjb-job-card__company\s*\{[^}]*display:\s*inline-flex/s',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.mjb-job-card__company--link\s*\{[^}]*text-decoration:\s*none/s',
            $css
        );
        $this->assertDoesNotMatchRegularExpression(
            '/\.mjb-job-card__company--link\s*\{[^}]*text-decoration:\s*underline/s',
            $css
        );
        $this->assertDoesNotMatchRegularExpression(
            '/\.mjb-job-card__company--link::after/',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.mjb-job-card__company--link\s+\.mjb-job-card__company-name::after\s*\{[^}]*width:\s*100%/s',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.mjb-job-card__company--link\s+\.mjb-job-card__company-name::after\s*\{[^}]*height:\s*1px/s',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.mjb-job-card__company--link\s+\.mjb-job-card__company-name::after\s*\{[^}]*transform:\s*scaleX\(0\)/s',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.mjb-job-card__company--link\s+\.mjb-job-card__company-name::after\s*\{[^}]*transform-origin:\s*left center/s',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.mjb-job-card__company--link\s+\.mjb-job-card__company-name::after\s*\{[^}]*cubic-bezier\(0\.16,\s*1,\s*0\.3,\s*1\)/s',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.mjb-job-card__company--link:hover\s+\.mjb-job-card__company-name::after[^{]*\{[^}]*transform:\s*scaleX\(1\)/s',
            $css
        );
    }
}
