<?php

namespace ApbctWP\ContactsEncoder\Shortcodes;

use Cleantalk\ApbctWP\ContactsEncoder\Shortcodes\ExcludedEncodeContentSC;
use PHPUnit\Framework\TestCase;

class ExcludedEncodeContentSCTest extends TestCase
{
    private $shortcode;

    private $exclude_content_sc;

    protected function setUp(): void
    {
        parent::setUp();

        global $apbct;
        $apbct->api_key         = 'testapikey';
        $this->contacts_encoder = apbctGetContactsEncoder();

        $this->exclude_content_sc = new ExcludedEncodeContentSC();

        // Create a partial mock of the tested class for isolation
        $this->shortcode = $this->getMockBuilder(ExcludedEncodeContentSC::class)
                                ->setMethods(null) // In PHPUnit 8, use setMethods(null) for no mocking
                                ->getMock();
    }

    protected function tearDown(): void
    {
        global $apbct;
        $apbct->errorDeleteAll(true);
        parent::tearDown();
    }

    /**
     * Test callback with empty content
     */
    public function testCallbackWithEmptyContent(): void
    {
        $result = $this->exclude_content_sc->callback([], null, '');
        $this->assertNull($result);

        $result = $this->exclude_content_sc->callback([], '', '');
        $this->assertEquals('', $result);
    }

    /**
     * Test callback with valid content containing data-original-string (buffer mode).
     */
    public function testCallbackWithValidContent(): void
    {
        global $apbct;

        $apbct->settings['data__email_decoder_buffer'] = true;

        $originalString = 'test@example.com';
        $encodedString = $this->contacts_encoder->modifyContent($originalString);

        $result = $this->exclude_content_sc->callback([], $encodedString, '');

        $this->assertEquals($originalString, $result);
    }

    /**
     * Test callback stores placeholder when buffer mode is off.
     */
    public function testCallbackWithValidContentBufferOffReturnsPlaceholder(): void
    {
        global $apbct;

        $previous_buffer = $apbct->settings['data__email_decoder_buffer'];
        $apbct->settings['data__email_decoder_buffer'] = false;

        try {
            $originalString = 'test@example.com';
            $encodedString = $this->contacts_encoder->modifyContent($originalString);

            $result = $this->exclude_content_sc->callback([], $encodedString, '');

            $this->assertStringContainsString('APBCT_SHORT_CODE_SKIP', $result);
        } finally {
            $apbct->settings['data__email_decoder_buffer'] = $previous_buffer;
        }
    }

    /**
     * Test callback with content without data-original-string
     */
    public function testCallbackWithContentWithoutDataAttribute(): void
    {
        global $apbct;

        $apbct->settings['data__email_decoder_buffer'] = true;

        $content = '<span>Some text without data attribute</span>';

        $result = $this->exclude_content_sc->callback([], $content, '');

        $this->assertEquals($content, $result);
    }

    /**
     * Test changeContentAfterEncoderModify when buffer is off and not in the_title
     */
    public function testChangeContentAfterEncoderModifyWithBufferOn(): void
    {
        global $apbct;

        $content = 'original buffer content test@example.com';

        $apbct->settings['data__email_decoder_buffer'] = true;
        $apbct->buffer = $content;
        $apbct->saveSettings();
        $this->contacts_encoder->dropInstance(); // Need to rebuild the object after the settings changed
        $this->contacts_encoder = apbctGetContactsEncoder();

        $result = $this->exclude_content_sc->changeContentAfterEncoderModify($content);

        $this->assertEquals($content, $result);
    }

