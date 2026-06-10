<?php

use PHPUnit\Framework\TestCase;

class ApplicationGuardTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['mjb_test_transients'] = array();
        $GLOBALS['mjb_test_posts'] = array();
        $GLOBALS['mjb_test_duplicate_exists'] = null;
        unset($_POST[MJB_Application_Guard::HONEYPOT_FIELD]);
    }

    public function test_rate_limit_key_is_stable_for_ip()
    {
        $key_one = MJB_Application_Guard::get_rate_limit_key('203.0.113.10');
        $key_two = MJB_Application_Guard::get_rate_limit_key('203.0.113.10');

        $this->assertSame($key_one, $key_two);
        $this->assertStringStartsWith('mjb_app_rate_', $key_one);
    }

    public function test_rate_limit_blocks_after_max_submissions()
    {
        $ip = '203.0.113.55';

        for ($i = 0; $i < MJB_Application_Guard::RATE_LIMIT_MAX; $i++) {
            MJB_Application_Guard::record_submission($ip);
        }

        $this->assertTrue(MJB_Application_Guard::is_rate_limited($ip));
        $this->assertSame(MJB_Application_Guard::RATE_LIMIT_MAX, MJB_Application_Guard::get_rate_limit_count($ip));
    }

    public function test_duplicate_application_detects_existing_post()
    {
        $GLOBALS['mjb_test_duplicate_exists'] = 101;

        $this->assertTrue(MJB_Application_Guard::has_duplicate_application(42, 'candidate@example.com'));
    }

    public function test_registration_rate_limit_blocks_after_max_attempts()
    {
        $ip = '203.0.113.99';

        for ($i = 0; $i < MJB_Application_Guard::REG_RATE_LIMIT_MAX; $i++) {
            MJB_Application_Guard::record_registration($ip);
        }

        $this->assertTrue(MJB_Application_Guard::is_registration_rate_limited($ip));
    }

    public function test_validate_spam_protection_rejects_honeypot()
    {
        $this->assertNull(MJB_Application_Guard::validate_spam_protection());
        $_POST[MJB_Application_Guard::HONEYPOT_FIELD] = 'spam';
        $this->assertSame('error_spam', MJB_Application_Guard::validate_spam_protection());
    }

    public function test_duplicate_application_returns_false_without_email()
    {
        $this->assertFalse(MJB_Application_Guard::has_duplicate_application(42, ''));
    }

    public function test_honeypot_is_not_triggered_when_empty()
    {
        $this->assertFalse(MJB_Application_Guard::is_honeypot_triggered(''));
        $this->assertFalse(MJB_Application_Guard::is_honeypot_triggered('   '));
    }

    public function test_honeypot_is_triggered_when_filled()
    {
        $this->assertTrue(MJB_Application_Guard::is_honeypot_triggered('https://spam.example'));
    }
}