<?php

namespace ApbctWP\ExternalForms;

use Cleantalk\ApbctWP\ExternalForms\HtmlBufferRewriter;
use Cleantalk\ApbctWP\ExternalForms\HtmlTagProcessor;
use PHPUnit\Framework\TestCase;

class TestHtmlBufferRewriter extends TestCase
{
    /**
     * @dataProvider provideForms
     */
    public function testExternalFormPreservesItsDestinationMethodAndContent($fallback, $tag, $action, $method)
    {
        $fields = '<input name="email" value="member@example.org"><button name="send" value="yes">Send</button>';
        $before = '<p data-note="&amp;">Before &lt;text&gt;</p>';
        $after = '<!-- untouched --><p>After</p>';
        $output = $this->rewrite($this->page($before . $tag . $fields . '</form>' . $after), $fallback);
        $dom = new \DOMDocument();
        $this->assertTrue($dom->loadHTML($output));
        $form = $dom->getElementById('target');

        $this->assertSame('https://site.example/capture?a=1&b=2', $form->getAttribute('action'));
        $this->assertSame('POST', $form->getAttribute('method'));
        $inputs = $form->getElementsByTagName('input');
        $this->assertSame(3, $inputs->length);
        $this->assertSame($action, $inputs->item(1)->getAttribute('value'));
        $this->assertSame($method, $inputs->item(2)->getAttribute('value'));
        $this->assertStringContainsString($before, $output);
        $this->assertStringContainsString($fields, $output);
        $this->assertStringContainsString($after, $output);
    }

    public function provideForms()
    {
        $cases = array(
            'entities' => array(
                '<form id="target" action="https://external.example/submit?a=1&amp;literal=&amp;amp;" method="post">',
                'https://external.example/submit?a=1&literal=&amp;',
                'post',
            ),
            'multiline single quotes' => array(
                "<FoRm\nid='target'\nACTION='https://external.example/submit'\nMETHOD='GET'\ndata-note='a > b'>",
                'https://external.example/submit',
                'GET',
            ),
            'unquoted and default method' => array(
                '<form id=target action=https://external.example/submit data-note="a > b">',
                'https://external.example/submit',
                'get',
            ),
            'semicolonless decimal query separator' => array(
                '<form id="target" action="https://external.example/submit?a=1&#38b=2" method="G&#69T">',
                'https://external.example/submit?a=1&b=2',
                'GET',
            ),
            'semicolonless decimal slashes' => array(
                "<form id='target' action='https:&#47&#47external.example/submit'>",
                'https://external.example/submit',
                'get',
            ),
            'semicolonless hexadecimal slashes' => array(
                '<form id=target action=https:&#x2f&#X2F;external.example/submit method=&#x50OST>',
                'https://external.example/submit',
                'POST',
            ),
        );
        return $this->withBackends($cases);
    }

