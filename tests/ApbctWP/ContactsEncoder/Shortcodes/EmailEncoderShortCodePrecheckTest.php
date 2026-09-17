<?php

namespace ApbctWP\ContactsEncoder\Shortcodes;

use Cleantalk\ApbctWP\ContactsEncoder\Shortcodes\EncodeContentSC;
use Cleantalk\ApbctWP\ContactsEncoder\Shortcodes\ExcludedEncodeContentSC;
use Cleantalk\Common\ContactsEncoder\Dto\Params;
use PHPUnit\Framework\TestCase;

/**
 * Covers the lightweight strpos() pre-check that guards the heavy shortcode regexes.
 *
 * The pre-check is a pure optimisation, so the tests assert two things:
 *  - content that cannot contain the shortcode is returned untouched (fast path);
 *  - content that does contain the shortcode is still processed exactly as before.
 */
class EmailEncoderShortCodePrecheckTest extends TestCase
{
    /**
     * @var EncodeContentSC
     */
    private $encode_sc;

    /**
     * @var ExcludedEncodeContentSC
     */
    private $exclude_sc;

    protected function setUp(): void
    {
        parent::setUp();

        global $apbct;
        $apbct->api_key = 'testapikey';

        $this->encode_sc  = new EncodeContentSC(new Params());
        $this->exclude_sc = new ExcludedEncodeContentSC();
    }

