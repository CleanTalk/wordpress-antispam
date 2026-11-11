<?php

namespace Cleantalk\ApbctWP\ExternalForms;

/**
 * Intercept external forms without reserializing the surrounding page.
 *
 * A preliminary scan hides text and foreign-content regions in an analysis copy.
 * The WordPress HTML API, when available, or the own tokenizer then supplies form
 * attributes. Both branches prepare byte-range replacements against the original
 * HTML, never return the masked copy, and leave unrelated page content untouched.
 *
 * This is not a complete HTML5 tree builder or a sanitizer. Ambiguous forms and
 * contexts are excluded rather than repaired; controlled failures preserve the page.
 *
 * Shared array shapes describe the data exchanged between scan and rewrite stages.
 * Tag/attribute end offsets are exclusive; patch length zero denotes an insertion.
 *
 * @psalm-type HtmlAttribute = array{name: string, start: int, end: int, value: string}
 * @psalm-type HtmlTag = array{
 *     start: int,
 *     end: int,
 *     name: string,
 *     closing: bool,
 *     self_closing: bool,
 *     attributes: list<HtmlAttribute>
 * }
 * @psalm-type HtmlForm = array{
 *     start: int,
 *     end: int,
 *     name: string,
 *     closing: bool,
 *     self_closing: bool,
 *     attributes: list<HtmlAttribute>,
 *     action: string|bool|null,
 *     method: string|bool|null
 * }
 * @psalm-type HtmlPatch = array{start: int, length: int, text: string}
 * @psalm-type HtmlScan = array{
 *     has_html: bool,
 *     has_rss: bool,
 *     forms: array<int, HtmlTag>,
 *     masked_html: string
 * }
 */
class HtmlBufferRewriter
{
    /** @var string */
    private $html;
    /** @var string|false|null */
    private $site_host;
    /** @var string */
    private $site_url;
    /** @var string */
    private $local_action;

    /**
     * Keep the original page and the URLs used to classify and intercept forms.
     *
     * @param string $html Original page bytes, retained for unchanged output on failure.
     * @param string $site_url Configured home URL; its host identifies internal actions.
     * @param string $local_action Already-sanitized local page URL receiving intercepted submissions.
     */
    public function __construct(string $html, string $site_url, string $local_action)
    {
        $this->html = $html;
        $this->site_url = $site_url;
        $this->site_host = parse_url($site_url, PHP_URL_HOST);
        $this->local_action = $local_action;
    }

    /**
     * Rewrite eligible forms, or preserve the entire page on a controlled failure.
     *
     * Technical failures are reported through apbct_skipped_request. They do not mean
     * that a spam check passed: Capture Buffer interception is skipped for this page.
     *
     * @return string Original HTML, optionally containing completed form replacements.
     */
    public function rewrite(): string
    {
        try {
            return $this->rewriteBuffer();
        } catch (\UnexpectedValueException $exception) {
            // A failed rewrite must not block the page or expose partially rewritten forms.
            $this->reportSkipped($exception->getMessage());
            return $this->html;
        }
    }

    /**
     * Collect form metadata and apply replacements only after preparation succeeds.
     *
     * The native tokenizer reads the masked analysis copy. Its form positions must
     * agree with the preliminary scan before they can address the original buffer.
     * Without the native API, scanned attributes feed the same form-rewriting logic.
     *
     * @return string Original HTML or the complete rewritten page.
     * @throws \UnexpectedValueException If scanning or replacement preparation cannot complete safely.
     */
    private function rewriteBuffer(): string
    {
        $scan = $this->scan();
        if ( ! $scan['has_html'] || $scan['has_rss'] || empty($scan['forms']) ) {
            return $this->html;
        }

        $processor = $this->createProcessor($scan['masked_html']);
        $forms = array();
        if ( $processor === null ) {
            foreach ( $scan['forms'] as $form ) {
                $form['action'] = $this->getAttribute($form, 'action');
                $form['method'] = $this->getAttribute($form, 'method');
                $forms[] = $form;
            }
        } else {
            while ( $processor->next_tag(array('tag_name' => 'FORM', 'tag_closers' => 'visit')) ) {
                $start = $processor->getTagStart();
                if ( ! isset($scan['forms'][$start]) ) {
                    $this->reportSkipped('External form tokenizers disagree.');
                    return $this->html;
                }
                $form = $scan['forms'][$start];
                if ( $form['closing'] !== $processor->is_tag_closer() ) {
                    $this->reportSkipped('External form tokenizers disagree on the closing tag.');
                    return $this->html;
                }
                $form['action'] = $processor->get_attribute('action');
                $form['method'] = $processor->get_attribute('method');
                $forms[] = $form;
            }
            if ( count($forms) !== count($scan['forms']) ) {
                $this->reportSkipped('External form tokenizers disagree on the number of form tags.');
                return $this->html;
            }
        }

        return $this->applyPatches($this->html, $this->getFormPatches($forms, $processor !== null));
    }

