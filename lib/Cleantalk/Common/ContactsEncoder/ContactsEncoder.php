<?php

namespace Cleantalk\Common\ContactsEncoder;

use Cleantalk\Common\Antispam\Cleantalk;
use Cleantalk\Common\Antispam\CleantalkRequest;
use Cleantalk\Common\ContactsEncoder\Helper\ContactsEncoderHelper;
use Cleantalk\Common\ContactsEncoder\Dto\Params;
use Cleantalk\Common\ContactsEncoder\Encoder\Encoder;
use Cleantalk\Common\ContactsEncoder\Exclusions\ExclusionsService;
use Cleantalk\Common\ContactsEncoder\Obfuscator\Obfuscator;
use Cleantalk\Common\ContactsEncoder\Obfuscator\ObfuscatorEmailData;

/**
 * Contacts Encoder common class.
 */
class ContactsEncoder
{
    const VERSION = '2.1.1';

    /**
     * Found contacts to process per request, all passes of nested render filters together.
     * A page that exceeds it stays as is instead of running out of the execution time.
     */
    const MAX_CONTACT_MATCHES_PER_REQUEST = 200000;

    /**
     * Possible contacts in one string. Checked before a regular expression: its matches take the memory.
     */
    const MAX_CONTACT_SIGNS_PER_CONTENT = 5000;

    /**
     * Encoded contacts per request. Each one adds about 0.5 KB of markup to all outer passes.
     */
    const MAX_ENCODED_CONTACTS_PER_REQUEST = 2000;

    protected $api_key;

    /**
     * @var Encoder
     */
    public $encoder;

    /**
     * @var ContactsEncoderHelper
     */
    protected $helper;

    /**
     * @var ExclusionsService
     */
    protected $exclusions;

    /**
     * Temporary content to use in regexp callback
     * @var string
     */
    protected $temp_content;

    /**
     * Regular expressions parts.
     * Lengths are limited by RFC 5321: each attempt to match costs a limited number of steps without PCRE JIT too.
     */
    const EMAIL_PATTERN = '[_A-Za-z0-9-\.]{1,64}@[_A-Za-z0-9-\.]{1,255}\.[A-Za-z]{2,63}\b';
    const EMAIL_PATTERN_DOMAIN_CATCHING = '[_A-Za-z0-9-\.]{1,64}@[_A-Za-z0-9-\.]{1,255}(\.[A-Za-z]{2,63}\b)';
    const PHONE_NUMBER = '\+\d{8,12}';
    /**
     * URI schemes are case-insensitive (RFC 3986). Clients often write Mailto:/Tel:.
     */
    const MAILTO_SCHEME_PATTERN = '(?i:mailto):';
    const TEL_SCHEME_PATTERN = '(?i:tel):';
    /**
     * Optional query string following a mailto email (e.g. "?Subject=...&cc=...").
     * It is captured together with the email instead of leaking out of the match.
     *
     * Two alternatives are used on purpose:
     *  - inside an attribute the query may contain spaces ("?subject=Hello World"),
     *    so it is only terminated by the closing quote;
     *  - in plain text there is no closing quote, so the query must stop at the
     *    first whitespace instead of swallowing the rest of the sentence.
     */
    const MAILTO_QUERY_PATTERN = '(?:\?(?:[^"\'<>]{0,2048}?(?=["\'])|[^"\'<>\s]{0,2048}))?';
    const PHONE_NUMBERS_PATTERNS = [
        '(' . self::TEL_SCHEME_PATTERN . self::PHONE_NUMBER . ')', // tel:+XXXXXXXXXX
        '([\+][\s-]?\(?\d[\d\s\-()]{7,}\d)',                       // +X XXX XXXXXXX, +X(XXX)XXXXX, etc.
        '(\(\d{3}\)\s?\d{3}-\d{4})',                               // (XXX) XXX-XXXX, (XXX) XXX XXXX
        '(\+\d{1,3}\.\d{1,3}\.((\d{3}\.\d{4})|\d{7})(?![\w.]))',   // +X?.XX?.XXX.XXXX
    ];

    /**
     * @var string example: '/((?i:mailto):\b[_A-Za-z0-9-\.]+@[_A-Za-z0-9-\.]+\.[A-Za-z]{2,}\b(?:\?(?:[^"\'<>]*?(?=["\'])|[^"\'<>\s]*))?)|(\b[_A-Za-z0-9-\.]+@[_A-Za-z0-9-\.]+(\.[A-Za-z]{2,}\b))/'
     */
    protected $global_email_pattern;

    /**
     * @var string example: '/((?i:tel):\+\d{8,12})|([\+][\s-]?\(?\d[\d\s\-()]{7,}\d)|(\(\d{3}\)\s?\d{3}-\d{4})|(\+\d{1,3}\.\d{1,3}\.((\d{3}\.\d{4})|\d{7})(?![\w.]))/'
     */
    protected $global_phones_pattern;