    /**
     * Test changeContentAfterEncoderModify uses apbct->buffer during shutdown when content is empty
     */
    public function testChangeContentAfterEncoderModifyWithBufferOnUsesBufferDuringShutdown(): void
    {
        global $apbct;

        $content = 'original buffer content test@example.com';

        $apbct->settings['data__email_decoder_buffer'] = true;
        $apbct->buffer = $content;
        $apbct->saveSettings();
        $this->contacts_encoder->dropInstance();
        $this->contacts_encoder = apbctGetContactsEncoder();

        $shortcodeMock = $this->getMockBuilder(ExcludedEncodeContentSC::class)
                              ->setMethods(['getCurrentAction'])
                              ->getMock();
        $shortcodeMock->method('getCurrentAction')->willReturn('shutdown');

        $result = $shortcodeMock->changeContentAfterEncoderModify('');

        $this->assertEquals($content, $result);
    }

    /**
     * Test changeContentAfterEncoderModify when buffer is off and not in the_title
     */
    public function testChangeContentAfterEncoderModifyWithBufferOffOnTheContent(): void
    {
        global $apbct;

        $content = '<p>[apbct_skip_encoding]asfg@srth.rew[/apbct_skip_encoding]</p>';

        $apbct->settings['data__email_decoder_buffer'] = false;
        $apbct->saveSettings();
        $this->contacts_encoder->dropInstance();
        $this->contacts_encoder = apbctGetContactsEncoder();

        $protected_content = $this->exclude_content_sc->changeContentBeforeEncoderModify($content);
        $encoded_content = $this->contacts_encoder->modifyContent($protected_content);
        $result = $this->exclude_content_sc->changeContentAfterEncoderModify($encoded_content);

        $this->assertEquals('<p>asfg@srth.rew</p>', $result);
    }

    /**
     * Test changeContentAfterEncoderModify when buffer is off and not in the_title
     */
    public function testChangeContentAfterEncoderModifyWithBufferOffSkipsPhoneAndEmail(): void
    {
        global $apbct;

        $content = '[apbct_skip_encoding]asfg@srth.rew[/apbct_skip_encoding] [apbct_skip_encoding]+79991112233[/apbct_skip_encoding]';

        $apbct->settings['data__email_decoder_buffer'] = false;
        $apbct->saveSettings();
        $this->contacts_encoder->dropInstance();
        $this->contacts_encoder = apbctGetContactsEncoder();

        $protected_content = $this->exclude_content_sc->changeContentBeforeEncoderModify($content);
        $encoded_content = $this->contacts_encoder->modifyContent($protected_content);
        $result = $this->exclude_content_sc->changeContentAfterEncoderModify($encoded_content);

        $this->assertEquals('asfg@srth.rew +79991112233', $result);
    }

    /**
     * Test changeContentAfterEncoderModify when buffer is off for title-like content
     */
    public function testChangeContentAfterEncoderModifyInTheTitleHook(): void
    {
        global $apbct;

        $content = 'title with [apbct_skip_encoding]test@example.com[/apbct_skip_encoding]';

        $apbct->settings['data__email_decoder_buffer'] = false;
        $apbct->saveSettings();
        $this->contacts_encoder->dropInstance();
        $this->contacts_encoder = apbctGetContactsEncoder();

        $protected_content = $this->exclude_content_sc->changeContentBeforeEncoderModify($content);
        $encoded_content = $this->contacts_encoder->modifyContent($protected_content);
        $result = $this->exclude_content_sc->changeContentAfterEncoderModify($encoded_content);

        $this->assertEquals('title with test@example.com', $result);
    }

    /**
     * Test changeContentAfterEncoderModify with multiple shortcodes in content
     */
    public function testChangeContentAfterEncoderModifyWithMultipleShortcodes(): void
    {
        global $apbct;

        $apbct->settings['data__email_decoder_buffer'] = true;
        $apbct->saveSettings();
        $this->contacts_encoder->dropInstance(); // Need to rebuild the object after the settings changed
        $this->contacts_encoder = apbctGetContactsEncoder();
        $buffer_content = 'buffer [apbct_skip_encoding]first[/apbct_skip_encoding] and [apbct_skip_encoding]second[/apbct_skip_encoding]';
        $apbct->buffer = $buffer_content;

        $shortcodeMock = $this->getMockBuilder(ExcludedEncodeContentSC::class)
                              ->setMethods(['callback', 'getCurrentAction'])
                              ->getMock();

        $shortcodeMock->method('getCurrentAction')->willReturn('shutdown');

        // Expect two calls to callback
        $matcher = $this->exactly(2);
        $shortcodeMock->expects($matcher)
                      ->method('callback')
                      ->willReturnCallback(function ($atts, $content, $tag) use ($matcher) {
                          switch ($matcher->getInvocationCount()) {
                              case 1:
                                  $this->assertEquals('first', $content);
                                  return 'decoded first';
                              case 2:
                                  $this->assertEquals('second', $content);
                                  return 'decoded second';
                          }
                          return '';
                      });

        $result = $shortcodeMock->changeContentAfterEncoderModify($buffer_content);

        $this->assertEquals('buffer decoded first and decoded second', $result);
    }

