<?php

namespace Cleantalk\Antispam\Tests;

use Cleantalk\Antispam\Integrations;
use Cleantalk\Antispam\Integrations\SimpleMembership;
use Cleantalk\ApbctWP\Variables\Post;

class TestSimpleMembership extends \WP_UnitTestCase
{
    private $original_post;
    private $original_get;
    private $original_post_cache;
    private $original_ct_comment;
    private $original_cleantalk_executed;

    public function set_up()
    {
        parent::set_up();

        $this->original_post = $_POST;
        $this->original_get = $_GET;
        $this->original_post_cache = Post::getInstance()->variables;
        $this->original_ct_comment = $GLOBALS['ct_comment'] ?? null;
        $this->original_cleantalk_executed = $GLOBALS['cleantalk_executed'] ?? null;

        $_POST = array(
            'email' => 'member@example.org',
            'user_name' => 'new-member',
        );
        $_GET = array();
        Post::getInstance()->variables = array();
        remove_all_filters('swpm_validate_registration_form_submission');
        remove_all_filters('swpm_front_end_registration_complete_user_data');
    }

    public function tear_down()
    {
        $_POST = $this->original_post;
        $_GET = $this->original_get;
        Post::getInstance()->variables = $this->original_post_cache;
        $GLOBALS['ct_comment'] = $this->original_ct_comment;
        $GLOBALS['cleantalk_executed'] = $this->original_cleantalk_executed;
        unset($GLOBALS['apbct_simple_membership_calls'], $GLOBALS['apbct_simple_membership_allow']);

        parent::tear_down();
    }

    public function testRegistryBindsValidationInsteadOfCompletedRegistration()
    {
        $this->registerIntegration(true);

        $this->assertTrue(has_filter('swpm_validate_registration_form_submission'));
        $this->assertSame(
            array(PHP_INT_MAX),
            array_keys($GLOBALS['wp_filter']['swpm_validate_registration_form_submission']->callbacks)
        );
        $this->assertFalse(has_filter('swpm_front_end_registration_complete_user_data'));
    }

    public function testDisabledRegistrationProtectionDoesNotBindValidation()
    {
        $this->registerIntegration(false);

        $this->assertFalse(has_filter('swpm_validate_registration_form_submission'));
        $this->assertFalse(has_filter('swpm_front_end_registration_complete_user_data'));
    }

    public function testCollectsRegistrationFieldsBeforeMemberDataExists()
    {
        $integration = new SimpleMembership();

        $this->assertSame(
            array('email' => 'member@example.org', 'nickname' => 'new-member'),
            $integration->getDataForChecking('')
        );
    }

    public function testArrayFieldsDoNotBecomeAccountIdentifiers()
    {
        $_POST['email'] = array('victim@example.org');
        $_POST['user_name'] = array('victim');
        $integration = new SimpleMembership();

        $this->assertSame(
            array('email' => '', 'nickname' => ''),
            $integration->getDataForChecking('')
        );
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testPreservesEarlierValidationErrorWithoutCallingCloud()
    {
        $this->loadIsolatedFixture();
        $error = 'CAPTCHA validation failed';
        add_filter('swpm_validate_registration_form_submission', static function () use ($error) {
            return $error;
        }, 20);
        $this->registerIntegration(true);

        $this->assertSame($error, apply_filters('swpm_validate_registration_form_submission', ''));
        $this->assertSame(array(), $GLOBALS['apbct_simple_membership_calls']);
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testAllowedRegistrationPreservesFilterResultAndCloudPayload()
    {
        $this->loadIsolatedFixture();
        $this->registerIntegration(true);

        $this->assertSame('', apply_filters('swpm_validate_registration_form_submission', ''));
        $this->assertCount(1, $GLOBALS['apbct_simple_membership_calls']);
        list($params, $registration_flag) = $GLOBALS['apbct_simple_membership_calls'][0];
        $this->assertSame('member@example.org', $params['sender_email']);
        $this->assertSame('new-member', $params['sender_nickname']);
        $this->assertSame('contact_form_wordpress_simplemembership', $params['post_info']['comment_type']);
        $this->assertFalse($registration_flag);
    }

    /**
     * @dataProvider registrationQueryProvider
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testBlockedRegistrationDoesNotLookUpOrDeleteAccounts($query)
    {
        $this->loadIsolatedFixture();
        $GLOBALS['apbct_simple_membership_allow'] = 0;
        $_GET = $query;
        $user_id = self::factory()->user->create(array(
            'user_login' => 'existing-member',
            'user_email' => 'member@example.org',
        ));
        $member = (object) array(
            'member_id' => 42,
            'email' => 'member@example.org',
            'user_name' => '',
            'subscription_id' => 'existing-subscription',
        );
        \SwpmMemberUtils::$members[42] = $member;
        $this->registerIntegration(true);

        $blocked = new SimpleMembershipBlockedException();
        add_action('apbct_pre_block_page', static function () use ($blocked) {
            throw $blocked;
        });

        try {
            apply_filters('swpm_validate_registration_form_submission', '');
            $this->fail('A denied registration must stop at the CleanTalk block page.');
        } catch ( SimpleMembershipBlockedException $exception ) {
            $this->assertSame($blocked, $exception);
        }

        $this->assertCount(1, $GLOBALS['apbct_simple_membership_calls']);
        $this->assertSame('Registration blocked', $GLOBALS['ct_comment']);
        $this->assertSame(array(), \SwpmMemberUtils::$lookups);
        $this->assertSame(array(), \SwpmMembers::$deleted_ids);
        $this->assertSame(array(42 => $member), \SwpmMemberUtils::$members);
        $this->assertSame('existing-subscription', \SwpmMemberUtils::$members[42]->subscription_id);
        $this->assertSame($user_id, get_user_by('email', 'member@example.org')->ID);
    }

    public function registrationQueryProvider()
    {
        return array(
            'free registration excluding victim from duplicate check' => array(array('member_id' => '42')),
            'paid registration completion' => array(array('member_id' => '42', 'code' => 'registration-code')),
        );
    }

    private function registerIntegration($enabled)
    {
        include CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-integrations-by-hook.php';

        new Integrations(
            array('SimpleMembership' => $apbct_active_integrations['SimpleMembership']),
            array('forms__registrations_test' => $enabled)
        );
    }

    private function loadIsolatedFixture()
    {
        $this->assertFalse(function_exists('Cleantalk\\Antispam\\apbct_base_call'));
        $this->assertFalse(function_exists('Cleantalk\\Antispam\\Integrations\\ct_die'));
        $this->assertFalse(class_exists('SwpmMemberUtils', false));
        $this->assertFalse(class_exists('SwpmMembers', false));
        require __DIR__ . '/../../fixtures/simple-membership.inc';
        $GLOBALS['apbct_simple_membership_calls'] = array();
        $GLOBALS['apbct_simple_membership_allow'] = 1;
    }
}

class SimpleMembershipBlockedException extends \RuntimeException
{
}
