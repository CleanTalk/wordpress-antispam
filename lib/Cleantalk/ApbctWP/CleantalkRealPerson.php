<?php

namespace Cleantalk\ApbctWP;

class CleantalkRealPerson
{
    public static function getLocalizingData()
    {
        /** @psalm-suppress PossiblyUndefinedVariable */
        $localize_array['theRealPerson'] = [
            'phrases' => [
                'trpHeading' => esc_html__('The Real Person Badge!', 'cleantalk-spam-protect'),
                'trpContent1' => esc_html__('The commenter acts as a real person and verified as not a bot.', 'cleantalk-spam-protect'),
                'trpContent2' => esc_html__('Passed all tests against spam bots. Anti-Spam by CleanTalk.', 'cleantalk-spam-protect'),
                'trpContentLearnMore' => esc_html__('Learn more', 'cleantalk-spam-protect'),
            ],
            'trpContentLink' => esc_attr(LinkConstructor::buildCleanTalkLink('trp_learn_more_link', 'the-real-person')),
            'imgPersonUrl' => esc_attr(APBCT_URL_PATH . '/css/images/real_user.svg'),
            'imgShieldUrl' => esc_attr(APBCT_URL_PATH . '/css/images/shield.svg'),
        ];
        return $localize_array;
    }

    public function __construct()
    {
        // Check comment meta 'ct_real_user_badge_hash' and add comment class 'apbct-trp' during 'comment_class' hook
        add_filter('comment_class', [$this, 'publicCommentAddTrpClass'], 10, 5);
    }

    public function publicCommentAddTrpClass($classes, $_css_class, $comment_id, $comment, $_post)
    {
        $ct_hash = get_comment_meta((int)$comment_id, 'ct_real_user_badge_hash', true);
        if ( $ct_hash && $comment->comment_author ) {
            $classes[] = 'apbct-trp';
            return $classes;
        }
        return $classes;
    }
}
