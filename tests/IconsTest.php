<?php

use PHPUnit\Framework\TestCase;

class IconsTest extends TestCase
{
    public function test_rendered_lucide_icons_use_stroke_width_1_5()
    {
        $svg = MJB_Icons::render('briefcase', 16);
        $this->assertStringContainsString('stroke-width="1.5"', $svg);
        $this->assertStringNotContainsString('stroke-width="2"', $svg);
    }

    public function test_plugin_js_lucide_svgs_use_stroke_width_1_5()
    {
        $matched = 0;
        foreach (glob(dirname(__DIR__) . '/assets/js/mjb-*.js') as $file) {
            $js = file_get_contents($file);
            $this->assertNotFalse($js);
            if (false === strpos($js, 'class="mjb-icon"')) {
                continue;
            }
            $matched++;
            $this->assertStringContainsString('stroke-width="1.5"', $js, basename($file));
            $this->assertStringNotContainsString('stroke-width="2"', $js, basename($file));
        }
        $this->assertGreaterThan(0, $matched);
    }

    public function test_shared_css_sets_lucide_stroke_width_1_5()
    {
        $css = file_get_contents(dirname(__DIR__) . '/assets/css/mjb-shared.css');
        $this->assertNotFalse($css);
        $this->assertMatchesRegularExpression(
            '/\.mjb-icon\s*\{[^}]*stroke-width:\s*1\.5/',
            $css
        );
    }

    public function test_plugin_css_does_not_override_lucide_stroke_away_from_1_5()
    {
        foreach (glob(dirname(__DIR__) . '/assets/css/mjb-*.css') as $file) {
            $css = file_get_contents($file);
            $this->assertNotFalse($css);
            $this->assertDoesNotMatchRegularExpression(
                '/stroke-width:\s*(?!1\.5\b)[\d.]+/',
                $css,
                basename($file) . ' must not override Lucide stroke away from 1.5'
            );
            $this->assertStringNotContainsString(
                "stroke-width='2'",
                $css,
                basename($file) . ' Lucide data-URI icons must use stroke-width 1.5'
            );
        }
    }
}
