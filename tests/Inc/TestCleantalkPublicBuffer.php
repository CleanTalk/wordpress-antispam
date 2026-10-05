<?php

namespace Inc;

use Cleantalk\ApbctWP\State;
use PHPUnit\Framework\TestCase;

class TestCleantalkPublicBuffer extends TestCase
{
    /**
     * @var int Output buffering level owned by PHPUnit, must be restored after every test.
     */
    private $level_before;

    protected function setUp(): void
    {
        parent::setUp();
        global $apbct;
        $apbct = new State('cleantalk', array('settings', 'data', 'errors', 'remote_calls', 'stats', 'fw_stats'));
        $this->level_before = ob_get_level();
    }

    protected function tearDown(): void
    {
        while ( ob_get_level() > $this->level_before ) {
            ob_end_clean();
        }

        global $apbct;
        unset($apbct);
        parent::tearDown();
    }

    public function testBufferEndCapturesOwnBufferAndLeavesTheOuterOneOpen()
    {
        global $apbct;

        ob_start();
        echo 'OUTER;';
        $outer_level = ob_get_level();

        apbct_buffer__start();
        echo 'PAGE;';

        apbct_buffer__end();

        $this->assertSame('PAGE;', $apbct->buffer);
        $this->assertSame($outer_level, ob_get_level());
        $this->assertSame('OUTER;', ob_get_contents());

        ob_end_clean();
    }

    public function testBufferStartRecordsItsOwnNestingLevel()
    {
        ob_start();

        apbct_buffer__start();

        $this->assertSame(ob_get_level(), apbct_buffer__own_level());

        apbct_buffer__end();
        ob_end_clean();
    }

    public function testBufferEndFlushesNestedBuffersAndAppliesTheirCallbacks()
    {
        global $apbct;

        ob_start();
        echo 'OUTER;';
        $outer_level = ob_get_level();

        apbct_buffer__start();
        echo 'HEAD:PLACEHOLDER;';

        // Emulates WP late-printed styles hoisting: a buffer opened after ours that replaces
        // its own content only when it is flushed.
        ob_start(static function ($chunk) {
            return str_replace('PLACEHOLDER', 'REAL_CSS', $chunk);
        });
        echo 'BODY:PLACEHOLDER;';

        ob_start();
        echo 'FOOTER;';

        apbct_buffer__end();

        $this->assertSame('HEAD:PLACEHOLDER;BODY:REAL_CSS;FOOTER;', $apbct->buffer);
        $this->assertSame($outer_level, ob_get_level());
        $this->assertSame('OUTER;', ob_get_contents());

        ob_end_clean();
    }

    public function testBufferEndDoesNotLeakPageContentIntoTheOuterBuffer()
    {
        global $apbct;

        ob_start();
        echo 'OUTER;';

        apbct_buffer__start();
        echo 'PAGE;';
        ob_start();
        echo 'NESTED;';

        apbct_buffer__end();

        $outer_content = ob_get_contents();

        $this->assertSame('OUTER;', $outer_content);
        $this->assertStringNotContainsString('PAGE;', $outer_content);
        $this->assertStringNotContainsString('NESTED;', $outer_content);
        $this->assertSame('PAGE;NESTED;', $apbct->buffer);

        ob_end_clean();
    }
}
