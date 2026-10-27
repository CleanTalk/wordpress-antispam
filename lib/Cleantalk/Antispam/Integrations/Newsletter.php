<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Post;

class Newsletter extends IntegrationBase
{
    /**
     * @inheritDoc
     */
    public function getDataForChecking($argument)
    {
        // The standard form gets the antibot page first, the subscription is made by the next request
        if ( ($argument === 's' || $argument === 'subscribe') && ! $this->isSubscriptionRequest() ) {
            return null;
        }

        if (
            $argument === 's' ||
            $argument === 'subscribe' ||
            $argument === 'sa' ||
            $argument === 'ajaxsub'
        ) {
            $posted = stripslashes_deep($_REQUEST);
            $email = '';
            if (isset($posted['ne'])) {
                $email = $posted['ne'];
                unset($posted['ne']);
            }
            $name = '';
            if (isset($posted['nn'])) {
                $name = $posted['nn'];
                unset($posted['nn']);
            }
            if (isset($posted['ns'])) {
                $name .=  ' ' . $posted['ns'];
                unset($posted['ns']);
            }
            $data = ct_gfa_dto(apply_filters('apbct__filter_post', $posted), $email, $name)->getArray();
            if ( Post::getString('ct_bot_detector_event_token') ) {
                $data['event_token'] = Post::get('ct_bot_detector_event_token');
            }
            return $data;
        }
        return null;
    }

    /**
     * Checks if Newsletter makes the subscription in this request.
     *
     * @return bool
     * @psalm-suppress UndefinedClass
     */
    private function isSubscriptionRequest()
    {
        if ( ! class_exists('NewsletterSubscription') || ! method_exists('NewsletterSubscription', 'antibot_form_check') ) {
            return true;
        }

        $options = \NewsletterSubscription::instance()->get_options('antispam');

        return
            (defined('NEWSLETTER_TEST') && NEWSLETTER_TEST) ||
            ! empty($_GET['_wp_amp_action_xhr_converted']) ||
            ! empty($options['disabled']) ||
            \NewsletterSubscription::antibot_form_check(! empty($options['captcha']));
    }

    /**
     * @inheritDoc
     */
    public function doBlock($message)
    {
        global $ct_comment;
        $ct_comment = $message;
        ct_die($message, 403);
    }
}
