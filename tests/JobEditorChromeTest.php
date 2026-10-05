<?php

use PHPUnit\Framework\TestCase;

class JobEditorChromeTest extends TestCase
{
    public function test_job_editor_header_matches_jobs_tab_lucide_chrome()
    {
        $php = file_get_contents(dirname(__DIR__) . '/includes/class-mjb-admin.php');
        $this->assertNotFalse($php);
        $start = strpos($php, 'function job_editor_chrome_open');
        $this->assertNotFalse($start);
        $chunk = substr($php, $start, 2200);
        $this->assertStringContainsString('mjb-sec', $chunk);
        $this->assertStringContainsString("MJB_Icons::render('pencil'", $chunk);
        $this->assertStringContainsString("MJB_Icons::render('chevron-left'", $chunk);
        $this->assertStringContainsString('Back to Jobs', $chunk);
        $this->assertStringContainsString('mjb-jobs', $chunk);
    }

    public function test_listing_details_use_settings_stack_not_form_table()
    {
        $php = file_get_contents(dirname(__DIR__) . '/includes/class-mjb-admin.php');
        $this->assertNotFalse($php);
        $start = strpos($php, 'function render_job_meta_box');
        $this->assertNotFalse($start);
        $chunk = substr($php, $start, 4500);
        $this->assertStringContainsString('mjb-settings-stack', $chunk);
        $this->assertStringContainsString('mjb-settings-field', $chunk);
        $this->assertStringContainsString('mjb-settings-check', $chunk);
        $this->assertStringNotContainsString('<table class="form-table"', $chunk);
    }

    public function test_listing_details_date_fields_use_license_calendar_not_native_picker()
    {
        $php = file_get_contents(dirname(__DIR__) . '/includes/class-mjb-admin.php');
        $this->assertNotFalse($php);
        $start = strpos($php, 'function render_job_meta_box');
        $end = strpos($php, 'function save_job_meta_data');
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);
        $chunk = substr($php, $start, $end - $start);