    /**
     * Select the native HTML API by availability, not by the WordPress version string.
     *
     * The parent class was introduced in WP 6.2. Checking it before loading the
     * adapter also permits the own tokenizer to work where that parent is absent.
     *
     * @param string $html Analysis copy or isolated opening-tag fragment to process.
     * @return HtmlTagProcessor|null Null selects the own tokenizer, not disabled Capture Buffer.
     */
    protected function createProcessor(string $html): ?HtmlTagProcessor
    {
        return class_exists('WP_HTML_Tag_Processor') ? new HtmlTagProcessor($html) : null;
    }

    /**
     * Locate explicit form tags while excluding contexts that must not be rewritten.
     *
     * Opaque regions are replaced with equally many spaces in a separate analysis
     * copy. Byte positions therefore still address the original HTML. Both branches
     * use this scan; the native API does not replace its context-boundary decisions.
     *
     * @return array HTML/RSS flags, form tokens indexed by start offset, and masked_html.
     * @psalm-return HtmlScan
     * @throws \UnexpectedValueException If an opaque-context scan fails.
     */
    private function scan(): array
    {
        $length = strlen($this->html);
        $at = 0;
        $copied_to = 0;
        $parts = array();
        $forms = array();
        $has_html = false;
        $has_rss = false;

        while ( $at < $length && ($start = strpos($this->html, '<', $at)) !== false ) {
            $opaque_end = $this->getMarkupEnd($start, false);
            if ( $opaque_end === null ) {
                $tag = $this->readTag($start);
                if ( $tag === null ) {
                    if ( $this->isTagStart($start) ) {
                        $opaque_end = $length;
                    } else {
                        $at = $start + 1;
                        continue;
                    }
                } else {
                    if ( ! $tag['closing'] ) {
                        if ( $tag['name'] === 'svg' || $tag['name'] === 'math' ) {
                            $opaque_end = $tag['self_closing'] ? $tag['end'] : $this->getForeignEnd($tag);
                        } elseif ( $tag['name'] === 'plaintext' ) {
                            $opaque_end = $length;
                        } elseif ( $tag['name'] === 'script' ) {
                            $opaque_end = $this->getScriptEnd($tag['end']);
                        } elseif ( $this->isRawTextTag($tag['name']) ) {
                            $opaque_end = $this->getRawTextEnd($tag['name'], $tag['end']);
                        }
                    }
                    if ( $opaque_end === null ) {
                        $has_html = $has_html || ($tag['name'] === 'html' && ! $tag['closing']);
                        $has_rss = $has_rss || ($tag['name'] === 'rss' && ! $tag['closing']);
                        if ( $tag['name'] === 'form' ) {
                            $forms[$start] = $tag;
                        }
                        $at = $tag['end'];
                        continue;
                    }
                }
            }

            $parts[] = substr($this->html, $copied_to, $start - $copied_to);
            // Keep byte offsets while hiding contexts whose contents must never be edited as form tags.
            $parts[] = str_repeat(' ', $opaque_end - $start);
            $copied_to = $opaque_end;
            $at = $opaque_end;
        }

        $parts[] = substr($this->html, $copied_to);
        return array(
            'has_html' => $has_html,
            'has_rss' => $has_rss,
            'forms' => $forms,
            'masked_html' => implode('', $parts),
        );
    }

