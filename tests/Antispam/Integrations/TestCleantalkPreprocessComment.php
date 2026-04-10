<?php

namespace Cleantalk\Antispam\Integrations;

use PHPUnit\Framework\TestCase;

/**
 * Covers skip rules for pingback/trackback in preprocess comment flow (see PR #764 / #773).
 */
class TestCleantalkPreprocessComment extends TestCase
{
    /**
     * @var mixed
     */
    private $savedCurrentUser;
    /**
     * @var string
     */
    private $savedDisallowedKeys;

    protected function setUp(): void
    {
        parent::setUp();
        global $current_user;
        $this->savedCurrentUser = isset($current_user) ? $current_user : null;
        $this->savedDisallowedKeys = (string) get_option('disallowed_keys', '');
    }

    protected function tearDown(): void
    {
        global $current_user;
        $current_user = $this->savedCurrentUser;
        if (function_exists('wp_set_current_user')) {
            wp_set_current_user(0);
        }
        update_option('disallowed_keys', $this->savedDisallowedKeys);
        parent::tearDown();
    }

    /**
     * @return \stdClass
     */
    private function makeSubscriberUser()
    {
        $subscriber = new \stdClass();
        $subscriber->ID = 91234;
        $subscriber->roles = array('subscriber');
        $subscriber->caps = array('subscriber' => true);

        return $subscriber;
    }

    /**
     * @param array<string, mixed> $wp_comment
     * @param object               $current_user
     * @param bool                 $ct_comment_done
     * @param object|null          $apbct
     *
     * @return mixed
     */
    private function invokeDoSkipReason(array $wp_comment, $current_user, $ct_comment_done = false, $apbct = null)
    {
        $integration = new CleantalkPreprocessComment();
        $reflection  = new \ReflectionClass($integration);

        $wp_comment_prop = $reflection->getProperty('wp_comment');
        $wp_comment_prop->setAccessible(true);
        $wp_comment_prop->setValue($integration, $wp_comment);

        if ($apbct !== null) {
            $apbct_prop = $reflection->getProperty('apbct');
            $apbct_prop->setAccessible(true);
            $apbct_prop->setValue($integration, $apbct);
        }

        $method = $reflection->getMethod('doSkipReason');
        $method->setAccessible(true);

        return $method->invoke($integration, $current_user, $ct_comment_done);
    }

    public function testDoSkipReasonSkipsAdministratorBeforePingbackHandling(): void
    {
        $user = new \stdClass();
        $user->roles = array('administrator');

        $result = $this->invokeDoSkipReason(
            array(
                'comment_type'    => 'pingback',
                'comment_post_ID' => 1,
            ),
            $user
        );

        $this->assertNotFalse($result);
        $this->assertStringContainsString('doSkipReason', $result);
    }

    public function testDoSkipReasonSkipsPingback(): void
    {
        $user = new \stdClass();
        $user->roles = array('subscriber');

        $result = $this->invokeDoSkipReason(
            array(
                'comment_type'    => 'pingback',
                'comment_post_ID' => 1,
            ),
            $user
        );

        $this->assertNotFalse($result);
        $this->assertStringContainsString('doSkipReason', $result);
    }

    public function testDoSkipReasonSkipsTrackback(): void
    {
        $user = new \stdClass();
        $user->roles = array('subscriber');

        $result = $this->invokeDoSkipReason(
            array(
                'comment_type'    => 'trackback',
                'comment_post_ID' => 1,
            ),
            $user
        );

        $this->assertNotFalse($result);
        $this->assertStringContainsString('doSkipReason', $result);
    }

    /**
     * Missing comment_type must default to "comment" and continue into the main ruleset (not pingback skip).
     */
    public function testDoSkipReasonDefaultsMissingCommentTypeToComment(): void
    {
        global $current_user;
        $subscriber = $this->makeSubscriberUser();
        $current_user = $subscriber;

        $apbct = (object) array(
            'settings' => array(
                'forms__comments_test' => 0,
            ),
        );

        $wp_comment = array(
            'comment_post_ID' => 1,
        );

        $result = $this->invokeDoSkipReason($wp_comment, $subscriber, false, $apbct);

        $this->assertNotFalse($result);
        $this->assertStringContainsString('doSkipReason', $result);
    }

    public function testDoSkipReasonSkipsWhenCommentAlreadyHandled(): void
    {
        global $current_user;
        $subscriber = $this->makeSubscriberUser();
        $current_user = $subscriber;

        $apbct = (object) array(
            'settings' => array(
                'forms__comments_test' => 1,
            ),
        );

        $wp_comment = array(
            'comment_type' => 'comment',
            'comment_post_ID' => 1,
            'comment_author' => 'John',
            'comment_author_email' => 'john@example.com',
            'comment_author_url' => 'https://example.com',
            'comment_content' => 'Regular text',
        );

        $result = $this->invokeDoSkipReason($wp_comment, $subscriber, true, $apbct);

        $this->assertNotFalse($result);
        $this->assertStringContainsString('doSkipReason', $result);
    }

    public function testDoSkipReasonReturnsFalseForRegularCommentWithoutBlacklistedContent(): void
    {
        global $current_user;
        $subscriber = $this->makeSubscriberUser();
        $current_user = $subscriber;

        update_option('disallowed_keys', 'forbidden-keyword-that-is-not-used');

        $apbct = (object) array(
            'settings' => array(
                'forms__comments_test' => 1,
            ),
        );

        $wp_comment = array(
            'comment_type' => 'comment',
            'comment_post_ID' => 1,
            'comment_author' => 'John',
            'comment_author_email' => 'john@example.com',
            'comment_author_url' => 'https://example.com',
            'comment_content' => 'Absolutely clean content',
        );

        $result = $this->invokeDoSkipReason($wp_comment, $subscriber, false, $apbct);

        $this->assertFalse($result);
    }

    public function testDoSkipReasonSkipsWhenWordPressBlacklistMatches(): void
    {
        global $current_user;
        $subscriber = $this->makeSubscriberUser();
        $current_user = $subscriber;

        update_option('disallowed_keys', 'blacklisted-fragment');

        $apbct = (object) array(
            'settings' => array(
                'forms__comments_test' => 1,
            ),
        );

        $wp_comment = array(
            'comment_type' => 'comment',
            'comment_post_ID' => 1,
            'comment_author' => 'John',
            'comment_author_email' => 'john@example.com',
            'comment_author_url' => 'https://example.com',
            'comment_content' => 'Message contains blacklisted-fragment inside',
        );

        $result = $this->invokeDoSkipReason($wp_comment, $subscriber, false, $apbct);

        $this->assertNotFalse($result);
        $this->assertStringContainsString('doSkipReason', $result);
    }
}

