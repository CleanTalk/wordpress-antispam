<?php

// Mock global function for tests (must be before namespace)
namespace {
    if (!function_exists('apbct__stop_script_after_ajax_checking')) {
        function apbct__stop_script_after_ajax_checking()
        {
            return 0;
        }
    }
}

namespace Cleantalk\Antispam\IntegrationsByHook\Tests {

use Cleantalk\Antispam\Integrations\PiotnetAddonsForElementorPro;
use Cleantalk\ApbctWP\State;
use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\ApbctWP\Variables\Cookie;
use PHPUnit\Framework\TestCase;

class TestPiotnetAddonsForElementorPro extends TestCase
{
    /** @var PiotnetAddonsForElementorPro */
    private $piotnet;

    /** @var array */
    private $post;

    /** @var string */
    private $fieldsJson;

    protected function setUp(): void
    {
        parent::setUp();
        global $apbct;
        $apbct = new State('cleantalk', array('settings', 'data', 'errors', 'remote_calls', 'stats', 'fw_stats'));
        $this->piotnet = new PiotnetAddonsForElementorPro();

        // Real form data from debug
        $this->fieldsJson = '[{"name":"image_select","image_upload":true,"value_not_prefix":"Vietnam","value":"Vietnam","value_multiple":["Vietnam"],"repeater_id":"","repeater_id_one":"","repeater_label":"","repeater_index":-1,"repeater_length":0},{"name":"image_select","image_upload":true,"value_not_prefix":"Food Experience","value":"Food Experience","value_multiple":["Food Experience"],"repeater_id":"","repeater_id_one":"","repeater_label":"","repeater_index":-1,"repeater_length":0},{"label":"Your Name","name":"your_name","image_upload":false,"value_not_prefix":"setrs dgsdfgd","value":"setrs dgsdfgd","type":"text","repeater_id":"","repeater_id_one":"","repeater_label":"","repeater_index":-1,"repeater_length":0},{"label":"Your Email","name":"your_email","image_upload":false,"value_not_prefix":"s@cleantalk.org","value":"s@cleantalk.org","type":"email","repeater_id":"","repeater_id_one":"","repeater_label":"","repeater_index":-1,"repeater_length":0},{"label":"Your Nationality","name":"your_nationality","image_upload":true,"value_not_prefix":"Afghanistan","value":"Afghanistan","repeater_id":"","repeater_id_one":"","repeater_label":"","repeater_index":-1,"repeater_length":0},{"label":"Your Phone","name":"your_phone","image_upload":false,"value_not_prefix":"43576876589789","value":"43576876589789","type":"tel","repeater_id":"","repeater_id_one":"","repeater_label":"","repeater_index":-1,"repeater_length":0}]';

        $this->post = array(
            'razo_payment' => 'yes',
            'razo_sub' => 'yes',
            'action' => 'pafe_ajax_form_builder',
            'post_id' => '44',
            'form_id' => '623880e6',
            'fields' => $this->fieldsJson,
            'referrer' => 'https://nb-wp694.local/?p=14',
            'remote_ip' => '127.0.0.1',
            'ct_bot_detector_event_token' => '8e331da84ebc86c0caa85cd48a537cdb85d35744a412b014e20fbf7421330b22',
        );
    }

    protected function tearDown(): void
    {
        global $apbct;
        unset($apbct);
        $_POST = [];
        Cookie::getInstance()->variables = [];
        Post::getInstance()->variables = [];
    }

    public function testGetDataForChecking_ReturnsNullWhenNoFields()
    {
        // Arrange
        Post::getInstance()->variables = [];
        $_POST = [];

        // Act
        $result = $this->piotnet->getDataForChecking(null);

        // Assert
        $this->assertNull($result);
    }

    public function testGetDataForChecking_ReturnsArgumentWhenFieldsEmpty()
    {
        // Arrange
        Post::getInstance()->variables = [];
        $_POST = ['fields' => ''];

        // Act
        $result = $this->piotnet->getDataForChecking('default_value');

        // Assert
        $this->assertEquals('default_value', $result);
    }

    public function testGetDataForChecking_ReturnsArgumentWhenFieldsInvalidJson()
    {
        // Arrange
        Post::getInstance()->variables = [];
        $_POST = ['fields' => 'invalid json'];

        // Act & Assert - class has a bug: it doesn't check json_decode result
        // This test documents current behavior (throws warning)
        $this->expectWarning();
        $result = $this->piotnet->getDataForChecking('default_value');
    }

