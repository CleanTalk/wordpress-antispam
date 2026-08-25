<?php

class TestCspNonce extends \PHPUnit\Framework\TestCase
{
    protected function tearDown(): void
    {
        remove_all_filters('apbct_csp_nonce');
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
