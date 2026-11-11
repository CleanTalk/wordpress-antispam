<?php

use Cleantalk\Common\API;
use PHPUnit\Framework\TestCase;

/**
 * Captures the request instead of sending it.
 */
class ApiPrivateListAddSpy extends \Cleantalk\ApbctWP\API
{
    public static $last_request = array();

    public static function sendRequest($data, $timeout = 10)
    {
        static::$last_request = $data;
        return true;
    }
}

class TestApiPrivateListAdd extends TestCase
{
    public function testSuccessResponseWrappedInDataIsAccepted()
    {
        // Real API answer (task 60535): records are inside "data"
        $json = '{"data":{"records":[{"record_id":70128457,"record":"203.0.113.77\/32","service_id":1273911,'
            . '"service_type":"spamfirewall","status":"allow","operation_status":"SUCCESS"}],"spbc":[111016367]}}';

        $this->assertTrue(API::checkResponse($json, null, 'private_list_add'));
    }

    public function testFailedOperationIsAnError()
    {
        $json = '{"data":{"records":[{"record":"203.0.113.77\/32","operation_status":"FAILED","operation_message":"x"}]}}';

        $this->assertSame(array('error' => 'COULDNT_ADD_WL_IP'), API::checkResponse($json, null, 'private_list_add'));
    }

    public function testUserTokenErrorIsAnError()
    {
        $json = '{"data":{"result":"FAIL","notice":"User token not found","operation_code":"51"}}';

        $this->assertSame(array('error' => 'COULDNT_ADD_WL_IP'), API::checkResponse($json, null, 'private_list_add'));
    }

    public function testAdminWhitelistRequestUsesAuthKeyAndRightOrder()
    {
        ApiPrivateListAddSpy::methodPrivateListAddSfwWl('apikey123', 1273911, '203.0.113.77');
        $request = ApiPrivateListAddSpy::$last_request;

        $this->assertSame('private_list_add', $request['method_name']);
        $this->assertSame('apikey123', $request['auth_key']);
        $this->assertArrayNotHasKey('user_token', $request);
        $this->assertSame(1273911, $request['service_id']);
        $this->assertSame('203.0.113.77', $request['records']);
        $this->assertSame('spamfirewall', $request['service_type']);
        $this->assertSame('allow', $request['status']);
    }
}