        $this->assertStringNotContainsString('type="date"', $chunk);
        $this->assertStringNotContainsString('type="datetime-local"', $chunk);
        $this->assertStringContainsString('id="mjb_job_expires"', $chunk);
        $this->assertStringContainsString('id="mjb_job_publish_at"', $chunk);
        $this->assertStringContainsString('data-mjb-cal-open', $chunk);
        $this->assertStringContainsString('data-mjb-cal-for="mjb_job_expires"', $chunk);
        $this->assertStringContainsString('data-mjb-cal-for="mjb_job_publish_at"', $chunk);
        $this->assertStringContainsString("MJB_Icons::render('calendar'", $chunk);
        $this->assertStringContainsString('mjb-license-cal', $chunk);
        $this->assertStringContainsString('mjb-license-cal__viewport', $chunk);
        $this->assertStringContainsString('data-cal-prev', $chunk);
        $this->assertStringContainsString('data-cal-next', $chunk);
        $this->assertStringContainsString("MJB_Icons::render('chevron-left'", $chunk);
        $this->assertStringContainsString("MJB_Icons::render('chevron-right'", $chunk);
        $this->assertStringContainsString('mjb-modal__dialog', $chunk);
        $this->assertStringContainsString('mjb-modal__header', $chunk);
        $this->assertStringContainsString('mjb-modal__footer', $chunk);
    }

    public function test_job_editor_enqueues_shared_calendar_script()
    {
        $php = file_get_contents(dirname(__DIR__) . '/includes/class-mjb-admin.php');
        $this->assertNotFalse($php);
        $this->assertStringContainsString('mjb-admin-calendar.js', $php);

        $path = dirname(__DIR__) . '/assets/js/mjb-admin-calendar.js';
        $this->assertFileExists($path);
        $js = file_get_contents($path);
        $this->assertNotFalse($js);
        $this->assertStringContainsString('data-mjb-cal-open', $js);
        $this->assertStringContainsString('data-mjb-cal-for', $js);
        $this->assertStringContainsString('slideCal', $js);
        $this->assertStringContainsString('prefers-reduced-motion', $js);
    }

    public function test_job_editor_css_lock_and_media_modals_match_license()
    {
        $css = file_get_contents(dirname(__DIR__) . '/assets/css/mjb-admin.css');
        $this->assertNotFalse($css);
        $this->assertMatchesRegularExpression(
            '/body\.mjb-job-editor-screen[^{]*#post-lock-dialog[^{]*\{[^}]*position:\s*fixed/s',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/body\.mjb-job-editor-screen[^{]*#post-lock-dialog[^{]*\{[^}]*display:\s*flex/s',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/body\.mjb-job-editor-screen[^{]*#post-lock-dialog\.hidden\s*\{[^}]*display:\s*none/s',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/body\.mjb-job-editor-screen[^{]*\.notification-dialog\s*\{[^}]*border-radius:\s*var\(--mjb-radius-lg\)/s',
            $css
        );
        $this->assertStringContainsString('rgba(15, 23, 42, 0.45)', $css);
        $this->assertMatchesRegularExpression(
            '/body\.mjb-job-editor-screen[^{]*\.media-modal-content\s*\{[^}]*border-radius:\s*var\(--mjb-radius-lg\)/s',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/body\.mjb-job-editor-screen[^{]*\.handle-order-higher[^{]*\{[^}]*border-radius:\s*var\(--mjb-radius-sm\)/s',
            $css
        );
        $this->assertStringContainsString('mjb-job-editor .order-higher-indicator', $css);
        $this->assertStringContainsString('mjb-job-editor .order-lower-indicator', $css);
    }

    public function test_job_editor_publish_box_uses_compact_card_padding()
    {
        $css = file_get_contents(dirname(__DIR__) . '/assets/css/mjb-admin.css');
        $this->assertNotFalse($css);
        // WP core uses #poststuff #submitdiv .inside { padding: 0 }. Include both IDs to win.
        $this->assertMatchesRegularExpression(
            '/body\.mjb-job-editor-screen[^{]*#poststuff\s+#submitdiv\s+\.inside\s*\{[^}]*padding:\s*var\(--mjb-pad-card-compact\)/s',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/body\.mjb-job-editor-screen[^{]*#minor-publishing-actions\s*\{[^}]*display:\s*flex/s',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/body\.mjb-job-editor-screen[^{]*#major-publishing-actions\s*\{[^}]*display:\s*flex/s',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/body\.mjb-job-editor-screen[^{]*#save-action[^{]*\{[^}]*float:\s*none/s',
            $css
        );
    }

    public function test_job_editor_publish_box_uses_lucide_not_dashicons()
    {
        $php = file_get_contents(dirname(__DIR__) . '/includes/class-mjb-admin.php');
        $this->assertNotFalse($php);
        $this->assertStringContainsString('mjb-admin-job-editor.js', $php);
        $this->assertStringContainsString("MJB_Icons::render('circle-dot'", $php);
        $this->assertStringContainsString("MJB_Icons::render('eye'", $php);
        $this->assertStringContainsString("MJB_Icons::render('calendar'", $php);

        $svg = MJB_Icons::render('circle-dot', 16);
        $this->assertStringContainsString('mjb-icon--circle-dot', $svg);
        $this->assertStringContainsString('stroke-width="1.5"', $svg);

        $js = file_get_contents(dirname(__DIR__) . '/assets/js/mjb-admin-job-editor.js');
        $this->assertNotFalse($js);
        $this->assertStringContainsString('mjbJobEditor.icons', $js);
        $this->assertStringContainsString('misc-pub-post-status', $js);
        $this->assertStringContainsString('misc-pub-visibility', $js);
        $this->assertStringContainsString('misc-pub-curtime', $js);

        $css = file_get_contents(dirname(__DIR__) . '/assets/css/mjb-admin.css');
        $this->assertNotFalse($css);
        $this->assertMatchesRegularExpression(
            '/#post-body[^{]*\.misc-pub-post-status::before[^{]*\{[^}]*content:\s*none/s',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/#post-body[^{]*#visibility::before[^{]*\{[^}]*content:\s*none/s',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/#timestamp::before[^{]*\{[^}]*content:\s*none/s',
            $css
        );
    }
}
