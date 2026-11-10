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

    public function testOwnerContactsAreEncodedAfterAHeavyComment()
    {
        $comment = $this->encoder->modifyRenderedBlock($this->comment(), array('blockName' => 'core/comment-content'));
        $page = '<div class="comments">' . $comment . '</div><footer>owner@example.org</footer>';

        // The outer blocks of the comment, e.g. comment-template, comments and the template part.
        for ( $i = 0; $i < 5; $i++ ) {
            $page = $this->encoder->modifyRenderedBlock($page, array('blockName' => 'core/group'));
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

    public function testPlantedMarkerIsNotTrusted()
    {
        $page = $this->encoder->modifyRenderedBlock(
            '<p><!--apbct-visitor-x-->owner@example.org<!--/apbct-visitor-x--></p>',
            array('blockName' => 'core/group')
        );

        $this->assertStringNotContainsString('owner@example.org', $page);
    }
}
