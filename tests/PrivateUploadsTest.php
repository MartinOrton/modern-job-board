<?php

use PHPUnit\Framework\TestCase;

class PrivateUploadsTest extends TestCase
{
    public function test_type_map_has_resume_logo_and_photo()
    {
        $map = MJB_Private_Uploads::type_map();

        $this->assertArrayHasKey(MJB_Private_Uploads::TYPE_RESUME, $map);
        $this->assertArrayHasKey(MJB_Private_Uploads::TYPE_COMPANY_LOGO, $map);
        $this->assertArrayHasKey(MJB_Private_Uploads::TYPE_CANDIDATE_PHOTO, $map);

        $this->assertSame(MJB_Private_Uploads::TIER_PRIVATE, $map[MJB_Private_Uploads::TYPE_RESUME]['tier']);
        $this->assertSame(MJB_Private_Uploads::TIER_BRAND, $map[MJB_Private_Uploads::TYPE_COMPANY_LOGO]['tier']);
        $this->assertSame(MJB_Private_Uploads::TIER_BRAND, $map[MJB_Private_Uploads::TYPE_CANDIDATE_PHOTO]['tier']);
    }

    public function test_validate_file_rejects_missing_file()
    {
        $result = MJB_Private_Uploads::validate_file(array(), MJB_Private_Uploads::TYPE_RESUME);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('missing_file', $result->get_error_code());
    }

    public function test_validate_file_rejects_unknown_type()
    {
        $result = MJB_Private_Uploads::validate_file(
            array(
                'name' => 'a.pdf',
                'tmp_name' => '/tmp/a.pdf',
                'size' => 100,
                'error' => 0,
            ),
            'not_a_real_type'
        );

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('invalid_type', $result->get_error_code());
    }

    public function test_validate_file_rejects_zero_size()
    {
        $result = MJB_Private_Uploads::validate_file(
            array(
                'name' => 'a.pdf',
                'tmp_name' => sys_get_temp_dir() . '/mjb-missing-upload-file',
                'size' => 0,
                'error' => 0,
            ),
            MJB_Private_Uploads::TYPE_RESUME
        );

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('missing_file', $result->get_error_code());
    }

    public function test_validate_file_rejects_disallowed_extension_without_client_fallback()
    {
        $tmp = tempnam(sys_get_temp_dir(), 'mjb');
        file_put_contents($tmp, 'not-a-pdf');

        $result = MJB_Private_Uploads::validate_file(
            array(
                'name' => 'malware.exe',
                'tmp_name' => $tmp,
                'size' => 10,
                'error' => 0,
            ),
            MJB_Private_Uploads::TYPE_RESUME
        );

        @unlink($tmp);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('invalid_type', $result->get_error_code());
    }

    public function test_absolute_to_relative_strips_content_storage_root()
    {
        $absolute = MJB_Private_Uploads::storage_base_dir() . 'mjb-private/resumes/hash.pdf';
        $relative = MJB_Private_Uploads::absolute_to_relative($absolute);

        $this->assertSame('mjb-private/resumes/hash.pdf', str_replace('\\', '/', $relative));
    }

    public function test_tier_roots_live_under_wp_content_not_plugin()
    {
        $private = wp_normalize_path(MJB_Private_Uploads::tier_root_dir(MJB_Private_Uploads::TIER_PRIVATE));
        $brand = wp_normalize_path(MJB_Private_Uploads::tier_root_dir(MJB_Private_Uploads::TIER_BRAND));
        $content = wp_normalize_path(MJB_Private_Uploads::storage_base_dir());
        $legacy_plugin_private = wp_normalize_path(MJB_Private_Uploads::plugin_root_dir() . 'mjb-private');

        $this->assertStringStartsWith($content, $private);
        $this->assertStringStartsWith($content, $brand);
        $this->assertStringContainsString('/mjb-private', $private);
        $this->assertStringContainsString('/mjb-brand', $brand);
        $this->assertStringNotContainsString('/uploads/', $private);
        // Primary storage is content-based, not the legacy plugin-local path.
        $this->assertNotSame(rtrim($legacy_plugin_private, '/'), rtrim($private, '/'));
    }

    public function test_resolve_path_rejects_arbitrary_absolute_paths()
    {
        $tmp = tempnam(sys_get_temp_dir(), 'mjb-outside');
        file_put_contents($tmp, 'secret');

        $resolved = MJB_Private_Uploads::resolve_path($tmp);
        @unlink($tmp);

        $this->assertSame('', $resolved);
    }

    public function test_resolve_path_accepts_file_under_private_tier()
    {
        $dir = MJB_Private_Uploads::tier_root_dir(MJB_Private_Uploads::TIER_PRIVATE) . '/resumes';
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $file = $dir . '/test-allowed.pdf';
        file_put_contents($file, 'pdf');

        $resolved = MJB_Private_Uploads::resolve_path($file);
        $this->assertSame(wp_normalize_path($file), wp_normalize_path($resolved));

        $relative = MJB_Private_Uploads::absolute_to_relative($file);
        $this->assertNotSame('', MJB_Private_Uploads::resolve_path($relative));

        @unlink($file);
    }

    public function test_username_from_email_prefers_email_when_free()
    {
        $email = 'unique-employer-' . wp_generate_password(8, false) . '@example.com';
        $username = MJB_Employer_Registration::username_from_email($email);

        $this->assertSame($email, $username);
    }
}
