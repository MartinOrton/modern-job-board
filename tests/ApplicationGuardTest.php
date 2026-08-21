<?php

use PHPUnit\Framework\TestCase;

class ApplicationGuardTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['mjb_test_transients'] = array();
        $GLOBALS['mjb_test_posts'] = array();
        $GLOBALS['mjb_test_options'] = array(
            MJB_Application_Guard::OPTION_MIN_SECONDS => 0,
            MJB_Application_Guard::OPTION_BANNED_IPS => '',
            MJB_Application_Guard::OPTION_SPAM_LOG => array(),
        );
        $GLOBALS['mjb_test_duplicate_exists'] = null;
        unset($_POST[MJB_Application_Guard::HONEYPOT_FIELD]);
        unset($_POST[MJB_Application_Guard::TIME_TRAP_FIELD]);
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

    public function test_time_trap_rejects_too_fast()
    {
        $GLOBALS['mjb_test_options'][MJB_Application_Guard::OPTION_MIN_SECONDS] = 5;
        $this->assertTrue(MJB_Application_Guard::is_time_trap_triggered(time()));
        $this->assertFalse(MJB_Application_Guard::is_time_trap_triggered(time() - 10));
    }

    public function test_banned_ip_detected()
    {
        $GLOBALS['mjb_test_options'][MJB_Application_Guard::OPTION_BANNED_IPS] = "203.0.113.9\n198.51.100.1";
        $this->assertTrue(MJB_Application_Guard::is_ip_banned('203.0.113.9'));
        $this->assertFalse(MJB_Application_Guard::is_ip_banned('203.0.113.10'));
    }

    public function test_spam_log_records_entry()
    {
        MJB_Application_Guard::log_spam('honeypot', 'application', '203.0.113.7');
        $log = MJB_Application_Guard::get_spam_log();
        $this->assertNotEmpty($log);
        $this->assertSame('honeypot', $log[0]['reason']);
        $this->assertSame('203.0.113.7', $log[0]['ip']);
    }
}