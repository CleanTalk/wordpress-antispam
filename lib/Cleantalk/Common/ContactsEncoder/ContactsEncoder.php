<?php

namespace Cleantalk\Common\ContactsEncoder;

use Cleantalk\Common\ContactsEncoder\Helper\ContactsEncoderHelper;
use Cleantalk\Common\ContactsEncoder\Dto\Params;
use Cleantalk\Common\ContactsEncoder\Encoder\Encoder;
use Cleantalk\Common\ContactsEncoder\Exclusions\ExclusionsService;
use Cleantalk\Common\ContactsEncoder\Obfuscator\Obfuscator;
use Cleantalk\Common\ContactsEncoder\Obfuscator\ObfuscatorEmailData;

/**
 * Contacts Encoder common class.
 */
abstract class ContactsEncoder
{
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
     */
    const ARIA_LABEL_PATTERN = '/aria-label.?=.?[\'"].+?[\'"]/';
    const EMAIL_PATTERN = '[_A-Za-z0-9-\.]+@[_A-Za-z0-9-\.]+\.[A-Za-z]{2,}\b';
    const EMAIL_PATTERN_DOMAIN_CATCHING = '[_A-Za-z0-9-\.]+@[_A-Za-z0-9-\.]+(\.[A-Za-z]{2,}\b)';
    const PHONE_NUMBER = '\+\d{8,12}';
    const PHONE_NUMBERS_PATTERNS = [
        '(tel:' . self::PHONE_NUMBER . ')',                        // tel:+XXXXXXXXXX
        '([\+][\s-]?\(?\d[\d\s\-()]{7,}\d)',                       // +X XXX XXXXXXX, +X(XXX)XXXXX, etc.
        '(\(\d{3}\)\s?\d{3}-\d{4})',                               // (XXX) XXX-XXXX, (XXX) XXX XXXX
        '(\+\d{1,3}\.\d{1,3}\.((\d{3}\.\d{4})|\d{7})(?![\w.]))',   // +X?.XX?.XXX.XXXX
    ];

    /**
     * @var string example: '/aria-label.?=.?[\'"].+?[\'"]/'
     */
    protected $aria_regex;

    /**
     * @var string example: '/(mailto\:\b[_A-Za-z0-9-\.]+@[_A-Za-z0-9-\.]+\.[A-Za-z]{2,}\b)|(\b[_A-Za-z0-9-\.]+@[_A-Za-z0-9-\.]+(\.[A-Za-z]{2,}\b))/'
     */
    protected $global_email_pattern;

    /**
     * @var string example: '/(tel:\+\d{8,12})|([\+][\s-]?\(?\d[\d\s\-()]{7,}\d)|(\(\d{3}\)\s?\d{3}-\d{4})|(\+\d{1,3}\.\d{1,3}\.((\d{3}\.\d{4})|\d{7})(?![\w.]))'/'
     */
    protected $global_phones_pattern;

    /**
     * @var string example: '/mailto\:(\b[_A-Za-z0-9-\.]+@[_A-Za-z0-9-\.]+\.[A-Za-z]{2,})/'
     */
    protected $global_mailto_pattern;

    /**
     * @var string example: '/(\b[_A-Za-z0-9-\.]+@[_A-Za-z0-9-\.]+\.[A-Za-z]{2,}\b)/'
     */
    protected $plain_email_pattern;

    /**
     * @var string example: '/\b[_A-Za-z0-9-\.]+@[_A-Za-z0-9-\.]+\.[A-Za-z]{2,}/'
     * @ToDo Is this regular expression needed? A little different against `$plain_email_pattern`.
     */
    protected $plain_email_pattern_without_capturing;

    /**
     * @var string example: '/tel:(\+\d{8,12})/'
     * @ToDo Is this regexp is actual and right?
     */
    protected $global_tel_pattern;

    /**
     * @var array
     */
    protected $aria_matches = array();

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
        $this->aria_regex = self::ARIA_LABEL_PATTERN;

