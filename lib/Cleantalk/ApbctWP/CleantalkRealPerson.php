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
                'trpContent2' => esc_html__(' Anti-Spam by CleanTalk', 'cleantalk-spam-protect'),
                'trpContentLearnMore' => esc_html__('Learn more', 'cleantalk-spam-protect'),
            ],
            'trpContentLink' => esc_attr(LinkConstructor::buildCleanTalkLink('trp_learn_more_link', 'help/the-real-person')),
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
        global $apbct;

        $show_trp_on_roles = false;
        $roles_to_show_trp = ['administrator', 'editor'];
        if ( $comment->user_id ) {
            $user = get_userdata($comment->user_id);
            if ( $user && is_array($user->roles) && count(array_intersect($user->roles, $roles_to_show_trp)) > 0 ) {
                $show_trp_on_roles = true;
            }
        }

        // Logic for show TRP badge
        $show_trp = false;
        $the_real_person = !empty($apbct->settings['comments__the_real_person']) && $apbct->settings['comments__the_real_person'] == '1';
        $allowed_moderation = !empty($apbct->settings['cleantalk_allowed_moderation']) && $apbct->settings['cleantalk_allowed_moderation'] == '1';

        if ($the_real_person && $allowed_moderation) {
            // Only for auto-moderated
            $automod_hash = get_comment_meta((int)$comment_id, 'ct_real_user_badge_automod_hash', true);
            $show_trp = $automod_hash && $comment->comment_author;
        } elseif ($the_real_person && !$allowed_moderation) {
            // Only for old
            $old_hash = get_comment_meta((int)$comment_id, 'ct_real_user_badge_hash', true);
            $show_trp = $old_hash && $comment->comment_author;
        }

        if ($show_trp || $show_trp_on_roles) {
            $classes[] = 'apbct-trp';
        }
        return $classes;
    }
}
