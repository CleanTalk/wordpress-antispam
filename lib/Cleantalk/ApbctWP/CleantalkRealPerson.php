<?php

namespace Cleantalk\ApbctWP;

class CleantalkRealPerson
{
    public static $meta_hash_name__old = 'ct_real_user_badge_hash';
    public static $meta_hash_name__automod = 'ct_real_user_badge_automod_hash';
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
        $the_real_person = !empty($apbct->settings['comments__the_real_person']) && $apbct->settings['comments__the_real_person'] == '1';
        $show_trp = $the_real_person && self::isTRPHashExist($comment_id) && $comment->comment_author;

        if ($show_trp || $show_trp_on_roles) {
            $classes[] = 'apbct-trp';
        }
        return $classes;
    }

    /**
     * @return bool
     */
    private static function isAutomodEnabled()
    {
        global $apbct;
        return !empty($apbct->settings['cleantalk_allowed_moderation']) && $apbct->settings['cleantalk_allowed_moderation'] == '1';
    }

    /**
     * Check if TRP hash is saved for comment ID. Autodetect if cleantalk_allowed_moderation is enabled.
     * @param int|string $comment_id
     * @return bool
     */
    public static function isTRPHashExist($comment_id)
    {
        if (self::isAutomodEnabled()) {
            // Only for auto-moderated
            $trp_hash = get_comment_meta((int)$comment_id, self::$meta_hash_name__automod, true);
        } else {
            // Only for old
            $trp_hash = get_comment_meta((int)$comment_id, self::$meta_hash_name__old, true);
        }

        return !empty($trp_hash);
    }

    /**
     * Save TRP hash for comment ID. Autodetect if cleantalk_allowed_moderation is enabled.
     * @param int|string $comment_id
     *
     * @return void
     */
    public static function setTRPHash($comment_id)
    {
        $hash = ct_hash();
        if ( ! empty($hash) ) {
            if (self::isAutomodEnabled()) {
                update_comment_meta((int)$comment_id, self::$meta_hash_name__automod, $hash);
            } else {
                update_comment_meta((int)$comment_id, self::$meta_hash_name__old, $hash);
            }
        }
    }
}
