<?php

use PHPUnit\Framework\TestCase;

class DocumentLabelTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['mjb_test_options'] = array();
        $GLOBALS['mjb_test_user_meta'] = array();
        $GLOBALS['mjb_test_user_roles'] = array();
        $GLOBALS['mjb_test_user_caps'] = array();
        $GLOBALS['mjb_test_current_user_id'] = 0;
    }

    public function test_sanitize_defaults_to_resume()
    {
        $this->assertSame('resume', MJB_Document_Label::sanitize('resume'));
        $this->assertSame('cv', MJB_Document_Label::sanitize('cv'));
        $this->assertSame('resume', MJB_Document_Label::sanitize('nope'));
        $this->assertSame('resume', MJB_Document_Label::sanitize(''));
    }

    public function test_site_option_and_nouns()
    {
        $this->assertSame('resume', MJB_Document_Label::get_site());
        $this->assertSame('Resume', MJB_Document_Label::singular());
        $this->assertSame('Resumes', MJB_Document_Label::plural());

        $GLOBALS['mjb_test_user_caps'][1]['manage_options'] = true;
        $GLOBALS['mjb_test_current_user_id'] = 1;
        $this->assertTrue(MJB_Document_Label::save('cv', 1));
        $this->assertSame('cv', MJB_Document_Label::get_site());
        $this->assertTrue(MJB_Document_Label::is_cv());
        $this->assertSame('CV', MJB_Document_Label::singular());
        $this->assertSame('CVs', MJB_Document_Label::plural());
    }

    public function test_recruiter_cannot_override_site_label()
    {
        $GLOBALS['mjb_test_options'][MJB_Document_Label::OPTION] = 'resume';
        $GLOBALS['mjb_test_current_user_id'] = 9;
        $GLOBALS['mjb_test_user_roles'][9] = array('employer');

        $this->assertFalse(MJB_Document_Label::save('cv', 9));
        $this->assertSame('', (string) get_user_meta(9, MJB_Document_Label::USER_META, true));
        $this->assertSame('resume', MJB_Document_Label::get(9));
        $this->assertSame('resume', MJB_Document_Label::get_site());
        $this->assertSame('Resume', MJB_Document_Label::singular(9));
        $this->assertSame('Resume', MJB_Document_Label::singular());
    }

    public function test_swap_replaces_resume_words_only()
    {
        $this->assertSame('CV', MJB_Document_Label::swap('Resume'));
        $this->assertSame('CVs', MJB_Document_Label::swap('Resumes'));
        $this->assertSame('Upload a CV', MJB_Document_Label::swap('Upload a resume'));
        $this->assertSame('Download CVs', MJB_Document_Label::swap('Download resumes'));
        $this->assertSame('Presumed', MJB_Document_Label::swap('Presumed'));
        $this->assertSame('CV Unlock', MJB_Document_Label::swap('CV Unlock'));
    }

    public function test_apply_to_swaps_to_the_selected_noun_only()
    {
        $this->assertSame('Upload Resume', MJB_Document_Label::apply_to('Upload Resume'));
        $this->assertSame('Upload Resume', MJB_Document_Label::apply_to('Upload CV'));
        $this->assertSame('Paid Resume Access', MJB_Document_Label::apply_to('Paid CV Access'));
        $this->assertSame('Download Resumes', MJB_Document_Label::apply_to('Download CVs'));

        $GLOBALS['mjb_test_options'][MJB_Document_Label::OPTION] = 'cv';
        $this->assertSame('Upload CV', MJB_Document_Label::apply_to('Upload Resume'));
        $this->assertSame('Download CVs', MJB_Document_Label::apply_to('Download Resumes'));
        $this->assertSame('Paid CV Access', MJB_Document_Label::apply_to('Paid CV Access'));
    }

    public function test_choice_field_uses_native_checkboxes()
    {
        ob_start();
        MJB_Document_Label::render_choice_fields('mjb_document_label', 'resume', 'mjb_document_label');
        $html = ob_get_clean();
        $this->assertStringContainsString('value="resume"', $html);
        $this->assertStringContainsString('value="cv"', $html);
        $this->assertStringContainsString('Resume', $html);
        $this->assertStringContainsString('CV', $html);
        $this->assertStringContainsString('type="checkbox"', $html);
        $this->assertStringContainsString('mjb-doc-label-choice', $html);
        $this->assertStringNotContainsString('type="radio"', $html);
        $this->assertStringNotContainsString('mjb-doc-label__opt', $html);
        $this->assertStringNotContainsString('mjb-settings-check', $html);
    }

    public function test_sanitize_accepts_posted_checkbox_array()
    {
        $this->assertSame('cv', MJB_Document_Label::sanitize(array('resume', 'cv')));
        $this->assertSame('resume', MJB_Document_Label::sanitize(array('resume')));
    }

    public function test_recruiter_form_is_not_rendered()
    {
        $GLOBALS['mjb_test_current_user_id'] = 9;
        $GLOBALS['mjb_test_user_roles'][9] = array('employer');
        ob_start();
        MJB_Document_Label::render_recruiter_form();
        $html = ob_get_clean();
        $this->assertSame('', $html);
    }
}
