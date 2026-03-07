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
     * Test callback with valid content containing data-original-string
     */
    public function testCallbackWithValidContent(): void
    {
        // Prepare test data
        $originalString = 'test@example.com';
        $encodedString = $this->contacts_encoder->modifyContent($originalString);

        $result = $this->exclude_content_sc->callback([], $encodedString, '');

        $this->assertEquals($originalString, $result);
    }

    /**
     * Test callback with content without data-original-string
     */
    public function testCallbackWithContentWithoutDataAttribute(): void
    {
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

        $result = $this->exclude_content_sc->changeContentAfterEncoderModify('');

        $this->assertEquals($content, $result);
    }

    /**
     * Test changeContentAfterEncoderModify when buffer is off but in the_title hook
     */
    public function testChangeContentAfterEncoderModifyInTheTitleHook(): void
    {
        global $apbct;

        $content = 'title with [apbct_skip_encoding]test@example.com[/apbct_skip_encoding]';

        $apbct->settings['data__email_decoder_buffer'] = false;
        $apbct->saveSettings();
        $this->contacts_encoder->dropInstance(); // Need to rebuild the object after the settings changed
        $this->contacts_encoder = apbctGetContactsEncoder();

        // Create a partial mock that overrides getCurrentAction
        $shortcodeMock = $this->getMockBuilder(ExcludedEncodeContentSC::class)
                              ->setMethods(['getCurrentAction'])
                              ->getMock();

        // Mock getCurrentAction to return 'the_title'
        $shortcodeMock->expects($this->once())
                      ->method('getCurrentAction')
                      ->willReturn('the_title');

        $encoded_content = $this->contacts_encoder->modifyContent($content);

        $result = $shortcodeMock->changeContentAfterEncoderModify($encoded_content);

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
        $apbct->buffer = 'buffer [apbct_skip_encoding]first[/apbct_skip_encoding] and [apbct_skip_encoding]second[/apbct_skip_encoding]';

        $shortcodeMock = $this->getMockBuilder(ExcludedEncodeContentSC::class)
                              ->setMethods(['callback'])
                              ->getMock();

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

        $result = $shortcodeMock->changeContentAfterEncoderModify('input');

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
        $apbct->buffer = 'buffer [apbct_skip_encoding]content[/apbct_skip_encoding]';

        $shortcodeMock = $this->getMockBuilder(ExcludedEncodeContentSC::class)
                              ->setMethods(['callback'])
                              ->getMock();

        $shortcodeMock->expects($this->once())
                      ->method('callback')
                      ->willReturn('content'); // Return unmodified content

        $result = $shortcodeMock->changeContentAfterEncoderModify('input');

        // Expect content not to change, but tags are removed
        $this->assertEquals('buffer content', $result);
    }
}