    /**
     * Skip comments and declarations without interpreting their contents as tags.
     *
     * CDATA uses its own terminator only in foreign content. Quoted DOCTYPE values
     * may contain ">". Unterminated markup excludes the remainder of the buffer.
     * Comment terminators are searched together so each comment scans only its
     * own contents, not the whole remaining page for an absent alternative.
     *
     * @param int $start Byte offset of the opening "<" in the original HTML.
     * @param bool $foreign Whether this position is inside SVG or MathML.
     * @return int|null Exclusive end offset, or null when normal tag parsing should be attempted.
     * @throws \UnexpectedValueException If the regular-expression engine cannot scan a comment.
     */
    private function getMarkupEnd(int $start, bool $foreign): ?int
    {
        $length = strlen($this->html);
        if ( substr($this->html, $start, 4) === '<!--' ) {
            if ( substr($this->html, $start, 5) === '<!-->' ) {
                return $start + 5;
            }
            if ( substr($this->html, $start, 6) === '<!--->' ) {
                return $start + 6;
            }
            $matched = preg_match('/--!?>/', $this->html, $match, PREG_OFFSET_CAPTURE, $start + 4);
            if ( $matched === 0 ) {
                return $length;
            }
            if ( $matched === false || ! isset($match[0]) ) {
                throw new \UnexpectedValueException('Unable to scan the comment context.');
            }
            return $match[0][1] + strlen($match[0][0]);
        }
        if ( $foreign && substr($this->html, $start, 9) === '<![CDATA[' ) {
            $end = strpos($this->html, ']]>', $start + 9);
            return $end === false ? $length : $end + 3;
        }
        if ( strcasecmp(substr($this->html, $start, 9), '<!DOCTYPE') === 0 ) {
            $quote = null;
            for ( $at = $start + 9; $at < $length; $at++ ) {
                $character = $this->html[$at];
                if ( $quote !== null ) {
                    if ( $character === $quote ) {
                        $quote = null;
                    }
                } elseif ( $character === '"' || $character === "'" ) {
                    $quote = $character;
                } elseif ( $character === '>' ) {
                    return $at + 1;
                }
            }
            return $length;
        }
        if ( substr($this->html, $start, 2) === '<!' || substr($this->html, $start, 2) === '<?' ) {
            $end = strpos($this->html, '>', $start + 2);
            return $end === false ? $length : $end + 1;
        }
        if ( substr($this->html, $start, 2) === '</' ) {
            $next = substr($this->html, $start + 2, 1);
            if ( $next !== '' && ! preg_match('/[a-z]/i', $next) ) {
                $end = strpos($this->html, '>', $start + 2);
                return $end === false ? $length : $end + 1;
            }
        }
        return null;
    }

    /**
     * Distinguish a possible tag prefix from an ordinary literal "<".
     *
     * This does not validate the whole tag. After readTag() fails, a matching
     * prefix means an incomplete tag may consume the rest of the source.
     *
     * @param int $start Byte offset of the possible opening "<".
     * @return bool Whether "<" or "</" is followed by an ASCII letter.
     */
    private function isTagStart(int $start): bool
    {
        return preg_match('/\A<\/?[a-z]/i', substr($this->html, $start, 3)) === 1;
    }

