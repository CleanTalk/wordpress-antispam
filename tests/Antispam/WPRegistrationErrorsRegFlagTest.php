<?php
require_once __DIR__ . '/WPRegistrationErrorsTestBase.php';
use Cleantalk\Antispam\IntegrationsByClass\WPRegistrationErrors;

class WPRegistrationErrorsRegFlagTest extends WPRegistrationErrorsTestBase
{
    public function test_force_reg_flag_cases_turns_flag_off_for_buddypress_custom_fields_mode()
    {
        $_POST['signup_username'] = 'buddy_user';
        $_POST['signup_email'] = 'buddy@example.com';
        $_POST['signup_profile_field_ids'] = '';

        $handler = new WPRegistrationErrors();

        $result = $handler->forceRegFlagCases(true);

        $this->assertFalse($result);
    }

    public function test_force_reg_flag_cases_keeps_flag_true_for_avada()
    {
        $_POST['fusion_login_box'] = '1';

        $handler = new WPRegistrationErrors();

        $result = $handler->forceRegFlagCases(false);

        $this->assertTrue($result);
    }

    public function test_define_message_for_non_registration_method_collects_profile_fields()
    {
        $_POST['signup_profile_field_ids'] = '2,3';
        $_POST['field_2'] = 'First field';
        $_POST['field_3'] = 'Second field';

        $handler = new WPRegistrationErrors();

        $result = $handler->defineMessageForNonRegistrationMethod();

        $this->assertSame("First field\nSecond field\n", $result);
    }
}
