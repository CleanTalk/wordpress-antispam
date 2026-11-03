<?php

namespace ApbctWP\ContactsEncoder\Shortcodes;

use Cleantalk\ApbctWP\ContactsEncoder\Shortcodes\EncodeContentSC;
use Cleantalk\ApbctWP\ContactsEncoder\Shortcodes\ExcludedEncodeContentSC;
use Cleantalk\Common\ContactsEncoder\Dto\Params;
use PHPUnit\Framework\TestCase;

/**
 * Encoder shortcodes in untrusted content: a pair is expanded only when it is a text run of the final HTML.
 */
class ShortcodeContextTest extends TestCase
{
    /**
     * @var EncodeContentSC
     */
    private $encode;

    /**
     * @var ExcludedEncodeContentSC
     */
    private $exclude;

    protected function setUp(): void
    {
        parent::setUp();

        global $apbct;
        $apbct->api_key = 'testapikey';
        $apbct->settings['data__email_decoder_buffer'] = 0;
        $apbct->settings['data__email_decoder'] = 1;

        $this->encode = new EncodeContentSC(new Params());
        $this->exclude = new ExcludedEncodeContentSC();
    }

    private function handlers($html)
    {
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<!doctype html><html><body>' . $html . '</body></html>');
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $found = array();
        foreach ( $document->getElementsByTagName('*') as $element ) {
            foreach ( $element->attributes as $attribute ) {
                if ( stripos($attribute->name, 'on') === 0 ) {
                    $found[] = $element->tagName . '.' . $attribute->name;
                }
            }
        }

        return $found;
    }

    /**
     * Whole hook chain of the encoder on one string.
     */
    private function run_encoder($content)
    {
        $content = $this->exclude->changeContentBeforeEncoderModify($content);
        $content = $this->encode->changeContentBeforeEncoderModify($content);
        $content = apbctGetContactsEncoder()->modifyContent($content);
        $content = $this->encode->changeContentAfterEncoderModify($content);
        return $this->exclude->changeContentAfterEncoderModify($content);
    }

    /**
     * @dataProvider attributeBreakoutProvider
     */
    public function testPairAcrossTagBoundaryStaysAsIs($payload)
    {
        $result = $this->run_encoder($payload);

        $this->assertSame(array(), $this->handlers(wptexturize($result)), $result);
        $this->assertStringNotContainsString('apbct-email-encoder', $result);
    }

    public function attributeBreakoutProvider()
    {
        return array(
            'opener swallows the tag end' => array(
                'Great read! <abbr title="[apbct_encode_data x">z</abbr>]AB[/apbct_encode_data]<code>" onfocus="alert(1)" autofocus tabindex="2" x="</code>',
            ),
            'well formed pair inside a value' => array(
                '<abbr title="[apbct_encode_data]ab@c_d.com[/apbct_encode_data]">z</abbr><code>" onfocus="alert(1)" autofocus tabindex="2" x="</code>',
            ),
            'aria-label as a text hides the tag start' => array(
                '<code>aria-label="</code><a href="http://example.org/" title="ab@c_d.com">L</a><code>" onfocus="alert(1)" autofocus tabindex="1" x="</code>',
            ),
            'aria-label as a text and a skip pair inside a value' => array(
                '<code>aria-label="</code><a href="http://example.org/" title="[apbct_skip_encoding]ab@c_d.com[/apbct_skip_encoding]">L</a><code>" onfocus="alert(1)" autofocus tabindex="1" x="</code>',
            ),
        );
    }

    public function testLegitPairNextToAPayloadIsStillExpanded()
    {
        $result = $this->run_encoder(
            '<abbr title="[apbct_encode_data]x@y.zz[/apbct_encode_data]">t</abbr> <p>[apbct_encode_data]secret text[/apbct_encode_data]</p>'
        );

        $this->assertStringContainsString('title="[apbct_encode_data]x@y.zz[/apbct_encode_data]"', $result);
        $this->assertSame(1, substr_count($result, "class='apbct-email-encoder'") + substr_count($result, 'class="apbct-email-encoder"'));
    }

    public function testAttributesOfAStrictOpenerAreParsed()
    {
        $result = $this->run_encoder('[apbct_encode_data mode="replace" replacing_text="Show it"]secret[/apbct_encode_data]');

        $this->assertStringContainsString('Show it', $result);
        $this->assertStringNotContainsString('secret', $result);
    }

    public function testNumberOfPairsIsLimited()
    {
        $reflection = new \ReflectionMethod($this->exclude, 'findShortcodePairs');
        $reflection->setAccessible(true);
        $pairs = $reflection->invoke(
            $this->exclude,
            str_repeat('[apbct_skip_encoding]a[/apbct_skip_encoding]', 5000)
        );

        $this->assertLessThanOrEqual(200, count($pairs));
    }

    public function testManyOpenersWithoutACloserAreFast()
    {
        $content = 'x' . str_repeat('[apbct_skip_encoding]', 3000);
        $start = microtime(true);

        $this->assertSame($content, $this->exclude->changeContentBeforeEncoderModify($content));
        $this->assertLessThan(0.2, microtime(true) - $start);
    }

    public function testAriaLabelOfARealTagStaysIntact()
    {
        $html = '<button aria-label="Write to label@example.org" class="x">go</button> and text@example.org';
        $result = apbctGetContactsEncoder()->modifyContent($html);

        $this->assertStringContainsString('aria-label="Write to label@example.org"', $result);
        $this->assertStringNotContainsString('text@example.org', $result);
    }

    public function testAriaLabelAsATextIsNotProtected()
    {
        $result = apbctGetContactsEncoder()->modifyContent('<p>aria-label="text@example.org"</p>');

        $this->assertStringNotContainsString('text@example.org', $result);
    }

    public function testAriaLabelInsideACommentIsNotTouched()
    {
        $html = '<!-- <a aria-label="x"> -->';

        $this->assertSame($html, apbctGetContactsEncoder()->modifyContent($html));
    }

    public function testLongAriaLabelValueDoesNotEmptyTheContent()
    {
        $html = '<button aria-label="' . str_repeat('x', 200000) . '">go</button> keep me';

        $this->assertSame($html, apbctGetContactsEncoder()->modifyContent($html));
    }
}
