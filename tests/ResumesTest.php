<?php

use PHPUnit\Framework\TestCase;

class ResumesTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['mjb_test_user_meta'] = array();
        $GLOBALS['mjb_test_timestamp'] = 1700000000;
        $GLOBALS['mjb_test_options'] = array();
        $GLOBALS['mjb_test_posts'] = array();
        $GLOBALS['mjb_test_post_meta'] = array();
        $GLOBALS['mjb_test_post_types'] = array();
        $GLOBALS['mjb_test_post_status'] = array();
    }

    public function test_employer_has_cv_access_when_pass_not_expired()
    {
        $GLOBALS['mjb_test_user_meta'][5]['_mjb_cv_access_expires'] = $GLOBALS['mjb_test_timestamp'] + 3600;

        $this->assertTrue(MJB_Resumes::employer_has_cv_access(5, 99));
    }

    public function test_employer_has_cv_access_when_application_unlocked()
    {
        $GLOBALS['mjb_test_user_meta'][5]['_mjb_unlocked_applications'] = array(99, 100);

        $this->assertTrue(MJB_Resumes::employer_has_cv_access(5, 99));
        $this->assertFalse(MJB_Resumes::employer_has_cv_access(5, 101));
    }

    public function test_employer_has_cv_access_false_without_pass_or_unlock()
    {
        $this->assertFalse(MJB_Resumes::employer_has_cv_access(5, 99));
    }

    public function test_copy_profile_resume_for_application_requires_resume_post()
    {
        $result = MJB_Resumes::copy_profile_resume_for_application(0);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('error_resume_required', $result->get_error_code());
    }

    public function test_copy_profile_resume_for_application_requires_existing_file()
    {
        $GLOBALS['mjb_test_post_status'][55] = 'publish';
        $GLOBALS['mjb_test_post_types'][55] = 'mjb_resume';
        $GLOBALS['mjb_test_post_meta'][55]['_resume_file_path'] = '';

        $result = MJB_Resumes::copy_profile_resume_for_application(55);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('error_resume_required', $result->get_error_code());
    }

    public function test_copy_profile_resume_for_application_creates_independent_file()
    {
        $dir = MJB_Private_Uploads::tier_root_dir(MJB_Private_Uploads::TIER_PRIVATE) . '/resumes';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $source = $dir . '/profile-source.pdf';
        file_put_contents($source, '%PDF-profile');

        $GLOBALS['mjb_test_post_status'][55] = 'publish';
        $GLOBALS['mjb_test_post_types'][55] = 'mjb_resume';
        $GLOBALS['mjb_test_post_meta'][55]['_resume_file_path'] = $source;

        $result = MJB_Resumes::copy_profile_resume_for_application(55);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('path', $result);
        $this->assertNotSame('', $result['path']);
        $this->assertNotSame($source, $result['path']);
        $this->assertSame(55, $result['resume_post_id']);

        $resolved = MJB_Private_Uploads::resolve_path($result['path']);
        $this->assertNotSame('', $resolved);
        $this->assertFileExists($resolved);
        $this->assertNotSame(wp_normalize_path($source), wp_normalize_path($resolved));
        $this->assertSame('%PDF-profile', file_get_contents($resolved));

        @unlink($resolved);
        @unlink($source);
    }

    public function test_is_resume_still_referenced_by_application_path()
    {
        $GLOBALS['mjb_test_posts'] = array(201);
        $GLOBALS['mjb_test_post_status'][201] = 'publish';
        $GLOBALS['mjb_test_post_types'][201] = 'job_application';
        $GLOBALS['mjb_test_post_meta'][201]['_candidate_resume_path'] = 'mjb-private/resumes/shared.pdf';

        $this->assertTrue(MJB_Resumes::is_resume_still_referenced(0, 'mjb-private/resumes/shared.pdf'));
        $this->assertFalse(MJB_Resumes::is_resume_still_referenced(0, 'mjb-private/resumes/other.pdf'));
    }

    public function test_is_resume_still_referenced_by_resume_post_id()
    {
        $GLOBALS['mjb_test_posts'] = array(202);
        $GLOBALS['mjb_test_post_status'][202] = 'publish';
        $GLOBALS['mjb_test_post_types'][202] = 'job_application';
        $GLOBALS['mjb_test_post_meta'][202]['_candidate_resume_id'] = 88;

        $this->assertTrue(MJB_Resumes::is_resume_still_referenced(88, ''));
        $this->assertFalse(MJB_Resumes::is_resume_still_referenced(89, ''));
    }

    public function test_maybe_retire_profile_resume_skips_when_referenced()
    {
        $dir = MJB_Private_Uploads::tier_root_dir(MJB_Private_Uploads::TIER_PRIVATE) . '/resumes';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $source = $dir . '/keep-me.pdf';
        file_put_contents($source, 'keep');

        $GLOBALS['mjb_test_posts'] = array(55, 201);
        $GLOBALS['mjb_test_post_status'][55] = 'publish';
        $GLOBALS['mjb_test_post_types'][55] = 'mjb_resume';
        $GLOBALS['mjb_test_post_meta'][55]['_resume_file_path'] = $source;

        $GLOBALS['mjb_test_post_status'][201] = 'publish';
        $GLOBALS['mjb_test_post_types'][201] = 'job_application';
        $GLOBALS['mjb_test_post_meta'][201]['_candidate_resume_id'] = 55;

        MJB_Resumes::maybe_retire_profile_resume(55);

        $this->assertFileExists($source);
        $this->assertSame('publish', $GLOBALS['mjb_test_post_status'][55] ?? null);

        @unlink($source);
    }

    public function test_maybe_retire_profile_resume_deletes_when_unreferenced()
    {
        $dir = MJB_Private_Uploads::tier_root_dir(MJB_Private_Uploads::TIER_PRIVATE) . '/resumes';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $source = $dir . '/drop-me.pdf';
        file_put_contents($source, 'drop');

        $GLOBALS['mjb_test_posts'] = array(56);
        $GLOBALS['mjb_test_post_status'][56] = 'publish';
        $GLOBALS['mjb_test_post_types'][56] = 'mjb_resume';
        $GLOBALS['mjb_test_post_meta'][56]['_resume_file_path'] = $source;

        MJB_Resumes::maybe_retire_profile_resume(56);

        $this->assertFileDoesNotExist($source);
        $this->assertArrayNotHasKey(56, $GLOBALS['mjb_test_post_status']);
    }
}