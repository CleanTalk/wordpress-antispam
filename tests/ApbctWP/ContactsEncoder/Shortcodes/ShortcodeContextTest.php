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

    public function testSkipPlaceholderInAttributeOfAShortcodeIsEscapedOnRestore()
    {
        $placeholder = $this->exclude->changeContentBeforeEncoderModify('[apbct_skip_encoding]a" onfocus="alert(1)[/apbct_skip_encoding]');
        $restored = $this->exclude->changeContentAfterEncoderModify('<figure class="x ' . $placeholder . '"></figure>');

        $this->assertSame(array(), $this->handlers($restored), $restored);
    }

    public function testSkipTextWithQuotesStaysVisible()
    {
        $result = $this->run_encoder('[apbct_skip_encoding]Say "hi" to a@example.org[/apbct_skip_encoding]');

        $this->assertSame('Say &quot;hi&quot; to a@example.org', $result);
    }

    public function testPairInsideAnotherShortcodeOpeningTagIsNotProtected()
    {
        $reflection = new \ReflectionMethod($this->exclude, 'isInsideOtherShortcodeTag');
        $reflection->setAccessible(true);

        $this->assertTrue($reflection->invoke($this->exclude, '[gallery ids="[apbct_skip_encoding]1', 13));
        $this->assertFalse($reflection->invoke($this->exclude, '[gallery ids="1"] [apbct_skip_encoding]1', 17));
        $this->assertFalse($reflection->invoke($this->exclude, 'list[0 [apbct_skip_encoding]1', 7));
    }

    /**
     * @dataProvider captionProvider
     */
    public function testPairInsideCaptionAttribute($payload)
    {
        $content = $this->exclude->changeContentBeforeEncoderModify($payload);
        $content = $this->encode->changeContentBeforeEncoderModify($content);
        $this->assertStringNotContainsString('%%APBCT', $content);

        $content = do_shortcode(apbctGetContactsEncoder()->modifyContent($content));
        $content = $this->encode->changeContentAfterEncoderModify($content);
        $content = $this->exclude->changeContentAfterEncoderModify($content);

        $this->assertSame(array(), $this->handlers($content), $content);
    }

    public function captionProvider()
    {
        return array(
            'skip' => array('[caption class="[apbct_skip_encoding]a" autofocus tabindex="0" onfocus="alert(1)" x="[/apbct_skip_encoding]"]body[/caption]'),
            'encode' => array('[caption class="[apbct_encode_data]a" autofocus tabindex="0" onfocus="alert(1)" x="[/apbct_encode_data]"]body[/caption]'),
        );
    }

    /**
     * @dataProvider commentMarkerProvider
     */
    public function testCommentMarkersInsidePairStayAsIs($payload)
    {
        $result = $this->run_encoder($payload . '<p>next</p> <!-- closed --> <p>after</p>');
        $document = new \DOMDocument();
        libxml_use_internal_errors(true);
        $document->loadHTML('<!doctype html><html><body>' . $result . '</body></html>');
        libxml_clear_errors();

        $this->assertStringContainsString('after', $document->textContent, $result);
    }

    public function commentMarkerProvider()
    {
        return array(
            'edges of the content' => array('Nice post! [apbct_encode_data]<!--a@b.cc xxxxxxxx-->[/apbct_encode_data] end'),
            'skip pair' => array('Hello <!-- x> <b>k</b> [apbct_skip_encoding] -->y[/apbct_skip_encoding]'),
        );
    }

    public function testVisiblePartOfEncodedStringIsEscaped()
    {
        $encoder = apbctGetContactsEncoder();
        $result = $encoder->modifyShortcodeContent('<!--a@b.cc xxxxxxxx-->', 'blur', null);

        $this->assertStringNotContainsString('<!--', $result);
        $this->assertStringNotContainsString('-->', $result);
    }

    public function testVisiblePartKeepsBrokenMultibyteEdge()
    {
        $result = apbctGetContactsEncoder()->modifyAny('Привет мир', 'obfuscate', null);

        $this->assertNotSame('', $result);
        $this->assertStringContainsString('*', $result);
    }

    public function testEncodedSpanIsOneLineWithDoubleQuotes()
    {
        $result = apbctGetContactsEncoder()->encodePlainEmail('span@example.org');

        $this->assertStringNotContainsString("\n", $result);
        $this->assertSame(1, preg_match('/^<span data-original-string="[^"\']+" class="apbct-email-encoder" title="[^"\']+">/', $result), $result);
    }

    public function testExcerptStringFilterIsNotEncoded()
    {
        $encoder = apbctGetContactsEncoder();
        $encoder->runEncoding();

        $this->assertFalse(has_filter('get_the_excerpt', array($encoder, 'modifyContent')));
        $this->assertSame(10, has_filter('the_excerpt', array($encoder, 'modifyContent')));
    }

    public function testCutSpanCanNotBeClosedByASingleQuoteOfOtherContent()
    {
        $span = apbctGetContactsEncoder()->encodePlainEmail('cut@example.org');
        $cut = substr($span, 0, 40);
        $html = '<div>' . $cut . '</div><h4><code>\' autofocus onfocus=alert(1) x=y </code></h4><p class="z">t</p>';

        $this->assertSame(array(), $this->handlers($html), $html);
    }

    public function testCoreShortcodePassInsideAnAttributeStaysSafe()
    {
        $this->encode->register();
        $content = '<a title="[apbct_encode_data]x@y.zz[/apbct_encode_data]" href="#">z</a><code>" onfocus="alert(1)" x="</code>';
        $content = $this->encode->changeContentBeforeEncoderModify($content);
        $content = do_shortcode(apbctGetContactsEncoder()->modifyContent($content));
        $content = $this->encode->changeContentAfterEncoderModify($content);

        $this->assertSame(array(), $this->handlers(wptexturize($content)), $content);
    }

    public function testReplacingTextIsPrintedOncePerShortcode()
    {
        $result = $this->encode->callback(
            array('mode' => 'replace', 'replacing_text' => 'Show it'),
            'a@b.cc, c@d.ee and text',
            'apbct_encode_data'
        );

        $this->assertSame(1, substr_count($result, 'Show it'));
        $this->assertStringNotContainsString('a@b.cc', $result);
    }

    public function testReplacingTextLengthIsLimited()
    {
        $result = $this->encode->callback(
            array('mode' => 'replace', 'replacing_text' => str_repeat('R', 32000)),
            'a@b.cc',
            'apbct_encode_data'
        );

        $visible = wp_strip_all_tags($result);

        $this->assertSame(EncodeContentSC::MAX_REPLACING_TEXT_LENGTH, substr_count($visible, 'R'));
    }

    public function testOutputOfManyContactsIsLimited()
    {
        $content = implode(',', array_fill(0, 4400, 'a@b.cc'));
        $result = apbctGetContactsEncoder()->modifyShortcodeContent($content, 'blur', null);

        $this->assertSame(1, substr_count($result, 'apbct-email-encoder'));
        $this->assertLessThan(strlen($content) * 3, strlen($result));
        $this->assertStringNotContainsString('a@b.cc', $result);
    }

    public function testFewContactsAreEncodedOneByOne()
    {
        $result = apbctGetContactsEncoder()->modifyShortcodeContent('text a@b.cc other c@d.ee', 'blur', null);

        $this->assertGreaterThanOrEqual(2, substr_count($result, 'apbct-email-encoder'));
    }

    public function testCleanedSlugIsUnique()
    {
        $first = wp_insert_post(array('post_title' => 'Slug owner', 'post_status' => 'publish', 'post_type' => 'post'));
        $data = array(
            'post_title' => 'Slug owner[apbct_skip_encoding][/apbct_skip_encoding]',
            'post_name' => 'slug-ownerapbct_skip_encoding',
            'post_status' => 'publish',
            'post_type' => 'post',
            'post_parent' => 0,
        );

        $result = $this->exclude->filterPostDataForSlug($data, array('ID' => 0));
        $data['post_name'] = '';
        $result_empty = $this->exclude->filterPostDataForSlug($data, array('ID' => 0));
        wp_delete_post($first, true);

        $this->assertSame('slug-owner-2', $result['post_name']);
        $this->assertSame('slug-owner-2', $result_empty['post_name']);
    }

    public function testCleanedSlugOfTheSamePostStaysTheSame()
    {
        $id = wp_insert_post(array('post_title' => 'Own slug', 'post_status' => 'publish', 'post_type' => 'post'));
        $result = $this->exclude->filterPostDataForSlug(
            array('post_title' => 'Own slug[apbct_skip_encoding][/apbct_skip_encoding]', 'post_name' => '', 'post_status' => 'publish', 'post_type' => 'post', 'post_parent' => 0),
            array('ID' => $id)
        );
        wp_delete_post($id, true);

        $this->assertSame('own-slug', $result['post_name']);
    }

    private function restoreLinks($html)
    {
        $reflection = new \ReflectionMethod($this->exclude, 'restorePageListLinkTitlesInHtml');
        $reflection->setAccessible(true);

        return $reflection->invoke($this->exclude, $html);
    }

    public function testLinkInsideACommentIsNotRestored()
    {
        $id = wp_insert_post(array('post_title' => '[apbct_skip_encoding]Notes[/apbct_skip_encoding]', 'post_status' => 'publish', 'post_type' => 'post'));
        $html = 'Thanks! <!-- > <a href="' . home_url('/?p=' . $id) . '"> -->[apbct_skip_encoding] x</a> end';
        $result = $this->restoreLinks($html);
        wp_delete_post($id, true);

        $this->assertSame($html, $result);
    }

    public function testLinkWithACommentInsideIsNotRestored()
    {
        $id = wp_insert_post(array('post_title' => '[apbct_skip_encoding]Notes[/apbct_skip_encoding]', 'post_status' => 'publish', 'post_type' => 'post'));
        $html = '<a href="' . home_url('/?p=' . $id) . '"><!-- [apbct_skip_encoding] --></a>';
        $result = $this->restoreLinks($html);
        wp_delete_post($id, true);

        $this->assertSame($html, $result);
    }

    public function testRealLinkIsRestored()
    {
        $id = wp_insert_post(array('post_title' => '[apbct_skip_encoding]Notes[/apbct_skip_encoding]', 'post_status' => 'publish', 'post_type' => 'post'));
        $result = $this->restoreLinks('<li><a class="x" href="' . home_url('/?p=' . $id) . '"><span class="apbct-email-encoder">n***</span></a></li>');
        wp_delete_post($id, true);

        $this->assertStringContainsString('>Notes</a></li>', $result);
    }

    public function testLinkToNonPublicPostTypeIsNotRestored()
    {
        register_post_type('ce_private_cpt', array('public' => false));
        $id = wp_insert_post(array('post_title' => '[apbct_skip_encoding]Hidden[/apbct_skip_encoding]', 'post_status' => 'publish', 'post_type' => 'ce_private_cpt'));
        $html = '<a href="' . home_url('/?p=' . $id) . '">[apbct_skip_encoding] x</a>';
        $result = $this->restoreLinks($html);
        wp_delete_post($id, true);
        unregister_post_type('ce_private_cpt');

        $this->assertSame($html, $result);
    }

    public function testHeadingInsideACommentIsNotRestored()
    {
        $id = wp_insert_post(array('post_title' => '[apbct_skip_encoding]Notes[/apbct_skip_encoding]', 'post_status' => 'publish', 'post_type' => 'post'));
        $reflection = new \ReflectionMethod($this->exclude, 'restorePostTitleInHtml');
        $reflection->setAccessible(true);
        $block = (object) array('context' => array('postId' => $id));
        $html = '<!-- <h1 class="wp-block-post-title"> -->[apbct_skip_encoding] x</h1> end';
        $real = '<h1 class="wp-block-post-title">[apbct_skip_encoding] x</h1> end';
        $result = $reflection->invoke($this->exclude, $html, array(), $block);
        $result_real = $reflection->invoke($this->exclude, $real, array(), $block);
        wp_delete_post($id, true);

        $this->assertSame($html, $result);
        $this->assertStringContainsString('Notes</h1>', $result_real);
    }
}
