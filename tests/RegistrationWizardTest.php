<?php

use PHPUnit\Framework\TestCase;

class RegistrationWizardTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['mjb_test_usernames'] = array();
        $GLOBALS['mjb_test_emails'] = array();
    }

    public function test_username_from_email_prefers_full_email()
    {
        $username = MJB_Employer_Registration::username_from_email('jane@example.com');
        $this->assertSame('jane@example.com', $username);
    }

    public function test_username_from_email_falls_back_when_email_taken()
    {
        $GLOBALS['mjb_test_usernames'] = array('jane@example.com');
        $username = MJB_Employer_Registration::username_from_email('jane@example.com');
        $this->assertSame('jane', $username);
    }

    public function test_message_for_code_returns_known_notice()
    {
        $msg = MJB_Candidate_Registration::message_for_code('error_email_exists');
        $this->assertStringContainsString('email', strtolower($msg));
    }

    public function test_candidate_process_fails_without_nonce()
    {
        $_POST = array(
            'mjb_register_candidate' => '1',
            'mjb_first_name' => 'A',
            'mjb_last_name' => 'B',
            'mjb_email' => 'a@example.com',
            'mjb_password' => 'password1',
            'mjb_password_confirm' => 'password1',
        );
        $_FILES = array();

        $reg = new MJB_Candidate_Registration();
        $result = $reg->process_registration(true);

        $this->assertFalse($result['ok']);
        $this->assertSame('error_security', $result['code']);
    }

    public function test_employer_process_fails_without_nonce()
    {
        $_POST = array(
            'mjb_register_employer' => '1',
            'mjb_company_name' => 'Acme',
            'mjb_contact_name' => 'Jane Doe',
            'mjb_email' => 'hr@acme.com',
            'mjb_password' => 'password1',
            'mjb_password_confirm' => 'password1',
        );
        $_FILES = array();

        $reg = new MJB_Employer_Registration();
        $result = $reg->process_registration(true);

        $this->assertFalse($result['ok']);
        $this->assertSame('error_security', $result['code']);
    }
}
