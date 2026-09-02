<?php

namespace ApbctWP\ContactsEncoder\Integrations;

use Cleantalk\ApbctWP\ContactsEncoder\Integrations\CEIntegrationCommentList;
use PHPUnit\Framework\TestCase;

class TestCEIntegrationCommentList extends TestCase
{
    public function testProtectAndRestoreCommentListPreservesSiblingComments()
    {
        $html = '<div id="comments" class="comments-area">'
            . '<ol class="comment-list">'
            . '<li class="comment"><div class="comment-content">[apbct_encode_data]</div></li>'
            . '<li class="comment"><div class="comment-content">Second comment text</div></li>'
            . '<li class="comment"><div class="comment-content">[apbct_encode_data]z[/apbct_encode_data][/apbct_encode_data]</div></li>'
            . '</ol>'
            . '</div>';

        $integration = new CEIntegrationCommentList();
        $protected   = $integration->protect($html);
        $restored    = $integration->restore($protected);

        $this->assertStringNotContainsString('[apbct_encode_data]', $protected);
        $this->assertStringContainsString('%%APBCT_COMMENT_REGION_0%%', $protected);
        $this->assertSame($html, $restored);
    }

    public function testProtectStandaloneCommentListWithoutCommentsWrapper()
    {
        $html = '<ol class="comment-list">'
            . '<li>one</li>'
            . '<li>two</li>'
            . '</ol>';

        $integration = new CEIntegrationCommentList();
        $protected   = $integration->protect($html);

        $this->assertStringContainsString('%%APBCT_COMMENT_REGION_0%%', $protected);
        $this->assertSame($html, $integration->restore($protected));
    }

    public function testProtectLeavesContentWithoutCommentListUntouched()
    {
        $html = '<article>[apbct_encode_data]post@example.com[/apbct_encode_data]</article>';

        $integration = new CEIntegrationCommentList();

        $this->assertSame($html, $integration->protect($html));
    }

    public function testRestoreClearsPlaceholdersForSubsequentCalls()
    {
        $html = '<ol class="comment-list"><li>one</li></ol>';

        $integration = new CEIntegrationCommentList();
        $protected   = $integration->protect($html);
        $restored    = $integration->restore($protected);

        $this->assertSame($html, $restored);
        $this->assertSame($protected, $integration->restore($protected));
    }
}
