<?php

namespace Cleantalk\ApbctWP\FindSpam\ListTable;

use Cleantalk\ApbctWP\FindSpam\ListTable\Comments;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Comments list table column_ct_author method.
 */
class TestComments extends \ApbctTestCase
{
    /**
     * @var Comments
     */
    private $instance;

    /**
     * @var \ReflectionMethod
     */
    private $columnCtAuthor;

    protected function setUp(): void
    {
        global $apbct;

        $reflection = new \ReflectionClass(Comments::class);
        $this->instance = $reflection->newInstanceWithoutConstructor();

        $this->columnCtAuthor = $reflection->getMethod('column_ct_author');
        $this->columnCtAuthor->setAccessible(true);

        $apbct->white_label = true;

        // Use reflection to set the protected property
        $apbctProperty = $reflection->getProperty('apbct');
        $apbctProperty->setAccessible(true);
        $apbctProperty->setValue($this->instance, $apbct);
    }

    /**
     * column_ct_author contains author name, email, and IP.
     */
    public function testColumnCtAuthorContainsAuthorEmailAndIp(): void
    {
        $comment = (object)[
            'comment_author'       => 'Test Author',
            'comment_author_email'  => 'test@example.com',
            'comment_author_IP'    => '192.168.1.1',
        ];
        $item = ['ct_comment' => $comment];

        $result = $this->columnCtAuthor->invoke($this->instance, $item);

        $this->assertStringContainsString('Test Author', $result);
        $this->assertStringContainsString('test@example.com', $result);
        $this->assertStringContainsString('mailto:test@example.com', $result);
        $this->assertStringContainsString('192.168.1.1', $result);
        $this->assertStringContainsString('edit-comments.php?s=192.168.1.1', $result);
    }

    /**
     * column_ct_author shows "No email" when comment has no email.
     */
    public function testColumnCtAuthorShowsNoEmailWhenEmpty(): void
    {
        $comment = (object)[
            'comment_author'       => 'No Email Author',
            'comment_author_email' => '',
            'comment_author_IP'     => '192.168.1.1',
        ];
        $item = ['ct_comment' => $comment];

        $result = $this->columnCtAuthor->invoke($this->instance, $item);

        $this->assertStringContainsString('No Email Author', $result);
        $this->assertStringContainsString('No email', $result);
    }

    /**
     * column_ct_author shows "No IP adress" when comment has no IP.
     */
    public function testColumnCtAuthorShowsNoIpWhenEmpty(): void
    {
        $comment = (object)[
            'comment_author'       => 'No IP Author',
            'comment_author_email' => 'author@example.com',
            'comment_author_IP'    => '',
        ];
        $item = ['ct_comment' => $comment];

        $result = $this->columnCtAuthor->invoke($this->instance, $item);

        $this->assertStringContainsString('No IP Author', $result);
        $this->assertStringContainsString('author@example.com', $result);
        $this->assertStringContainsString('No IP adress', $result);
    }

    /**
     * column_ct_author shows email link without cleantalk link when white_label is true.
     */
    public function testColumnCtAuthorShowsEmailWithoutCleantalkLinkWhenWhiteLabel(): void
    {
        global $apbct;

        $comment = (object)[
            'comment_author'       => 'Test Author',
            'comment_author_email' => 'test@example.com',
            'comment_author_IP'    => '192.168.1.1',
        ];
        $item = ['ct_comment' => $comment];

        $result = $this->columnCtAuthor->invoke($this->instance, $item);

        $this->assertStringContainsString('mailto:test@example.com', $result);
        $this->assertStringNotContainsString('cleantalk.org/blacklists/test@example.com', $result);
    }

    /**
     * column_ct_author shows email link with cleantalk link when white_label is false.
     */
    public function testColumnCtAuthorShowsEmailWithCleantalkLinkWhenNotWhiteLabel(): void
    {
        global $apbct;

        // Update global apbct
        $apbct->white_label = false;

        // Update instance apbct using reflection
        $reflection = new \ReflectionClass(Comments::class);
        $apbctProperty = $reflection->getProperty('apbct');
        $apbctProperty->setAccessible(true);
        $instanceApbct = $apbctProperty->getValue($this->instance);
        $instanceApbct->white_label = false;
        $apbctProperty->setValue($this->instance, $instanceApbct);

        $comment = (object)[
            'comment_author'       => 'Test Author',
            'comment_author_email' => 'test@example.com',
            'comment_author_IP'    => '192.168.1.1',
        ];
        $item = ['ct_comment' => $comment];

        $result = $this->columnCtAuthor->invoke($this->instance, $item);

        $this->assertStringContainsString('mailto:test@example.com', $result);
        $this->assertStringContainsString('cleantalk.org/blacklists/test@example.com', $result);
    }

    /**
     * column_ct_author escapes quotes in email, so it can not break out of the href attribute.
     */
    public function testColumnCtAuthorEscapesEmail(): void
    {
        global $apbct;
        $apbct->white_label = false;

        $comment = (object)[
            'comment_author'       => 'Test Author',
            'comment_author_email' => "x'class='apbct_dog_one'onanimationstart='alert(1)'@ev.il",
            'comment_author_IP'    => '192.168.1.1',
        ];
        $item = ['ct_comment' => $comment];

        $result = $this->columnCtAuthor->invoke($this->instance, $item);

        $this->assertStringNotContainsString("'class='", $result);
        $this->assertStringNotContainsString("onanimationstart='", $result);
        $this->assertStringContainsString('mailto:x&#039;class=&#039;apbct_dog_one&#039;', $result);
        $this->assertStringContainsString('cleantalk.org/blacklists/x&#039;class=&#039;', $result);
    }

    /**
     * column_ct_author escapes author name and IP.
     */
    public function testColumnCtAuthorEscapesAuthorAndIp(): void
    {
        global $apbct;
        $apbct->white_label = false;

        $comment = (object)[
            'comment_author'       => '<script>alert(1)</script>',
            'comment_author_email' => 'test@example.com',
            'comment_author_IP'    => "1.1.1.1' onmouseover='alert(1)",
        ];
        $item = ['ct_comment' => $comment];

        $result = $this->columnCtAuthor->invoke($this->instance, $item);

        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $result);
        $this->assertStringNotContainsString("' onmouseover='", $result);
        $this->assertStringContainsString('edit-comments.php?s=1.1.1.1&#039; onmouseover=&#039;', $result);
    }

    /**
     * column_ct_comment strips dangerous HTML from comment content and keeps allowed tags.
     */
    public function testColumnCtCommentSanitizesContent(): void
    {
        $comment_id = wp_insert_comment(array(
            'comment_post_ID' => 0,
            'comment_content' => 'Hello',
        ));
        $comment = (object)[
            'comment_content' => '<img src=x onerror=alert(1)><b>bold</b>',
        ];
        $item = ['ct_id' => $comment_id, 'ct_comment' => $comment];

        $reflection = new \ReflectionClass(Comments::class);
        $columnCtComment = $reflection->getMethod('column_ct_comment');
        $columnCtComment->setAccessible(true);
        $result = $columnCtComment->invoke($this->instance, $item);

        wp_delete_comment($comment_id, true);

        $this->assertStringNotContainsString('onerror', $result);
        $this->assertStringContainsString('<b>bold</b>', $result);
    }
}

