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

    /**
     * @dataProvider externalFormMethodProvider
     */
    public function testStringRewritePreservesExternalFormDestinationAndMethod($method)
    {
        $action = 'https://external-forms.invalid/submit?list=1&source=page';
        $buffer = '<p>Before</p><form action="' . $action . '" method="' . $method . '">'
            . '<input name="email" value="member@example.org"></form><p>After</p>';

        $expected = '<p>Before</p><form action="' . esc_url(home_url('capture-form')) . '" method="POST">'
            . '<input name="email" value="member@example.org">'
            . '<input type="hidden" name="cleantalk_hidden_action" value="' . esc_attr($action) . '">'
            . '<input type="hidden" name="cleantalk_hidden_method" value="' . $method . '">'
            . '</form><p>After</p>';

        $this->assertSame($expected, $this->rewriteStringBuffer($buffer, 'capture-form'));
    }

    public function externalFormMethodProvider()
    {
        return array(
            'get' => array('get'),
            'post' => array('POST'),
        );
    }

    /**
     * @dataProvider replacementSequenceProvider
     */
    public function testStringRewriteDoesNotInterpretReplacementSequences($request)
    {
        $buffer = '<form action="https://external-forms.invalid/submit" method="get"></form>';
        $local_action = esc_url(home_url(add_query_arg(array(), $request)));
        $expected = '<form action="' . $local_action . '" method="POST">'
            . '<input type="hidden" name="cleantalk_hidden_action" value="https://external-forms.invalid/submit">'
            . '<input type="hidden" name="cleantalk_hidden_method" value="get"></form>';

        $this->assertSame($expected, $this->rewriteStringBuffer($buffer, $request));
    }

    public function replacementSequenceProvider()
    {
        return array(
            'whole match dollar' => array('capture/$0'),
            'capture group dollar' => array('capture/$1'),
            'whole match backslash' => array('capture/\\0'),
            'capture group backslash' => array('capture/\\1'),
        );
    }

    /**
     * @dataProvider unsafeRequestProvider
     */
    public function testStringRewriteDoesNotTurnRequestIntoMarkup($request)
    {
        $buffer = '<form action="https://external-forms.invalid/submit" method="get"></form>';
        $output = $this->rewriteStringBuffer($buffer, $request);
        $dom = new \DOMDocument();
        $this->assertTrue($dom->loadHTML($output));

        $this->assertSame(1, $dom->getElementsByTagName('form')->length);
        $this->assertSame(0, $dom->getElementsByTagName('img')->length);
        $this->assertSame(0, $dom->getElementsByTagName('script')->length);
        $this->assertFalse($dom->getElementsByTagName('form')->item(0)->hasAttribute('onmouseover'));
        $this->assertSame(2, $dom->getElementsByTagName('input')->length);
        $this->assertStringNotContainsString('https://external-forms.invalid/submit', $dom
            ->getElementsByTagName('form')->item(0)->getAttribute('action'));
    }

    public function unsafeRequestProvider()
    {
        return array(
            'literal tag delimiters' => array('capture"><img src=x onerror=alert(129)><span data-x="'),
            'literal attribute delimiter' => array('capture" onmouseover="alert(129)'),
            'entity attribute delimiter' => array('capture&quot; onmouseover=&quot;alert(129)'),
            'encoded tag delimiters' => array('capture%22%3E%3Cimg%20src=x%20onerror=alert(129)%3E'),
            'replacement with delimiters' => array('capture/$0"><script>alert(129)</script>'),
        );
    }

    public function testStringRewriteEscapesHiddenAttributeValuesWithoutChangingTheirMeaning()
    {
        $action = 'https://external-forms.invalid/submit?a=&quot;&b=<value>&c=\'';
        $method = 'get&copy;';
        $buffer = '<form action="' . $action . '" method="' . $method . '"></form>';
        $output = $this->rewriteStringBuffer($buffer, 'capture-form');

        $this->assertStringContainsString('value="' . esc_attr($action) . '"', $output);
        $this->assertStringContainsString('value="' . esc_attr($method) . '"', $output);
        $dom = new \DOMDocument();
        $this->assertTrue($dom->loadHTML('<meta charset="utf-8">' . $output));
        $inputs = $dom->getElementsByTagName('input');
        $this->assertSame(2, $inputs->length);
        $this->assertSame(
            html_entity_decode($action, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            $inputs->item(0)->getAttribute('value')
        );
        $this->assertSame(
            html_entity_decode($method, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            $inputs->item(1)->getAttribute('value')
        );
        $this->assertSame(0, $dom->getElementsByTagName('value')->length);
    }

    public function testStringRewriteLeavesInternalFormsAndOtherMarkupUnchanged()
    {
        $buffer = '<p>Untouched &lt;text&gt;</p><form action="' . esc_url(home_url('submit'))
            . '" method="post"><input name="email"></form>';

        $this->assertSame($buffer, $this->rewriteStringBuffer($buffer, 'capture-form'));
    }

    public function testStringRewriteLeavesBufferWithoutFormsUnchanged()
    {
        $buffer = '<p>Untouched &lt;text&gt;</p>';

        $this->assertSame($buffer, $this->rewriteStringBuffer($buffer, 'capture-form'));
    }

    private function rewriteStringBuffer($buffer, $request)
    {
        global $apbct, $wp;

        $original_wp = $wp;
        $wp = (object) array('request' => $request);
        $apbct->buffer = $buffer;

        try {
            return apbct_buffer_modify_by_string();
        } finally {
            $wp = $original_wp;
        }
    }
}
