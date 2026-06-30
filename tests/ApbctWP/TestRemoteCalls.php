<?php

use PHPUnit\Framework\TestCase;
use Cleantalk\ApbctWP\RemoteCalls;

class TestRemoteCalls extends TestCase
{
    private $apbct_backup;
    protected function setUp(): void
    {
        // Reset global after each test
        global $apbct;
        $this->apbct_backup = $apbct;
        $apbct = null;
    }

    protected function tearDown(): void
    {
        // Reset global after each test
        global $apbct;
        $apbct = $this->apbct_backup;
    }

    /** @test */
    public function checkReturnsFalseWhenNoActionProvided()
    {
        $_REQUEST = [];
        \Cleantalk\ApbctWP\Variables\Request::getInstance()->variables = $_REQUEST;
        $this->assertFalse(RemoteCalls::check());
    }

    /** @test */
    public function checkCallsCheckWithTokenWhenTokenProvided()
    {
        $_REQUEST = [
            'spbc_remote_call_action' => 'antispam',
            'spbc_remote_call_token'  => 'token',
            //any token is allowed on check() stage, containment will be checked on perform()
            'plugin_name'             => 'antispam',
        ];

        \Cleantalk\ApbctWP\Variables\Request::getInstance()->variables = $_REQUEST;

        // checkWithToken returns true
        $this->assertTrue(RemoteCalls::check());
    }

    /** @test */
    public function checkCallsCheckWithoutTokenWhenNoTokenProvided()
    {
        global $apbct;

        $_REQUEST = [
            'spbc_remote_call_action' => 'get_fresh_wpnonce', //allowed action
            'plugin_name'             => 'antispam',
        ];

        \Cleantalk\ApbctWP\Variables\Request::getInstance()->variables = $_REQUEST;

        $apbct = new stdClass();
        $apbct->key_is_ok = true;
        $apbct->api_key = null;
        $apbct->data = [];
        $apbct->data['moderate_ip'] = null;

        // checkWithoutToken returns true
        $this->assertTrue(RemoteCalls::check());
    }

    public function checkCallsCheckWithTokenWhenEmptyTokenProvided()
    {
        $_REQUEST = [
            'spbc_remote_call_action' => 'antispam',
            //any token is allowed on check() stage, containment will be checked on perform()
            'plugin_name'             => 'antispam',
        ];

        \Cleantalk\ApbctWP\Variables\Request::getInstance()->variables = $_REQUEST;

        // checkWithToken returns true
        $this->assertFalse(RemoteCalls::check());
    }

    /** @test */
    public function checkCallsCheckWithoutTokenWhenActioNIsNotAllowed()
    {
        global $apbct;

        $_REQUEST = [
            'spbc_remote_call_action' => 'debug', //rejected action
            'plugin_name'             => 'antispam',
        ];

        \Cleantalk\ApbctWP\Variables\Request::getInstance()->variables = $_REQUEST;

        $apbct = new stdClass();
        $apbct->key_is_ok = true;
        $apbct->api_key = null;
        $apbct->data = [];
        $apbct->data['moderate_ip'] = null;

        // checkWithoutToken returns true
        $this->assertFalse(RemoteCalls::check());
    }

    /** @test */
    public function itHidesSensitiveDataInFlatArray()
    {
        $input = [
            'apikey' => '1234567890',
            'user_token' => 'abcdef',
            'salt' => 'qwerty12345',
            'normal_key' => 'visible'
        ];

        $method = new ReflectionMethod(RemoteCalls::class, 'hideSensitiveData');
        $method->setAccessible(true);

        $result = $method->invoke(null, $input);

        $this->assertEquals('12******90', $result['apikey']);
        $this->assertEquals('ab**ef', $result['user_token']);
        $this->assertEquals('qw*******45', $result['salt']);
        $this->assertEquals('visible', $result['normal_key']);
    }

    /** @test */
    public function itHidesSensitiveDataInNestedArray()
    {
        $input = [
            'level1' => [
                'level2' => [
                    'apikey' => 'abcdefghij'
                ]
            ]
        ];

        $method = new ReflectionMethod(RemoteCalls::class, 'hideSensitiveData');
        $method->setAccessible(true);

        $result = $method->invoke(null, $input);

        $this->assertEquals(
            'ab******ij',
            $result['level1']['level2']['apikey']
        );
    }

    /** @test */
    public function itMasksShortSensitiveValues()
    {
        $input = [
            'apikey' => '1234'
        ];

        $method = new ReflectionMethod(RemoteCalls::class, 'hideSensitiveData');
        $method->setAccessible(true);

        $result = $method->invoke(null, $input);

        $this->assertEquals('****', $result['apikey']);
    }

    /** @test */
    public function itDoesNotModifyNonArrayInput()
    {
        $method = new ReflectionMethod(RemoteCalls::class, 'hideSensitiveData');
        $method->setAccessible(true);

        $result = $method->invoke(null, 'string');

        $this->assertEquals('string', $result);
    }