        $this->global_email_pattern = '/(mailto\:\b' . self::EMAIL_PATTERN . ')|(\b' . self::EMAIL_PATTERN_DOMAIN_CATCHING . ')/';
        $this->global_phones_pattern = '/' . implode('|', self::PHONE_NUMBERS_PATTERNS) . '/';
        $this->global_mailto_pattern = '/mailto\:(' . self::EMAIL_PATTERN . ')/';
        $this->plain_email_pattern = '/(\b' . self::EMAIL_PATTERN . '\b)/';
        $this->plain_email_pattern_without_capturing = '/\b' . self::EMAIL_PATTERN . '/';
        $this->global_tel_pattern = '/tel:(' . self::PHONE_NUMBER . ')/';
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
        $content = $this->handleAriaLabelContent($content);

        // will use this in regexp callback
        $this->temp_content = $content;

        $content = self::dropAttributesContainEmail($content, self::$attributes_to_drop);

        // Main logic

        $this->do_encode_emails && $content = $this->modifyGlobalEmails($content);

        $this->do_encode_phones && $content = $this->modifyGlobalPhoneNumbers($content);

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
        $replacing_result = '';

        if ( version_compare(phpversion(), '7.4.0', '>=') ) {
            $replacing_result = preg_replace_callback($this->global_email_pattern, function ($matches) use ($content) {
                if ( isset($matches[3][0], $matches[0][0]) && in_array(strtolower($matches[3][0]), ['.jpg', '.jpeg', '.png', '.gif', '.svg', '.webp']) ) {
                    return $matches[0][0];
                }

                //chek if email is placed in excluded attributes and return unchanged if so
                if ( isset($matches[0][0]) && $this->helper->hasAttributeExclusions($matches[0][0], $this->temp_content) ) {
                    return $matches[0][0];
                }

                // skip encoding if the content in script tag
                if ( isset($matches[0][0]) && $this->helper->isInsideScriptTag($matches[0][0], $content) ) {
                    return $matches[0][0];
                }

                if ( isset($matches[0][0]) && $this->helper->isMailto($matches[0][0]) ) {
                    return $this->encodeMailtoLinkV2($matches[0], $content);
                }

                if (
                    isset($matches[0]) &&
                    is_array($matches[0]) &&
                    $this->helper->isMailtoAdditionalCopy($matches[0], $content)
                ) {
                    return '';
                }

                if (
                    isset($matches[0], $matches[0][0]) &&
                    is_array($matches[0]) &&
                    $this->helper->isEmailInLink($matches[0], $content)
                ) {
                    return $matches[0][0];
                }

                if ( isset($matches[0][0]) ) {
                    return $this->encodePlainEmail($matches[0][0]);
                }

                return '';
            }, $content, -1, $count, PREG_OFFSET_CAPTURE);
        }

        if ( version_compare(phpversion(), '7.4.0', '<') ) {
            $replacing_result = preg_replace_callback($this->global_email_pattern, function ($matches) {
                if ( isset($matches[3]) && in_array(strtolower($matches[3]), ['.jpg', '.jpeg', '.png', '.gif', '.svg', '.webp']) && isset($matches[0]) ) {
                    return $matches[0];
                }

                //chek if email is placed in excluded attributes and return unchanged if so
                if ( isset($matches[0]) && $this->helper->hasAttributeExclusions($matches[0], $this->temp_content) ) {
                    return $matches[0];
                }

                if ( isset($matches[0]) &&  $this->helper->isMailto($matches[0]) ) {
                    return $this->encodeMailtoLink($matches[0]);
                }

                if ( isset($matches[0]) ) {
                    return $this->encodePlainEmail($matches[0]);
                }

                return '';
            }, $content);
        }

        // modify content to turn back aria-label
        $replacing_result = $this->handleAriaLabelContent($replacing_result, true);

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
        $phones_pattern = $this->global_phones_pattern;
        $replacing_result = '';

