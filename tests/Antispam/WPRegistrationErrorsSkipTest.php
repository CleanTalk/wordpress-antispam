<?php
require_once __DIR__ . '/WPRegistrationErrorsTestBase.php';
use Cleantalk\Antispam\IntegrationsByClass\WPRegistrationErrors;

class WPRegistrationErrorsSkipTest extends WPRegistrationErrorsTestBase
{
    public function test_do_skip_request_returns_errors_when_user_check_disabled()
    {
        $handler = $this->getMockBuilder(WPRegistrationErrors::class)
            ->onlyMethods(array(
                'isUserEnabled',
                'isRegistrationCheckDisabled',
                'isSignupHandled',
                'isWPMembersExclusion',
                'isBuddyBossLoginForm',
                'isBuddyPressAlreadyHasValidationErrors',
            ))
            ->disableOriginalConstructor()
            ->getMock();

        $this->setHandlerState($handler);

        $handler->method('isUserEnabled')->willReturn(true);
        $handler->method('isRegistrationCheckDisabled')->willReturn(false);
        $handler->method('isSignupHandled')->willReturn(false);
        $handler->method('isWPMembersExclusion')->willReturn(false);
        $handler->method('isBuddyBossLoginForm')->willReturn(false);
        $handler->method('isBuddyPressAlreadyHasValidationErrors')->willReturn(false);

        $errors = new WP_Error();
        $result = $handler->doSkipRequest($errors);

        $this->assertSame($errors, $result);
    }

    public function test_do_skip_request_returns_wp_error_when_signup_already_handled_and_input_is_not_wp_error()
    {
        $handler = $this->getMockBuilder(WPRegistrationErrors::class)
            ->onlyMethods(array(
                'isUserEnabled',
                'isRegistrationCheckDisabled',
                'isSignupHandled',
                'isWPMembersExclusion',
                'isBuddyBossLoginForm',
                'isBuddyPressAlreadyHasValidationErrors',
            ))
            ->disableOriginalConstructor()
            ->getMock();

        $this->setHandlerState($handler);

        $handler->method('isUserEnabled')->willReturn(false);
        $handler->method('isRegistrationCheckDisabled')->willReturn(false);
        $handler->method('isSignupHandled')->willReturn(true);
        $handler->method('isWPMembersExclusion')->willReturn(false);
        $handler->method('isBuddyBossLoginForm')->willReturn(false);
        $handler->method('isBuddyPressAlreadyHasValidationErrors')->willReturn(false);

        $errors = (object) array('errors' => array('x' => array('msg')));
        $result = $handler->doSkipRequest($errors);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('An unexpected error occurred.', $result->get_error_message());
    }

    public function test_do_skip_request_returns_false_when_no_skip_conditions_match()
    {
        $handler = $this->getMockBuilder(WPRegistrationErrors::class)
            ->onlyMethods(array(
                'isUserEnabled',
                'isRegistrationCheckDisabled',
                'isSignupHandled',
                'isWPMembersExclusion',
                'isBuddyBossLoginForm',
                'isBuddyPressAlreadyHasValidationErrors',
            ))
            ->disableOriginalConstructor()
            ->getMock();

        $this->setHandlerState($handler);

        $handler->method('isUserEnabled')->willReturn(false);
        $handler->method('isRegistrationCheckDisabled')->willReturn(false);
        $handler->method('isSignupHandled')->willReturn(false);
        $handler->method('isWPMembersExclusion')->willReturn(false);
        $handler->method('isBuddyBossLoginForm')->willReturn(false);
        $handler->method('isBuddyPressAlreadyHasValidationErrors')->willReturn(false);

        $result = $handler->doSkipRequest(new WP_Error());

        $this->assertFalse($result);
    }
}