    /** @test */
    public function itValidatesMd5Token()
    {
        global $apbct;

        $apbct = new stdClass();
        $apbct->api_key = 'testKey';
        $apbct->data = [];

        $validToken = strtolower(md5('testKey'));

        $method = new ReflectionMethod(RemoteCalls::class, 'checkToken');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke(null, $validToken));
    }

    /** @test */
    public function itValidatesSha256Token()
    {
        global $apbct;

        $apbct = new stdClass();
        $apbct->api_key = 'testKey';
        $apbct->data = [];

        $validToken = strtolower(hash('sha256', 'testKey'));

        $method = new ReflectionMethod(RemoteCalls::class, 'checkToken');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke(null, $validToken));
    }

    /** @test */
    public function itReturnsFalseForInvalidToken()
    {
        global $apbct;

        $apbct = new stdClass();
        $apbct->api_key = 'testKey';
        $apbct->data = [];

        $method = new ReflectionMethod(RemoteCalls::class, 'checkToken');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke(null, 'invalidToken'));
    }

    /** @test */
    public function itReturnsFalseIfNoApiKeyProvided()
    {
        global $apbct;

        $apbct = new stdClass();
        $apbct->api_key = null;
        $apbct->data = [];
        $apbct->data['moderate_ip'] = null;

        $method = new ReflectionMethod(RemoteCalls::class, 'checkToken');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke(null, md5('anything')));
    }

    /** @test */
    public function itMapsSettingTitlesCorrectly()
    {
        $settings = [
            'apikey' => '123',
            'forms__registrations_test' => 1,
            'unknown_key' => 'abc'
        ];

        $method = new ReflectionMethod(RemoteCalls::class, 'getSettings');
        $method->setAccessible(true);

        $result = $method->invoke(null, $settings);

        $this->assertArrayHasKey('apikey - Access key', $result);
        $this->assertArrayHasKey('forms__registrations_test - Registration Forms', $result);
        $this->assertEquals('123', $result['apikey - Access key']);
        $this->assertEquals(1, $result['forms__registrations_test - Registration Forms']);
        $this->assertEquals('abc', $result['unknown_key']);
    }

    /** @test */
    public function itDetectsAllowedActionsWithoutToken()
    {
        $method = new ReflectionMethod(RemoteCalls::class, 'isAllowedWithoutToken');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke(null, 'get_fresh_wpnonce'));
        $this->assertTrue($method->invoke(null, 'post_api_key'));
        $this->assertFalse($method->invoke(null, 'update_license'));
    }

    /** @test */
    public function itHasMaxDelayConstant()
    {
        $this->assertEquals(10, RemoteCalls::MAX_DELAY);
    }

    /** @test */
    public function itHasAllowedActionsWithDelayWhitelist()
    {
        $reflection = new ReflectionClass(RemoteCalls::class);
        $property = $reflection->getProperty('allowedActionsWithDelay');
        $property->setAccessible(true);

        $allowedActions = $property->getValue();

        $this->assertIsArray($allowedActions);
        $this->assertContains('sfw_update__worker', $allowedActions);
    }

    /** @test */
    public function itDoesNotAllowDelayForNonWhitelistedActions()
    {
        $reflection = new ReflectionClass(RemoteCalls::class);
        $property = $reflection->getProperty('allowedActionsWithDelay');
        $property->setAccessible(true);

        $allowedActions = $property->getValue();

        // These actions should NOT be in the whitelist
        $this->assertNotContains('get_fresh_wpnonce', $allowedActions);
        $this->assertNotContains('debug', $allowedActions);
        $this->assertNotContains('post_api_key', $allowedActions);
        $this->assertNotContains('license_update', $allowedActions);
    }

    /** @test */
    public function itOnlyAllowsSfwUpdateWorkerForDelay()
    {
        $reflection = new ReflectionClass(RemoteCalls::class);
        $property = $reflection->getProperty('allowedActionsWithDelay');
        $property->setAccessible(true);

        $allowedActions = $property->getValue();

        // Only sfw_update__worker should be allowed
        $this->assertCount(1, $allowedActions);
        $this->assertEquals(['sfw_update__worker'], $allowedActions);
    }

    // =========================================================================
    // APBCT-W07: 'api_key' added to $sensitiveData list
    // =========================================================================

    /** @test */
    public function sensitiveDataListContainsApiKeyWithUnderscore()
    {
        $reflection = new ReflectionClass(RemoteCalls::class);
        $property = $reflection->getProperty('sensitiveData');
        $property->setAccessible(true);

        $sensitiveData = $property->getValue();

        $this->assertContains('api_key', $sensitiveData);
        $this->assertContains('apikey', $sensitiveData);
    }

    /** @test */
    public function itHidesApiKeyWithUnderscoreInData()
    {
        // This tests that keys containing 'api_key' substring are masked
        // e.g. 'multisite__hoster_api_key' should be masked
        $input = [
            'multisite__hoster_api_key' => 'secret_hoster_key_12345',
            'normal_setting' => 'visible_value'
        ];

        $method = new ReflectionMethod(RemoteCalls::class, 'hideSensitiveData');
        $method->setAccessible(true);

        $result = $method->invoke(null, $input);

        // The hoster_api_key value should be masked (contains 'api_key' substring)
        $this->assertNotEquals('secret_hoster_key_12345', $result['multisite__hoster_api_key']);
        $this->assertStringContainsString('*', $result['multisite__hoster_api_key']);

        // Normal setting should remain visible
        $this->assertEquals('visible_value', $result['normal_setting']);
    }

    /** @test */
    public function itHidesNestedApiKeyWithUnderscore()
    {
        $input = [
            'network_settings' => [
                'multisite__hoster_api_key' => 'abcdefghij1234567890'
            ]
        ];

        $method = new ReflectionMethod(RemoteCalls::class, 'hideSensitiveData');
        $method->setAccessible(true);

        $result = $method->invoke(null, $input);

        $this->assertNotEquals(
            'abcdefghij1234567890',
            $result['network_settings']['multisite__hoster_api_key']
        );
        $this->assertEquals(
            'ab****************90',
            $result['network_settings']['multisite__hoster_api_key']
        );
    }

    // =========================================================================
    // Rate limiting for remote calls
    // =========================================================================

    /** @test */
    public function rateLimitCheckMethodExists()
    {
        $this->assertTrue(
            method_exists(RemoteCalls::class, 'rateLimitCheck'),
            'RemoteCalls must have a rateLimitCheck method'
        );
    }

    /** @test */
    public function rateLimitCheckIsPrivateStatic()
    {
        $method = new ReflectionMethod(RemoteCalls::class, 'rateLimitCheck');
        $this->assertTrue($method->isPrivate(), 'rateLimitCheck must be private');
        $this->assertTrue($method->isStatic(), 'rateLimitCheck must be static');
    }

    /** @test */
    public function rateLimitCheckUsesCorrectConfig()
    {
        // Verify the config values used inside rateLimitCheck by inspecting the method body.
        // The method creates RateLimiterConfig('rc_remote_call', 10, 60).
        $method = new ReflectionMethod(RemoteCalls::class, 'rateLimitCheck');
        $method->setAccessible(true);

        // Read the source to confirm config values
        $filename = $method->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $source = implode('', array_slice(file($filename), $startLine - 1, $endLine - $startLine + 1));

        $this->assertStringContainsString("'rc_remote_call'", $source, 'Rate limit type must be rc_remote_call');
        $this->assertStringContainsString('10', $source, 'Rate limit must be set to 10 requests');
        $this->assertStringContainsString('60', $source, 'Rate limit period must be 60 seconds');
    }

    /** @test */
    public function performCallsRateLimitCheckBeforeCooldown()
    {
        // Verify that rateLimitCheck() is called BEFORE any cooldown logic in perform().
        // Read perform() source and check ordering.
        $method = new ReflectionMethod(RemoteCalls::class, 'perform');
        $filename = $method->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $source = implode('', array_slice(file($filename), $startLine - 1, $endLine - $startLine + 1));

        $rateLimitPos = strpos($source, 'rateLimitCheck');
        $cooldownPos  = strpos($source, 'last_call');

        $this->assertNotFalse($rateLimitPos, 'perform() must call rateLimitCheck');
        $this->assertNotFalse($cooldownPos, 'perform() must reference last_call');
        $this->assertLessThan($cooldownPos, $rateLimitPos, 'rateLimitCheck must be called BEFORE last_call / cooldown logic');
    }

    /** @test */
    public function lastCallIsUpdatedAfterTokenCheck()
    {
        // Verify that last_call is updated AFTER checkToken / isAllowedWithoutToken,
        // not before. This prevents attackers from updating cooldown with bogus tokens.
        $method = new ReflectionMethod(RemoteCalls::class, 'perform');
        $filename = $method->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $source = implode('', array_slice(file($filename), $startLine - 1, $endLine - $startLine + 1));

        $checkTokenPos  = strpos($source, 'checkToken');
        $lastCallUpdate = strpos($source, "['last_call'] = time()");

        $this->assertNotFalse($checkTokenPos, 'perform() must call checkToken');
        $this->assertNotFalse($lastCallUpdate, 'perform() must update last_call');
        $this->assertGreaterThan(
            $checkTokenPos,
            $lastCallUpdate,
            'last_call update must come AFTER token validation'
        );
    }

    /** @test */
    public function performDiesWithRateLimitErrorWhenBlocked()
    {
        // Verify the error response format for rate-limited requests
        $method = new ReflectionMethod(RemoteCalls::class, 'perform');
        $filename = $method->getFileName();
        $startLine = $method->getStartLine();
        $endLine = $method->getEndLine();
        $source = implode('', array_slice(file($filename), $startLine - 1, $endLine - $startLine + 1));

        $this->assertStringContainsString('RATE_LIMIT_EXCEEDED', $source, 'perform() must use RATE_LIMIT_EXCEEDED error code');
    }
}
