<?php

namespace ApbctWP\ContactsEncoder;

use Cleantalk\Common\ContactsEncoder\ContactsEncoder;
use PHPUnit\Framework\TestCase;

/**
 * Contacts of visitors take their own budget and the outer blocks do not count them again.
 */
class TestContactsEncoderVisitorBudget extends TestCase
{
    /**
     * @var \Cleantalk\ApbctWP\ContactsEncoder\ContactsEncoder
     */
    private $encoder;

    protected function setUp(): void
    {
        parent::setUp();

        global $apbct;
        $apbct->api_key = 'testapikey';
        $apbct->settings['data__email_decoder'] = 1;
        $apbct->settings['data__email_decoder_buffer'] = 0;

        $this->encoder = apbctGetContactsEncoder();
        foreach ( array('contact_signs_processed', 'encoded_contacts_count', 'visitor_contact_signs_processed', 'visitor_encoded_contacts_count') as $name ) {
            $property = new \ReflectionProperty(ContactsEncoder::class, $name);
            $property->setAccessible(true);
            $property->setValue($this->encoder, 0);
        }
    }

    protected function tearDown(): void
    {
        global $apbct;
        $apbct->settings['data__email_decoder_buffer'] = 0;

        parent::tearDown();
    }

    private function comment()
    {
        return '<p>' . str_repeat('v@b.cc, ', ContactsEncoder::MAX_ENCODED_CONTACTS_PER_REQUEST + 1)
            . str_repeat('<abbr title="t@b.cc">x</abbr>', 2000) . '</p>';
    }

    /**
     * The filters of render_block for one block: the visitor budget is on from 8 to 1001.
     */
    private function renderBlock($content, $name, $after = null)
    {
        $block = array('blockName' => $name);
        $content = $this->encoder->enterVisitorBlock($content, $block);
        $content = $this->encoder->modifyContent($content);
        $content = $after === null ? $content : $after($content);

        return $this->encoder->leaveVisitorBlock($content, $block);
    }

    public function testOwnerContactsAreEncodedAfterAHeavyComment()
    {
        $comment = $this->renderBlock($this->comment(), 'core/comment-content');
        $page = '<div class="comments">' . $comment . '</div><footer>owner@example.org</footer>';

        // The outer blocks of the comment, e.g. comment-template, comments and the template part.
        for ( $i = 0; $i < 5; $i++ ) {
            $page = $this->renderBlock($page, 'core/group');
        }

        $this->assertStringContainsString($comment, $page);
        $this->assertStringNotContainsString('owner@example.org', $page);
        $this->assertStringNotContainsString('*****@*******.org', $page);
        $this->assertSame(ContactsEncoder::MAX_ENCODED_CONTACTS_PER_REQUEST + 1, substr_count($page, 'data-original-string'));
    }

    public function testBufferModeEncodesMarkedContentOfVisitorsOnTheirBudget()
    {
        global $apbct;
        $apbct->settings['data__email_decoder_buffer'] = 1;

        $comment = $this->encoder->markVisitorBlock($this->comment(), array('blockName' => 'core/comment-content'));
        $page = $this->encoder->modifyContent('<html><body>' . $comment . '<footer>owner@example.org</footer></body></html>');

        $this->assertStringNotContainsString('v@b.cc', $page);
        $this->assertStringNotContainsString('owner@example.org', $page);
        $this->assertSame(ContactsEncoder::MAX_ENCODED_CONTACTS_PER_REQUEST + 1, substr_count($page, 'data-original-string'));
    }

    public function testShortcodesOfACommentTakeTheVisitorBudget()
    {
        $property = new \ReflectionProperty(ContactsEncoder::class, 'encoded_contacts_count');
        $property->setAccessible(true);
        $property->setValue($this->encoder, ContactsEncoder::MAX_ENCODED_CONTACTS_PER_REQUEST);

        $comment = $this->renderBlock('<p>x</p>', 'core/comment-content', function ($content) {
            return $content . $this->encoder->modifyShortcodeContent('secret@example.org');
        });

        $this->assertStringContainsString('data-original-string', $comment);
        $this->assertSame(ContactsEncoder::MAX_ENCODED_CONTACTS_PER_REQUEST, $property->getValue($this->encoder));
    }

    public function testPlantedMarkerIsNotTrusted()
    {
        $page = $this->renderBlock('<p><!--apbct-visitor-x-->owner@example.org<!--/apbct-visitor-x--></p>', 'core/group');

        $this->assertStringNotContainsString('owner@example.org', $page);
    }
}
