<?php

use Cleantalk\Common\AbstractUpdateChangelogNotice;
use PHPUnit\Framework\TestCase;

/**
 * Minimal, no-frills concrete implementation used only to reach the
 * protected methods under test.
 */
class UpdateChangelogNoticeStub extends AbstractUpdateChangelogNotice
{
    protected function getPluginFile()
    {
        return 'cleantalk-spam-protect/cleantalk.php';
    }

    protected function getNoticeHtml($version, $changelog_html)
    {
        return '<details open><summary>' . $version . '</summary>' . $changelog_html . '</details>';
    }

    protected function escapeChangelogHtml($html)
    {
        // No WP kses whitelist here on purpose: keeps the test focused on
        // this class's own logic (normalization / disc style injection).
        return $html;
    }
}

class AbstractUpdateChangelogNoticeTest extends TestCase
{
    /**
     * @return UpdateChangelogNoticeStub
     */
    private function makeInstance()
    {
        return new UpdateChangelogNoticeStub();
    }

    /**
     * @return \ReflectionMethod
     */
    private function getMethod($name)
    {
        $method = new \ReflectionMethod(UpdateChangelogNoticeStub::class, $name);
        $method->setAccessible(true);

        return $method;
    }

    public function testNormalizeChangelogBodyConvertsFlatBrSeparatedLinesToList()
    {
        $body = "\nNew. Feature added.<br />\nFix. Bug fixed.<br />\nUpd. Something updated.";

        $result = $this->getMethod('normalizeChangelogBody')->invoke($this->makeInstance(), $body);

        $this->assertSame(
            '<ul><li>New. Feature added.</li><li>Fix. Bug fixed.</li><li>Upd. Something updated.</li></ul>',
            $result
        );
    }

    public function testNormalizeChangelogBodyLeavesReadyMadeListUntouched()
    {
        $body = "\n<ul>\n<li>Fix. Code. Re-minify JS.</li>\n<li>Upd. Scan. Improve UX.</li>\n</ul>\n";

        $result = $this->getMethod('normalizeChangelogBody')->invoke($this->makeInstance(), $body);

        $this->assertSame($body, $result);
    }

    public function testNormalizeChangelogBodyReturnsEmptyStringForBlankInput()
    {
        $result = $this->getMethod('normalizeChangelogBody')->invoke($this->makeInstance(), "  \n  ");

        $this->assertSame('', $result);
    }

    public function testForceDiscListStyleAddsStyleWhenMissing()
    {
        $result = $this->getMethod('forceDiscListStyle')->invoke($this->makeInstance(), '<ul><li>a</li></ul>');

        $this->assertSame('<ul style="list-style-type: disc; padding-left: 20px;"><li>a</li></ul>', $result);
    }

    public function testForceDiscListStyleMergesWithExistingStyle()
    {
        $result = $this->getMethod('forceDiscListStyle')->invoke(
            $this->makeInstance(),
            '<ul style="color:red"><li>a</li></ul>'
        );

        $this->assertSame(
            '<ul style="color:red; list-style-type: disc; padding-left: 20px;"><li>a</li></ul>',
            $result
        );
    }

    public function testForceDiscListStyleIgnoresContentWithoutUl()
    {
        $html = '<p>no lists here</p>';

        $result = $this->getMethod('forceDiscListStyle')->invoke($this->makeInstance(), $html);

        $this->assertSame($html, $result);
    }

    public function testInjectNoticeReturnsRowUnchangedWhenNoUpdateMessageFound()
    {
        $row = '<div class="something-else"></div>';

        $result = $this->getMethod('injectNotice')->invoke(
            $this->makeInstance(),
            $row,
            'cleantalk-spam-protect/cleantalk.php'
        );

        $this->assertSame($row, $result);
    }

    public function testInjectNoticeReturnsRowUnchangedWhenNoUpdateIsOffered()
    {
        // No update_plugins transient is set, so getOfferedVersion() yields ''.
        $row = '<div class="update-message notice"><p>Update available.</p></div>';

        $result = $this->getMethod('injectNotice')->invoke(
            $this->makeInstance(),
            $row,
            'cleantalk-spam-protect/cleantalk.php'
        );

        $this->assertSame($row, $result);
    }
}
