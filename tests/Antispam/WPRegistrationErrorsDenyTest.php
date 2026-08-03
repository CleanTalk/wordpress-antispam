<?php
require_once __DIR__ . '/WPRegistrationErrorsTestBase.php';
use Cleantalk\Antispam\IntegrationsByClass\WPRegistrationErrors;

class WPRegistrationErrorsDenyTest extends WPRegistrationErrorsTestBase
{
    public function test_on_deny_actions_adds_ct_error_to_wp_error()
    {
        $handler = $this->getMockBuilder(WPRegistrationErrors::class)
            ->onlyMethods(array('runMaybeDieActions'))
            ->getMock();

        $ctResult = $this->makeCtResult(array(
            'allow' => 0,
            'comment' => 'Spam detected',
        ));

        $errors = new WP_Error();

        $handler->expects($this->once())
            ->method('runMaybeDieActions');

        $result = $handler->onDenyActions($ctResult, $errors);

        $this->assertSame($errors, $result);
        $this->assertArrayHasKey('ct_error', $errors->errors);
        $this->assertSame('Spam detected', $errors->errors['ct_error'][0]);
        $this->assertSame('Spam detected', $GLOBALS['ct_negative_comment']);
        $this->assertSame('Spam detected', $GLOBALS['ct_registration_error_comment']);
    }

    public function test_on_deny_actions_cleans_facebook_payload()
    {
        $_POST['FB_userdata'] = array(
            'email' => 'john@example.com',
            'name' => 'John Doe',
        );

        $handler = $this->getMockBuilder(WPRegistrationErrors::class)
            ->onlyMethods(array('runMaybeDieActions'))
            ->getMock();

        $handler->is_facebook = true;

        $ctResult = $this->makeCtResult(array(
            'allow' => 0,
            'comment' => 'Spam detected',
        ));

        $handler->method('runMaybeDieActions')->willReturn(null);

        $handler->onDenyActions($ctResult, new WP_Error());

        $this->assertSame('', $_POST['FB_userdata']['email']);
        $this->assertSame('', $_POST['FB_userdata']['name']);
    }
}
