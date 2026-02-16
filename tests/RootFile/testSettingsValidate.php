<?php

use PHPUnit\Framework\TestCase;
use Cleantalk\ApbctWP\State;

class ApbctSettingsTest extends TestCase
{
    private $list_to_keep;
    private $saved_state;

    protected function setUp(): void
    {
        global $apbct;
        $this->saved_state = $apbct;
        $apbct = new State('cleantalk', array('settings', 'data', 'errors', 'remote_calls', 'stats', 'fw_stats'));
        $this->list_to_keep = [
            'data__email_decoder_encode_email_addresses',
            'data__email_decoder_encode_phone_numbers',
            'data__email_decoder_obfuscation_mode',
            'data__email_decoder_obfuscation_custom_text',
            'data__email_decoder_buffer',
        ];

        $apbct->default_settings = [
            'test_key' => 'string_value',
            'test_int' => 42,
            'test_bool' => true,
            'test_array' => [1, 2],
            'test_null' => null,
        ];

        $apbct->default_network_settings = [
            'network_key' => 'net_string',
            'network_int' => 100,
            'network_bool' => false,
        ];

        $apbct->settings = array_merge([
            'existing_key' => 'from_state',
            'apikey' => 'secret_key',
            'data__email_decoder_encode_email_addresses' => '1',
            'sfw__enabled' => '1',
        ], (array)$apbct->settings);

        $apbct->network_settings = [
            'multisite__allow_custom_settings' => false,
        ];

        $apbct->option_prefix = 'mock_apbct_';
        $apbct->allow_custom_key = false;
        $apbct->saveSettings();
    }

    protected function tearDown(): void
    {
        global $apbct;
        $apbct = $this->saved_state;
    }

    public function test_apbct_settings__fill_settings_for_wpms_subsite_does_not_fill()
    {
        global $apbct;

        // Allow custom settings, so no filling
        $apbct->network_settings['multisite__allow_custom_settings'] = true;
        $incoming = ['some_key' => 'value'];
        $result = apbct_settings__fill_settings_for_wpms_subsite($incoming, $apbct);

        $this->assertEquals($incoming, $result);
        $this->assertArrayNotHasKey('existing_key', $result);
    }

    public function test_apbct_settings__keep_settings_state_values_adds_missing()
    {
        global $apbct;

        $incoming = [];
        $result = apbct_settings__keep_settings_state_values($incoming, $apbct->settings, $this->list_to_keep);

        $this->assertArrayHasKey('data__email_decoder_encode_email_addresses', $result);
        $this->assertEquals('1', $result['data__email_decoder_encode_email_addresses']);
    }

    public function test_apbct_settings__keep_settings_state_values_preserves_existing()
    {
        global $apbct;

        $incoming = ['data__email_decoder_encode_email_addresses' => 'new_value'];
        $result = apbct_settings__keep_settings_state_values($incoming, $apbct->settings, $this->list_to_keep);

        $this->assertEquals('new_value', $result['data__email_decoder_encode_email_addresses']); // Not overwritten
    }

    public function test_apbct_settings__keep_settings_state_values_no_keep_list_match()
    {
        global $apbct;

        $incoming = [];
        $result = apbct_settings__keep_settings_state_values($incoming, $apbct->settings, ['non_matching_key']);

        $this->assertArrayNotHasKey('data__email_decoder_encode_email_addresses', $result); // Only keeps matching list
    }

    public function test_apbct_settings__set_missed_settings_adds_missing_with_types()
    {
        global $apbct;

        $incoming = ['test_key' => 'overridden']; // Existing, preserved
        $result = apbct_settings__set_missed_settings($incoming, $apbct->default_settings);

        $this->assertArrayHasKey('test_key', $result);
        $this->assertEquals('overridden', $result['test_key']); // Preserved
        $this->assertArrayHasKey('test_int', $result);
        $this->assertIsInt($result['test_int']); // Type preserved as int (0 if null, but settype on null -> 0, but test expects int)
        $this->assertEquals(0, $result['test_int']); // settype(null, 'integer') -> 0
        $this->assertArrayHasKey('test_bool', $result);
        $this->assertIsBool($result['test_bool']); // false
        $this->assertArrayHasKey('test_array', $result);
        $this->assertIsArray($result['test_array']); // empty array
        $this->assertArrayHasKey('test_null', $result);
        $this->assertNull($result['test_null']); // null remains null
    }

    public function test_apbct_settings__set_missed_settings_all_present()
    {
        global $apbct;

        $incoming = $apbct->default_settings;
        $result = apbct_settings__set_missed_settings($incoming, $apbct->default_settings);

        $this->assertEquals($incoming, $result); // No changes
    }

    public function test_apbct_settings__validate_full_flow_no_fill()
    {
        global $apbct;

        $incoming = ['some_key' => 'value']; // Partial
        // Set mocks: assume no fill trigger (e.g., main site or allow custom)
        $apbct->network_settings['multisite__allow_custom_settings'] = true; // No fill

        $result = apbct_settings__validate($incoming);

        // Keep adds from list if missing (none here)
        // Set missed: adds all defaults with types (null -> typed empty)
        $this->assertArrayHasKey('some_key', $result); // Preserved
        $this->assertArrayHasKey('test_key', $result);
        $this->assertEquals('', $result['test_key']); // settype(null, 'string') -> ''
        $this->assertArrayHasKey('test_int', $result);
        $this->assertEquals(0, $result['test_int']); // int 0
        $this->assertArrayHasKey('data__email_decoder_encode_email_addresses', $result);
        $this->assertEquals('1', $result['data__email_decoder_encode_email_addresses']); // From keep
        // Network: from stored (empty) or default
        $this->assertArrayHasKey('network_key', $result);
        $this->assertEquals('net_string', $result['network_key']);
    }

    public function test_apbct_settings__validate_with_fill_trigger()
    {
        global $apbct;

        $incoming = []; // Empty to trigger fill
        // Trigger fill: !allow_custom, empty template, !main_site, correct filter
        $apbct->network_settings['multisite__allow_custom_settings'] = false;
        // Assume mocks for is_main_site()=false, current_filter()='sanitize_option_cleantalk_settings', empty template

        $result = apbct_settings__validate($incoming);

        // Then keep adds specific
        $this->assertArrayHasKey('data__email_decoder_encode_email_addresses', $result);
        // Set missed adds rest with types
        $this->assertArrayHasKey('test_int', $result);
        $this->assertEquals(0, $result['test_int']);
        // Network as above
    }
}