    /**
     * @var string example: '/(?i:mailto):(\b[_A-Za-z0-9-\.]+@[_A-Za-z0-9-\.]+\.[A-Za-z]{2,})(?:\?(?:[^"\'<>]*?(?=["\'])|[^"\'<>\s]*))?/'
     */
    protected $global_mailto_pattern;

    /**
     * @var string example: '/(\b[_A-Za-z0-9-\.]+@[_A-Za-z0-9-\.]+\.[A-Za-z]{2,}\b)/'
     */
    protected $plain_email_pattern;

    /**
     * @var string example: '/(?i:tel):(\+\d{8,12})/'
     * @ToDo Is this regexp is actual and right?
     */
    protected $global_tel_pattern;

    /**
     * @var array Placeholder => original aria-label for restore
     */
    protected $aria_placeholders = array();

    /**
     * Attributes with possible email-like content to drop from the content to avoid unnecessary encoding.
     * Key is a tag we want to find, value is an attribute with email to drop.
     * @var array
     */
    protected static $attributes_to_drop = array(
        'a' => 'title',
    );

    /**
     * @var string
     */
    protected $global_obfuscation_mode;

    /**
     * @var string
     */
    protected $global_replacing_text;

    /**
     * @var int|mixed
     */
    protected $do_encode_emails;

    /**
     * @var int|mixed
     */
    protected $do_encode_phones;

    /**
     * @var bool
     * @psalm-suppress PossiblyUnusedProperty
     */
    protected $is_encode_allowed = true;

    /**
     * @var bool
     */
    protected $is_logged_in = false;

    protected $request_result_comment;

    protected $decoded_contacts_array = [];

    /**
     * @var int
     */
    protected $contact_matches_processed = 0;

    /**
     * @var int
     */
    protected $encoded_contacts_count = 0;

    protected static $instance;

    public function __construct()
    {
    }

    public function __wakeup()
    {
    }

    public function __clone()
    {
    }

    /**
     * Constructor
     * @return $this
     * @throws \Exception
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function getInstance(Params $params)
    {
        if ( ! isset(static::$instance) ) {
            static::$instance = new static();
            static::$instance->init($params);
        }
        return static::$instance;
    }

    /**
     * @return void
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function dropInstance()
    {
        self::$instance = null;
    }

    /**
     * @param Params $params
     *
     * @return void
     */
    protected function init(Params $params)
    {
        $this->api_key = $params->api_key;
        $this->exclusions = new ExclusionsService($params);
        $this->encoder = new Encoder(md5($params->api_key));
        $this->helper = new ContactsEncoderHelper();
        $this->global_obfuscation_mode = $params->obfuscation_mode;
        $this->global_replacing_text = $params->obfuscation_text;
        $this->do_encode_emails = $params->do_encode_emails;
        $this->do_encode_phones = $params->do_encode_phones;
        $this->is_logged_in = $params->is_logged_in;
        $this->prepareRegularExpressions();

        if ($this->is_logged_in) {
            $this->ignoreOpenSSLMode();
        }
    }

    private function prepareRegularExpressions()
    {
        $this->global_email_pattern = '/(' . self::MAILTO_SCHEME_PATTERN . '\b' . self::EMAIL_PATTERN . self::MAILTO_QUERY_PATTERN . ')|(\b' . self::EMAIL_PATTERN_DOMAIN_CATCHING . ')/';
        $this->global_phones_pattern = '/' . implode('|', self::PHONE_NUMBERS_PATTERNS) . '/';
        $this->global_mailto_pattern = '/' . self::MAILTO_SCHEME_PATTERN . '(' . self::EMAIL_PATTERN . ')' . self::MAILTO_QUERY_PATTERN . '/';
        $this->plain_email_pattern = '/(\b' . self::EMAIL_PATTERN . '\b)/';
        $this->global_tel_pattern = '/' . self::TEL_SCHEME_PATTERN . '(' . self::PHONE_NUMBER . ')/';
    }

    /**
     * @param $content
     * @return void|mixed|string
     * @psalm-suppress PossiblyUnusedMethod
     * @psalm-suppress PossiblyUnusedReturnValue
     */
    public function runEncoding($content = '')
    {
        if ( $this->exclusions->doSkipBeforeAnything() ) {
            return $content;
        }
        return $this->modifyContent($content);
    }

    /**
     * @param array $encoded_contacts_data
     *
     * @return string JSON like {success: bool, data: [compiled response data]}
     * @psalm-suppress PossiblyUnusedMethod
     * @codeCoverageIgnore
     */
    public function runDecoding($encoded_contacts_data)
    {
        $decoded_strings = $this->decodeContactData($encoded_contacts_data);
        $this->decoded_contacts_array = $decoded_strings;
        // @ToDo Check connections errors during checkRequest and return success:false
        return json_encode([
            'success' => true,
            'data' => $this->compileResponse($decoded_strings, $this->is_encode_allowed),
        ]);
    }

    /*
     * =============== MODIFYING ===============
     */