        if ( version_compare(phpversion(), '7.4.0', '>=') ) {
            $replacing_result = preg_replace_callback(
                $phones_pattern,
                function ($matches) use ($content) {
                    if ( isset($matches[0]) ) {
                        $first_group = $matches[0];
                    } else {
                        return '';
                    }

                    if ( isset($first_group[0]) ) {
                        $second_group = $first_group[0];
                    } else {
                        return '';
                    }

                    if (is_array($first_group) && $this->helper->isTelTag($second_group) ) {
                        return $this->encodeTelLinkV2($first_group, $content);
                    }
                    //symbols clearance
                    $item_length = strlen(str_replace([' ', '(', ')', '-', '+', '.'], '', $second_group));
                    //check length
                    if ( $item_length > 12 || $item_length < 8 ) {
                        return $second_group;
                    }
                    //check attribute exclusions
                    if ( $this->helper->hasAttributeExclusions($second_group, $this->temp_content) ) {
                        return $second_group;
                    }
                    //check if in script
                    if ( $this->helper->isInsideScriptTag($second_group, $content) ) {
                        return $second_group;
                    }
                    //do encode
                    return $this->encodeAny(
                        $second_group,
                        $this->global_obfuscation_mode,
                        $this->global_replacing_text,
                        true
                    );
                },
                $content,
                -1,
                $count,
                PREG_OFFSET_CAPTURE
            );
        }

        if ( version_compare(phpversion(), '7.4.0', '<') ) {
            $replacing_result = preg_replace_callback(
                $phones_pattern,
                function ($matches) {
                    if ( isset($matches[0]) ) {
                        if ( $this->helper->isTelTag($matches[0]) ) {
                            return $this->encodeTelLink($matches[0]);
                        }

                        $item_length = strlen(str_replace([' ', '(', ')', '-', '+', '.'], '', $matches[0]));
                        if ( $item_length > 12 || $item_length < 8 ) {
                            return $matches[0];
                        }

                        if ( $this->helper->hasAttributeExclusions($matches[0][0], $this->temp_content) ) {
                            return $matches[0];
                        }
                    }

                    if ( isset($matches[0]) ) {
                        return $this->encodeAny(
                            $matches[0],
                            $this->global_obfuscation_mode,
                            $this->global_replacing_text,
                            true
                        );
                    }

                    return '';
                },
                $content
            );
        }

        // modify content to turn back aria-label
        $replacing_result = $this->handleAriaLabelContent($replacing_result, true);

