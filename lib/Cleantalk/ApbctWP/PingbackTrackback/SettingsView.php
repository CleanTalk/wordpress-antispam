<?php

namespace Cleantalk\ApbctWP\PingbackTrackback;

use Cleantalk\Common\TextPlate;

class SettingsView
{
    use TextPlate;

    public static function getDescription()
    {
        return __('Blocks incoming Pingback and Trackback requests that are commonly abused for spam. Prevents WordPress from creating Pingback and Trackback comments while keeping XML-RPC functionality available for Jetpack and other services.', 'cleantalk-spam-protect');
    }

    public static function getTitle()
    {
        return __('Disable pingbacks and trackbacks', 'cleantalk-spam-protect');
    }

    public static function getLongDescription()
    {
        $text = '<p>{{1}}</p><p>{{2}}</p><p>{{3}}</p><p>{{4}}</p>';
        $plate = array(
            '1' => esc_html__('Pingbacks and Trackbacks are legacy WordPress features designed to notify a website when another site links to its content. When enabled, WordPress can accept incoming Pingback and Trackback requests and create special comment entries associated with the linked post.', 'cleantalk-spam-protect'),
            '2' => esc_html__('Unfortunately, these mechanisms are frequently abused by spammers. Attackers can send large numbers of fake Pingback or Trackback requests to create spam comments, promote malicious websites, manipulate search engine rankings, or consume server resources. Since Pingbacks are processed through XML-RPC and Trackbacks use a dedicated endpoint, they may remain available even when regular comments are protected.', 'cleantalk-spam-protect'),
            '3' => esc_html__('When this option is enabled, incoming Pingback and Trackback requests are blocked before WordPress can create a corresponding comment. Pingback requests receive an XML-RPC error response, while Trackback requests receive a standard Trackback error response. This protection applies to all posts, including existing content, without modifying post settings or disabling XML-RPC functionality required by services such as Jetpack.', 'cleantalk-spam-protect'),
            '4' => esc_html__('Regular comments, XML-RPC publishing, and other XML-RPC features continue to work normally.', 'cleantalk-spam-protect'),
        );
        return self::textPlateRender($text, $plate);
    }
}
