<?php

use PHPUnit\Framework\TestCase;

class RestApiV2Test extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['mjb_test_is_logged_in'] = false;
        $GLOBALS['mjb_test_current_user_id'] = 0;
        $GLOBALS['mjb_test_user_roles'] = array();
        $GLOBALS['mjb_test_user_caps'] = array();
        $GLOBALS['mjb_test_user_meta'] = array();
        $GLOBALS['mjb_test_post_meta'] = array();
        $GLOBALS['mjb_test_post_status'] = array();
        $GLOBALS['mjb_test_post_types'] = array();
        $GLOBALS['mjb_test_titles'] = array();
        $GLOBALS['mjb_test_post_authors'] = array();
        $GLOBALS['mjb_test_posts'] = array();
        $GLOBALS['mjb_test_user_emails'] = array();
    }

    public function test_current_user_is_employer_requires_login()
    {
        $result = MJB_REST_API_V2::current_user_is_employer();
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('mjb_rest_auth_required', array_key_first($result->errors));
    }

    public function test_current_user_is_candidate_allows_candidate_role()
    {
        $GLOBALS['mjb_test_is_logged_in'] = true;
        $GLOBALS['mjb_test_current_user_id'] = 4;
        $GLOBALS['mjb_test_user_roles'][4] = array('candidate');

        $this->assertTrue(MJB_REST_API_V2::current_user_is_candidate());
    }

    public function test_format_application_for_api_includes_workflow_status()
    {
        $GLOBALS['mjb_test_post_status'][88] = 'publish';
        $GLOBALS['mjb_test_post_types'][88] = 'job_application';
        $GLOBALS['mjb_test_post_meta'][88]['_job_applied_for'] = 12;
        $GLOBALS['mjb_test_post_meta'][88]['_candidate_name'] = 'Alex';
        $GLOBALS['mjb_test_post_meta'][88]['_candidate_email'] = 'alex@example.test';
        $GLOBALS['mjb_test_post_meta'][88][MJB_Application_Status::META_KEY] = 'reviewed';
        $GLOBALS['mjb_test_titles'][12] = 'Designer';

        $formatted = MJB_REST_API_V2::format_application_for_api(88);

        $this->assertSame(88, $formatted['id']);
        $this->assertSame('reviewed', $formatted['status']);
        $this->assertSame('Reviewed', $formatted['status_label']);
        $this->assertSame('Alex', $formatted['candidate_name']);
    }

    public function test_format_candidate_profile_for_api_returns_profile_fields()
    {
        $GLOBALS['mjb_test_user_meta'][6]['first_name'] = 'Jamie';
        $GLOBALS['mjb_test_user_meta'][6]['last_name'] = 'Lee';
        $GLOBALS['mjb_test_user_meta'][6]['_candidate_headline'] = 'Product Designer';
        $GLOBALS['mjb_test_user_meta'][6]['_candidate_resume_id'] = 0;
        $GLOBALS['mjb_test_user_emails'][6] = 'jamie@example.test';

        $profile = MJB_REST_API_V2::format_candidate_profile_for_api(6);

        $this->assertSame('Jamie', $profile['first_name']);
        $this->assertSame('Lee', $profile['last_name']);
        $this->assertSame('Product Designer', $profile['headline']);
        $this->assertSame('jamie@example.test', $profile['email']);
    }
}