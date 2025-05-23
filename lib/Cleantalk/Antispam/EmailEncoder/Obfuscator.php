<?php

namespace Cleantalk\Antispam\EmailEncoder;

/**
 * Class Obfuscator
 * This class is responsible for obfuscating email addresses by hiding parts of the email.
 * @package Cleantalk\Antispam
 */
class Obfuscator
{
    const EMAIL_CHARS_TO_SHOW = 2; // Number of characters to show in the email address
    const STRING_CHARS_TO_SHOW = 2; // Number of characters to show in a string
    const PHONE_CHARS_TO_SHOW_LEFT = 5; // Number of characters to show in a string
    const PHONE_CHARS_TO_SHOW_RIGHT = 2; // Number of characters to show in a string

    /**
     * Data contains email parts and service information
     * @var ObfuscatorEmailData
     */
    private $email_data;
    /**
     * Flag of obfuscation success
     * @var bool
     */
    public $obfuscate_success = false;

    /**
     * Obfuscator constructor.
     * Initializes the email data object.
     */
    public function __construct()
    {
        $this->email_data = new ObfuscatorEmailData();
    }

    /**
     * Processes the given email address and obfuscates it.
     *
     * @param string $original_email The original email address to be obfuscated.
     * @return ObfuscatorEmailData The obfuscated email data.
     */
    public function getEmailData($original_email)
    {
        $this->email_data->original_email = $original_email;
        try {
            $this->setDomainAndWorkString();
            $this->setAtSymbolPosition();
            $this->detectShortParts();
            $this->setLeftClearChunk();
            $this->setRightClearChunk();
            $this->setLeftObfuscatedChunk();
            $this->setRightObfuscatedChunk();
            $this->obfuscateParts();
            $this->validateEmailChunks();
            $this->obfuscate_success = true;
        } catch ( \Exception $e ) {
            $this->obfuscate_success = false;
        }
        return $this->email_data;
    }

    /**
     * Obfuscates a given string by hiding its middle part. Used to obfuscate names, phone numbers, etc.
     *
     * @param string $string The string to be obfuscated.
     * @return string The obfuscated string.
     */
    public function processString($string)
    {
        $length = strlen($string);
        $first_part = substr($string, 0, static::STRING_CHARS_TO_SHOW);
        $last_part = substr($string, $length - static::STRING_CHARS_TO_SHOW, static::STRING_CHARS_TO_SHOW);
        $middle_part = str_pad('', $length - static::STRING_CHARS_TO_SHOW * 2, '*');
        return $first_part . $middle_part . $last_part;
    }

    /**
     * Obfuscates a phone number
     * @param $string
     *
     * @return string
     */
    public function processPhone($string)
    {
        if (strlen($string) < 9) {
            return self::processString($string);
        }
        $length = strlen($string);
        $first_part = substr($string, 0, static::PHONE_CHARS_TO_SHOW_LEFT);
        $last_part = substr($string, $length - static::PHONE_CHARS_TO_SHOW_RIGHT, static::PHONE_CHARS_TO_SHOW_RIGHT);
        $middle_part = str_pad('', $length - static::PHONE_CHARS_TO_SHOW_RIGHT - static::PHONE_CHARS_TO_SHOW_LEFT, '*');
        return $first_part . $middle_part . $last_part;
    }

    /**
     * Sets the domain and working string for the email address.
     *
     * @return void
     * @throws \Exception If the email is not valid.
     */
    private function setDomainAndWorkString()
    {
        $regexp = '/@[a-zA-Z0-9.-]+(\.[a-zA-Z]{2,})$/';
        preg_match_all($regexp, $this->email_data->original_email, $matches);
        if ( ! empty($matches[1][0]) ) {
            $this->email_data->domain           = $matches[1][0];
            $this->email_data->work_with_string = substr(
                $this->email_data->original_email,
                0,
                strlen($this->email_data->original_email) - strlen($this->email_data->domain)
            );
        } else {
            throw new \Exception('Email is not valid - no domain');
        }
    }

