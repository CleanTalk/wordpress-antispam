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
}