    /**
     * @param string $content
     *
     * @return string
     * @psalm-suppress PossiblyUnusedReturnValue
     */
    public function modifyContent($content, $skip_exclusions = false)
    {
        if ( ! $skip_exclusions && $this->exclusions->doReturnContentBeforeModify($content) ) {
            return $content;
        }

        // modify content to prevent aria-label replaces by hiding it
        if ( $this->do_encode_emails || $this->do_encode_phones ) {
            $content = $this->handleAriaLabelContent($content);
        }

        // will use this in regexp callback
        $this->temp_content = $content;

        $content = $this->dropAttributesContainEmail($content, self::$attributes_to_drop);

        // Main logic

        $this->do_encode_emails && $content = $this->modifyGlobalEmails($content);

        $this->do_encode_phones && $content = $this->modifyGlobalPhoneNumbers($content);

        if ( $this->do_encode_emails || $this->do_encode_phones ) {
            $content = $this->handleAriaLabelContent($content, true);
        }

        return $content;
    }

    /**
     * @param string $content
     * @return string
     * @psalm-suppress PossiblyUnusedReturnValue
     * @phpcs:disable PHPCompatibility.FunctionUse.NewFunctionParameters.preg_replace_callback_flagsFound
     */
    public function modifyGlobalEmails($content)
    {
        // Nothing to look for, or too much of it.
        if ( is_string($content) && ( strpos($content, '@') === false || substr_count($content, '@') > self::MAX_CONTACT_SIGNS_PER_CONTENT ) ) {
            return $content;
        }

        $owns_aria_protection = empty($this->aria_placeholders);
        if ( $owns_aria_protection ) {
            $content = $this->handleAriaLabelContent($content);
        }

        $this->temp_content = $content;
        $this->helper->indexMarkup($this->temp_content);

        $match_cursor = 0;
        $match_offsets = $this->collectMatchOffsets($this->global_email_pattern, $content);
        if ( $this->isContactMatchesBudgetExceeded($match_offsets) ) {
            return $owns_aria_protection ? $this->handleAriaLabelContent($content, true) : $content;
        }
        $match_offset_index = 0;
        $replacing_result = preg_replace_callback($this->global_email_pattern, function ($matches) use ($match_offsets, &$match_offset_index, &$match_cursor) {
            if ( ! isset($matches[0]) ) {
                return '';
            }

            $position = $this->resolveNextMatchOffset($matches[0], $match_offsets, $match_offset_index, $match_cursor);

            if ( $this->exclusions->isContactExcluded($matches[0]) ) {
                return $matches[0];
            }

            if ( isset($matches[3]) && in_array(strtolower($matches[3]), ['.jpg', '.jpeg', '.png', '.gif', '.svg', '.webp']) ) {
                return $matches[0];
            }

            // Raw text of script/style/template blocks is never markup we may rewrite.
            if ( $this->helper->isInsideRawTextTag($matches[0], $this->temp_content, $position) ) {
                return $matches[0];
            }

            // Drop the cc=/bcc= copies before the markup guards take them out of the flow.
            if ( $this->helper->isMailtoAdditionalCopy($matches[0], $this->temp_content, $position) ) {
                return '';
            }

            // mailto: links are encoded in place inside the href attribute, so they bypass the markup guards.
            if ( $this->helper->isMailto($matches[0]) ) {
                $position = $position === false ? 0 : $position;

                return $this->encodeMailtoLink($matches[0], $position);
            }

            //chek if email is placed in excluded attributes and return unchanged if so
            if ( $this->helper->hasAttributeExclusions($matches[0], $this->temp_content, $position) ) {
                return $matches[0];
            }

            // Encoding injects a <span>, which is only valid in a text node.
            if ( $this->helper->isInsideAttributeValue($matches[0], $this->temp_content, $position) ) {
                return $matches[0];
            }

            if ( $this->helper->isInsideOptionTag($matches[0], $this->temp_content, $position) ) {
                return $matches[0];
            }

            if ( ++$this->encoded_contacts_count > self::MAX_ENCODED_CONTACTS_PER_REQUEST ) {
                return $matches[0];
            }

            return $this->encodePlainEmail($matches[0]);
        }, $content);

        // A failed regular expression returns null: a guard could have failed as well, encode nothing.
        if ( ! is_string($replacing_result) ) {
            $replacing_result = $content;
        }

        if ( $owns_aria_protection ) {
            $replacing_result = $this->handleAriaLabelContent($replacing_result, true);
        }

        //please keep this var (do not simplify the code) for further debug
        return $replacing_result;
    }

