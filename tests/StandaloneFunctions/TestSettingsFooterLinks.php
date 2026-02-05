<?php

require_once dirname(__DIR__, 2) . '/templates/apbct_settings__footer.php';

use PHPUnit\Framework\TestCase;

class TestSettingsFooterLinks extends TestCase
{
    /**
     * JSON structure for $block2_links
     */
    public function testBlock2LinksJsonStructureIsValid(): void
    {
        $function = new ReflectionFunction('apbct_settings__footer');

        ob_start();
        $function->invoke();
        $output = ob_get_clean();

        preg_match('/blockName:\s*["\']Recommended plugins["\'][\s\S]*?links:\s*(\[[\s\S]*?\])\s*,?\s*}/s', $output, $matches);

        if (isset($matches[1])) {
            $json = $matches[1];

            $decoded = json_decode($json, true);
            $this->assertIsArray($decoded, 'Must be a valid JSON');

            $this->assertArrayHasKey('text', $decoded[0]);
            $this->assertArrayHasKey('url', $decoded[0]);
            $this->assertEquals('Security plugin by CleanTalk', $decoded[0]['text']);

            $this->assertArrayHasKey('text', $decoded[1]);
            $this->assertArrayHasKey('url', $decoded[1]);
            $this->assertStringContainsString('Gravity Add-On', $decoded[1]['text']);
        } else {
            $this->fail('JSON for Recommended plugins not found');
        }
    }
}
