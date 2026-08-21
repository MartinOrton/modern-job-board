<?php

use PHPUnit\Framework\TestCase;

class ResumePrivacyTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['mjb_test_options'] = array();
        $GLOBALS['mjb_test_is_logged_in'] = false;
        $GLOBALS['mjb_test_current_user_id'] = 0;
        $GLOBALS['mjb_test_user_caps'] = array();
        $GLOBALS['mjb_test_user_roles'] = array();
    }

    public function test_anonymize_name_hides_surname_when_enabled()
    {
        $GLOBALS['mjb_test_options'][MJB_Resume_Privacy::OPTION_ANON] = array(
            'enabled' => '1',
            'hide_surname' => '1',
            'noindex' => '0',
        );
        $this->assertSame('Jane D.', MJB_Resume_Privacy::anonymize_name('Jane Doe'));
    }

    public function test_anonymize_name_noop_when_disabled()
    {
        $GLOBALS['mjb_test_options'][MJB_Resume_Privacy::OPTION_ANON] = array(
            'enabled' => '0',
            'hide_surname' => '1',
            'noindex' => '0',
        );
        $this->assertSame('Jane Doe', MJB_Resume_Privacy::anonymize_name('Jane Doe'));
    }

    public function test_access_all_allows_guests()
    {
        $GLOBALS['mjb_test_options'][MJB_Resume_Privacy::OPTION_ACCESS] = 'all';
        $this->assertTrue(MJB_Resume_Privacy::current_user_can_browse_resumes());
    }

    public function test_access_registered_requires_login()
    {
        $GLOBALS['mjb_test_options'][MJB_Resume_Privacy::OPTION_ACCESS] = 'registered';
        $this->assertFalse(MJB_Resume_Privacy::current_user_can_browse_resumes());
        $GLOBALS['mjb_test_is_logged_in'] = true;
        $GLOBALS['mjb_test_current_user_id'] = 3;
        $this->assertTrue(MJB_Resume_Privacy::current_user_can_browse_resumes());
    }
}
