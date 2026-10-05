<?php

use PHPUnit\Framework\TestCase;

class CompanyPreviewTest extends TestCase
{
    public function test_globe_icon_renders()
    {
        $svg = MJB_Icons::render('globe', 18);
        $this->assertStringContainsString('mjb-icon--globe', $svg);
        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('width="18"', $svg);
    }

    public function test_preview_localizes_website_linkedin_and_x_icons()
    {
        $php = file_get_contents(dirname(__DIR__) . '/includes/class-mjb-company-preview.php');
        $this->assertNotFalse($php);
        $this->assertStringContainsString("MJB_Icons::render('globe'", $php);
        $this->assertStringContainsString("MJB_Icons::render('linkedin'", $php);
        $this->assertStringContainsString("MJB_Icons::render('twitter'", $php);
        $this->assertStringContainsString("'icons'", $php);
    }

    public function test_preview_js_links_logo_and_name_and_uses_social_icons()
    {
        $js = file_get_contents(dirname(__DIR__) . '/assets/js/mjb-company-preview.js');
        $this->assertNotFalse($js);
        $this->assertStringContainsString('mjb-company-preview__logo-link', $js);
        $this->assertStringContainsString('mjb-company-preview__name-link', $js);
        $this->assertStringContainsString('data.url', $js);
        $this->assertStringContainsString('mjb-company-preview__icon', $js);
        $this->assertStringContainsString('mjbCompanyPreview.icons', $js);
        $this->assertStringNotContainsString('>Website</a>', $js);
        $this->assertStringNotContainsString('>LinkedIn</a>', $js);
        $this->assertStringNotContainsString("links.join(' · ')", $js);
    }

    public function test_preview_css_identity_links_have_no_underline_and_icons_match_share()
    {
        $css = file_get_contents(dirname(__DIR__) . '/assets/css/mjb-style.css');
        $this->assertNotFalse($css);
        $this->assertMatchesRegularExpression(
            '/\.mjb-company-preview__logo-link[^{]*\{[^}]*text-decoration:\s*none/s',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.mjb-company-preview__name-link[^{]*\{[^}]*text-decoration:\s*none/s',
            $css
        );
        $this->assertDoesNotMatchRegularExpression(
            '/\.mjb-company-preview__(logo|name)-link[^{]*:hover[^{]*\{[^}]*text-decoration:\s*underline/s',
            $css
        );
        $this->assertDoesNotMatchRegularExpression(
            '/\.mjb-company-preview__links a:hover[^{]*\{[^}]*text-decoration:\s*underline/s',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.mjb-company-preview__icon\s*\{[^}]*width:\s*2\.25rem/s',
            $css
        );
        $this->assertMatchesRegularExpression(
            '/\.mjb-company-preview__icon\s*\{[^}]*text-decoration:\s*none/s',
            $css
        );
    }
}
