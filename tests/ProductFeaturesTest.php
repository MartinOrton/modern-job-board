<?php

use PHPUnit\Framework\TestCase;

/**
 * Smoke tests for backlog product feature modules (#6–#42).
 */
class ProductFeaturesTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['mjb_test_options'] = array();
        $GLOBALS['mjb_test_user_meta'] = array();
        $GLOBALS['mjb_test_posts'] = array();
        $GLOBALS['mjb_test_post_meta'] = array();
        $GLOBALS['mjb_test_post_types'] = array();
        $GLOBALS['mjb_test_post_status'] = array();
        $GLOBALS['mjb_test_post_authors'] = array();
        $GLOBALS['mjb_test_titles'] = array();
        $GLOBALS['mjb_test_inserted_posts'] = array();
        $GLOBALS['mjb_test_next_post_id'] = 5000;
        $GLOBALS['mjb_test_mails'] = array();
    }

    public function test_feature_classes_exist()
    {
        $classes = array(
            'MJB_Legacy_Redirects',
            'MJB_Private_Board',
            'MJB_Job_Alerts',
            'MJB_Talent_Pool',
            'MJB_Collaborators',
            'MJB_License_Remote',
            'MJB_Embed',
            'MJB_Brand',
            'MJB_String_Overrides',
            'MJB_Auto_Approve',
            'MJB_Company_Preview',
            'MJB_Messaging',
            'MJB_Analytics_Export',
            'MJB_Api_Keys',
            'MJB_Pwa',
            'MJB_Filter_Settings',
            'MJB_Packages',
            'MJB_Partner_Import',
            'MJB_Seo_Landings',
            'MJB_Media_Listings',
            'MJB_Sms',
            'MJB_I18n_Board',
            'MJB_Blog_Package',
        );
        foreach ($classes as $class) {
            $this->assertTrue(class_exists($class), $class . ' should be loadable');
        }
    }

    public function test_private_board_default_off()
    {
        $this->assertFalse(MJB_Private_Board::is_enabled());
    }

    public function test_filter_settings_defaults()
    {
        $filters = MJB_Filter_Settings::get_filters();
        $this->assertTrue(!empty($filters['keywords']));
        $this->assertTrue(!empty($filters['location']));
    }

    public function test_job_alert_save_requires_user()
    {
        $result = MJB_Job_Alerts::save_alert(0, array('search_keywords' => 'php'));
        $this->assertInstanceOf(WP_Error::class, $result);
    }

    public function test_job_alert_save_for_user()
    {
        $id = MJB_Job_Alerts::save_alert(42, array(
            'search_keywords' => 'developer',
            'search_location' => 'cape-town',
        ), 'weekly');
        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
        $this->assertSame('weekly', get_post_meta($id, '_mjb_alert_frequency', true));
    }

    public function test_api_key_issue_and_validate()
    {
        if (!function_exists('wp_hash_password') || !function_exists('wp_check_password')) {
            $this->markTestSkipped('Password helpers not in bootstrap');
        }
        $issued = MJB_Api_Keys::issue_key('test');
        $this->assertArrayHasKey('key', $issued);
        $this->assertTrue(MJB_Api_Keys::validate_key($issued['key']));
        $this->assertFalse(MJB_Api_Keys::validate_key('mjb_invalid_key_value_xxx'));
    }

    public function test_string_override_map()
    {
        update_option(MJB_String_Overrides::OPTION, array('Hello' => 'Hallo'));
        $this->assertSame('Hallo', MJB_String_Overrides::filter_gettext('Hello', 'Hello', 'modern-job-board'));
        $this->assertSame('Other', MJB_String_Overrides::filter_gettext('Other', 'Other', 'modern-job-board'));
        delete_option(MJB_String_Overrides::OPTION);
    }

    public function test_talent_pool_public_flag()
    {
        $user_id = 77;
        $this->assertFalse(MJB_Talent_Pool::is_public($user_id));
        MJB_Talent_Pool::set_public($user_id, true);
        $this->assertTrue(MJB_Talent_Pool::is_public($user_id));
    }

    public function test_auto_approve_option()
    {
        update_option(MJB_Auto_Approve::OPTION, '0');
        $this->assertFalse(MJB_Auto_Approve::is_enabled());
        update_option(MJB_Auto_Approve::OPTION, '1');
        $this->assertTrue(MJB_Auto_Approve::is_enabled());
    }

    public function test_license_remote_domain()
    {
        $domain = MJB_License_Remote::site_domain();
        $this->assertIsString($domain);
    }

    public function test_messaging_send()
    {
        $id = MJB_Messaging::send(10, 11, 'Hello there partner');
        $this->assertIsInt($id);
        $this->assertSame(11, (int) get_post_meta($id, '_mjb_to', true));
    }

    public function test_profile_completeness_guest()
    {
        $c = MJB_Applications::profile_completeness(0);
        $this->assertFalse($c['complete']);
        $this->assertSame(0, $c['percent']);
    }

    public function test_brand_sanitize_hex()
    {
        $clean = MJB_Brand::sanitize(array(
            'primary' => '#0f766e',
            'primary_hover' => 'not-a-color',
            'logo_url' => 'https://example.com/logo.png',
            'nav_style' => 'dark',
        ));
        $this->assertSame('#0f766e', $clean['primary']);
        $this->assertSame('', $clean['primary_hover']);
        $this->assertSame('dark', $clean['nav_style']);
    }
}
