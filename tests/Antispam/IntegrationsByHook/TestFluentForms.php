<?php

namespace Antispam\IntegrationsByHook;

use Cleantalk\Antispam\Integrations\FluentForm;
use Cleantalk\ApbctWP\State;
use PHPUnit\Framework\TestCase;

class TestFluentForms extends TestCase
{
    /** @var FluentForm */
    private $fluentForm;

    protected function setUp(): void
    {
        parent::setUp();
        global $apbct;
        $apbct = new State('cleantalk', array('settings', 'data', 'errors', 'remote_calls', 'stats', 'fw_stats'));
        $this->fluentForm = new FluentForm();
        $this->post = array (
            'ct_bot_detector_event_token' => 'e38086feb53a3e02c9e65631bbe538575cfba5cacac48bb4925776db6a00386e',
            'apbct_visible_fields' => 'eyIwIjp7InZpc2libGVfZmllbGRzIjoibmYtZmllbGQtNS10ZXh0Ym94IGVtYWlsIG5mLWZpZWxkLTciLCJ2aXNpYmxlX2ZpZWxkc19jb3VudCI6MywiaW52aXNpYmxlX2ZpZWxkcyI6Im5mLWZpZWxkLWhwIGN0X2JvdF9kZXRlY3Rvcl9ldmVudF90b2tlbiIsImludmlzaWJsZV9maWVsZHNfY291bnQiOjJ9fQ==',
            'action' => 'fluentform_submit',
            'form_id' => '1',
            'data' => 'ff_ct_form_load_time%3D1770377495%26__fluent_form_embded_post_id%3D38%26_fluentform_1_fluentformnonce%3Db7c8107641%26_wp_http_referer%3D%252F%253Fpage_id%253D38%26names%255Bfirst_name%255D%3DAlexander%26names%255Blast_name%255D%3DG.%26email%3Ds%2540cleantalk.org%26subject%3D%26message%3Dss%26ct_bot_detector_event_token%3D1d6087b3ed1b1944f5e48aea8395ac8e185b125bc0ba4917ad63ecd39b247d56%26apbct_visible_fields%3DeyIwIjp7InZpc2libGVfZmllbGRzIjoibmFtZXNbZmlyc3RfbmFtZV0gbmFtZXNbbGFzdF9uYW1lXSBlbWFpbCBzdWJqZWN0IG1lc3NhZ2UiLCJ2aXNpYmxlX2ZpZWxkc19jb3VudCI6NSwiaW52aXNpYmxlX2ZpZWxkcyI6ImZmX2N0X2Zvcm1fbG9hZF90aW1lIF9fZmx1ZW50X2Zvcm1fZW1iZGVkX3Bvc3RfaWQgX2ZsdWVudGZvcm1fMV9mbHVlbnRmb3Jtbm9uY2UgX3dwX2h0dHBfcmVmZXJlciBjdF9ib3RfZGV0ZWN0b3JfZXZlbnRfdG9rZW4iLCJpbnZpc2libGVfZmllbGRzX2NvdW50Ijo1fX0%253D',
        );
        delete_option('_fluentform_cleantalk_details');
    }

    protected function tearDown(): void
    {
        global $apbct;
        unset($apbct);
        $_POST = [];
        global $fluentformCleantalkExecuted;
        $fluentformCleantalkExecuted = null;
        global $cleantalk_executed;
        $cleantalk_executed = false;
    }

    public function testGetDataForChecking_SkipsOnCleantalkExecuted()
    {
        global $cleantalk_executed;
        $cleantalk_executed = true;
        // Arrange
        $_POST = [];
        $_GET = [];

        // Act & Assert
        $result = $this->fluentForm->getDataForChecking(null);
        $this->assertNull($result);

        $cleantalk_executed = false;
    }

    public function testGetGFAOld_ReturnsDtoWithPostData()
    {
        // Mock apply_filters
        $_POST = $this->post;
        $input_array = apply_filters('apbct__filter_post', $this->post);

        // Act
        $dto = $this->fluentForm->getDataForChecking($input_array);

        // Assert
        $this->assertIsArray($dto);
        $this->assertArrayHasKey('email', $dto);
        $this->assertArrayHasKey('nickname', $dto);
        $this->assertArrayHasKey('message', $dto);
    }

    public function testVendorExcludedByGlobal()
    {
        global $fluentformCleantalkExecuted;
        $fluentformCleantalkExecuted = true;
        // Act
        $skipped = $this->fluentForm->skipDueVendorIntegration();
        $this->assertIsString($skipped);
        $this->assertStringContainsString('FLUENTFORM_VENDOR_ACTIVE_INTEGRATION_EXECUTED', $skipped);
    }

    public function testVendorExcludedByGlobalNegative()
    {
        global $fluentformCleantalkExecuted;
        $fluentformCleantalkExecuted = false;
        // Act
        $skipped = $this->fluentForm->skipDueVendorIntegration();
        $this->assertFalse($skipped);
    }

    public function testVendorExcludedByGlobalUnset()
    {
        // Act
        $skipped = $this->fluentForm->skipDueVendorIntegration();
        $this->assertFalse($skipped);
    }

    public function testVendorExcludedByOption()
    {
        $updated = update_option('_fluentform_cleantalk_details', ['status'=>true], false);
        $this->assertTrue($updated);
        // Act
        $skipped = $this->fluentForm->skipDueVendorIntegration();
        $this->assertIsString($skipped);
        $this->assertStringContainsString('FLUENTFORM_VENDOR_ACTIVE_INTEGRATION_FOUND', $skipped);
    }

    public function testVendorExcludedByOptionNegative()
    {
        $updated = update_option('_fluentform_cleantalk_details', ['status'=>false], false);
        $this->assertTrue($updated);
        // Act
        $skipped = $this->fluentForm->skipDueVendorIntegration();
        $this->assertFalse($skipped);
    }

    public function testVendorExcludedByOptionUnset()
    {
        // Act
        $skipped = $this->fluentForm->skipDueVendorIntegration();
        $this->assertFalse($skipped);
    }
}