    /**
     * Call a protected method on the given object.
     *
     * @param object $object
     * @param string $method
     * @param array  $args
     *
     * @return mixed
     */
    private function callProtected($object, $method, array $args)
    {
        $reflection = new \ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($object, $args);
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public function encodeShortcodePrecheckProvider()
    {
        return array(
            'plain text'                 => array('Just a plain sentence with no shortcodes.', false),
            'email without shortcode'    => array('Contact us at test@example.com please.', false),
            'html without shortcode'     => array('<p>Some <a href="#">markup</a> here.</p>', false),
            'other plugin shortcode'     => array('[gallery ids="1,2,3"]', false),
            'similar but different name' => array('[apbct_encode_data_other]x[/apbct_encode_data_other]', true),
            'bare opening tag'           => array('[apbct_encode_data]', true),
            'full shortcode'             => array('[apbct_encode_data]a@b.com[/apbct_encode_data]', true),
            'shortcode with attributes'  => array('[apbct_encode_data mode="blur"]a@b.com[/apbct_encode_data]', true),
        );
    }

    /**
     * @dataProvider encodeShortcodePrecheckProvider
     *
     * @param string $content
     * @param bool   $expected
     */
    public function testContentMayContainShortcodeForEncodeSC($content, $expected): void
    {
        $this->assertSame(
            $expected,
            $this->callProtected($this->encode_sc, 'contentMayContainShortcode', array($content))
        );
    }

    /**
     * The pre-check must reject every non-string and empty value without touching a regex.
     *
     * @return array<string, array{0: mixed}>
     */
    public function nonStringProvider()
    {
        return array(
            'null'         => array(null),
            'empty string' => array(''),
            'false'        => array(false),
            'integer'      => array(10),
            'array'        => array(array('[apbct_encode_data]')),
            'object'       => array(new \stdClass()),
        );
    }

    /**
     * @dataProvider nonStringProvider
     *
     * @param mixed $content
     */
    public function testContentMayContainShortcodeRejectsNonStrings($content): void
    {
        $this->assertFalse(
            $this->callProtected($this->encode_sc, 'contentMayContainShortcode', array($content))
        );
        $this->assertFalse(
            $this->callProtected($this->exclude_sc, 'contentMayContainShortcode', array($content))
        );
    }

    /**
     * Each subclass must match only its own tag.
     */
    public function testPrecheckIsScopedToOwnShortcodeName(): void
    {
        $encode_tag  = '[apbct_encode_data]a@b.com[/apbct_encode_data]';
        $exclude_tag = '[apbct_skip_encoding]a@b.com[/apbct_skip_encoding]';

        $this->assertTrue(
            $this->callProtected($this->encode_sc, 'contentMayContainShortcode', array($encode_tag))
        );
        $this->assertFalse(
            $this->callProtected($this->encode_sc, 'contentMayContainShortcode', array($exclude_tag))
        );

        $this->assertTrue(
            $this->callProtected($this->exclude_sc, 'contentMayContainShortcode', array($exclude_tag))
        );
        $this->assertFalse(
            $this->callProtected($this->exclude_sc, 'contentMayContainShortcode', array($encode_tag))
        );
    }

    /**
     * A closing tag alone is not a valid pair, so the pre-check must skip it.
     */
    public function testPrecheckIgnoresOrphanClosingTag(): void
    {
        $this->assertFalse(
            $this->callProtected($this->encode_sc, 'contentMayContainShortcode', array('text [/apbct_encode_data]'))
        );
    }

    /**
     * Fast path: content without the shortcode must come back byte-identical.
     */
    public function testChangeContentBeforeEncoderModifyReturnsContentWithoutShortcodeUntouched(): void
    {
        $content = '<p>Hello, reach us at test@example.com or [gallery id="2"].</p>';

        $this->assertSame($content, $this->encode_sc->changeContentBeforeEncoderModify($content));
        $this->assertSame($content, $this->exclude_sc->changeContentBeforeEncoderModify($content));
    }

    /**
     * Slow path must still work: the shortcode is replaced by a placeholder.
     */
    public function testChangeContentBeforeEncoderModifyStillProcessesShortcode(): void
    {
        $encode_result = $this->encode_sc->changeContentBeforeEncoderModify(
            'Mail: [apbct_encode_data]a@b.com[/apbct_encode_data]'
        );
        $this->assertStringContainsString('APBCT_SHORT_CODE_INCLUDE', $encode_result);
        $this->assertStringNotContainsString('[apbct_encode_data]', $encode_result);

        $exclude_result = $this->exclude_sc->changeContentBeforeEncoderModify(
            'Mail: [apbct_skip_encoding]a@b.com[/apbct_skip_encoding]'
        );
        $this->assertStringContainsString('APBCT_SHORT_CODE_SKIP', $exclude_result);
        $this->assertStringNotContainsString('[apbct_skip_encoding]', $exclude_result);
    }

    /**
     * WP passes null to get_header/get_footer hooks - the guard must survive it.
     */
    public function testChangeContentBeforeEncoderModifyHandlesNull(): void
    {
        $this->assertNull($this->encode_sc->changeContentBeforeEncoderModify(null));
        $this->assertNull($this->exclude_sc->changeContentBeforeEncoderModify(null));
    }

    /**
     * The "after" pass must short-circuit when there is nothing to restore and no shortcode left.
     */
    public function testChangeContentAfterEncoderModifyShortCircuitsWithoutReplacements(): void
    {
        $this->encode_sc->resetShortcodeReplacements();

        $content = '<p>Nothing to do here, test@example.com.</p>';

        $this->assertSame($content, $this->encode_sc->changeContentAfterEncoderModify($content));
    }

    /**
     * The short-circuit must not drop pending placeholders.
     */
    public function testChangeContentAfterEncoderModifyRestoresPendingPlaceholders(): void
    {
        $this->encode_sc->resetShortcodeReplacements();

        $before = $this->encode_sc->changeContentBeforeEncoderModify(
            'Mail: [apbct_encode_data]a@b.com[/apbct_encode_data]'
        );

        $this->assertNotEmpty($this->encode_sc->shortcode_replacements);

        $after = $this->encode_sc->changeContentAfterEncoderModify($before);

        $this->assertStringNotContainsString('APBCT_SHORT_CODE_INCLUDE', $after);
    }

    /**
     * Round-trip on shortcode-free content must be a no-op through both passes.
     */
    public function testFullRoundTripIsNoOpForContentWithoutShortcodes(): void
    {
        $this->encode_sc->resetShortcodeReplacements();

        $content = '<div class="x">Plain content, no shortcodes at all.</div>';

        $processed = $this->encode_sc->changeContentBeforeEncoderModify($content);
        $processed = $this->encode_sc->changeContentAfterEncoderModify($processed);

        $this->assertSame($content, $processed);
    }

    /**
     * The pre-check must not change the "shortcode inside an HTML tag" detection result.
     */
    public function testIsShortcodeInsideHtmlTagStillDetectsAttributeContext(): void
    {
        $inside  = '<a title="[apbct_encode_data]a@b.com[/apbct_encode_data]">x</a>';
        $outside = '<p>[apbct_encode_data]a@b.com[/apbct_encode_data]</p>';

        $this->assertTrue($this->callProtected($this->encode_sc, 'isShortcodeInsideHtmlTag', array($inside)));
        $this->assertFalse($this->callProtected($this->encode_sc, 'isShortcodeInsideHtmlTag', array($outside)));
    }

    /**
     * Guarded early return: no shortcode at all means "not inside a tag", and no regex is run.
     */
    public function testIsShortcodeInsideHtmlTagReturnsFalseWithoutShortcode(): void
    {
        $this->assertFalse(
            $this->callProtected($this->encode_sc, 'isShortcodeInsideHtmlTag', array('<a title="plain">x</a>'))
        );
        $this->assertFalse(
            $this->callProtected($this->encode_sc, 'isShortcodeInsideHtmlTag', array(null))
        );
    }
}
