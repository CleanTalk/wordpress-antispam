<?php

namespace Antispam\IntegrationsByClass;

use Cleantalk\Antispam\CleantalkResponse;
use Cleantalk\Antispam\IntegrationsByClass\ArmMember;
use Cleantalk\ApbctWP\State;
use Cleantalk\ApbctWP\Variables\Post;
use PHPUnit\Framework\TestCase;

class TestArmMember extends TestCase
{
    private $integration;
    private $apbct_backup;
    private $post_backup;
    private $server_backup;
    private $post_cache_backup;
    private $executed_backup;
    private $user_backup;
    private $post_filter;

    public function setUp(): void
    {
        parent::setUp();
        global $apbct, $cleantalk_executed;

        $this->apbct_backup = $apbct;
        $this->post_backup = $_POST;
        $this->server_backup = $_SERVER;
        $this->post_cache_backup = Post::getInstance()->variables;
        $this->executed_backup = $cleantalk_executed;
        $this->user_backup = get_current_user_id();

        $apbct = new State('cleantalk', array('settings', 'data', 'errors', 'remote_calls', 'stats', 'fw_stats'));
        $apbct->settings['forms__registrations_test'] = 1;
        $apbct->settings['data__protect_logged_in'] = 0;
        $cleantalk_executed = false;
        wp_set_current_user(0);
        $_POST = array();
        Post::getInstance()->variables = array();

        $this->integration = $this->getMockBuilder(ArmMember::class)
            ->setMethods(array('checkRegistration'))
            ->getMock();
        $this->integration->doPublicWork();
    }

    public function tearDown(): void
    {
        global $apbct, $cleantalk_executed;

        $this->integration->checkSpam(new \WP_Error('arm_reg_error', 'Cleanup'));
        remove_filter('arm_before_member_register', array($this->integration, 'gatherData'), 100);
        remove_action('arm_remove_third_party_error', array($this->integration, 'checkSpam'), 20);
        if ( $this->post_filter ) {
            remove_filter('apbct__filter_post', $this->post_filter);
        }

        $apbct = $this->apbct_backup;
        $cleantalk_executed = $this->executed_backup;
        $_POST = $this->post_backup;
        $_SERVER = $this->server_backup;
        Post::getInstance()->variables = $this->post_cache_backup;
        wp_set_current_user($this->user_backup);
        parent::tearDown();
    }

    private function registrationData()
    {
        return array(
            'user_email' => 'member@example.com',
            'user_login' => 'newmember',
            'first_name' => 'New',
            'last_name' => 'Member',
            'user_pass' => 'private-password',
            'repeat_pass' => 'private-password',
            'ct_bot_detector_event_token' => str_repeat('a', 64),
        );
    }

    private function response($allow, $comment = '')
    {
        return array('ct_result' => new CleantalkResponse((object)array(
            'allow' => $allow,
            'comment' => $comment,
        ), null));
    }

    public function testHooksAreRegisteredAfterArMemberErrorCleanup()
    {
        $this->assertSame(100, has_filter(
            'arm_before_member_register',
            array($this->integration, 'gatherData')
        ));
        $this->assertSame(20, has_action(
            'arm_remove_third_party_error',
            array($this->integration, 'checkSpam')
        ));
    }

    public function testRegistrationPayloadContainsIdentityAndTopLevelTokenButNoPasswords()
    {
        $data = $this->integration->collectBaseCallData($this->registrationData());

        $this->assertSame('member@example.com', $data['sender_email']);
        $this->assertSame('newmember', $data['sender_nickname']);
        $this->assertSame(str_repeat('a', 64), $data['event_token']);
        $this->assertSame('contact_form_wordpress_armmember', $data['post_info']['comment_type']);
        $this->assertStringNotContainsString('private-password', json_encode($data));
    }

    public function testHiddenUsernameFallsBackToEmail()
    {
        $data = $this->registrationData();
        unset($data['user_login']);

        $result = $this->integration->collectBaseCallData($data);

        $this->assertSame('member@example.com', $result['sender_nickname']);
    }