    /**
     * @param string $content
     *
     * @return string
     * @psalm-suppress PossiblyUnusedReturnValue
     * @phpcs:disable PHPCompatibility.FunctionUse.NewFunctionParameters.preg_replace_callback_flagsFound
     */
    public function modifyGlobalPhoneNumbers($content)
    {
        if ( is_string($content) && substr_count($content, '+') + substr_count($content, '(') > self::MAX_CONTACT_SIGNS_PER_CONTENT ) {
            return $content;
        }

        $owns_aria_protection = empty($this->aria_placeholders);
        if ( $owns_aria_protection ) {
            $content = $this->handleAriaLabelContent($content);
        }

        $this->temp_content = $content;
        $this->helper->indexMarkup($this->temp_content);

        $phones_pattern = $this->global_phones_pattern;
        $match_cursor = 0;
        $match_offsets = $this->collectMatchOffsets($phones_pattern, $content);
        if ( $this->isContactMatchesBudgetExceeded($match_offsets) ) {
            return $owns_aria_protection ? $this->handleAriaLabelContent($content, true) : $content;
        }
        $match_offset_index = 0;
        $replacing_result = preg_replace_callback(
            $phones_pattern,
            function ($matches) use ($match_offsets, &$match_offset_index, &$match_cursor) {
                if ( ! isset($matches[0]) ) {
                    return '';
                }

                $position = $this->resolveNextMatchOffset($matches[0], $match_offsets, $match_offset_index, $match_cursor);

                if ( $this->exclusions->isContactExcluded($matches[0]) ) {
                    return $matches[0];
                }

                // Raw text of script/style/template blocks is never markup we may rewrite.
                if ( $this->helper->isInsideRawTextTag($matches[0], $this->temp_content, $position) ) {
                    return $matches[0];
                }

                // tel: links are encoded in place inside the href attribute, so they bypass the markup guards.
                if ( $this->helper->isTelTag($matches[0]) ) {
                    $position = $position === false ? 0 : $position;

                    return $this->encodeTelLink($matches[0], $position);
                }

                // symbols clearance
                $item_length = strlen(str_replace([' ', '(', ')', '-', '+', '.'], '', $matches[0]));

                // check length
                if ( $item_length > 12 || $item_length < 8 ) {
                    return $matches[0];
                }

                // check attribute exclusions
                if ( $this->helper->hasAttributeExclusions($matches[0], $this->temp_content, $position) ) {
                    return $matches[0];
                }

                // Encoding injects a <span>, which is only valid in a text node.
                if ( $this->helper->isInsideAttributeValue($matches[0], $this->temp_content, $position) ) {
                    return $matches[0];
                }

                return $this->encodeAny(
                    $matches[0],
                    $this->global_obfuscation_mode,
                    $this->global_replacing_text,
                    true
                );
            },
            $content
        );

        if ( ! is_string($replacing_result) ) {
            $replacing_result = $content;
        }

        if ( $owns_aria_protection ) {
            $replacing_result = $this->handleAriaLabelContent($replacing_result, true);
        }

        //please keep this var (do not simplify the code) for further debug
        return $replacing_result;
    }

    /**
     * Resolve the real offset of the current match.
     *
     * The offsets are collected up front with PREG_OFFSET_CAPTURE and consumed in order, because
     * preg_match_all() and preg_replace_callback() walk the same pattern over the same subject and
     * therefore produce the same sequence of matches. Looking the match up with strpos() instead
     * would land on an earlier literal occurrence that the pattern did not match, e.g. the address
     * inside `<script>var t="user@example.com1";</script>` shadowing a real one further down.
     *
     * @param string $match
     * @param int[] $offsets
     * @param int $offset_index
     * @param int $cursor
     * @return int|false
     */
    private function resolveNextMatchOffset($match, $offsets, &$offset_index, &$cursor)
    {
        $length = strlen($match);

        if ( isset($offsets[$offset_index]) ) {
            $position = $offsets[$offset_index];
            $offset_index++;

            if ( substr($this->temp_content, $position, $length) === $match ) {
                $cursor = $position + $length;

                return $position;
            }
        }

        // The pre-collected offsets are unusable, fall back to a left-to-right scan.
        $position = strpos($this->temp_content, $match, $cursor);
        if ( $position !== false ) {
            $cursor = $position + $length;
        }

        return $position;
    }

    /**
     * @param int[] $match_offsets
     * @return bool
     */
    private function isContactMatchesBudgetExceeded($match_offsets)
    {
        $this->contact_matches_processed += count($match_offsets);

        return $this->contact_matches_processed > self::MAX_CONTACT_MATCHES_PER_REQUEST;
    }

    /**
     * Collect the real offset of every match of the pattern in the content.
     *
     * @param string $pattern
     * @param string $content
     * @return int[]
     */
    private function collectMatchOffsets($pattern, $content)
    {
        $offsets = array();

        if ( preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE) && isset($matches[0]) ) {
            foreach ( $matches[0] as $match ) {
                if ( isset($match[1]) ) {
                    $offsets[] = $match[1];
                }
            }
        }

