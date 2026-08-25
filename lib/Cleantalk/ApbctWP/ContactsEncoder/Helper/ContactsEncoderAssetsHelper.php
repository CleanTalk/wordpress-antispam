<?php

namespace Cleantalk\ApbctWP\ContactsEncoder\Helper;

class ContactsEncoderAssetsHelper extends \Cleantalk\Common\ContactsEncoder\Helper\ContactsEncoderAssetsHelper
{
    public static function renderJsTag()
    {
        $js_content = file_get_contents(self::getJsPath());

        return apbct_get_inline_script_tag($js_content);
    }
}
