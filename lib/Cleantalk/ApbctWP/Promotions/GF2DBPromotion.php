<?php

namespace Cleantalk\ApbctWP\Promotions;

use Cleantalk\ApbctWP\LinkConstructor;

class GF2DBPromotion extends Promotion
{
    /**
     * @inheritDoc
     */
    public static $setting_name = 'forms__gravity_promotion_gf2db';

    /**
     * @inheritDoc
     */
    public function couldPerformActions()
    {
        $gravity_active = apbct_is_plugin_active('gravityforms/gravityforms.php');
        $gf2db_active = apbct_is_plugin_active('cleantalk-doboard-add-on-for-gravity-forms/cleantalk-doboard-add-on-for-gravity-forms.php');
        return $gravity_active && !$gf2db_active;
    }

    /**
     * @inheritDoc
     */
    public function performActions()
    {
        add_action('gform_pre_send_email', array($this, 'modifyAdminEmailContent'), 999, 4);
    }

    /**
     * @inheritDoc
     */
    public function getDescriptionForSettings()
    {
        return __('Add helpful links to the bottom of all notifications about new messages from the form. These emails are sent to administrators.', 'cleantalk-spam-protect');
    }

    /**
     * @inheritDoc
     */
    public function getTitleForSettings()
    {
        return __('Attach helpful links to Gravity Forms admin notifications', 'cleantalk-spam-protect');
    }

    /**
     * Modify email content by adding a helpful link to the bottom of the email.
     * @param array $email Email content provided by Gravity Forms.
     * @param string $_message_format Hook misc param.
     * @param array $notification Notification array data.
     * @param array $_entry Hook misc param.
     *
     * @return array
     * @psalm-suppress PossiblyUnusedReturnValue
     */
    public function modifyAdminEmailContent($email, $_message_format, $notification, $_entry)
    {
        if (
            isset($notification['to'], $email['message']) &&
            $notification['to'] === '{admin_email}' &&
            is_string($email['message'])
        ) {
            $new_content = $this->prepareAdminEmailContentHTML();
            $email['message'] = str_replace('</body>', $new_content . '</body>', $email['message']);
        }

        return $email;
    }

    /**
     * Prepare new helpful HTML for email content
     * @return string
     */
    public function prepareAdminEmailContentHTML()
    {
        $text__call_to_action = __('Organize and track all messages by upgrading Gravity Forms with project management', 'cleantalk-spam-protect');
        $text__turnoff = __('You can turn off this message on CleanTalk Anti-Spam plugin', 'cleantalk-spam-protect');
        $text__turnoff_link_text = __('settings page', 'cleantalk-spam-protect');
        $link__promotion = 'https://wordpress.org/plugins/cleantalk-doboard-add-on-for-gravity-forms/';
        $link__promotion = LinkConstructor::buildSimpleHref(
            $link__promotion,
            $link__promotion
        );
        $link__settings = get_site_option('siteurl') . '/wp-admin/options-general.php?page=cleantalk#apbct_setting_group__forms_protection';
        $link__settings = LinkConstructor::buildSimpleHref(
            $link__settings,
            $text__turnoff_link_text
        );
        $template = '<p>%s: %s</p><p style="font-size: x-small">%s %s.</p>';
        return sprintf(
            $template,
            $text__call_to_action,
            $link__promotion,
            $text__turnoff,
            $link__settings
        );
    }
}