    /**
     * Read one lexical tag, keeping source ranges and undecoded attribute values.
     *
     * Quotes protect embedded ">" characters from ending the tag. Tag and attribute
     * names are lowercased for comparison, but their source bytes are not changed.
     * No DOM ancestry or browser tree-construction rules are inferred here.
     *
     * @param int $start Byte offset of the opening "<" in the original HTML.
     * @return array|null Tag name, closing/self-closing flags, start/end offsets and attributes;
     *                    null for a non-tag or incomplete tag. All end offsets are exclusive.
     * @psalm-return HtmlTag|null
     */
    private function readTag(int $start): ?array
    {
        $length = strlen($this->html);
        $at = $start + 1;
        $closing = $at < $length && $this->html[$at] === '/';
        $at += $closing ? 1 : 0;
        if ( $at >= $length || ! preg_match('/[a-z]/i', $this->html[$at]) ) {
            return null;
        }

        $name_start = $at;
        $at += strcspn($this->html, " \t\n\f\r/>", $at);
        $name = strtolower(substr($this->html, $name_start, $at - $name_start));
        $attributes = array();
        while ( $at < $length ) {
            $at += strspn($this->html, " \t\n\f\r", $at);
            if ( $at >= $length ) {
                break;
            }
            if ( $this->html[$at] === '>' || substr($this->html, $at, 2) === '/>' ) {
                $self_closing = $this->html[$at] === '/';
                return array(
                    'start' => $start,
                    'end' => $at + ($self_closing ? 2 : 1),
                    'name' => $name,
                    'closing' => $closing,
                    'self_closing' => $self_closing,
                    'attributes' => $attributes,
                );
            }
            if ( $this->html[$at] === '/' ) {
                ++$at;
                continue;
            }

            $attribute_start = $at;
            if ( $this->html[$at] === '=' ) {
                ++$at;
            }
            $at += strcspn($this->html, " \t\n\f\r/>=", $at);
            $attribute_name = strtolower(substr($this->html, $attribute_start, $at - $attribute_start));
            $attribute_end = $at;
            $at += strspn($this->html, " \t\n\f\r", $at);
            $value = '';
            if ( $at < $length && $this->html[$at] === '=' ) {
                ++$at;
                $at += strspn($this->html, " \t\n\f\r", $at);
                if ( $at >= $length ) {
                    break;
                }
                if ( $this->html[$at] === '"' || $this->html[$at] === "'" ) {
                    $quote = $this->html[$at++];
                    $value_start = $at;
                    $at = strpos($this->html, $quote, $at);
                    if ( $at === false ) {
                        return null;
                    }
                    $value = substr($this->html, $value_start, $at - $value_start);
                    ++$at;
                } else {
                    $value_start = $at;
                    $at += strcspn($this->html, " \t\n\f\r>", $at);
                    $value = substr($this->html, $value_start, $at - $value_start);
                }
                $attribute_end = $at;
            }
            $attributes[] = array(
                'name' => $attribute_name,
                'start' => $attribute_start,
                'end' => $attribute_end,
                'value' => $value,
            );
        }
        return null;
    }

    /**
     * Find the matching end tag without scanning apparent forms in text content.
     *
     * A name prefix alone is insufficient: "</textarea-extra>" must not terminate
     * TEXTAREA. An absent or incomplete closing tag excludes the remaining buffer.
     *
     * @param string $name Lowercase container name.
     * @param int $at First byte after its opening tag.
     * @return int Exclusive closing-tag end, or the buffer length if no complete closer is found.
     */
    private function getRawTextEnd(string $name, int $at): int
    {
        while ( ($start = stripos($this->html, '</' . $name, $at)) !== false ) {
            $after_name = $start + strlen($name) + 2;
            if ( $after_name < strlen($this->html) && strspn($this->html, " \t\n\f\r/>", $after_name, 1) === 1 ) {
                $tag = $this->readTag($start);
                return $tag === null ? strlen($this->html) : $tag['end'];
            }
            $at = $after_name;
        }
        return strlen($this->html);
    }

    /**
     * Select text containers handled by matching their end tag rather than child tags.
     *
     * This group includes raw-text elements, TITLE/TEXTAREA (RCDATA), and NOSCRIPT
     * for browsers with scripting enabled. SCRIPT needs separate escape-state handling.
     *
     * @param string $name Lowercase tag name.
     * @return bool Whether the container's contents should be masked as opaque text.
     */
    private function isRawTextTag(string $name): bool
    {
        return in_array(
            $name,
            array('style', 'textarea', 'title', 'noscript', 'iframe', 'xmp', 'noembed', 'noframes'),
            true
        );
    }

    /**
     * Locate a SCRIPT closer using a limited HTML script-data state machine.
     *
     * "data" is ordinary script text; "escaped" follows a legacy "<!--" wrapper.
     * In "double-escaped" text, the next "</script>" returns to the escaped state
     * instead of closing the element. JavaScript syntax and string quotes are not
     * parsed: HTML script-data states determine where the element ends.
     * Short "<!-->" and "<!--->" sequences immediately return to data. They must
     * be consumed whole because their final dash-dash transition overlaps "<!--".
     *
     * @param int $at First byte after the opening SCRIPT tag.
     * @return int Exclusive closing-tag end, or the buffer length for an incomplete context.
     * @throws \UnexpectedValueException If the regular-expression engine cannot scan the context.
     */
    private function getScriptEnd(int $at): int
    {
        $state = 'data';
        while ( true ) {
            $matched = preg_match(
                '/<!--(?:-?>)?|-->|<\/?script(?=[ \t\n\f\r\/>])/i',
                $this->html,
                $match,
                PREG_OFFSET_CAPTURE,
                $at
            );
            if ( $matched === 0 ) {
                return strlen($this->html);
            }
            if ( $matched === false || ! isset($match[0]) ) {
                throw new \UnexpectedValueException('Unable to scan the script context.');
            }
            $token = strtolower($match[0][0]);
            $start = $match[0][1];
            $at = $start + strlen($token);
            if ( $token === '-->' || $token === '<!-->' || $token === '<!--->' ) {
                $state = 'data';
            } elseif ( $token === '<!--' && $state === 'data' ) {
                $state = 'escaped';
            } elseif ( $token === '<script' && $state === 'escaped' ) {
                $state = 'double-escaped';
            } elseif ( $token === '</script' ) {
                if ( $state === 'double-escaped' ) {
                    $state = 'escaped';
                    continue;
                }
                $tag = $this->readTag($start);
                return $tag === null ? strlen($this->html) : $tag['end'];
            }
        }
    }

