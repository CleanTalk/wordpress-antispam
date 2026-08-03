<?php

require_once __DIR__ . '/WPRegistrationErrorsTestBase.php';

use Cleantalk\Antispam\IntegrationsByClass\WPRegistrationErrors;

class WPRegistrationErrorsHandleTest extends WPRegistrationErrorsTestBase
{
    public function test_handle_returns_early_when_skip_rule_matches()
    {
        $handler = $this->getMockBuilder(WPRegistrationErrors::class)
            ->onlyMethods(array(
                'defineBuddyPressObject',
                'probablyBuddyPressCredentials',
                'probablyFacebookCredentials',
                'probablyWooCommerceCredentials',
                'probablyTutorLMSCredentials',
                'doSkipRequest',
            ))
            ->getMock();

        $errors = new WP_Error();

        $handler->method('defineBuddyPressObject')->willReturn(null);
        $handler->method('probablyBuddyPressCredentials')->willReturn(false);
        $handler->method('probablyFacebookCredentials')->willReturn(false);
        $handler->method('probablyWooCommerceCredentials')->willReturn(null);
        $handler->method('probablyTutorLMSCredentials')->willReturn(null);
        $handler->method('doSkipRequest')->willReturn($errors);

        $result = $handler->handle($errors, 'john', 'john@example.com');

        $this->assertSame($errors, $result);
    }

    public function test_handle_returns_errors_if_base_call_has_no_ct_result()
    {
        $handler = $this->getMockBuilder(WPRegistrationErrors::class)
            ->onlyMethods(array(
                'defineBuddyPressObject',
                'probablyBuddyPressCredentials',
                'probablyFacebookCredentials',
                'probablyWooCommerceCredentials',
                'probablyTutorLMSCredentials',
                'doSkipRequest',
                'getCheckJSData',
                'forceRegFlagCases',
                'doBaseCall',
            ))
            ->getMock();

        $errors = new WP_Error();

        $handler->method('defineBuddyPressObject')->willReturn(null);
        $handler->method('probablyBuddyPressCredentials')->willReturn(false);
        $handler->method('probablyFacebookCredentials')->willReturn(false);
        $handler->method('probablyWooCommerceCredentials')->willReturn(null);
        $handler->method('probablyTutorLMSCredentials')->willReturn(null);
        $handler->method('doSkipRequest')->willReturn(false);
        $handler->method('getCheckJSData')->willReturn(array(
            'checkjs' => 1,
            'checkjs_post' => 1,
            'checkjs_cookie' => 1,
        ));
        $handler->method('forceRegFlagCases')->willReturn(true);
        $handler->method('doBaseCall')->willReturn(array());

        $result = $handler->handle($errors, 'john', 'john@example.com');

        $this->assertSame($errors, $result);
    }

    public function test_handle_calls_on_deny_actions_when_ct_result_disallows_registration()
    {
        $handler = $this->getMockBuilder(WPRegistrationErrors::class)
            ->onlyMethods(array(
                'defineBuddyPressObject',
                'probablyBuddyPressCredentials',
                'probablyFacebookCredentials',
                'probablyWooCommerceCredentials',
                'probablyTutorLMSCredentials',
                'doSkipRequest',
                'getCheckJSData',
                'forceRegFlagCases',
                'doBaseCall',
                'doServiceActions',
                'onDenyActions',
            ))
            ->getMock();

        $errors = new WP_Error();
        $ctResult = $this->makeCtResult(array(
            'allow' => 0,
            'comment' => 'Spam detected',
        ));

        $handler->method('defineBuddyPressObject')->willReturn(null);
        $handler->method('probablyBuddyPressCredentials')->willReturn(false);
        $handler->method('probablyFacebookCredentials')->willReturn(false);
        $handler->method('probablyWooCommerceCredentials')->willReturn(null);
        $handler->method('probablyTutorLMSCredentials')->willReturn(null);
        $handler->method('doSkipRequest')->willReturn(false);
        $handler->method('getCheckJSData')->willReturn(array(
            'checkjs' => 1,
            'checkjs_post' => 1,
            'checkjs_cookie' => 1,
        ));
        $handler->method('forceRegFlagCases')->willReturn(true);
        $handler->method('doBaseCall')->willReturn(array(
            'ct_result' => $ctResult,
        ));
        $handler->expects($this->once())->method('doServiceActions');
        $handler->expects($this->once())
            ->method('onDenyActions')
            ->with($ctResult, $errors)
            ->willReturn($errors);

        $result = $handler->handle($errors, 'john', 'john@example.com');

        $this->assertSame($errors, $result);
    }
}