        return $offsets;
    }

    /*
     * =============== ENCODE ENTITIES ===============
     */

    /**
     * Method to process plain email
     *
     * @param string $email_str
     *
     * @return string
     */
    public function encodePlainEmail($email_str)
    {
        $obfuscated_string = $email_str;
        $chunks_data = false;

        if ( $this->global_obfuscation_mode !== Params::OBFUSCATION_MODE_REPLACE) {
            $obfuscator = new Obfuscator();
            $chunks = $obfuscator->getEmailData($email_str);
            $chunks_data = $obfuscator->obfuscate_success ? $chunks : false;
        }

        $handled_string = $this->applyEffectsOnMode(
            true,
            $obfuscated_string,
            $this->global_obfuscation_mode,
            $this->global_replacing_text,
            $chunks_data
        );
        $encoded_string = $this->encoder->encodeString($email_str);

        return $this->constructEncodedSpan($encoded_string, $handled_string);
    }

    /**
     * @param string $string of any data
     * @param string $mode
     * @param string $replacing_text
     *
     * @return string
     */
    protected function encodeAny($string, $mode = Params::OBFUSCATION_MODE_BLUR, $replacing_text = null, $is_phone_number = false)
    {
        $obfuscated_string = $string;

        if ($mode !== Params::OBFUSCATION_MODE_REPLACE) {
            $obfuscator = new Obfuscator();
            $obfuscated_string = $is_phone_number
                ? $obfuscator->processPhone($string)
                : $obfuscator->processString($string);
            // The edges stay as they are in the source. A text cut by bytes must not turn into an empty value.
            $obfuscated_string = htmlspecialchars($obfuscated_string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
        }

        $string_with_effect = $this->applyEffectsOnMode(
            false,
            $obfuscated_string,
            $mode,
            $replacing_text
        );
        $encoded_string = $this->encoder->encodeString($string);

        return $this->constructEncodedSpan($encoded_string, $string_with_effect);
    }

    /**
     * Method to process mailto: links.
     *
     * @param string $mailto_link_str
     * @param int $position Offset of the match inside the content being processed.
     *
     * @return string
     */
    private function encodeMailtoLink($mailto_link_str, $position = 0)
    {
        // Get inner tag text and place it in $matches[1]
        preg_match($this->global_mailto_pattern, $mailto_link_str, $matches);
        if ( isset($matches[1]) ) {
            $mailto_inner_text = preg_replace_callback($this->plain_email_pattern, function ($matches) {
                return isset($matches[0]) ? $this->getObfuscatedEmailString($matches[0]) : '';
            }, $matches[1]);
        }
        $mailto_link_str = preg_replace('/^mailto:/i', '', $mailto_link_str);

        // Keep the query string (e.g. "?Subject=...") separate from the email itself,
        // so it can be re-appended after obfuscation/encryption instead of leaking
        // out of the match and corrupting the data-original-string attribute.
        $email_part = $matches[1] ?? $mailto_link_str;
        $query_part = '';
        if ( strpos($mailto_link_str, $email_part) === 0 ) {
            $query_part = substr($mailto_link_str, strlen($email_part));
        }

        // Encrypt the whole target (email + query) so decoding restores it in full,
        // including any cc=/bcc= copies.
        $encoded = $this->encoder->encodeString($mailto_link_str);

        // The publicly visible copy must not leak cc=/bcc= addresses in plain text.
        $visible_query_part = preg_replace('/\b(b?cc=)' . self::EMAIL_PATTERN . '/i', '$1', $query_part);

        $text = ($mailto_inner_text ?? $email_part) . $visible_query_part;

        return 'mailto:' . $text . $this->buildSchemeLinkAttributes($encoded, $position);
    }

    /**
     * Method to process tel: links.
     *
     * @param string $tel_link_str
     * @param int $position Offset of the match inside the content being processed.
     *
     * @return string
     */
    private function encodeTelLink($tel_link_str, $position = 0)
    {
        // Get inner tag text and place it in $matches[1]
        preg_match($this->global_tel_pattern, $tel_link_str, $matches);
        if ( isset($matches[1]) ) {
            $tel_inner_text = preg_replace_callback('/' . self::PHONE_NUMBER . '/', function ($matches) {
                if (isset($matches[0])) {
                    $obfuscator = new Obfuscator();
                    return $obfuscator->processPhone($matches[0]);
                }
                return '';
            }, $matches[1]);
        }
        $tel_link_str = preg_replace('/^tel:/i', '', $tel_link_str);
        $encoded      = $this->encoder->encodeString($tel_link_str);

        $text = isset($tel_inner_text) ? $tel_inner_text : $tel_link_str;

        return 'tel:' . $text . $this->buildSchemeLinkAttributes($encoded, $position);
    }

    /**
     * Builds the attribute tail appended to an opening tag when a scheme link is encoded in place.
     *
     * The tail intentionally leaves the last attribute value unclosed: the quote that used to close
     * the original href value closes it instead. The tooltip is skipped when the tag already carries
     * a title attribute, otherwise the markup would end up with a duplicated attribute and the
     * author's own title would be wiped by the decoder script.
     *
     * @param string $encoded Encoded original string.
     * @param int $position Offset of the match inside the content being processed.
     *
     * @return string
     */
    private function buildSchemeLinkAttributes($encoded, $position)
    {
        $has_own_title = $this->helper->enclosingTagHasAttribute($this->temp_content, (int)$position, 'title');

        if ( $has_own_title ) {
            return '" data-original-string="' . $encoded;
        }

        return '" title="' . htmlspecialchars($this->getTooltip(), ENT_QUOTES, 'UTF-8')
            . '" data-original-string="' . $encoded;
    }

    /**
     * @param string $email_str
     *
     * @return string
     */
    public function getObfuscatedEmailString($email_str)
    {
        $obfuscator = new Obfuscator();
        $chunks = $obfuscator->getEmailData($email_str);
        return $obfuscator->obfuscate_success ? $chunks->getFinalString() : $email_str;
    }

    /*
     * =============== VISUALS ===============
     */

    /**
     * @param $email_str
     *
     * @return string
     */
    private function addMagicBlurEmail($email_str)
    {
        $obfuscator = new Obfuscator();
        $chunks = $obfuscator->getEmailData($email_str);
        $chunks_data = $obfuscator->obfuscate_success ? $chunks : false;

        return false !== $chunks_data
            ? $this->addMagicBlurViaChunksData($chunks_data)
            : $this->addMagicBlurToString($email_str)
            ;
    }

    /**
     * @param bool $is_email
     * @param string $obfuscated_string
     * @param string $mode
     * @param string $replacing_text
     * @param ObfuscatorEmailData|false $email_chunks_data
     *
     * @return string
     */
    private function applyEffectsOnMode($is_email, $obfuscated_string, $mode, $replacing_text = null, $email_chunks_data = null)
    {
        switch ($mode) {
            case Params::OBFUSCATION_MODE_BLUR:
                $handled_string = $is_email && $email_chunks_data
                    ? $this->addMagicBlurEmail($obfuscated_string)
                    : $this->addMagicBlurToString($obfuscated_string);
                break;
            case Params::OBFUSCATION_MODE_OBFUSCATE:
                $handled_string = $is_email
                    ? $this->getObfuscatedEmailString($obfuscated_string)
                    : $obfuscated_string;
                break;
            case Params::OBFUSCATION_MODE_REPLACE:
                $handled_string = !empty($replacing_text) ? $replacing_text : static::getDefaultReplacingText();
                $handled_string = '<span style="text-decoration: underline">' .  $handled_string . '</span>';
                break;
            default:
                return $obfuscated_string;
        }
        return $handled_string;
    }

    /**
     * @param $encoded_string
     * @param $obfuscated_string
     *
     * @return string
     */
    private function constructEncodedSpan($encoded_string, $obfuscated_string)
    {
        // One line with double quotes: third-party code may cut the text, and the rest of a page uses double quotes.
        return '<span data-original-string="' . htmlspecialchars($encoded_string, ENT_QUOTES, 'UTF-8')
            . '" class="apbct-email-encoder" title="' . htmlspecialchars($this->getTooltip(), ENT_QUOTES, 'UTF-8') . '">'
            . $obfuscated_string . '</span>';
    }

    /**
     * @param ObfuscatorEmailData $email_chunks
     *
     * @return string
     */
    private function addMagicBlurViaChunksData($email_chunks)
    {
        return $email_chunks->chunk_raw_left
            . '<span class="apbct-blur">' . $email_chunks->chunk_obfuscated_left . '</span>'
            . $email_chunks->chunk_raw_center
            . '<span class="apbct-blur">' . $email_chunks->chunk_obfuscated_right . '</span>'
            . $email_chunks->chunk_raw_right
            . $email_chunks->domain;
    }

    /**
     * @param string $obfuscated_string with ** symbols
     *
     * @return string
     */
    private function addMagicBlurToString($obfuscated_string)
    {
        //preparing data to blur
        $regex = '/^([^*]+)(\*+)([^*]+)$/';
        preg_match_all($regex, $obfuscated_string, $matches);
        if (isset($matches[1][0], $matches[2][0], $matches[3][0])) {
            $first = $matches[1][0];
            $middle = $matches[2][0];
            $end = $matches[3][0];
        } else {
            return $obfuscated_string;
        }
        return $first . '<span class="apbct-blur">' . $middle . '</span>' . $end;
    }

    /**
     * Get text for the title attribute
     *
     * @return string
     * @recommended Override this method in child classes to provide custom tooltip text
     */
    protected function getTooltip()
    {
        return 'This contact has been encoded. Click to decode. To finish the decoding make sure that JavaScript is enabled in your browser.';
    }


    /*
    * =============== DECODING ===============
    */

    /**
     * Main logic of the decoding the encoded data.
     *
     * @param array $encoded_contacts_array array of decoded email
     *
     * @return array
     */
    public function decodeContactData($encoded_contacts_array)
    {
        $decoded_emails_array = [];

        if ( empty($encoded_contacts_array) || ! is_array($encoded_contacts_array) ) {
            return $decoded_emails_array;
        }

        if ( ! $this->is_logged_in ) {
            $this->is_encode_allowed = $this->checkRequest();
        }

        foreach ( $encoded_contacts_array as $encoded_contact) {
            $decoded_emails_array[$encoded_contact] = $this->encoder->decodeString($encoded_contact);
        }

        return $decoded_emails_array;
    }

    /**
     * Ajax handler for the apbct_decode_email action
     *
     * @return bool returns json string to the JS
     */
    protected function checkRequest()
    {
        $event_javascript_data = '';
        $event_token = '';
        if ( isset($_POST['event_token']) && is_string($_POST['event_token']) ) {
            $event_token = $_POST['event_token'];
        } elseif ( isset($_POST['event_javascript_data']) && is_string($_POST['event_javascript_data']) ) {
            $event_javascript_data = $_POST['event_javascript_data'];
        }

        $params = array(
            'auth_key'              => $this->api_key,        // Access key
            'agent'                 => 'contacts_encoder_lib_' . self::VERSION,
            'event_token'           => $event_token, // Unique event ID
            'event_javascript_data' => $event_javascript_data, // JSON-string params to analysis
            'sender_ip'             => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '',
            'event_type'            => 'CONTACT_DECODING',     // 'GENERAL_BOT_CHECK' || 'CONTACT_DECODING'
            'message_to_log'        => json_encode(array_values($this->decoded_contacts_array), JSON_FORCE_OBJECT),   // Custom message
            'page_url'              => isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '',
            'sender_info'           => array(
                'site_referrer'         => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '',
            ),
        );

        $ct_request = new CleantalkRequest($params);

        $ct = new Cleantalk();

        $ct->server_url = 'https://moderate.cleantalk.org';
        $ct->work_url = 'https://moderate.cleantalk.org';
        $api_response = $ct->checkBot($ct_request);

        // Allow to see the decoded contact if error occurred.
        if ( ! empty($api_response->errstr)) {
            $this->request_result_comment = $api_response->errstr;
            return true;
        }

        $stub_comment = $api_response->allow
            ? 'Allowed'
            : 'Blocked';

        $this->request_result_comment = ! empty($api_response->comment) ? $api_response->comment : $stub_comment;

        return $api_response->allow === 1;
    }

    protected function getCheckRequestComment()
    {
        return $this->request_result_comment;
    }

    /**
     * @param $decoded_emails_array
     * @param $is_allowed
     * @return array|false
     * @psalm-suppress PossiblyUnusedMethod
     */
    protected function compileResponse($decoded_emails_array, $is_allowed)
    {
        $result = array();

        if ( empty($decoded_emails_array) ) {
            return false;
        }

        foreach ( $decoded_emails_array as $encoded_email => $decoded_email ) {
            $result[] = array(
                'is_allowed' => $is_allowed,
                'show_comment' => !$is_allowed,
                'comment' => $this->getCheckRequestComment(),
                'encoded_email' => strip_tags($encoded_email, '<a>'),
                'decoded_email' => $is_allowed ? strip_tags($decoded_email, '<a>') : '',
            );
        }
        return $result;
    }

    /*
     * =============== SERVICE ===============
     */

    /**
     * Fluid. Ignore SSL mode for encoding/decoding on the instance.
     * @return $this
     */
    public function ignoreOpenSSLMode()
    {
        $this->encoder->useSSL(false);
        return $this;
    }

    /**
     * Get default replacing text for the encoded email
     *
     * @return string
     * @recommended Override this method in child classes to provide custom replacement text
     */
    protected static function getDefaultReplacingText()
    {
        return 'Click to show email!';
    }

    /**
     * Drop attributes contains email from tag in the content to avoid unnecessary encoding.
     *
     * Example: <code><a title="example1@mail.com" href="mailto:example2@mail.com">Email</a></code>
     * Will be turned to <code><a href="mailto:example2@mail.com">Email</a></code>
     *
     * The attribute is dropped wherever the address sits inside its value, so
     * <code>title="Write to example1@mail.com"</code> is handled as well. Matching is scoped to a
     * single tag and a single quoted value, otherwise a greedy match would swallow the rest of the
     * markup along with it.
     *
     * @param string $content The content to process.
     * @return string The content with attributes removed.
     */
    private function dropAttributesContainEmail($content, $tags)
    {
        $email_pattern = '/' . self::EMAIL_PATTERN . '/';
        $this->helper->indexMarkup($content);

        foreach ($tags as $tag => $attribute) {
            if ( ! is_string($tag) || $tag === '' || ! is_string($attribute) || $attribute === '' ) {
                continue;
            }

            // A whole tag, with quoted attribute values consumed as a unit so `>` inside them is kept.
            $tag_pattern = '/<' . preg_quote($tag, '/') . '\b(?:[^>"\']++|"[^"]*+"|\'[^\']*+\')*+>/i';
            // The target attribute inside that tag, value limited to its own quotes.
            $attribute_pattern = '/\s' . preg_quote($attribute, '/') . '\s*=\s*(["\'])(.*?)\1/is';

            $replaced = preg_replace_callback(
                $tag_pattern,
                function ($tag_match) use ($attribute_pattern, $email_pattern, $content) {
                    if ( ! isset($tag_match[0]) || ! is_array($tag_match[0]) || ! isset($tag_match[0][0], $tag_match[0][1]) ) {
                        return '';
                    }

                    $opening_tag = $tag_match[0][0];
                    $tag_offset = $tag_match[0][1];

                    if ( ! is_string($opening_tag) || ! is_int($tag_offset) ) {
                        return '';
                    }

                    if ( $this->helper->isInsideRawTextTag($opening_tag, $content, $tag_offset) ) {
                        return $opening_tag;
                    }

                    $stripped = preg_replace_callback(
                        $attribute_pattern,
                        static function ($attribute_match) use ($email_pattern) {
                            if ( ! isset($attribute_match[0], $attribute_match[2]) ) {
                                return '';
                            }

                            return preg_match($email_pattern, $attribute_match[2])
                                ? ''
                                : $attribute_match[0];
                        },
                        $opening_tag
                    );

                    return $stripped === null ? $opening_tag : $stripped;
                },
                $content,
                -1,
                $count,
                PREG_OFFSET_CAPTURE
            );

            if ( $replaced !== null && $count > 0 ) {
                $content = $replaced;
                $this->helper->indexMarkup($content);
            }
        }

        return $content;
    }

    /**
     * Modify content to skip aria-label cases correctly.
     * Protects aria-label attributes of real tags from email encoding.
     * @param string $content
     * @param bool $reverse
     *
     * @return string
     */
    private function handleAriaLabelContent($content, $reverse = false)
    {
        if ( !$reverse ) {
            $this->aria_placeholders = array();
            if ( !is_string($content) || stripos($content, 'aria-label') === false || !$this->isSecureAriaLabelPlaceholderAvailable() ) {
                return $content;
            }
            return $this->replaceAriaLabelsWithPlaceholders($content);
        }
        if ( !empty($this->aria_placeholders) ) {
            foreach ($this->aria_placeholders as $placeholder => $original) {
                $content = $this->restoreAriaLabelPlaceholder($content, $placeholder, $original);
            }
            $this->aria_placeholders = array();
        }
        return $content;
    }

    /**
     * Replace each quoted aria-label attribute with a unique placeholder.
     * The same text outside of a tag is just a text, and must not hide the markup next to it.
     *
     * @param string $content
     *
     * @return string
     */
    private function replaceAriaLabelsWithPlaceholders($content)
    {
        $result = '';
        $position = 0;

        foreach ( ContactsEncoderHelper::scanMarkup($content) as $token ) {
            foreach ( $token['attributes'] as $attribute ) {
                list($name, $name_start, , $value_end, $end) = $attribute;
                if ( $name !== 'aria-label' || $value_end === $end ) {
                    continue;
                }

                $placeholder = $this->generateAriaLabelPlaceholder();
                if ( $placeholder === null ) {
                    continue;
                }

                $this->aria_placeholders[$placeholder] = substr($content, $name_start, $end - $name_start);
                $result .= substr($content, $position, $name_start - $position) . $placeholder;
                $position = $end;
            }
        }

        return $result . substr($content, $position);
    }

    /**
     * Whether a cryptographically secure placeholder can be generated.
     *
     * @return bool
     */
    private function isSecureAriaLabelPlaceholderAvailable()
    {
        return function_exists('random_bytes') || function_exists('openssl_random_pseudo_bytes');
    }

    /**
     * Build an unguessable placeholder so attacker-controlled content cannot collide with it.
     *
     * @return string|null Null when no secure entropy source is available.
     */
    private function generateAriaLabelPlaceholder()
    {
        $bytes = $this->getSecureRandomBytes(16);
        if ( !is_string($bytes) || strlen($bytes) !== 16 ) {
            return null;
        }

        return '%%APBCT_ARIA_' . bin2hex($bytes) . '%%';
    }

    /**
     * @param int $length
     *
     * @return string|null
     */
    private function getSecureRandomBytes($length)
    {
        if ( !is_int($length) || $length < 1 ) {
            return null;
        }

        if ( function_exists('random_bytes') ) {
            try {
                // phpcs:ignore PHPCompatibility.FunctionUse.NewFunctions.random_bytesFound
                $bytes = random_bytes($length);
                if ( strlen($bytes) === $length ) {
                    return $bytes;
                }
            } catch ( \Exception $e ) {
                // Fall through to OpenSSL.
            }
        }

        if ( function_exists('openssl_random_pseudo_bytes') ) {
            $crypto_strong = false;
            $bytes = openssl_random_pseudo_bytes($length, $crypto_strong);
            if ( $crypto_strong && is_string($bytes) && strlen($bytes) === $length ) {
                return $bytes;
            }
        }

        return null;
    }

    /**
     * Restore a single aria-label placeholder at its first occurrence only.
     *
     * @param string $content
     * @param string $placeholder
     * @param string $original
     *
     * @return string
     */
    private function restoreAriaLabelPlaceholder($content, $placeholder, $original)
    {
        $pos = strpos($content, $placeholder);
        if ( $pos === false ) {
            return $content;
        }

        return substr($content, 0, $pos) . $original . substr($content, $pos + strlen($placeholder));
    }
}
