<?php

namespace MJB\Tests;

use MJB_Shortcodes;
use PHPUnit\Framework\TestCase;

class CompanyTemplatesTest extends TestCase
{
    public function test_get_company_initials_uses_first_two_words()
    {
        $this->assertSame('AC', MJB_Shortcodes::get_company_initials('Acme Corporation'));
    }

    public function test_get_company_initials_handles_single_word()
    {
        $this->assertSame('O', MJB_Shortcodes::get_company_initials('Oak'));
    }

    public function test_get_company_initials_handles_empty_name()
    {
        $this->assertSame('?', MJB_Shortcodes::get_company_initials(''));
    }
}