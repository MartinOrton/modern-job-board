<?php

use PHPUnit\Framework\TestCase;

class CandidateDashboardTest extends TestCase
{
    public function test_welcome_message_includes_first_name()
    {
        $message = MJB_Notices::candidate_registered_message('Amina');
        $this->assertSame('Registration successful! Welcome Amina.', $message);
    }

    public function test_welcome_message_without_name_stays_generic()
    {
        $message = MJB_Notices::candidate_registered_message('  ');
        $this->assertSame('Registration successful! Welcome.', $message);
    }

    public function test_phone_submission_keeps_country_and_e164()
    {
        $_POST = array(
            'mjb_phone' => '+27 82 000 0001',
            'mjb_phone_country' => 'za',
        );

        $parsed = MJB_Candidate_Registration::sanitize_phone_submission();

        $this->assertSame('+27820000001', $parsed['phone']);
        $this->assertSame('ZA', $parsed['country']);
    }

    public function test_dashboard_drops_duplicate_title_and_uses_phone_field()
    {
        $php = file_get_contents(dirname(__DIR__) . '/includes/class-mjb-candidate-dashboard.php');
        $this->assertNotFalse($php);
        $this->assertStringNotContainsString("esc_html_e('Candidate Dashboard'", $php);
        $this->assertStringContainsString('mjb-candidate-dashboard', $php);
        $this->assertStringContainsString('render_phone_field', $php);
        $this->assertStringContainsString('mjb-cd-stats', $php);
        $this->assertStringContainsString('mjb-cd-cv-view', $php);
        $this->assertStringContainsString('mjb-cd-cv-delete', $php);
        $this->assertStringContainsString('mjb-cd-cv-dialog', $php);
        $this->assertStringContainsString("'type' => 'geocity'", $php);
        $this->assertStringContainsString('mjb-cd-stage', $php);
        $this->assertStringContainsString('wp_ajax_mjb_candidate_layer', $php);
        $this->assertStringContainsString('wp_ajax_mjb_candidate_profile', $php);
        $this->assertStringContainsString('mjb-skeleton', $php);
        $this->assertStringContainsString('Drop a new photo or browse', $php);
        $this->assertStringContainsString('mjb_delete_photo', $php);
        $this->assertStringNotContainsString('data-photo-caption', $php);
        $this->assertStringNotContainsString('Danny Leather', $php);

        $js = file_get_contents(dirname(__DIR__) . '/assets/js/mjb-candidate-dashboard.js');
        $this->assertNotFalse($js);
        $this->assertStringContainsString('activateLayer', $js);
        $this->assertStringContainsString('dash.spinner', $js);

        $css = file_get_contents(dirname(__DIR__) . '/assets/css/mjb-candidate-dashboard.css');
        $this->assertNotFalse($css);
        $this->assertStringContainsString('.mjb-cd-stat', $css);

        $bootstrap = file_get_contents(dirname(__DIR__) . '/modern-job-board.php');
        $this->assertNotFalse($bootstrap);
        $this->assertStringContainsString("page_has_shortcode('mjb_candidate_dashboard')", $bootstrap);
        $this->assertStringContainsString('mjb-candidate-dashboard.css', $bootstrap);
    }
}