    public function testTokenCanBeReadFromCurrentPost()
    {
        $_POST['ct_bot_detector_event_token'] = str_repeat('b', 64);
        $data = $this->registrationData();
        unset($data['ct_bot_detector_event_token']);

        $result = $this->integration->collectBaseCallData($data);

        $this->assertSame(str_repeat('b', 64), $result['event_token']);
    }

    public function testRegistrationUsesSavedDataInsteadOfPaymentCallbackPost()
    {
        $_POST = array('payment_status' => 'Completed', 'payer_email' => 'payer@example.com');
        $data = $this->registrationData();
        $expected = $this->integration->collectBaseCallData($data);
        $this->integration->expects($this->once())
            ->method('checkRegistration')
            ->with($expected)
            ->willReturn($this->response(1));

        $this->assertSame($data, apply_filters('arm_before_member_register', $data));
        do_action('arm_remove_third_party_error', new \WP_Error());
    }

    public function testPostFilterReceivesNoPasswordsAndCanExcludeFields()
    {
        $this->post_filter = function ($data) {
            $this->assertArrayNotHasKey('user_pass', $data);
            $this->assertArrayNotHasKey('repeat_pass', $data);
            unset($data['user_login']);
            return $data;
        };
        add_filter('apbct__filter_post', $this->post_filter);

        $result = $this->integration->collectBaseCallData($this->registrationData());

        $this->assertSame('member@example.com', $result['sender_nickname']);
    }

    public function testSpamIsAddedToArMemberRegistrationErrors()
    {
        $this->integration->expects($this->once())
            ->method('checkRegistration')
            ->willReturn($this->response(0, 'Spam registration'));
        $errors = new \WP_Error();

        apply_filters('arm_before_member_register', $this->registrationData());
        do_action('arm_remove_third_party_error', $errors);

        $this->assertSame(array('Spam registration'), $errors->get_error_messages('arm_reg_error'));
        $this->assertFalse(ArmMember::isRegistrationInProgress());
    }

    public function testAllowedRegistrationLeavesErrorsUnchanged()
    {
        $this->integration->expects($this->once())
            ->method('checkRegistration')
            ->willReturn($this->response(1));
        $errors = new \WP_Error();

        apply_filters('arm_before_member_register', $this->registrationData());
        do_action('arm_remove_third_party_error', $errors);

        $this->assertSame(array(), $errors->get_error_codes());
    }

    public function testExistingValidationErrorsArePreservedWithoutCloudCall()
    {
        $this->integration->expects($this->never())->method('checkRegistration');
        $errors = new \WP_Error('arm_reg_error', 'Email already registered');
        $errors->add('other_error', 'Other validation error');

        apply_filters('arm_before_member_register', $this->registrationData());
        do_action('arm_remove_third_party_error', $errors);

        $this->assertSame(array('Email already registered', 'Other validation error'), $errors->get_error_messages());
        $this->assertFalse(ArmMember::isRegistrationInProgress());
    }

    public function testStandardRegistrationCheckIsSkippedOnlyWithinArMemberRegistration()
    {
        global $cleantalk_executed;
        $this->integration->expects($this->once())
            ->method('checkRegistration')
            ->willReturn($this->response(1));
        $errors = new \WP_Error();

        $this->assertFalse(ArmMember::isRegistrationInProgress());
        apply_filters('arm_before_member_register', $this->registrationData());
        $this->assertTrue(ArmMember::isRegistrationInProgress());
        $this->assertSame($errors, ct_register_post('newmember', 'member@example.com', $errors));
        $this->assertFalse($cleantalk_executed);

        do_action('arm_remove_third_party_error', $errors);
        do_action('arm_remove_third_party_error', $errors);
        $this->assertFalse(ArmMember::isRegistrationInProgress());
    }

    public function testAdministrativeCreationWithoutRegistrationFilterIsNotChecked()
    {
        $_POST = array('arm_action' => 'add_member', 'user_email' => 'member@example.com');
        $this->integration->expects($this->never())->method('checkRegistration');
        $errors = new \WP_Error();

        do_action('arm_remove_third_party_error', $errors);

        $this->assertSame(array(), $errors->get_error_codes());
    }

