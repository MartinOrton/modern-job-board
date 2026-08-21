<?php

use PHPUnit\Framework\TestCase;

class LicenseCommerceTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['mjb_test_options'] = array();
        $GLOBALS['mjb_test_mails'] = array();
        $GLOBALS['mjb_test_post_meta'] = array();
    }

    public function test_purchase_url_uses_configured_https()
    {
        $GLOBALS['mjb_test_options'][MJB_License_Commerce::OPTION_URL_PRO] = 'https://pay.example.com/pro';

        $this->assertSame(
            'https://pay.example.com/pro',
            MJB_License_Commerce::get_purchase_url(MJB_License::PLAN_PRO)
        );
    }

    public function test_purchase_url_falls_back_to_mailto()
    {
        $GLOBALS['mjb_test_options'][MJB_License_Commerce::OPTION_SALES_EMAIL] = 'sales@example.com';

        $url = MJB_License_Commerce::get_purchase_url(MJB_License::PLAN_BUSINESS);
        $this->assertStringStartsWith('mailto:sales@example.com?', $url);
        $this->assertStringContainsString('subject=', $url);
        $this->assertStringContainsString('Business', urldecode($url));
    }

    public function test_sanitize_purchase_url_rejects_javascript()
    {
        $this->assertSame('', MJB_License_Commerce::sanitize_purchase_url('javascript:alert(1)'));
        $this->assertSame('https://example.com/buy', MJB_License_Commerce::sanitize_purchase_url('https://example.com/buy'));
    }

    public function test_issue_key_returns_signed_key()
    {
        $key = MJB_License_Commerce::issue_key(MJB_License::PLAN_PRO, '00000000');
        $this->assertIsString($key);
        $this->assertStringStartsWith('MJB-PRO-', $key);
        $this->assertTrue(MJB_License::activate_key($key));
        $this->assertSame(MJB_License::PLAN_PRO, MJB_License::get_plan());
    }

    public function test_email_license_key_sends_mail()
    {
        $key = MJB_License_Commerce::issue_key(MJB_License::PLAN_BUSINESS, '00000000');
        $this->assertTrue(MJB_License_Commerce::email_license_key('buyer@example.com', $key, MJB_License::PLAN_BUSINESS, 42));
        $this->assertNotEmpty($GLOBALS['mjb_test_mails']);
        $mail = $GLOBALS['mjb_test_mails'][0];
        $this->assertSame('buyer@example.com', $mail['to']);
        $this->assertStringContainsString($key, $mail['message']);
        $this->assertStringContainsString('Business', $mail['subject']);
    }

    public function test_email_rejects_invalid_address()
    {
        $this->assertFalse(MJB_License_Commerce::email_license_key('not-an-email', 'MJB-PRO-00000000-AAAAAAAA', MJB_License::PLAN_PRO));
        $this->assertEmpty($GLOBALS['mjb_test_mails']);
    }

    public function test_purchase_cta_html_contains_buy_and_activate()
    {
        $html = MJB_License_Commerce::purchase_cta_html(MJB_License::PLAN_PRO);
        $this->assertStringContainsString('Buy Pro', $html);
        $this->assertStringContainsString('I already have a key', $html);
        $this->assertStringContainsString('mjb-license', $html);
    }

    public function test_is_safe_url()
    {
        $this->assertTrue(MJB_License_Commerce::is_safe_url('https://example.com'));
        $this->assertTrue(MJB_License_Commerce::is_safe_url('mailto:a@b.com'));
        $this->assertFalse(MJB_License_Commerce::is_safe_url('ftp://example.com'));
        $this->assertFalse(MJB_License_Commerce::is_safe_url(''));
    }
}
