<?php

use PHPUnit\Framework\TestCase;

class AdminBarTest extends TestCase
{
    protected function tearDown(): void
    {
        $GLOBALS['mjb_test_is_logged_in'] = false;
        $GLOBALS['mjb_test_current_user_id'] = 0;
        $GLOBALS['mjb_test_user_roles'] = array();
        $GLOBALS['mjb_test_user_caps'] = array();
        parent::tearDown();
    }

    public function test_candidate_and_recruiter_hide_the_toolbar()
    {
        $candidate = (object) array('ID' => 4, 'roles' => array('candidate'));
        $recruiter = (object) array('ID' => 5, 'roles' => array('employer'));
        $both = (object) array('ID' => 6, 'roles' => array('candidate', 'employer'));

        $this->assertTrue(MJB_Admin_Bar::hides_toolbar_for_user($candidate));
        $this->assertTrue(MJB_Admin_Bar::hides_toolbar_for_user($recruiter));
        $this->assertTrue(MJB_Admin_Bar::hides_toolbar_for_user($both));
    }

    public function test_other_roles_and_staff_keep_the_toolbar()
    {
        $guest = (object) array('ID' => 0, 'roles' => array());
        $subscriber = (object) array('ID' => 8, 'roles' => array('subscriber'));
        $admin = (object) array('ID' => 1, 'roles' => array('administrator', 'candidate'));
        $GLOBALS['mjb_test_user_caps'][1]['manage_options'] = true;

        $this->assertFalse(MJB_Admin_Bar::hides_toolbar_for_user($guest));
        $this->assertFalse(MJB_Admin_Bar::hides_toolbar_for_user($subscriber));
        $this->assertFalse(MJB_Admin_Bar::hides_toolbar_for_user($admin));
        $this->assertFalse(MJB_Admin_Bar::hides_toolbar_for_user(null));
    }

    public function test_show_admin_bar_filter_overrides_the_user_preference()
    {
        $GLOBALS['mjb_test_is_logged_in'] = true;
        $GLOBALS['mjb_test_current_user_id'] = 4;
        $GLOBALS['mjb_test_user_roles'][4] = array('candidate');

        $this->assertFalse(MJB_Admin_Bar::filter_show_admin_bar(true));
    }

    public function test_plugin_registers_the_toolbar_hooks()
    {
        $class = file_get_contents(dirname(__DIR__) . '/includes/class-mjb-admin-bar.php');
        $bootstrap = file_get_contents(dirname(__DIR__) . '/modern-job-board.php');

        $this->assertStringContainsString("add_filter('show_admin_bar'", $class);
        $this->assertStringContainsString("remove_action('in_admin_header', 'wp_admin_bar_render', 0)", $class);
        $this->assertStringContainsString('MJB_Admin_Bar::init()', $bootstrap);
    }
}
