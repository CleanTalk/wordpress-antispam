<?php

namespace Cleantalk\Common\ContactsEncoder\Dto;

class Params
{
    /** @var string Encoded data will be blured */
    const OBFUSCATION_MODE_BLUR = 'blur';

    /** @var string Encoded data will be replaced by `**` */
    const OBFUSCATION_MODE_OBFUSCATE = 'obfuscate';

    /** @var string Encoded data will be replaced by a provided text */
    const OBFUSCATION_MODE_REPLACE = 'replace';

    public $api_key;
    public $is_logged_in = false;
    public $obfuscation_mode = self::OBFUSCATION_MODE_BLUR;
    public $obfuscation_text = 'hidden contact data';
    public $do_encode_emails = 1;
    public $do_encode_phones = 0;
}
