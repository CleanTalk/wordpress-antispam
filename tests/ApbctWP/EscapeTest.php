<?php

use Cleantalk\ApbctWP\Escape;
use PHPUnit\Framework\TestCase;

class EscapeTest extends TestCase
{
    public function testEscKsesPresetKeepsWhitelistedChangelogMarkup()
    {
        $html = '<details open><summary><strong>Title</strong></summary>'
            . '<ul style="list-style: disc;padding-left: 20px"><li>Item one</li></ul>'
            . '</details>';

        $result = Escape::escKsesPreset($html, 'apbct_update_changelog_notice');

        $this->assertSame($html, $result);
    }

    public function testEscKsesPresetStripsDisallowedTags()
    {
        $html = '<script>alert(1)</script><p>paragraph</p><ul><li>ok</li></ul>';

        $result = Escape::escKsesPreset($html, 'apbct_update_changelog_notice');

        // <p> was intentionally removed from the whitelist (it caused the
        // ".update-message p:before" update icon to be duplicated).
        $this->assertStringNotContainsString('<p>', $result);
        $this->assertStringNotContainsString('<script', $result);
        $this->assertStringContainsString('<ul>', $result);
        $this->assertStringContainsString('<li>ok</li>', $result);
    }

    public function testEscKsesPresetKeepsListStyleDeclaration()
    {
        // wp_kses filters "style" attribute values against the safe_style_css
        // whitelist. "list-style" must survive it, otherwise the bullets
        // disappear on admin themes that reset "ul { list-style: none }".
        $html = '<ul style="list-style: disc; padding-left: 20px;"><li>a</li></ul>';

        $result = Escape::escKsesPreset($html, 'apbct_update_changelog_notice');

        $this->assertStringContainsString('list-style: disc', $result);
        $this->assertStringContainsString('padding-left: 20px', $result);
    }
}