    /**
     * Exclude an SVG/MathML region, accounting for nested tags of the same root name.
     *
     * HTML integration points can make lexical matching unreliable. When their
     * text boundary is ambiguous, return the buffer length: scan() then also
     * excludes later forms instead of guessing where foreign content ends.
     *
     * @param array $opening Root tag metadata from readTag(), with original byte offsets.
     * @psalm-param HtmlTag $opening
     * @return int Exclusive root-closing end, or the buffer length when no trusted boundary is found.
     */
    private function getForeignEnd(array $opening): int
    {
        $depth = 1;
        $at = $opening['end'];
        while ( ($start = strpos($this->html, '<', $at)) !== false ) {
            $opaque_end = $this->getMarkupEnd($start, true);
            if ( $opaque_end !== null ) {
                $at = $opaque_end;
                continue;
            }
            $tag = $this->readTag($start);
            if ( $tag === null ) {
                if ( $this->isTagStart($start) ) {
                    $this->reportSkipped('Incomplete foreign tag; the remaining buffer was left unchanged.');
                    return strlen($this->html);
                }
                $at = $start + 1;
                continue;
            }
            if ( ! $tag['closing'] && $tag['name'] === 'plaintext' ) {
                return strlen($this->html);
            }
            if ( ! $tag['closing'] && ($tag['name'] === 'script' || $this->isRawTextTag($tag['name'])) ) {
                $next = strpos($this->html, '<', $tag['end']);
                $closing = $next === false ? null : $this->readTag($next);
                if ( $tag['self_closing'] || $closing === null
                    || ! $closing['closing'] || $closing['name'] !== $tag['name']
                ) {
                    // Foreign integration points can contain HTML raw text, e.g. "</svg>" in a script string.
                    // Nested markup or a self-closing HTML tag makes the foreign boundary ambiguous:
                    // leave the remaining buffer unchanged rather than rewrite potential script/text contents.
                    $this->reportSkipped('Ambiguous foreign content; the remaining buffer was left unchanged.');
                    return strlen($this->html);
                }
                $at = $closing['end'];
                continue;
            }
            if ( $tag['name'] === $opening['name'] ) {
                if ( $tag['closing'] ) {
                    --$depth;
                } elseif ( ! $tag['self_closing'] ) {
                    ++$depth;
                }
                if ( $depth === 0 ) {
                    return $tag['end'];
                }
            }
            $at = $tag['end'];
        }
        return strlen($this->html);
    }

    /**
     * Obtain a fallback attribute value after HTML input normalization and reference decoding.
     *
     * The first matching attribute wins. Duplicate action/method attributes are
     * checked separately before a form can be rewritten.
     *
     * @param array $tag Scanned tag metadata containing raw attribute values.
     * @psalm-param HtmlTag $tag
     * @param string $name Lowercase attribute name.
     * @return string Decoded value, or an empty string for a missing attribute.
     */
    private function getAttribute(array $tag, string $name): string
    {
        foreach ( $tag['attributes'] as $attribute ) {
            if ( $attribute['name'] === $name ) {
                $value = str_replace(array("\r\n", "\r", "\0"), array("\n", "\n", "\xEF\xBF\xBD"), $attribute['value']);
                return $this->decodeAttributeValue($value);
            }
        }
        return '';
    }

