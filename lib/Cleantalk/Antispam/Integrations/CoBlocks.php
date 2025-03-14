<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\Common\TT;

class CoBlocks extends IntegrationBase
{
    /**
     * @inheritDoc
     */
    public function getDataForChecking($argument)
    {

        $email_field_id = Post::get('email-field-id')
            ? sanitize_text_field(TT::toString(Post::get('email-field-id')))
            : 'field-email';
        $name_field_id  = Post::get('name-field-id')
            ? sanitize_text_field(TT::toString(Post::get('name-field-id')))
            : 'field-name';
        $email_field_value = isset(Post::get($email_field_id)['value'])
            ? sanitize_text_field(TT::getArrayValueAsString(Post::get($email_field_id), 'value'))
            : '';
        $name_field_value  = isset(Post::get($name_field_id)['value'])
            ? sanitize_text_field(TT::getArrayValueAsString(Post::get($name_field_id), 'value'))
            : '';

        $data = ct_gfa(apply_filters('apbct__filter_post', $_POST), $email_field_value, $name_field_value);

        return $data;
    }

    /**
     * @inheritDoc
     */
    public function doBlock($message)
    {
        global $ct_comment;
        $ct_comment = $message;
        ct_die(null, null);
    }
}
