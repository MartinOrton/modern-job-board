<?php

use PHPUnit\Framework\TestCase;

class CandidateAccountTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['mjb_test_user_meta']);
        parent::tearDown();
    }

    public function test_deletion_requires_the_word_delete()
    {
        $this->assertTrue(MJB_Candidate_Account::deletion_confirmed('delete'));
        $this->assertTrue(MJB_Candidate_Account::deletion_confirmed(' DELETE '));
        $this->assertTrue(MJB_Candidate_Account::deletion_confirmed('Delete'));
        $this->assertFalse(MJB_Candidate_Account::deletion_confirmed('deleted'));
        $this->assertFalse(MJB_Candidate_Account::deletion_confirmed(''));
    }

    public function test_current_password_ignores_surrounding_whitespace()
    {
        $hash = wp_hash_password('correct horse');
        $this->assertTrue(MJB_Candidate_Account::current_password_matches('correct horse', $hash, 7));
        $this->assertTrue(MJB_Candidate_Account::current_password_matches("  correct horse\n", $hash, 7));
        $this->assertFalse(MJB_Candidate_Account::current_password_matches('wrong horse', $hash, 7));
        $this->assertFalse(MJB_Candidate_Account::current_password_matches('   ', $hash, 7));
    }

    public function test_device_label_uses_the_user_agent_only()
    {
        $this->assertSame('Chrome on Windows', MJB_Candidate_Account::device_label('Mozilla/5.0 Chrome/120.0 Windows NT 10.0'));
        $this->assertSame('Safari on iPhone', MJB_Candidate_Account::device_label('Mozilla/5.0 (iPhone) Safari/604.1'));
        $this->assertSame('This browser', MJB_Candidate_Account::device_label(''));
        $this->assertSame('smartphone', MJB_Candidate_Account::device_icon('iPhone'));
        $this->assertSame('monitor', MJB_Candidate_Account::device_icon('Windows'));
    }

    public function test_notification_defaults_and_alert_pause()
    {
        $this->assertTrue(MJB_Candidate_Account::allows_email('applications', 41));
        $this->assertTrue(MJB_Candidate_Account::allows_email('messages', 41));
        $this->assertFalse(MJB_Candidate_Account::allows_email('news', 41));

        update_user_meta(41, MJB_Candidate_Account::META_NOTIFY_APPLICATIONS, '0');
        update_user_meta(41, MJB_Candidate_Account::META_NOTIFY_NEWS, '1');
        update_user_meta(41, MJB_Candidate_Account::META_ALERTS_PAUSED, '1');

        $this->assertFalse(MJB_Candidate_Account::allows_email('applications', 41));
        $this->assertTrue(MJB_Candidate_Account::allows_email('news', 41));
        $this->assertTrue(MJB_Candidate_Account::alerts_paused(41));
        $this->assertSame('off', MJB_Job_Alerts::preference_for_user(41));
    }

    public function test_account_screen_is_wired_to_real_actions()
    {
        $account = file_get_contents(dirname(__DIR__) . '/includes/class-mjb-candidate-account.php');
        $dashboard = file_get_contents(dirname(__DIR__) . '/includes/class-mjb-candidate-dashboard.php');
        $alerts = file_get_contents(dirname(__DIR__) . '/includes/class-mjb-job-alerts.php');
        $emails = file_get_contents(dirname(__DIR__) . '/includes/class-mjb-emails.php');
        $messages = file_get_contents(dirname(__DIR__) . '/includes/class-mjb-messaging.php');

        $this->assertNotFalse($account);
        $this->assertStringContainsString('mjb_delete_confirm', $account);
        $this->assertStringContainsString('mjb_current_password_typed', $account);
        $this->assertStringContainsString('wp_ajax_mjb_candidate_password', $account);
        $this->assertStringContainsString('wp_doing_ajax()', $account);
        $this->assertStringContainsString('mjb-cd-password-status', $account);
        $this->assertStringContainsString('mjb-cd-btn-spin', $account);
        $script = file_get_contents(dirname(__DIR__) . '/assets/js/mjb-candidate-dashboard.js');
        $this->assertStringContainsString("body.set('action', 'mjb_candidate_password')", $script);
        $this->assertStringContainsString('event.preventDefault()', $script);
        $this->assertStringContainsString('WP_Session_Tokens', $account);
        $this->assertStringContainsString('mjb-candidate-data.json', $account);
        $this->assertStringNotContainsString('Danny Leather', $account);
        $this->assertStringContainsString('MJB_Candidate_Account::render', $dashboard);
        $this->assertStringContainsString('mjb-cd-layer-account', $dashboard);
        $this->assertStringContainsString('mjb-cd-account', $dashboard);
        $this->assertStringContainsString('alerts_paused', $alerts);
        $this->assertStringContainsString("allows_email('applications'", $emails);
        $this->assertStringContainsString("allows_email('messages'", $messages);

        $messages = MJB_Candidate_Account::notice_messages(array());
        $this->assertArrayHasKey('error_delete_confirm', $messages);
        $this->assertArrayHasKey('success_account_deleted', $messages);
    }
}