    /**
     * Test changeContentAfterEncoderModify when callback returns unmodified content
     */
    public function testChangeContentAfterEncoderModifyWhenCallbackReturnsUnmodified(): void
    {
        global $apbct;

        $apbct->settings['data__email_decoder_buffer'] = true;
        $apbct->saveSettings();
        $this->contacts_encoder->dropInstance(); // Need to rebuild the object after the settings changed
        $this->contacts_encoder = apbctGetContactsEncoder();
        $buffer_content = 'buffer [apbct_skip_encoding]content[/apbct_skip_encoding]';
        $apbct->buffer = $buffer_content;

        $shortcodeMock = $this->getMockBuilder(ExcludedEncodeContentSC::class)
                              ->setMethods(['callback', 'getCurrentAction'])
                              ->getMock();

        $shortcodeMock->method('getCurrentAction')->willReturn('shutdown');

        $shortcodeMock->expects($this->once())
                      ->method('callback')
                      ->willReturn('content'); // Return unmodified content

        $result = $shortcodeMock->changeContentAfterEncoderModify($buffer_content);

        // Expect content not to change, but tags are removed
        $this->assertEquals('buffer content', $result);
    }

    /**
     * Test that callback sanitizes content with script tags (fallback path - no data-original-string)
     */
    public function testCallbackSanitizesDirectScriptInjection(): void
    {
        global $apbct;

        $apbct->settings['data__email_decoder_buffer'] = true;

        $content = '<script>alert(document.domain)</script>';
        $result = $this->exclude_content_sc->callback([], $content, '');
        $this->assertStringNotContainsString('<script>', $result);
    }

    /**
     * Test that callback sanitizes content with mixed HTML and script (fallback path)
     */
    public function testCallbackSanitizesScriptInSpanFallback(): void
    {
        global $apbct;

        $apbct->settings['data__email_decoder_buffer'] = true;

        $content = '<span><script>alert(1)</script></span>';
        $result = $this->exclude_content_sc->callback([], $content, '');
        $this->assertStringNotContainsString('<script>', $result);
        // <span> is allowed by wp_kses_post, so it should remain
        $this->assertStringContainsString('<span>', $result);
    }

    /**
     * get_header/get_footer fire as actions with a nullable template name.
     * Filters hooked there must not pass null into preg_* and should preserve the value.
     */
    public function testChangeContentBeforeEncoderModifyAcceptsNullFromGetHeader(): void
    {
        $this->assertNull(
            $this->exclude_content_sc->changeContentBeforeEncoderModify(null)
        );
    }

    /**
     * @see testChangeContentBeforeEncoderModifyAcceptsNullFromGetHeader
     */
    public function testChangeContentAfterEncoderModifyAcceptsNullFromGetHeader(): void
    {
        $this->assertNull(
            $this->exclude_content_sc->changeContentAfterEncoderModify(null)
        );
    }

    /**
     * Test that shortcode inside HTML attribute is detected
     */
    public function testOffsetDetectionInsideHtmlTag(): void
    {
        $content = '<a title="[apbct_skip_encoding]">X</a>';
        $pos = strpos($content, '[apbct_skip_encoding]');

        $this->assertTrue(
            $this->exclude_content_sc->isOffsetInsideHtmlTag($content, $pos)
        );
    }

