<?php

use Cleantalk\ApbctWP\State;

class TestCspNonce extends \PHPUnit\Framework\TestCase
{
    /** @var mixed */
    private $original_apbct;

    protected function setUp(): void
    {
        parent::setUp();
        global $apbct;
        $this->original_apbct = $apbct;
        $apbct = new State('cleantalk', array('settings', 'data', 'errors', 'remote_calls', 'stats', 'fw_stats'));
    }

    protected function tearDown(): void
    {
        remove_all_filters('apbct_csp_nonce');
        global $apbct;
        $apbct = $this->original_apbct;
        parent::tearDown();
    }

    public function testInlineScriptTagWithoutNonce()
    {
        $tag = apbct_get_inline_script_tag('var test = 1;');

        $this->assertStringContainsString('var test = 1;', $tag);
        $this->assertStringNotContainsString('nonce=', $tag);
    }

    public function testInlineScriptTagWithNonceFilter()
    {
        add_filter('apbct_csp_nonce', function () {
            return 'test-nonce-value';
        });

        $tag = apbct_get_inline_script_tag('var test = 1;');

        $this->assertStringContainsString('nonce="test-nonce-value"', $tag);
    }

    public function testLocalizeScriptsIncludeNonceWhenFilterIsSet()
    {
        add_filter('apbct_csp_nonce', function () {
            return 'localize-nonce';
        });

        $functions_tag = \Cleantalk\ApbctWP\Localize\CtPublicFunctionsLocalize::getCode();
        $public_tag    = \Cleantalk\ApbctWP\Localize\CtPublicLocalize::getCode();

        $this->assertStringContainsString('nonce="localize-nonce"', $functions_tag);
        $this->assertStringContainsString('nonce="localize-nonce"', $public_tag);
    }
}