    /**
     * Decode numeric references with HTML rules, retaining PHP's named-reference behavior.
     *
     * Numeric references consume their digits once. Disjoint literal segments go
     * through PHP's decoder, never the already-decoded numeric output. This keeps
     * the pass linear and prevents references such as "&amp;#47;" decoding twice.
     * Semicolonless named references remain unsupported, as before.
     *
     * @param string $value Attribute bytes after input newline and NUL normalization.
     * @return string Decoded attribute value, without recursive decoding.
     */
    private function decodeAttributeValue(string $value): string
    {
        $parts = array();
        $at = 0;
        $length = strlen($value);
        while ( $at < $length && ($start = strpos($value, '&#', $at)) !== false ) {
            $parts[] = html_entity_decode(substr($value, $at, $start - $at), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $reference = $this->decodeNumericReference($value, $start);
            if ( $reference === null ) {
                $parts[] = '&#';
                $at = $start + 2;
            } else {
                $parts[] = $reference['value'];
                $at = $reference['end'];
            }
        }
        $parts[] = html_entity_decode(substr($value, $at), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return implode('', $parts);
    }

    /**
     * Consume a decimal or hexadecimal reference, with an optional semicolon.
     *
     * Saturation above the Unicode range prevents integer overflow, even on
     * 32-bit PHP. Remaining digits are still consumed before decoding continues.
     *
     * @param string $value Complete normalized attribute value.
     * @param int $start Byte offset of the reference's opening ampersand.
     * @return array{value: string, end: int}|null Decoded bytes and exclusive end, or null without digits.
     */
    private function decodeNumericReference(string $value, int $start): ?array
    {
        $length = strlen($value);
        $at = $start + 2;
        $base = 10;
        if ( $at < $length && ($value[$at] === 'x' || $value[$at] === 'X') ) {
            $base = 16;
            ++$at;
        }
        $digits_start = $at;
        $code_point = 0;
        while ( $at < $length ) {
            $byte = ord($value[$at]);
            if ( $byte >= 48 && $byte <= 57 ) {
                $digit = $byte - 48;
            } elseif ( $base === 16 && $byte >= 65 && $byte <= 70 ) {
                $digit = $byte - 55;
            } elseif ( $base === 16 && $byte >= 97 && $byte <= 102 ) {
                $digit = $byte - 87;
            } else {
                break;
            }
            if ( $code_point <= 0x10FFFF ) {
                $code_point = min(0x110000, $code_point * $base + $digit);
            }
            ++$at;
        }
        if ( $at === $digits_start ) {
            return null;
        }
        if ( $at < $length && $value[$at] === ';' ) {
            ++$at;
        }
        return array('value' => $this->encodeReferenceCodePoint($code_point), 'end' => $at);
    }

    /**
     * Apply HTML numeric-reference replacements and encode the resulting Unicode scalar.
     *
     * NUL, surrogates, and out-of-range values become U+FFFD. HTML also remaps
     * selected C1 controls using Windows-1252; other controls and noncharacters
     * are retained. Encoding here avoids mbstring and PHP entity-decoder differences.
     *
     * @param int $code_point Numeric value, saturated above the Unicode maximum.
     * @return string UTF-8 bytes for exactly one resulting code point.
     */
    private function encodeReferenceCodePoint(int $code_point): string
    {
        static $replacements = array(
            0x80 => 0x20AC, 0x82 => 0x201A, 0x83 => 0x0192, 0x84 => 0x201E, 0x85 => 0x2026,
            0x86 => 0x2020, 0x87 => 0x2021, 0x88 => 0x02C6, 0x89 => 0x2030, 0x8A => 0x0160,
            0x8B => 0x2039, 0x8C => 0x0152, 0x8E => 0x017D, 0x91 => 0x2018, 0x92 => 0x2019,
            0x93 => 0x201C, 0x94 => 0x201D, 0x95 => 0x2022, 0x96 => 0x2013, 0x97 => 0x2014,
            0x98 => 0x02DC, 0x99 => 0x2122, 0x9A => 0x0161, 0x9B => 0x203A, 0x9C => 0x0153,
            0x9E => 0x017E, 0x9F => 0x0178,
        );
        if ( $code_point === 0 || $code_point > 0x10FFFF || ($code_point >= 0xD800 && $code_point <= 0xDFFF) ) {
            $code_point = 0xFFFD;
        } elseif ( isset($replacements[$code_point]) ) {
            $code_point = $replacements[$code_point];
        }
        if ( $code_point <= 0x7F ) {
            return chr($code_point);
        }
        if ( $code_point <= 0x7FF ) {
            return chr(0xC0 | ($code_point >> 6)) . chr(0x80 | ($code_point & 0x3F));
        }
        if ( $code_point <= 0xFFFF ) {
            return chr(0xE0 | ($code_point >> 12)) . chr(0x80 | (($code_point >> 6) & 0x3F))
                . chr(0x80 | ($code_point & 0x3F));
        }
        return chr(0xF0 | ($code_point >> 18)) . chr(0x80 | (($code_point >> 12) & 0x3F))
            . chr(0x80 | (($code_point >> 6) & 0x3F)) . chr(0x80 | ($code_point & 0x3F));
    }

    /**
     * Pair explicit FORM tags and prepare the local POST relay and its hidden metadata.
     *
     * The existing action-host comparison decides which forms are external.
     * Nested forms, duplicate action/method attributes, and missing closers prevent
     * rewriting. Original action and method values are retained in hidden inputs
     * inserted before the paired closing tag; a missing/empty method defaults to get.
     *
     * @param array $forms Ordered form tokens enriched with parsed action and method values.
     * @psalm-param list<HtmlForm> $forms
     * @param bool $native Whether opening tags should be edited through the WordPress API.
     * @return array Replacement and insertion records relative to the original page bytes.
     * @psalm-return list<HtmlPatch>
     * @throws \UnexpectedValueException If an eligible opening tag cannot be updated.
     */
    private function getFormPatches(array $forms, bool $native): array
    {
        $patches = array();
        /** @var HtmlForm|null $opening */
        $opening = null;
        $ambiguous = false;
        $depth = 0;
        foreach ( $forms as $form ) {
            if ( ! $form['closing'] ) {
                if ( $opening !== null ) {
                    $ambiguous = true;
                } else {
                    $opening = $form;
                    $ambiguous = $this->hasDuplicateAttributes($form);
                }
                ++$depth;
                continue;
            }
            if ( $opening === null ) {
                continue;
            }
            if ( --$depth !== 0 ) {
                continue;
            }
            $action = is_string($opening['action']) && $opening['action'] !== ''
                ? $opening['action']
                : $this->site_url;
            $action_host = parse_url($action, PHP_URL_HOST);
            if ( is_string($action_host) && $action_host !== $this->site_host ) {
                if ( $ambiguous ) {
                    $this->reportSkipped('Ambiguous external form was left unchanged.');
                } else {
                    $method = is_string($opening['method']) ? $opening['method'] : '';
                    $method = $method ?: 'get';
                    $patches[] = array(
                        'start' => $opening['start'],
                        'length' => $opening['end'] - $opening['start'],
                        'text' => $this->rewriteOpeningTag($opening, $native),
                    );
                    $patches[] = array(
                        'start' => $form['start'],
                        'length' => 0,
                        'text' => '<input type="hidden" name="cleantalk_hidden_action" value="'
                            . $this->escapeAttribute($action) . '">'
                            . '<input type="hidden" name="cleantalk_hidden_method" value="'
                            . $this->escapeAttribute($method) . '">',
                    );
                }
            }
            $opening = null;
        }
        if ( $opening !== null ) {
            $this->reportSkipped('Unclosed form was left unchanged.');
        }
        return $patches;
    }

    /**
     * Reject ambiguous relay metadata, without treating every duplicate attribute alike.
     *
     * Only action and method participate in this check. readTag() has already
     * lowercased names, so differing source capitalization does not hide duplicates.
     *
     * @param array $tag Scanned opening-tag metadata.
     * @psalm-param HtmlTag $tag
     * @return bool Whether action or method occurs more than once.
     */
    private function hasDuplicateAttributes(array $tag): bool
    {
        $seen = array();
        foreach ( $tag['attributes'] as $attribute ) {
            if ( $attribute['name'] !== 'action' && $attribute['name'] !== 'method' ) {
                continue;
            }
            if ( isset($seen[$attribute['name']]) ) {
                return true;
            }
            $seen[$attribute['name']] = true;
        }
        return false;
    }

    /**
     * Change action and method in an isolated opening-tag fragment.
     *
     * The native API receives only this fragment, never the complete page or the
     * masked analysis copy. The fallback replaces scanned attribute spans and adds
     * method when absent, converting page offsets into fragment-relative offsets.
     *
     * @param array $tag Eligible opening FORM metadata with original byte ranges.
     * @psalm-param HtmlTag $tag
     * @param bool $native Whether to use the WordPress attribute-editing API.
     * @return string Opening tag targeting the local page with method POST.
     * @throws \UnexpectedValueException If the tag or its replacements cannot be updated safely.
     */
    private function rewriteOpeningTag(array $tag, bool $native): string
    {
        $html = substr($this->html, $tag['start'], $tag['end'] - $tag['start']);
        if ( $native ) {
            $processor = $this->createProcessor($html);
            if ( $processor === null
                || ! $processor->next_tag('FORM')
                || ! $processor->set_attribute('action', $this->local_action)
                || ! $processor->set_attribute('method', 'POST')
            ) {
                throw new \UnexpectedValueException('Unable to update the external form tag.');
            }
            return $processor->get_updated_html();
        }

        $patches = array();
        $method_found = false;
        foreach ( $tag['attributes'] as $attribute ) {
            if ( $attribute['name'] === 'method' || $attribute['name'] === 'action' ) {
                $method_found = $method_found || $attribute['name'] === 'method';
                $patches[] = array(
                    'start' => $attribute['start'] - $tag['start'],
                    'length' => $attribute['end'] - $attribute['start'],
                    'text' => $attribute['name'] . '="' . $this->escapeAttribute(
                        $attribute['name'] === 'action' ? $this->local_action : 'POST'
                    ) . '"',
                );
            }
        }
        if ( ! $method_found ) {
            $patches[] = array(
                'start' => strlen($html) - ($tag['self_closing'] ? 2 : 1),
                'length' => 0,
                'text' => ' method="POST"',
            );
        }
        return $this->applyPatches($html, $patches);
    }

    /**
     * Encode a parsed value for insertion into a double-quoted HTML attribute.
     *
     * Remaining literal entities must also be escaped: a decoded value containing
     * "&amp;" needs "&amp;amp;" in the source to retain that literal value in the browser.
     *
     * @param string $value Parsed attribute value, not its original encoded source.
     * @return string Escaped attribute contents, without surrounding quotes.
     */
    private function escapeAttribute(string $value): string
    {
        // Parsed attribute values must be encoded once, including any remaining literal entities.
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Assemble replacements in one pass without shifting subsequent source coordinates.
     *
     * Every record has start, length and text; length zero denotes an insertion.
     * Positions refer to the supplied, unchanged HTML string. Sorting by start lets
     * untouched byte slices be copied directly and rejects overlapping ranges.
     * Nothing is committed to the caller until the complete result is assembled.
     *
     * @param string $html Original page or opening-tag fragment.
     * @param array $patches Byte-range replacements and zero-length insertions.
     * @psalm-param list<HtmlPatch> $patches
     * @return string HTML containing all requested patches, or the same string if none are requested.
     * @throws \UnexpectedValueException If replacements overlap or exceed the supplied string.
     */
    private function applyPatches(string $html, array $patches): string
    {
        if ( empty($patches) ) {
            return $html;
        }
        usort(
            $patches,
            /**
             * Order patches by their source positions, not by rewritten output positions.
             *
             * @param array $left First patch.
             * @param array $right Second patch.
             * @psalm-param HtmlPatch $left
             * @psalm-param HtmlPatch $right
             * @return int Relative order of the original byte offsets.
             */
            static function (array $left, array $right) {
                return $left['start'] <=> $right['start'];
            }
        );
        $parts = array();
        $at = 0;
        foreach ( $patches as $patch ) {
            if ( $patch['start'] < $at || $patch['start'] + $patch['length'] > strlen($html) ) {
                throw new \UnexpectedValueException('External form replacements overlap or exceed the HTML buffer.');
            }
            $parts[] = substr($html, $at, $patch['start'] - $at);
            $parts[] = $patch['text'];
            $at = $patch['start'] + $patch['length'];
        }
        $parts[] = substr($html, $at);
        return implode('', $parts);
    }

    /**
     * Emit skip metadata through the existing plugin hook without including page contents.
     *
     * This reports skipped interception, not a successful spam check. The method
     * itself does not output an error page or change the HTTP response status.
     *
     * @param string $reason Technical explanation suitable for skip diagnostics.
     * @return void
     */
    private function reportSkipped(string $reason)
    {
        do_action('apbct_skipped_request', __METHOD__, array($reason));
    }
}