        //please keep this var (do not simplify the code) for further debug
        return $replacing_result;
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
     * Method to process mailto: links. For PHP < 7.4
     *
     * @param string $mailto_link_str
     *
     * @return string
     */
    private function encodeMailtoLink($mailto_link_str)
    {
        // Get inner tag text and place it in $matches[1]
        preg_match($this->global_mailto_pattern, $mailto_link_str, $matches);
        if ( isset($matches[1]) ) {
            $mailto_inner_text = preg_replace_callback($this->plain_email_pattern, function ($matches) {
                if (isset($matches[0])) {
                    return $this->getObfuscatedEmailString($matches[0]);
                }
            }, $matches[1]);
        }
        $mailto_link_str = str_replace('mailto:', '', $mailto_link_str);
        $encoded = $this->encoder->encodeString($mailto_link_str);

        $text = isset($mailto_inner_text) ? $mailto_inner_text : $mailto_link_str;

        return 'mailto:' . $text . '" data-original-string="' . $encoded . '" title="' . htmlspecialchars($this->getTooltip(), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Method to process mailto: links. Use this only for PHP 7.4+
     *
     * @param $match array
     * @param $content string
     *
     * @return string
     */
    private function encodeMailtoLinkV2($match, $content)
    {
        $position = $match[1];
        $q_position = $position + strcspn($content, '\'"', $position);
        $mailto_link_str = substr($content, $position, $q_position - $position);
        // Get inner tag text and place it in $matches[1]
        preg_match($this->global_mailto_pattern, $mailto_link_str, $matches);
        if ( isset($matches[1]) ) {
            $mailto_inner_text = preg_replace_callback($this->plain_email_pattern_without_capturing, function ($matches) {
                if ( isset($matches[0]) ) {
                    return $this->getObfuscatedEmailString($matches[0]);
                }

                return '';
            }, $matches[1]);
        }

        $mailto_link_str = str_replace('mailto:', '', $mailto_link_str);
        $encoded = $this->encoder->encodeString($mailto_link_str);

        $text = isset($mailto_inner_text) ? $mailto_inner_text : $mailto_link_str;

        return 'mailto:' . $text . '" data-original-string="' . $encoded . '" title="' . htmlspecialchars($this->getTooltip(), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Method to process tel: links. For PHP < 7.4
     *
     * @param string $tel_link_str
     *
     * @return string
     */
    private function encodeTelLink($tel_link_str)
    {
        // Get inner tag text and place it in $matches[1]
        preg_match($this->global_tel_pattern, $tel_link_str, $matches);
        if ( isset($matches[1]) ) {
            $mailto_inner_text = preg_replace_callback('/' . self::PHONE_NUMBER . '/', function ($matches) {
                if (isset($matches[0])) {
                    $obfuscator = new Obfuscator();
                    return $obfuscator->processPhone($matches[0]);
                }
            }, $matches[1]);
        }
        $tel_link_str = str_replace('tel:', '', $tel_link_str);
        $encoded      = $this->encoder->encodeString($tel_link_str);

        $text = isset($mailto_inner_text) ? $mailto_inner_text : $tel_link_str;

        return 'tel:' . $text . '" data-original-string="' . $encoded . '" title="' . htmlspecialchars($this->getTooltip(), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Method to process tel: links. Use this only for PHP 7.4+
     *
     * @param array $match
     * @param string $content
     *
     * @return string
     */
    private function encodeTelLinkV2($match, $content)
    {
        $position = !empty($match[1]) ? (int)$match[1] : null;
        if (null === $position) {
            return $content;
        }
        $q_position = $position + strcspn($content, '\'"', $position);
        $tel_link_string = substr($content, $position, $q_position - $position);
        // Get inner tag text and place it in $matches[1]
        preg_match($this->global_tel_pattern, $tel_link_string, $matches);
        if ( isset($matches[1]) ) {
            $tel_inner_text = preg_replace_callback('/' . self::PHONE_NUMBER . '/', function ($matches) {
                if ( isset($matches[0]) ) {
                    $obfuscator = new Obfuscator();
                    return $obfuscator->processPhone($matches[0]);
                }
                return '';
            }, $matches[1]);
        }

        $tel_link_string = str_replace('tel:', '', $tel_link_string);
        $encoded = $this->encoder->encodeString($tel_link_string);

        $text = isset($tel_inner_text) ? $tel_inner_text : $tel_link_string;

        return 'tel:' . $text . '" data-original-string="' . $encoded . '" title="' . htmlspecialchars($this->getTooltip(), ENT_QUOTES, 'UTF-8');
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
        return "<span 
                data-original-string='" . $encoded_string . "'
                class='apbct-email-encoder'
                title='" . htmlspecialchars($this->getTooltip(), ENT_QUOTES, 'UTF-8') . "'>" . $obfuscated_string . "</span>";
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
    abstract protected function checkRequest();

    abstract protected function getCheckRequestComment();

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
     * @param string $content The content to process.
     * @return string The content with attributes removed.
     */
    private static function dropAttributesContainEmail($content, $tags)
    {
        $attribute_content_chunk = '[\s]{0,}=[\s]{0,}[\"\']\b[_A-Za-z0-9-\.]+@[_A-Za-z0-9-\.]+\..*\b[\"\']';
        foreach ($tags as $tag => $attribute) {
            // Regular expression to match the attribute without the tag
            $regexp_chunk_without_tag = "/{$attribute}{$attribute_content_chunk}/";
            // Regular expression to match the attribute with the tag
            $regexp_chunk_with_tag = "/<{$tag}.*{$attribute}{$attribute_content_chunk}/";
            // Find all matches of the attribute with the tag in the content
            preg_match_all($regexp_chunk_with_tag, $content, $matches);
            if (!empty($matches[0])) {
                // Remove the attribute without the tag from the content
                $content = preg_replace($regexp_chunk_without_tag, '', $content, count($matches[0]));
            }
        }
        return $content;
    }

    /**
     * Modify content to skip aria-label cases correctly.
     * @param string $content
     * @param bool $reverse
     *
     * @return string
     */
    private function handleAriaLabelContent($content, $reverse = false)
    {
        if ( !$reverse ) {
            $this->aria_matches = array();
            //save match
            preg_match($this->aria_regex, $content, $this->aria_matches);
            if (empty($this->aria_matches)) {
                return $content;
            }
            //replace with temp
            return preg_replace($this->aria_regex, 'ct_temp_aria', $content);
        }
        if ( !empty($this->aria_matches[0]) ) {
            //replace temp with match
            return preg_replace('/ct_temp_aria/', $this->aria_matches[0], $content);
        }
        return $content;
    }
}