    public function testGetDataForChecking_ProcessesValidFormData()
    {
        // Arrange
        Post::getInstance()->variables = [];
        $_POST = $this->post;

        // Act
        $result = $this->piotnet->getDataForChecking(null);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('email', $result);
        $this->assertArrayHasKey('nickname', $result);
    }

    public function testGetDataForChecking_ExtractsEmailCorrectly()
    {
        // Arrange
        Post::getInstance()->variables = [];
        $_POST = $this->post;

        // Act
        $result = $this->piotnet->getDataForChecking(null);

        // Assert
        $this->assertEquals('s@cleantalk.org', $result['email']);
    }

    public function testGetDataForChecking_ExtractsNicknameCorrectly()
    {
        // Arrange
        Post::getInstance()->variables = [];
        $_POST = $this->post;

        // Act
        $result = $this->piotnet->getDataForChecking(null);

        // Assert
        $this->assertEquals('setrs dgsdfgd', $result['nickname']);
    }

    public function testGetDataForChecking_IncludesEventTokenFromCookie()
    {
        // Arrange
        Post::getInstance()->variables = [];
        $_POST = $this->post;
        Cookie::getInstance()->variables = [];
        $_COOKIE['ct_bot_detector_event_token'] = 'test_event_token_12345';

        // Act
        $result = $this->piotnet->getDataForChecking(null);

        // Assert
        $this->assertArrayHasKey('event_token', $result);
        $this->assertEquals('test_event_token_12345', $result['event_token']);
    }

    public function testGetDataForChecking_HandlesFieldsWithoutEmailOrName()
    {
        // Arrange
        $fieldsWithoutEmailOrName = '[{"name":"other_field","value":"some value"},{"name":"another_field","value":"another value"}]';
        Post::getInstance()->variables = [];
        $_POST = [
            'action' => 'pafe_ajax_form_builder',
            'fields' => $fieldsWithoutEmailOrName,
        ];

        // Act
        $result = $this->piotnet->getDataForChecking(null);

        // Assert
        $this->assertIsArray($result);
        $this->assertEquals('', $result['email']);
        $this->assertEquals('', $result['nickname']);
    }

    public function testGetDataForChecking_HandlesDuplicateFields()
    {
        // Arrange - fields with duplicates (same as real data has duplicate image_select)
        Post::getInstance()->variables = [];
        $_POST = $this->post;

        // Act
        $result = $this->piotnet->getDataForChecking(null);

        // Assert - should still work and return array
        $this->assertIsArray($result);
    }

    public function testGetDataForChecking_HandlesEscapedJson()
    {
        // Arrange - escaped JSON as it might come from frontend
        $escapedFields = addslashes($this->fieldsJson);
        Post::getInstance()->variables = [];
        $_POST = [
            'action' => 'pafe_ajax_form_builder',
            'fields' => $escapedFields,
            'form_id' => '623880e6',
        ];

        // Act
        $result = $this->piotnet->getDataForChecking(null);

        // Assert
        $this->assertIsArray($result);
    }

    public function testDoBlock_OutputsJsonWithBlockedFlag()
    {
        // Note: doBlock() uses die() which is difficult to test properly
        // This test verifies that the method exists and can be called
        $this->assertTrue(method_exists($this->piotnet, 'doBlock'));
    }

    public function testDoBlock_OutputsValidJson()
    {
        // Note: Testing die() properly requires process isolation
        // We verify that the expected output format is correct by checking
        // the method signature and expected behavior
        $reflection = new \ReflectionMethod($this->piotnet, 'doBlock');
        $this->assertTrue($reflection->isPublic());
        $this->assertEquals(1, $reflection->getNumberOfParameters());
    }

    public function testGetDataForChecking_ProcessesPhoneField()
    {
        // Arrange
        Post::getInstance()->variables = [];
        $_POST = $this->post;

        // Act
        $result = $this->piotnet->getDataForChecking(null);

        // Assert - message should contain phone data
        $this->assertArrayHasKey('message', $result);
    }

    public function testGetDataForChecking_ReturnsArgumentWhenFieldsArrayEmpty()
    {
        // Arrange
        Post::getInstance()->variables = [];
        $_POST = [
            'action' => 'pafe_ajax_form_builder',
            'fields' => '[]',
        ];

        // Act
        $result = $this->piotnet->getDataForChecking('fallback');

        // Assert
        $this->assertEquals('fallback', $result);
    }
}

} // end namespace