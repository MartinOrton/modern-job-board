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
        $this->assertTrue($formatted['cv_access']);
    }

    public function test_resolve_applications_scope_empty_employer_jobs_is_empty_not_unscoped()
    {
        $GLOBALS['mjb_test_is_logged_in'] = true;
        $GLOBALS['mjb_test_current_user_id'] = 7;
        $GLOBALS['mjb_test_user_roles'][7] = array('employer');
        $GLOBALS['mjb_test_posts'] = array(21);
        $GLOBALS['mjb_test_post_types'][21] = 'job_listing';
        $GLOBALS['mjb_test_post_status'][21] = 'publish';
        $GLOBALS['mjb_test_post_authors'][21] = 99; // owned by someone else

        $scope = MJB_REST_API_V2::resolve_applications_scope(0);

        $this->assertIsArray($scope);
        $this->assertTrue($scope['empty_result']);
        $this->assertSame(array(), $scope['job_ids']);
        $this->assertSame(0, $scope['job_filter']);
    }

    public function test_resolve_applications_scope_employer_with_jobs_gets_in_filter()
    {
        $GLOBALS['mjb_test_is_logged_in'] = true;
        $GLOBALS['mjb_test_current_user_id'] = 7;
        $GLOBALS['mjb_test_user_roles'][7] = array('employer');
        $GLOBALS['mjb_test_posts'] = array(21, 22, 30);
        $GLOBALS['mjb_test_post_types'][21] = 'job_listing';
        $GLOBALS['mjb_test_post_types'][22] = 'job_listing';
        $GLOBALS['mjb_test_post_types'][30] = 'job_listing';
        $GLOBALS['mjb_test_post_status'][21] = 'publish';
        $GLOBALS['mjb_test_post_status'][22] = 'pending';
        $GLOBALS['mjb_test_post_status'][30] = 'publish';
        $GLOBALS['mjb_test_post_authors'][21] = 7;
        $GLOBALS['mjb_test_post_authors'][22] = 7;
        $GLOBALS['mjb_test_post_authors'][30] = 99;

        $scope = MJB_REST_API_V2::resolve_applications_scope(0);

        $this->assertIsArray($scope);
        $this->assertFalse($scope['empty_result']);
        $this->assertSame(array(21, 22), $scope['job_ids']);
    }

    public function test_resolve_applications_scope_forbids_foreign_job_for_employer()
    {
        $GLOBALS['mjb_test_is_logged_in'] = true;
        $GLOBALS['mjb_test_current_user_id'] = 7;
        $GLOBALS['mjb_test_user_roles'][7] = array('employer');
        $GLOBALS['mjb_test_posts'] = array(21);
        $GLOBALS['mjb_test_post_types'][21] = 'job_listing';
        $GLOBALS['mjb_test_post_status'][21] = 'publish';
        $GLOBALS['mjb_test_post_authors'][21] = 7;

        $scope = MJB_REST_API_V2::resolve_applications_scope(999);

        $this->assertInstanceOf(WP_Error::class, $scope);
        $this->assertSame('mjb_rest_forbidden', array_key_first($scope->errors));
    }

    public function test_resolve_applications_scope_employer_with_no_jobs_cannot_query_by_job_id()
    {
        $GLOBALS['mjb_test_is_logged_in'] = true;
        $GLOBALS['mjb_test_current_user_id'] = 7;
        $GLOBALS['mjb_test_user_roles'][7] = array('employer');
        $GLOBALS['mjb_test_posts'] = array();

        $scope = MJB_REST_API_V2::resolve_applications_scope(12);

        $this->assertInstanceOf(WP_Error::class, $scope);
        $this->assertSame('mjb_rest_forbidden', array_key_first($scope->errors));
    }

    public function test_resolve_applications_scope_admin_is_unscoped()
    {
        $GLOBALS['mjb_test_is_logged_in'] = true;
        $GLOBALS['mjb_test_current_user_id'] = 1;
        $GLOBALS['mjb_test_user_caps'][1]['manage_options'] = true;

        $scope = MJB_REST_API_V2::resolve_applications_scope(0);

        $this->assertIsArray($scope);
        $this->assertFalse($scope['empty_result']);
        $this->assertSame(array(), $scope['job_ids']);
        $this->assertSame(0, $scope['job_filter']);
    }

    public function test_format_application_for_api_redacts_pii_when_paid_cv_locked()
    {
        $GLOBALS['mjb_test_is_logged_in'] = true;
        $GLOBALS['mjb_test_current_user_id'] = 7;
        $GLOBALS['mjb_test_user_roles'][7] = array('employer');
        $GLOBALS['mjb_test_options']['mjb_paid_cv_access'] = 1;
        $GLOBALS['mjb_test_post_status'][88] = 'publish';
        $GLOBALS['mjb_test_post_types'][88] = 'job_application';
        $GLOBALS['mjb_test_post_meta'][88]['_job_applied_for'] = 12;
        $GLOBALS['mjb_test_post_meta'][88]['_candidate_name'] = 'Alex';
        $GLOBALS['mjb_test_post_meta'][88]['_candidate_email'] = 'alex@example.test';
        $GLOBALS['mjb_test_post_content'][88] = 'Cover letter secret';
        $GLOBALS['mjb_test_post_meta'][88][MJB_Application_Status::META_KEY] = 'new';
        $GLOBALS['mjb_test_titles'][12] = 'Designer';

        $formatted = MJB_REST_API_V2::format_application_for_api(88);

        $this->assertFalse($formatted['cv_access']);
        $this->assertSame('', $formatted['candidate_name']);
        $this->assertSame('', $formatted['candidate_email']);
        $this->assertSame('', $formatted['message']);
        $this->assertSame('', $formatted['resume_url']);
        $this->assertSame('new', $formatted['status']);
    }

    public function test_format_application_for_api_shows_pii_when_employer_has_cv_pass()
    {
        $GLOBALS['mjb_test_is_logged_in'] = true;
        $GLOBALS['mjb_test_current_user_id'] = 7;
        $GLOBALS['mjb_test_user_roles'][7] = array('employer');
        $GLOBALS['mjb_test_options']['mjb_paid_cv_access'] = 1;
        $GLOBALS['mjb_test_user_meta'][7]['_mjb_cv_access_expires'] = 9999999999;
        $GLOBALS['mjb_test_post_status'][88] = 'publish';
        $GLOBALS['mjb_test_post_types'][88] = 'job_application';
        $GLOBALS['mjb_test_post_meta'][88]['_job_applied_for'] = 12;
        $GLOBALS['mjb_test_post_meta'][88]['_candidate_name'] = 'Alex';
        $GLOBALS['mjb_test_post_meta'][88]['_candidate_email'] = 'alex@example.test';
        $GLOBALS['mjb_test_post_content'][88] = 'Cover letter';
        $GLOBALS['mjb_test_post_meta'][88][MJB_Application_Status::META_KEY] = 'new';
        $GLOBALS['mjb_test_titles'][12] = 'Designer';

        $formatted = MJB_REST_API_V2::format_application_for_api(88);

        $this->assertTrue($formatted['cv_access']);
        $this->assertSame('Alex', $formatted['candidate_name']);
        $this->assertSame('alex@example.test', $formatted['candidate_email']);
        $this->assertSame('Cover letter', $formatted['message']);
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