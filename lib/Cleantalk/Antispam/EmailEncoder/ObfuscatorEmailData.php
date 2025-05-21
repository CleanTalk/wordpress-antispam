<?php

namespace Cleantalk\Antispam\EmailEncoder;

class ObfuscatorEmailData
{
    /**
     * @var string
     */
    public $original_email = '';
    /**
     * @var string
     */
    public $chunk_raw_left = '';
    /**
     * @var string
     */
    public $chunk_obfuscated_left = '';
    /**
     * @var string
     */
    public $chunk_prep_to_ob_left = '';
    /**
     * @var string
     */
    public $chunk_raw_center = '@';
    /**
     * @var string
     */
    public $chunk_obfuscated_right = '';
    /**
     * @var string
     */
    public $chunk_prep_to_ob_right = '';
    /**
     * @var string
     */
    public $chunk_raw_right = '';
    /**
     * @var bool
     */
    public $left_part_is_too_short = false;
    /**
     * @var bool
     */
    public $right_part_is_too_short = false;
    /**
     * @var int
     */
    public $at_symbol_position;
    /**
     * @var string
     */
    public $domain = '';

    public $work_with_string = '';

    /**
     * @return string
     */
    public function getFinalString()
    {
        return $this->chunk_raw_left
               . $this->chunk_obfuscated_left
               . $this->chunk_raw_center
               . $this->chunk_obfuscated_right
               . $this->chunk_raw_right
               . $this->domain;
    }
}
