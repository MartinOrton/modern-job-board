<?php

use PHPUnit\Framework\TestCase;

class RecaptchaTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['mjb_test_options'] = array();
        $GLOBALS['mjb_test_remote_responses'] = array();
    }

    public function test_is_enabled_requires_all_settings()
    {
        $GLOBALS['mjb_test_options']['mjb_recaptcha_enabled'] = 1;
        $GLOBALS['mjb_test_options']['mjb_recaptcha_site_key'] = 'site-key';
        $GLOBALS['mjb_test_options']['mjb_recaptcha_secret_key'] = 'secret-key';

        $this->assertTrue(MJB_Recaptcha::is_enabled());

        $GLOBALS['mjb_test_options']['mjb_recaptcha_secret_key'] = '';
        $this->assertFalse(MJB_Recaptcha::is_enabled());
    }

    public function test_verify_skips_when_disabled()
    {
        $this->assertTrue(MJB_Recaptcha::verify(''));
    }

    public function test_verify_rejects_empty_response_when_enabled()
    {
        $GLOBALS['mjb_test_options']['mjb_recaptcha_enabled'] = 1;
        $GLOBALS['mjb_test_options']['mjb_recaptcha_site_key'] = 'site-key';
        $GLOBALS['mjb_test_options']['mjb_recaptcha_secret_key'] = 'secret-key';

        $this->assertFalse(MJB_Recaptcha::verify(''));
    }

    public function test_verify_accepts_successful_google_response()
    {
        $GLOBALS['mjb_test_options']['mjb_recaptcha_enabled'] = 1;
        $GLOBALS['mjb_test_options']['mjb_recaptcha_site_key'] = 'site-key';
        $GLOBALS['mjb_test_options']['mjb_recaptcha_secret_key'] = 'secret-key';
        $GLOBALS['mjb_test_remote_responses'][] = array('body' => json_encode(array('success' => true)));

        $this->assertTrue(MJB_Recaptcha::verify('valid-token'));
    }

    public function test_verify_rejects_failed_google_response()
    {
        $GLOBALS['mjb_test_options']['mjb_recaptcha_enabled'] = 1;
        $GLOBALS['mjb_test_options']['mjb_recaptcha_site_key'] = 'site-key';
        $GLOBALS['mjb_test_options']['mjb_recaptcha_secret_key'] = 'secret-key';
        $GLOBALS['mjb_test_remote_responses'][] = array('body' => json_encode(array('success' => false)));

        $this->assertFalse(MJB_Recaptcha::verify('bad-token'));
    }
}