    /**
     * Compare serialized fallback metadata without relying on libxml's HTML entity rules.
     *
     * @dataProvider provideFallbackReferences
     * @param string $raw Reference text as it appears in the source attribute.
     * @param string $decoded Expected browser-decoded text.
     * @return void
     */
    public function testFallbackReferencesAreDecodedBeforeMetadataSerialization($raw, $decoded)
    {
        $action = 'https://external.example/submit?value=';
        foreach ( array('"', "'") as $quote ) {
            $html = $this->page(
                '<form action=' . $quote . $action . $raw . $quote . ' method=' . $quote . $raw . $quote . '>'
                . '<input name="email" value="member@example.org"></form>'
            );
            $output = $this->rewrite($html, true);
            $this->assertStringContainsString(
                'name="cleantalk_hidden_action" value="'
                . htmlspecialchars($action . $decoded, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"',
                $output
            );
            $this->assertStringContainsString(
                'name="cleantalk_hidden_method" value="'
                . htmlspecialchars($decoded ?: 'get', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"',
                $output
            );
            $this->assertStringContainsString('<input name="email" value="member@example.org">', $output);
        }
    }

    /**
     * Cover numeric limits, unchanged PHP named-reference behavior, and single-pass decoding.
     *
     * @return array<string, array{string, string}> Raw and browser-decoded attribute fragments.
     */
    public function provideFallbackReferences(): array
    {
        $replacement = "\xEF\xBF\xBD";
        return array(
            'decimal without semicolon' => array('a=1&#38b=2', 'a=1&b=2'),
            'hexadecimal without semicolon' => array('a=1&#x26z=2', 'a=1&z=2'),
            'terminated numbers' => array('&#38;&#x2F;', '&/'),
            'leading zeroes' => array('&#0000000047&#x00002f', '//'),
            'missing digits' => array('&#;&#x;&#Xz;&#-1;', '&#;&#x;&#Xz;&#-1;'),
            'trailing ampersand' => array('text&', 'text&'),
            'embedded ampersands' => array('&&#38&&amp;', '&&&&'),
            'nul reference' => array('&#0;&#x0;', $replacement . $replacement),
            'surrogate limits' => array('&#xD800;&#xDFFF;', $replacement . $replacement),
            'out of range' => array('&#1114112;&#x110000;', $replacement . $replacement),
            'unicode maximum' => array('&#x10FFFF;', "\xF4\x8F\xBF\xBF"),
            'noncharacter' => array('&#xFFFF;', "\xEF\xBF\xBF"),
            'two byte encoding' => array('&#x7FF;', "\xDF\xBF"),
            'three byte encoding' => array('&#x800;', "\xE0\xA0\x80"),
            'astral character' => array('&#x1F600;', "\xF0\x9F\x98\x80"),
            'c1 replacements' => array('&#128;&#x9F;', "\xE2\x82\xAC\xC5\xB8"),
            'unmapped c1' => array('&#129;', "\xC2\x81"),
            'other control' => array('&#1;', "\x01"),
            'decoded newline' => array('&#10;', "\n"),
            'decoded carriage return' => array('&#13;', "\r"),
            'source normalization' => array("a\r\nb\rc\0d", "a\nb\nc" . $replacement . 'd'),
            'decoded ampersand is not rescanned' => array('&amp;#47;&amp;amp;', '&#47;&amp;'),
            'numeric ampersand is not rescanned' => array('&#38amp;', '&amp;'),
            'semicolonless names remain unchanged' => array('&copy!&AMP!&amp', '&copy!&AMP!&amp'),
            'legacy names before alphanumeric' => array('&copycat&notit;&amp1', '&copycat&notit;&amp1'),
            'legacy names before equals' => array('&amp=2&copy=3', '&amp=2&copy=3'),
            'only legacy names can omit semicolon' => array('&apos!&euro!&sol!', '&apos!&euro!&sol!'),
            'case sensitive names' => array('&Amp;&COPY;', '&Amp;' . "\xC2\xA9"),
            'complete name wins over legacy prefix' => array('&notin;', "\xE2\x88\x89"),
            'multi-code-point name' => array('&NotEqualTilde;', "\xE2\x89\x82\xCC\xB8"),
            'longest name' => array('&CounterClockwiseContourIntegral;', "\xE2\x88\xB3"),
            'unknown name' => array('&definitelyUnknown;', '&definitelyUnknown;'),
            'mixed reference kinds' => array('&copy;&#38;&amp;&#x2f;&apos;', "\xC2\xA9&&/'"),
            'name before numeric reference' => array('&copy&#47', '&copy/'),
            'numeric output before named reference' => array('&#38copy;&amp;', '&copy;&'),
            'long decimal reference' => array('&#' . str_repeat('9', 4096) . ';tail', $replacement . 'tail'),
            'long zero reference' => array('&#' . str_repeat('0', 4096) . '47tail', '/tail'),
            'long hexadecimal reference' => array('&#x' . str_repeat('F', 4096) . ';tail', $replacement . 'tail'),
            'long unknown name' => array('&' . str_repeat('a', 4096) . ';', '&' . str_repeat('a', 4096) . ';'),
            'repeated failed candidates' => array(str_repeat('&#x&unknown', 512), str_repeat('&#x&unknown', 512)),
        );
    }

    /**
     * @dataProvider provideUnchangedDocuments
     */
    public function testExcludedOrAmbiguousMarkupRemainsByteExact($fallback, $html)
    {
        $this->assertSame($html, $this->rewrite($html, $fallback));
    }

    public function provideUnchangedDocuments()
    {
        $external = '<form action="https://external.example/submit" method="get">';
        $cases = array(
            'no forms and mutation payloads' => array($this->page($this->mutationContexts())),
            'internal and relative forms' => array($this->page(
                '<form action="https://site.example/submit"></form><form action="/submit"></form>'
            )),
            'fragment' => array($external . '</form>'),
            'rss' => array($this->page('<rss>' . $external . '</form></rss>')),
            'nested forms' => array($this->page($external . $external . '</form></form>')),
            'duplicate destination' => array($this->page(
                '<form action="https://external.example/submit" ACTION="https://other.example/submit"></form>'
            )),
            'unclosed form' => array($this->page($external . '<input name="email">')),
            'incomplete quoted tag' => array($this->page(
                '<form action="https://external.example/submit><form action=https://other.example/submit></form>'
            )),
            'unterminated comment' => array($this->page('<!--' . $external . '</form>')),
        );
        foreach ( $this->scriptEscapeContexts() as $name => $opaque ) {
            $cases[$name] = array($this->page($opaque));
        }
        return $this->withBackends($cases);
    }

    /**
     * @dataProvider provideOpaqueContexts
     */
    public function testOpaqueContextsStayByteExactWhenAnotherFormIsModified($fallback, $opaque)
    {
        $form = '<form id="target" action="https://external.example/submit" method="get"></form>';
        $expected_form = '<form id="target" action="https://site.example/capture?a=1&amp;b=2" method="POST">'
            . '<input type="hidden" name="cleantalk_hidden_action" value="https://external.example/submit">'
            . '<input type="hidden" name="cleantalk_hidden_method" value="get"></form>';

        $this->assertRewrittenPage(
            $this->page($opaque . $expected_form),
            $this->rewrite($this->page($opaque . $form), $fallback)
        );
    }

    public function provideOpaqueContexts()
    {
        $fake = '<form action="https://external.example/fake"><input name="fake"></form>';
        $cases = array(
            'comment' => array('<!--' . $fake . '-->'),
            'bang-ended comment' => array('<!--' . $fake . '--!>'),
            'empty comment' => array('<!---->'),
            'abrupt empty comment' => array('<!-->'),
            'abrupt dash comment' => array('<!--->'),
            'noscript' => array('<noscript>' . $fake . '</noscript>'),
            'textarea and title' => array('<textarea>' . $fake . '</textarea><title>' . $fake . '</title>'),
            'raw-text closer boundaries' => array(
                '<textarea></textarea-extra>' . $fake . "</textarea\n>"
                . '<title></title-extra>' . $fake . "</title\t>"
            ),
            'style' => array('<style>/*' . $fake . '*/</style>'),
            'script' => array('<script>var markup = ' . json_encode($fake) . ';</script>'),
            'double escaped script' => array('<script><!--<script></script>' . $fake . '</script>'),
            'foreign content' => array(
                '<svg><title>Icon</title><foreignObject>' . $fake . '</foreignObject></svg><math><mtext>'
                . $fake . '</mtext></math>'
            ),
            'quoted doctype' => array("<!DOCTYPE html SYSTEM '" . $fake . "'>"),
            'original mutation payloads' => array($this->mutationContexts()),
        );
        foreach ( $this->scriptEscapeContexts() as $name => $opaque ) {
            $cases[$name] = array($opaque);
        }
        return $this->withBackends($cases);
    }

    /**
     * A later alternative terminator must not hide a real form following the first one.
     *
     * @dataProvider provideCommentTerminators
     * @param bool $fallback Whether to force the own tokenizer.
     * @param string $first First valid comment terminator.
     * @param string $later Alternative terminator outside the comment.
     * @return void
     */
    public function testFirstCommentTerminatorLeavesFollowingFormVisible($fallback, $first, $later)
    {
        $comment = '<!--<form action="https://external.example/fake"></form>' . $first;
        $form = '<form action="https://external.example/submit" method="get"></form>';
        $expected_form = '<form action="https://site.example/capture?a=1&amp;b=2" method="POST">'
            . '<input type="hidden" name="cleantalk_hidden_action" value="https://external.example/submit">'
            . '<input type="hidden" name="cleantalk_hidden_method" value="get"></form>';

        $this->assertRewrittenPage(
            $this->page($comment . $expected_form . $later),
            $this->rewrite($this->page($comment . $form . $later), $fallback)
        );
    }

    /**
     * Exercise both orders of the normal and bang-ended comment terminators.
     *
     * @return array Test arguments for both tokenizer backends.
     */
    public function provideCommentTerminators()
    {
        return $this->withBackends(array(
            'normal before bang' => array('-->', '--!>'),
            'bang before normal' => array('--!>', '-->'),
        ));
    }

    /**
     * @dataProvider provideBackends
     */
    public function testSeveralFormsAreModifiedIndependently($fallback)
    {
        $form = '<form action="https://external.example/submit" method="get"></form>';
        $middle = '<p>Between</p><form action="/internal"></form>';
        $output = $this->rewrite($this->page($form . $middle . $form), $fallback);

        $this->assertSame(2, substr_count($output, 'name="cleantalk_hidden_action"'));
        $this->assertSame(2, substr_count($output, 'name="cleantalk_hidden_method"'));
        $this->assertStringContainsString($middle, $output);
    }

    public function provideBackends()
    {
        return array('WordPress' => array(false), 'fallback' => array(true));
    }

    /**
     * @dataProvider provideAmbiguousForeignContexts
     */
    public function testAmbiguousForeignContentLeavesTheRemainingBufferUntouched($fallback, $foreign)
    {
        $form = '<form action="https://external.example/submit" method="get"></form>';
        $expected_form = '<form action="https://site.example/capture?a=1&amp;b=2" method="POST">'
            . '<input type="hidden" name="cleantalk_hidden_action" value="https://external.example/submit">'
            . '<input type="hidden" name="cleantalk_hidden_method" value="get"></form>';
        $this->assertRewrittenPage(
            $this->page($expected_form . $foreign . $form),
            $this->rewrite($this->page($form . $foreign . $form), $fallback)
        );
    }

    public function provideAmbiguousForeignContexts()
    {
        $fake = '<form action=https://external.example/fake></form>';
        return $this->withBackends(array(
            'script string' => array('<svg><foreignObject><script>var markup = "</svg>' . $fake
                . '";</script></foreignObject></svg>'),
            'textarea' => array('<math><mtext><textarea></math>' . $fake . '</textarea></mtext></math>'),
            'self-closing raw text tag' => array('<svg><foreignObject><style/></foreignObject></svg>'),
            'incomplete quoted foreign tag' => array('<svg><g title="</svg>' . $fake),
        ));
    }

    /**
     * @dataProvider provideRewriteFailures
     */
    public function testRewriteFailureReturnsTheOriginalPageAndReportsTheReason($failure_at)
    {
        if ( $failure_at > 1 && ! class_exists('WP_HTML_Tag_Processor') ) {
            $this->markTestSkipped('The WordPress HTML API is unavailable.');
        }
        $form = '<form action="https://external.example/submit" method="get"><input name="email"></form>';
        $html = $this->page($form . '<p>Between</p>' . $form);
        $rewriter = new class (
            $html,
            'https://site.example',
            'https://site.example/capture'
        ) extends HtmlBufferRewriter {
            public $failure_at;
            private $processor_calls = 0;

            protected function createProcessor(string $html): ?HtmlTagProcessor
            {
                if ( ++$this->processor_calls === $this->failure_at ) {
                    throw new \UnexpectedValueException('External form rewrite test failure.');
                }
                return parent::createProcessor($html);
            }
        };
        $rewriter->failure_at = $failure_at;
        $reasons = array();
        $record_skip = static function ($caller, $reason) use (&$reasons) {
            $reasons[] = $reason;
        };
        add_action('apbct_skipped_request', $record_skip, 10, 2);
        try {
            $this->assertSame($html, $rewriter->rewrite());
            $this->assertSame(array(array('External form rewrite test failure.')), $reasons);
        } finally {
            remove_action('apbct_skipped_request', $record_skip, 10);
        }
    }

    public function provideRewriteFailures()
    {
        return array(
            'initial tokenizer failure' => array(1),
            'failure after preparing the first form' => array(3),
        );
    }

    /**
     * Apparent forms after short SCRIPT escapes must remain text in the next container.
     *
     * Prefixes reach each supported script state before the short escape. The
     * following "<script>" then stays ordinary script text until its real closer.
     *
     * @return array<string, string> Opaque fixtures shared by form-free and protected-form tests.
     */
    private function scriptEscapeContexts(): array
    {
        $fake = '<form action="https://external.example/fake"><input name="fake"></form>';
        $contexts = array();
        foreach ( array('data' => '', 'escaped' => '<!--x', 'double-escaped' => '<!--<script>') as $state => $prefix ) {
            foreach ( array('<!-->', '<!--->') as $ending ) {
                foreach ( array('textarea', 'title') as $container ) {
                    $contexts['short script escape: ' . $state . ' ' . $ending . ' ' . $container] =
                        '<script type="text/plain">' . $prefix . $ending . '<script></script>'
                        . '<' . $container . '></script>' . $fake . '</' . $container . '>';
                }
            }
        }
        return $contexts;
    }

    private function withBackends(array $cases): array
    {
        $result = array();
        foreach ( $this->provideBackends() as $backend => $arguments ) {
            foreach ( $cases as $name => $case ) {
                $result[$backend . ': ' . $name] = array_merge($arguments, $case);
            }
        }
        return $result;
    }

    private function assertRewrittenPage(string $expected, string $actual)
    {
        // Newer WordPress versions escape URI ampersands as numeric references instead of named references.
        $this->assertContains($actual, array($expected, str_replace('&amp;b=2', '&#038;b=2', $expected)));
    }

    private function rewrite(string $html, bool $fallback): string
    {
        if ( $fallback ) {
            $rewriter = new class (
                $html,
                'https://site.example',
                'https://site.example/capture?a=1&b=2'
            ) extends HtmlBufferRewriter {
                protected function createProcessor(string $html): ?HtmlTagProcessor
                {
                    return null;
                }
            };
        } else {
            if ( ! class_exists('WP_HTML_Tag_Processor') ) {
                $this->markTestSkipped('The WordPress HTML API is unavailable.');
            }
            $rewriter = new HtmlBufferRewriter($html, 'https://site.example', 'https://site.example/capture?a=1&b=2');
        }
        return $rewriter->rewrite();
    }

    private function mutationContexts(): string
    {
        $html = '';
        foreach ( array('noscript', 'textarea', 'title') as $tag ) {
            $payload = '<!--</' . $tag . '><img src=x onerror=alert(1)>-->';
            $html .= '<' . $tag . '><abbr title="'
                . htmlspecialchars($payload, ENT_QUOTES, 'UTF-8')
                . '">Text</abbr></' . $tag . '>';
        }
        return $html;
    }

    private function page(string $body): string
    {
        return '<!doctype html><html><head><meta charset="utf-8"></head><body>' . $body . '</body></html>';
    }
}
