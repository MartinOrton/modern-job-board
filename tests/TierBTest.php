<?php

use PHPUnit\Framework\TestCase;

class TierBTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['mjb_test_options'] = array();
        $GLOBALS['mjb_test_post_meta'] = array();
        $GLOBALS['mjb_test_user_meta'] = array();
        $GLOBALS['mjb_test_posts'] = array();
        $GLOBALS['mjb_test_titles'] = array();
        $GLOBALS['mjb_test_permalinks'] = array();
    }

    public function test_email_template_merge_replaces_tags()
    {
        $out = MJB_Email_Templates::merge('Hello {user_name} on {site_name}', array(
            'user_name' => 'Ada',
            'site_name' => 'Board',
        ));
        $this->assertSame('Hello Ada on Board', $out);
    }

    public function test_email_template_merge_strips_unknown_tags()
    {
        $out = MJB_Email_Templates::merge('X {missing} Y', array());
        $this->assertSame('X  Y', $out);
    }

    public function test_ingestion_normalize_requires_title()
    {
        $err = MJB_Ingestion::normalize_job_payload(array('company' => 'Acme'));
        $this->assertInstanceOf(WP_Error::class, $err);
    }

    public function test_ingestion_normalize_accepts_broadbean_ish_keys()
    {
        $data = MJB_Ingestion::normalize_job_payload(array(
            'jobTitle' => 'Engineer',
            'companyName' => 'Acme',
            'job_description' => '<p>Hi</p>',
            'city' => 'Cape Town',
            'reference' => 'ATS-9',
            'application_url' => 'https://example.com/apply',
            'source' => 'broadbean',
        ));
        $this->assertIsArray($data);
        $this->assertSame('Engineer', $data['title']);
        $this->assertSame('Acme', $data['company']);
        $this->assertSame('Cape Town', $data['location']);
        $this->assertSame('ATS-9', $data['external_id']);
        $this->assertSame('https://example.com/apply', $data['apply_url']);
        $this->assertSame('broadbean', $data['source']);
    }

    public function test_mailchimp_datacenter_from_key()
    {
        $this->assertSame('us21', MJB_Mailchimp::datacenter_from_key('abc123-us21'));
        $this->assertSame('', MJB_Mailchimp::datacenter_from_key('invalid'));
    }

    public function test_memberships_alert_slot_limit_sums_free_and_extra()
    {
        $GLOBALS['mjb_test_options'][MJB_Memberships::OPTION_FREE_ALERT_SLOTS] = 3;
        $GLOBALS['mjb_test_user_meta'][7][MJB_Memberships::META_ALERT_SLOTS] = 5;
        $this->assertSame(8, MJB_Memberships::alert_slot_limit(7));
    }

    public function test_memberships_grant_trial_once()
    {
        $GLOBALS['mjb_test_options'][MJB_Memberships::OPTION_TRIAL_CREDITS] = 2;
        $GLOBALS['mjb_test_options'][MJB_Memberships::OPTION_TRIAL_CV_DAYS] = 0;
        $GLOBALS['mjb_test_user_meta'][11] = array();

        MJB_Memberships::grant_employer_trial(11, 0, false);
        $this->assertSame(2, (int) $GLOBALS['mjb_test_user_meta'][11]['_mjb_job_credits']);
        $this->assertNotEmpty($GLOBALS['mjb_test_user_meta'][11][MJB_Memberships::USER_TRIAL_GRANTED]);

        // Second call must not double-credit.
        MJB_Memberships::grant_employer_trial(11, 0, false);
        $this->assertSame(2, (int) $GLOBALS['mjb_test_user_meta'][11]['_mjb_job_credits']);
    }

    public function test_memberships_feature_job_sets_meta()
    {
        MJB_Memberships::feature_job(42, 0);
        $this->assertSame('1', $GLOBALS['mjb_test_post_meta'][42]['_featured']);
    }

    public function test_promotions_banners_for_position()
    {
        $GLOBALS['mjb_test_options'][MJB_Promotions::OPTION_BANNERS] = array(
            array('html' => '<div>A</div>', 'position' => 0, 'enabled' => true, 'label' => 'top'),
            array('html' => '<div>B</div>', 'position' => 5, 'enabled' => true, 'label' => 'mid'),
            array('html' => '<div>C</div>', 'position' => 5, 'enabled' => false, 'label' => 'off'),
        );
        $zero = MJB_Promotions::banners_for_position(0);
        $this->assertCount(1, $zero);
        $mid = MJB_Promotions::banners_for_position(5);
        $this->assertCount(1, $mid);
        $this->assertSame('mid', $mid[0]['label']);
    }

    public function test_backfill_filter_respects_disabled()
    {
        $GLOBALS['mjb_test_options'][MJB_Ingestion::OPTION_BACKFILL_ENABLED] = '0';
        $html = MJB_Ingestion::filter_empty_list('<p>empty</p>', null);
        $this->assertSame('<p>empty</p>', $html);
    }

    public function test_backfill_filter_when_enabled()
    {
        $GLOBALS['mjb_test_options'][MJB_Ingestion::OPTION_BACKFILL_ENABLED] = '1';
        $GLOBALS['mjb_test_options'][MJB_Ingestion::OPTION_BACKFILL_MESSAGE] = 'Try partners';
        $GLOBALS['mjb_test_options'][MJB_Ingestion::OPTION_BACKFILL_URL] = 'https://example.com/jobs';
        $html = MJB_Ingestion::filter_empty_list('<p>empty</p>', null);
        $this->assertStringContainsString('mjb-backfill', $html);
        $this->assertStringContainsString('Try partners', $html);
        $this->assertStringContainsString('https://example.com/jobs', $html);
    }
}
