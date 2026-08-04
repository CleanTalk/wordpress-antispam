<?php
require_once __DIR__ . '/WPRegistrationErrorsTestBase.php';
use Cleantalk\Antispam\IntegrationsByClass\WPRegistrationErrors;

class WPRegistrationErrorsAllowTest extends WPRegistrationErrorsTestBase
{
    public function test_on_allow_actions_sets_cookie_request_id()
    {
        $handler = new WPRegistrationErrors();
        $ctResult = $this->makeCtResult(array('id' => 555));

        $handler->onAllowActions($ctResult);

        $this->assertSame(555, $GLOBALS['apbct_cookie_request_id']);
    }
}
