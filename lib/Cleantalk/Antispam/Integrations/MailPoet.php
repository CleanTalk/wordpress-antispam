<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\Common\TT;

/**
 * MailPoet integration.
 * Note: the integration hook is "mailpoet_subscription_before_subscribe",
 * MailPoet fires it for the subscription form sent by AJAX and by admin-post.php.
 */
class MailPoet extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        if ( ! is_array($argument) ) {
            return null;
        }

        if ( isset($_POST['data'], $_POST['data']['ct_bot_detector_event_token']) ) {
            $event_token = $_POST['data']['ct_bot_detector_event_token'];
        }

        // The subscriber data prepared by MailPoet
        $email = TT::getArrayValueAsString($argument, 'email');
        $nickname = trim(
            TT::getArrayValueAsString($argument, 'first_name') . ' ' . TT::getArrayValueAsString($argument, 'last_name'),
            " \n\r\t\v\x00"
        );

        $data = apply_filters('apbct__filter_post', $argument);
        $data = ct_gfa($data, $email, $nickname);
        $data['event_token'] = isset($event_token) ? $event_token : '';

        return $data;
    }

    /**
     * MailPoet shows the exception message to the visitor and does not create the subscriber.
     *
     * @param $message
     *
     * @return void
     * @psalm-suppress UndefinedClass
     */
    public function doBlock($message)
    {
        throw new \MailPoet\UnexpectedValueException($message);
    }
}
