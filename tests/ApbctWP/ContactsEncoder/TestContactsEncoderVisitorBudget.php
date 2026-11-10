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

    public function testManyCommentsAreRestoredInOnePass()
    {
        $comment = $this->encoder->markVisitorBlock('<p>' . str_repeat('x ', 1000) . '</p>', array('blockName' => 'core/comment-content'));
        $page = str_repeat($comment, 2000);
        $start = microtime(true);

        $this->assertSame($page, $this->encoder->modifyContent($page));
        $this->assertLessThan(1, microtime(true) - $start);
    }

    private function setOwnerEncodedLimitReached()
    {
        $property = new \ReflectionProperty(ContactsEncoder::class, 'encoded_contacts_count');
        $property->setAccessible(true);
        $property->setValue($this->encoder, ContactsEncoder::MAX_ENCODED_CONTACTS_PER_REQUEST);
    }

    private function comment_by($user_id)
    {
        return wp_insert_comment(array('comment_post_ID' => 1, 'comment_content' => 'x', 'comment_approved' => 1, 'user_id' => $user_id));
    }

    /**
     * The name of the author is printed in the avatar and in the reply link too. A moderator writes for the site.
     */
    public function testBlocksOfACommentTakeTheBudgetOfItsAuthor()
    {
        $this->setOwnerEncodedLimitReached();
        $admin = self::factory_user('administrator');
        $visitor_comment = $this->comment_by(0);
        $admin_comment = $this->comment_by($admin);
        $link = '<a aria-label="Reply to name@example.org" href="#">name@example.org</a>';

        foreach ( array('core/comment-reply-link', 'core/avatar', 'core/comment-author-name') as $name ) {
            $block = array('blockName' => $name);
            $visitor = (object)array('context' => array('commentId' => $visitor_comment));
            $content = $this->encoder->enterVisitorBlock($link, $block, $visitor);
            $content = $this->encoder->leaveVisitorBlock($this->encoder->modifyContent($content), $block, $visitor);
            $this->assertStringContainsString('data-original-string', $content, $name);
            $this->assertStringContainsString('<!--apbct-visitor-', $content, $name);

            $moderator = (object)array('context' => array('commentId' => $admin_comment));
            $content = $this->encoder->enterVisitorBlock($link, $block, $moderator);
            $content = $this->encoder->leaveVisitorBlock($this->encoder->modifyContent($content), $block, $moderator);
            $this->assertStringNotContainsString('data-original-string', $content, $name);
            $this->assertStringNotContainsString('<!--apbct-visitor-', $content, $name);
        }

        wp_delete_comment($visitor_comment, true);
        wp_delete_comment($admin_comment, true);
        wp_delete_user($admin);
    }

    private static function factory_user($role)
    {
        return wp_insert_user(array('user_login' => 'ce_' . $role . '_' . wp_generate_password(6, false), 'user_pass' => wp_generate_password(), 'role' => $role));
    }

    /**
     * A classic theme in buffer mode: the parts of a comment of a visitor are marked, of a moderator are not.
     */
    public function testClassicCommentPartsAreMarked()
    {
        $admin = self::factory_user('administrator');
        $visitor_comment = get_comment($this->comment_by(0));
        $admin_comment = get_comment($this->comment_by($admin));

        $this->assertStringStartsWith('<!--apbct-visitor-', $this->encoder->markVisitorCommentPart('<p>t</p>', $visitor_comment));
        $this->assertStringStartsWith('<!--apbct-visitor-', $this->encoder->markVisitorCommentPart('<a>n</a>', (string)$visitor_comment->comment_ID));
        $this->assertStringStartsWith('<!--apbct-visitor-', $this->encoder->markVisitorCommentAvatar('<img alt="n">', $visitor_comment));
        $this->assertSame('<p>t</p>', $this->encoder->markVisitorCommentPart('<p>t</p>', $admin_comment));
        $this->assertSame('<img alt="n">', $this->encoder->markVisitorCommentAvatar('<img alt="n">', 'user@example.org'));

        wp_delete_comment($visitor_comment->comment_ID, true);
        wp_delete_comment($admin_comment->comment_ID, true);
        wp_delete_user($admin);
    }

    /**
     * In buffer mode the shortcodes of a comment are expanded on the budget of visitors, nested marks too.
     */
    public function testBufferModeShortcodesOfACommentTakeTheVisitorBudget()
    {
        global $apbct;
        $apbct->settings['data__email_decoder_buffer'] = 1;
        $this->setOwnerEncodedLimitReached();

        $text = $this->encoder->markVisitorCommentPart('<p>[apbct_encode_data]secret@example.org[/apbct_encode_data]</p>', null);
        $comment = $this->encoder->markVisitorBlock('<div>' . $this->encoder->markVisitorBlock($text, array('blockName' => 'core/comment-content')) . '</div>', array('blockName' => 'core/comment-content'));
        $apbct->buffer = '<html><body>' . $comment . '<footer>owner@example.org</footer></body></html>';
        $flag = new \ReflectionProperty($this->encoder, 'buffer_modified');
        $flag->setAccessible(true);
        $flag->setValue($this->encoder, false);

        $this->encoder->modifyBuffer();

        $this->assertStringNotContainsString('secret@example.org', $apbct->buffer);
        $this->assertStringNotContainsString('[apbct_encode_data]', $apbct->buffer);
        $this->assertSame(1, substr_count($apbct->buffer, 'data-original-string'));
        $this->assertStringNotContainsString('owner@example.org', $apbct->buffer);
    }

    public function testPlantedMarkerIsNotTrusted()
    {
        $page = $this->renderBlock('<p><!--apbct-visitor-x-->owner@example.org<!--/apbct-visitor-x--></p>', 'core/group');

        $this->assertStringNotContainsString('owner@example.org', $page);
    }
}
