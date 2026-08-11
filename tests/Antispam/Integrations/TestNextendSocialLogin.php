<?php

namespace {
    if ( ! class_exists('NextendSocialProviderDummy', false) ) {
        class NextendSocialProviderDummy
        {
            private $auth_user_data = array();

            public function __construct(array $auth_user_data = array())
            {
                $this->auth_user_data = $auth_user_data;
            }

            public function getAuthUserData($key)
            {
                return $this->auth_user_data[$key] ?? null;
            }
        }
    }
}

namespace Cleantalk\Antispam\Integrations {

    if ( ! function_exists(__NAMESPACE__ . '\\ct_die') ) {
        function ct_die($message = null, $code = null)
        {
            $GLOBALS['nextend_social_login_test_ct_die'] = array(
                'message' => $message,
                'code'    => $code,
            );
        }
    }

    class TestNextendSocialLogin extends \ApbctTestCase
    {
        protected function setUp(): void
        {
            parent::setUp();
            $GLOBALS['nextend_social_login_test_ct_die'] = null;
            $GLOBALS['ct_comment'] = null;
        }

        protected function tearDown(): void
        {
            unset($GLOBALS['nextend_social_login_test_ct_die']);
            $GLOBALS['ct_comment'] = null;
            parent::tearDown();
        }

        public function testGetDataForCheckingReturnsEmailAndNicknameForNextendProvider()
        {
            $integration = new NextendSocialLogin();
            $provider = new \NextendSocialProviderDummy(array(
                'email' => 'user@example.com',
                'name'  => 'User Name',
            ));

            $this->assertSame(
                array(
                    'email'    => 'user@example.com',
                    'nickname' => 'User Name',
                ),
                $integration->getDataForChecking($provider)
            );
        }

        public function testGetDataForCheckingReturnsNullForUnsupportedArgument()
        {
            $integration = new NextendSocialLogin();

            $this->assertNull($integration->getDataForChecking(new \stdClass()));
        }

        public function testDoBlockSetsGlobalCommentAndInvokesCtDie()
        {
            $integration = new NextendSocialLogin();

            $integration->doBlock('blocked message');

            $this->assertSame('blocked message', $GLOBALS['ct_comment']);
            $this->assertSame(
                array(
                    'message' => null,
                    'code'    => null,
                ),
                $GLOBALS['nextend_social_login_test_ct_die']
            );
        }

        public function testIsOAuthProviderEnabledReturnsFalseForInvalidProviderId()
        {
            $this->assertFalse(NextendSocialLogin::isOAuthProviderEnabled(''));
            $this->assertFalse(NextendSocialLogin::isOAuthProviderEnabled(123));
            $this->assertFalse(NextendSocialLogin::isOAuthProviderEnabled(null));
        }

        public function testIsOAuthProviderEnabledReturnsTrueWhenClientSecretExists()
        {

            update_option('nextend_social_login_provider', serialize(array(
                'client_secret' => 'secret-value',
            )));

            $this->assertTrue(NextendSocialLogin::isOAuthProviderEnabled('nextend_social_login_provider'));
        }
    }

}
