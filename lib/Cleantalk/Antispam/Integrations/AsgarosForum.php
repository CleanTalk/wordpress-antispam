<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\DTO\GetFieldsAnyDTO;
use Cleantalk\Common\TT;
use Cleantalk\Variables\Post;

class AsgarosForum extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        global $apbct;

        if ( Post::getString('submit_action') ) {
            $email    = '';
            $nickname = '';

            $message = TT::getArrayValueAsString($argument, 'content');

            $subject = TT::getArrayValueAsString($argument, 'subject');

            $author_id = TT::getArrayValueAsInt($argument, 'author');

            if ( ! empty($author_id) ) {
                $author_data = get_userdata($author_id);
            } else {
                $author_data = get_userdata(get_current_user_id());
            }

            $author_data = $author_data instanceof \WP_User ? $author_data : null;
            if ( $author_data ) {
                $email    = $author_data->data->user_email;
                $nickname = $author_data->data->user_nicename;
            }

            $data_to_spam_check = new GetFieldsAnyDTO(
                array(
                    'email'        => $email,
                    'emails_array' => array(),
                    'nickname'     => $nickname,
                    'subject'      => $subject,
                    'contact'      => true,
                    'message'      => $message
                )
            );

            return $data_to_spam_check->getArray();
        }

        return $argument;
    }

    public function doBlock($message)
    {
        ct_die_extended($message);
    }
}