    /**
     * Sets the position of the '@' symbol in the email address.
     *
     * @return void
     * @throws \Exception If the email is not valid.
     */
    private function setAtSymbolPosition()
    {
        $at_position = strpos($this->email_data->work_with_string, '@');
        if ( $at_position === false ) {
            throw new \Exception('Email is not valid - no @ symbol');
        }
        $this->email_data->at_symbol_position = $at_position;
    }

    /**
     * Detects if the parts of the email address are too short to be obfuscated.
     *
     * @return void
     * @throws \Exception If the email is not valid.
     */
    private function detectShortParts()
    {
        $this->email_data->left_part_is_too_short =
            $this->email_data->at_symbol_position
            <=
            static::EMAIL_CHARS_TO_SHOW;

        $right_part_length = strlen(
            substr(
                $this->email_data->work_with_string,
                $this->email_data->at_symbol_position + 1
            )
        );
        $this->email_data->right_part_is_too_short =
            $right_part_length  <= static::EMAIL_CHARS_TO_SHOW;
    }

    /**
     * Sets the left clear chunk of the email address.
     *
     * @return void
     */
    private function setLeftClearChunk()
    {
        if ( $this->email_data->left_part_is_too_short ) {
            $chunk = '';
        } else {
            $chunk = substr($this->email_data->work_with_string, 0, static::EMAIL_CHARS_TO_SHOW);
        }
        $this->email_data->chunk_raw_left = $chunk;
    }

    /**
     * Sets the right clear chunk of the email address.
     *
     * @return void
     */
    private function setRightClearChunk()
    {
        if ( $this->email_data->right_part_is_too_short ) {
            $chunk = '';
        } else {
            $chunk = substr(
                $this->email_data->work_with_string,
                -1 * static::EMAIL_CHARS_TO_SHOW,
                static::EMAIL_CHARS_TO_SHOW
            );
        }
        $this->email_data->chunk_raw_right = $chunk;
    }

    /**
     * Sets the left obfuscated chunk of the email address.
     *
     * @return void
     * @throws \Exception If the email is not valid.
     */
    private function setLeftObfuscatedChunk()
    {
        if ($this->email_data->left_part_is_too_short) {
            $chunk = substr(
                $this->email_data->work_with_string,
                0,
                $this->email_data->at_symbol_position
            );
        } else {
            $chunk = substr(
                $this->email_data->work_with_string,
                static::EMAIL_CHARS_TO_SHOW,
                $this->email_data->at_symbol_position - static::EMAIL_CHARS_TO_SHOW
            );
        }

        $this->email_data->chunk_prep_to_ob_left = $chunk;
    }

    /**
     * Sets the right obfuscated chunk of the email address.
     *
     * @return void
     * @throws \Exception If the email is not valid.
     */
    private function setRightObfuscatedChunk()
    {
        if ($this->email_data->right_part_is_too_short) {
            $chunk = substr(
                $this->email_data->work_with_string,
                $this->email_data->at_symbol_position + 1
            );
        } else {
            $right_obfuscated_offset             = $this->email_data->at_symbol_position + 1;
            $chunk                               = substr(
                $this->email_data->work_with_string,
                $right_obfuscated_offset,
                strlen($this->email_data->work_with_string) - $right_obfuscated_offset - static::EMAIL_CHARS_TO_SHOW
            );
        }
        $this->email_data->chunk_prep_to_ob_right = $chunk;
    }

    /**
     * Obfuscates the prepared parts of the email address.
     *
     * @return void
     */
    private function obfuscateParts()
    {
        $this->email_data->chunk_obfuscated_left = str_pad(
            '',
            strlen($this->email_data->chunk_prep_to_ob_left),
            '*'
        );
        $this->email_data->chunk_obfuscated_right = str_pad(
            '',
            strlen($this->email_data->chunk_prep_to_ob_right),
            '*'
        );
    }

    /**
     * Validates the obfuscated email chunks. CHeck if the original email length is the same as the obfuscated email length.
     *
     * @return void
     * @throws \Exception If the validation fails.
     */
    private function validateEmailChunks()
    {
        if ( strlen($this->email_data->getFinalString()) !== strlen($this->email_data->original_email)) {
            throw new \Exception('Email chunks validation failed');
        }
    }
}