    public function testDisabledRegistrationProtectionDoesNotCaptureRegistration()
    {
        global $apbct;
        $apbct->settings['forms__registrations_test'] = 0;
        $this->integration->expects($this->never())->method('checkRegistration');
        $data = $this->registrationData();

        $this->assertSame($data, apply_filters('arm_before_member_register', $data));
        do_action('arm_remove_third_party_error', new \WP_Error());

        $this->assertFalse(ArmMember::isRegistrationInProgress());
    }

    public function testDisablingProtectionAfterGatheringClearsRegistrationContext()
    {
        global $apbct;
        $this->integration->expects($this->never())->method('checkRegistration');
        apply_filters('arm_before_member_register', $this->registrationData());
        $apbct->settings['forms__registrations_test'] = 0;

        do_action('arm_remove_third_party_error', new \WP_Error());

        $this->assertFalse(ArmMember::isRegistrationInProgress());
    }

    public function testLoggedInSubscriberIsSkippedWhenLoggedInProtectionIsDisabled()
    {
        $user = new \WP_User();
        $user->ID = 12345;
        $user->roles = array('subscriber');
        $GLOBALS['current_user'] = $user;
        $this->integration->expects($this->never())->method('checkRegistration');

        apply_filters('arm_before_member_register', $this->registrationData());
        do_action('arm_remove_third_party_error', new \WP_Error());

        $this->assertTrue(is_user_logged_in());
        $this->assertFalse(ArmMember::isRegistrationInProgress());
    }

    public function testLoggedInSubscriberCanBeCheckedWhenProtectionIsEnabled()
    {
        global $apbct;
        $apbct->settings['data__protect_logged_in'] = 1;
        $user = new \WP_User();
        $user->ID = 12345;
        $user->roles = array('subscriber');
        $GLOBALS['current_user'] = $user;
        $this->integration->expects($this->once())
            ->method('checkRegistration')
            ->willReturn($this->response(1));

        apply_filters('arm_before_member_register', $this->registrationData());
        do_action('arm_remove_third_party_error', new \WP_Error());

        $this->assertFalse(ArmMember::isRegistrationInProgress());
    }

    public function testAdministratorIsNotCheckedEvenWithLoggedInProtectionEnabled()
    {
        global $apbct;
        $apbct->settings['data__protect_logged_in'] = 1;
        $user = new \WP_User();
        $user->ID = 12345;
        $user->roles = array('administrator');
        $GLOBALS['current_user'] = $user;
        $this->integration->expects($this->never())->method('checkRegistration');

        apply_filters('arm_before_member_register', $this->registrationData());
        do_action('arm_remove_third_party_error', new \WP_Error());

        $this->assertFalse(ArmMember::isRegistrationInProgress());
    }

    public function testInvalidRegistrationDataIsLoggedAndNotChecked()
    {
        $this->integration->expects($this->never())->method('checkRegistration');
        $before = did_action('apbct_skipped_request');

        $this->assertSame('invalid', apply_filters('arm_before_member_register', 'invalid'));
        do_action('arm_remove_third_party_error', new \WP_Error());

        $this->assertGreaterThan($before, did_action('apbct_skipped_request'));
        $this->assertFalse(ArmMember::isRegistrationInProgress());
    }

    public function testInvalidErrorArgumentIsLoggedAndClearsRegistrationContext()
    {
        $this->integration->expects($this->never())->method('checkRegistration');
        apply_filters('arm_before_member_register', $this->registrationData());
        $before = did_action('apbct_skipped_request');

        do_action('arm_remove_third_party_error', null);

        $this->assertGreaterThan($before, did_action('apbct_skipped_request'));
        $this->assertFalse(ArmMember::isRegistrationInProgress());
    }

    public function testLoginAndProfileRequestsDoNotTriggerRegistrationCheck()
    {
        $this->integration->expects($this->never())->method('checkRegistration');

        foreach ( array('please-login', 'edit_profile') as $action ) {
            $_POST = array('action' => 'arm_shortcode_form_ajax_action', 'arm_action' => $action);
            do_action('arm_before_form_submit_action', new \stdClass(), $_POST);
            do_action('arm_after_form_validate_action', new \stdClass(), $_POST);
        }

        $this->assertFalse(ArmMember::isRegistrationInProgress());
    }
}
