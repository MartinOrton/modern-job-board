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

    public function test_format_company_job_count_label_singular()
    {
        $this->assertSame('1 job', MJB_Shortcodes::format_company_job_count_label(1));
    }

    public function test_format_company_job_count_label_plural()
    {
        $this->assertSame('3 jobs', MJB_Shortcodes::format_company_job_count_label(3));
        $this->assertSame('0 jobs', MJB_Shortcodes::format_company_job_count_label(0));
    }
}