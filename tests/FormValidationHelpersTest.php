<?php

use PHPUnit\Framework\TestCase;

class FormValidationHelpersTest extends TestCase
{
    public function test_required_mark_outputs_asterisk_span()
    {
        $html = MJB_Shortcodes::required_mark();

        $this->assertStringContainsString('mjb-required-mark', $html);
        $this->assertStringContainsString('*', $html);
        $this->assertStringContainsString('aria-hidden="true"', $html);
    }

    public function test_required_fields_note_outputs_legend()
    {
        $html = MJB_Shortcodes::required_fields_note();

        $this->assertStringContainsString('mjb-form-required-note', $html);
        $this->assertStringContainsString('mjb-required-mark', $html);
        $this->assertStringContainsString('Required fields', $html);
    }
}