    /**
     * Test that shortcode outside HTML tag is detected as safe
     */
    public function testOffsetDetectionOutsideHtmlTag(): void
    {
        $content = '[apbct_skip_encoding]test[/apbct_skip_encoding]';
        $pos = strpos($content, '[apbct_skip_encoding]');

        $this->assertFalse(
            $this->exclude_content_sc->isOffsetInsideHtmlTag($content, $pos)
        );
    }

    /**
     * Test that > inside quoted attribute value does not fool the parser
     */
    public function testOffsetDetectionWithGreaterThanInQuotedAttribute(): void
    {
        // The > inside title="1>2" must not be treated as tag close
        $content = '<a title="1>2 [apbct_skip_encoding]payload[/apbct_skip_encoding]">';
        $pos = strpos($content, '[apbct_skip_encoding]');

        $this->assertTrue(
            $this->exclude_content_sc->isOffsetInsideHtmlTag($content, $pos)
        );
    }

    /**
     * Test that shortcode inside HTML attribute is not processed by changeContentAfterEncoderModify
     */
    public function testChangeContentAfterEncoderModifySkipsShortcodeInsideHtmlTag(): void
    {
        global $apbct;

        $content = '<a title="[apbct_skip_encoding]<span data-original-string="test">x</span>[/apbct_skip_encoding]">link</a>';

        $apbct->settings['data__email_decoder_buffer'] = true;
        $apbct->buffer = $content;
        $apbct->saveSettings();
        $this->contacts_encoder->dropInstance();
        $this->contacts_encoder = apbctGetContactsEncoder();

        $result = $this->exclude_content_sc->changeContentAfterEncoderModify($content);

        // Content should be returned unchanged because shortcode is inside HTML tag
        $this->assertEquals($content, $result);
    }

    /**
     * Test mixed shortcodes: safe outside and unsafe inside HTML tag
     */
    public function testMixedShortcodesSafeAndUnsafe(): void
    {
        global $apbct;

        $content =
            '[apbct_skip_encoding]safe[/apbct_skip_encoding]' .
            '<a title="[apbct_skip_encoding]bad[/apbct_skip_encoding]">' .
            'X</a>';

        $apbct->settings['data__email_decoder_buffer'] = true;
        $apbct->buffer = $content;
        $apbct->saveSettings();
        $this->contacts_encoder->dropInstance();
        $this->contacts_encoder = apbctGetContactsEncoder();

        $result = $this->exclude_content_sc->changeContentAfterEncoderModify($content);

        // Full block should be skipped if ANY shortcode is inside HTML tag
        $this->assertEquals($content, $result);
    }

    /**
     * Test that callback sanitizes content with data-original-string when decoding fails
     */
    public function testCallbackSanitizesFallbackWithDataOriginalAndScript(): void
    {
        // Content has data-original-string but decoding will fail,
        // so fallback wp_kses_post should strip the script tag
        $content = '<span data-original-string="nonexistent_encoded_data"><script>alert(1)</script></span>';
        $result = $this->exclude_content_sc->callback([], $content, '');
        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringNotContainsString('alert(1)', $result);
    }

    /**
     * Test that normal email decoding still works after sanitization
     */
    public function testCallbackDecodesEmailCorrectlyWithSanitization(): void
    {
        $originalString = 'test@example.com';
        $encodedString = $this->contacts_encoder->modifyContent($originalString);

        $result = $this->exclude_content_sc->callback([], $encodedString, '');

        // esc_html should not affect a plain email address
        $this->assertEquals($originalString, $result);
    }

    /**
     * Themes may call apply_filters('the_title', $title) with a single argument.
     * filterTheTitle must accept that without ArgumentCountError on PHP 8+.
     */
    public function testFilterTheTitleAcceptsSingleArgument(): void
    {
        $title = 'Plain title without shortcode';
        $result = $this->exclude_content_sc->filterTheTitle($title);
        $this->assertSame($title, $result);
    }
}
