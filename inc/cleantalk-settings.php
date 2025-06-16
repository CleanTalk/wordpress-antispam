<?php

use Cleantalk\ApbctWP\AdjustToEnvironmentModule\AdjustToEnvironmentHandler;
use Cleantalk\ApbctWP\AdjustToEnvironmentModule\AdjustToEnvironmentSettings;
use Cleantalk\ApbctWP\AJAXService;
use Cleantalk\ApbctWP\Antispam\EmailEncoder;
use Cleantalk\ApbctWP\Escape;
use Cleantalk\ApbctWP\Helper;
use Cleantalk\ApbctWP\LinkConstructor;
use Cleantalk\ApbctWP\Validate;
use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\ApbctWP\Cron;
use Cleantalk\ApbctWP\Variables\Server;
use Cleantalk\Common\TT;
use Cleantalk\ApbctWP\PluginSettingsPage\SettingsField;

/**
 * Admin action 'admin_menu' - Add the admin options page
 */
function apbct_settings_add_page()
{
    global $apbct, $pagenow, $wp_version;

    $parent_slug = is_network_admin() ? 'settings.php' : 'options-general.php';
    $callback    = is_network_admin() ? 'apbct_settings__display__network' : 'apbct_settings__display';

    $actual_plugin_name = $apbct->plugin_name;
    if (isset($apbct->data['wl_brandname']) && $apbct->data['wl_brandname'] !== APBCT_NAME) {
        $actual_plugin_name = $apbct->data['wl_brandname'];
    }

    // Adding settings page
    add_submenu_page(
        $parent_slug,
        $apbct->plugin_name . ' ' . __('settings'),
        $actual_plugin_name,
        'manage_options',
        'cleantalk',
        $callback
    );

    // Add CleanTalk Moderation option to the Discussion page
    add_settings_field(
        'cleantalk_allowed_moderation',
        esc_html__('CleanTalk allowed comments moderation', 'cleantalk-spam-protect'),
        'apbct_discussion_settings__field__moderation',
        'discussion'
    );
    add_filter('allowed_options', function ($options) {
        $options['discussion'][] = 'cleantalk_allowed_moderation';
        return $options;
    });
    // End modification Discussion page

    if ( ! in_array($pagenow, array('options.php', 'options-general.php', 'settings.php', 'admin.php')) ) {
        return;
    }

    $callback_format = array(
        'type' => 'string',
        'sanitize_callback' => 'apbct_settings__validate',
        'default' => null
    );

    if (version_compare($wp_version, '4.7') < 0) {
        $callback_format = 'apbct_settings__validate';
    }

    /** @psalm-suppress PossiblyInvalidArgument */
    register_setting(
        'cleantalk_settings',
        'cleantalk_settings',
        $callback_format
    );

    $fields = apbct_settings__set_fields();
    $fields = APBCT_WPMS && is_main_site() ? apbct_settings__set_fields__network($fields) : $fields;
    apbct_settings__add_groups_and_fields($fields);
}

function apbct_settings__set_fields()
{
    global $apbct;

    $additional_ac_title = '';
    if ( $apbct->api_key && is_null($apbct->fw_stats['firewall_updating_id']) ) {
        if ( $apbct->settings['sfw__enabled'] && ! $apbct->stats['sfw']['entries'] ) {
            $additional_ac_title =
                ' <span style="color:red">'
                . esc_html__(
                    'The functionality was disabled because SpamFireWall database is empty. Please, do the synchronization or',
                    'cleantalk-spam-protect'
                )
                . ' '
                //HANDLE LINK
                . '<a href="https://cleantalk.org/my/support/open" target="_blank" style="color:red">'
                . esc_html__(
                    'contact to our support.',
                    'cleantalk-spam-protect'
                )
                . '</a></span>';
        }
    }
    $additional_sfw_description = '';
    if ( ! empty($apbct->data['notice_incompatibility']) ) {
        $additional_sfw_description .= '<br>';
        foreach ( $apbct->data['notice_incompatibility'] as $notice ) {
            $additional_sfw_description .= '<span style="color:red">' . $notice . '</span><br>';
        }
    }

    $send_connection_reports__sfw_text = $apbct->settings['sfw__enabled']
        ? '<br>' . __(' - status of SpamFireWall database updating process', 'cleantalk-spam-protect')
        : '';

    $fields = array(

        'main' => array(
            'title'          => '',
            'default_params' => array(),
            'description'    => '',
            'html_before'    => '',
            'html_after'     => '',
            'fields'         => array(
                'action_buttons'     => array(
                    'callback' => 'apbct_settings__field__action_buttons',
                ),
                'connection_reports' => array(
                    'callback' => 'apbct_settings__field__statistics',
                ),
                'api_key'            => array(
                    'callback' => 'apbct_settings__field__apikey',
                ),
            ),
        ),

        'state' => array(
            'title'          => '',
            'default_params' => array(),
            'description'    => '',
            'html_before'    => '<hr style="width: 100%;">',
            'html_after'     => '',
            'fields'         => array(
                'state' => array(
                    'callback' => 'apbct_settings__field__state',
                ),
            ),
        ),

        // Different
        'different'             => array(
            'title'          => '',
            'default_params' => array(),
            'description'    => '',
            'html_before'    => '<hr>',
            'html_after'     => '',
            'fields'         => array(
                'comments__hide_website_field'             => array(
                    'type'        => 'checkbox',
                    'title'       => __('Hide the "Website" field', 'cleantalk-spam-protect'),
                    'description' => __(
                        'This option hides the "Website" field on the comment form.',
                        'cleantalk-spam-protect'
                    ),
                    'long_description' => true,
                    'display'     => ! $apbct->white_label,
                ),
                'comments__the_real_person' => array(
                    'type'        => 'checkbox',
                    'title'       => __('The Real Person Badge!', 'cleantalk-spam-protect'),
                    'description' => __(
                        'Plugin shows special benchmark for author of a comment or review, that the author passed all anti-spam filters and acts as a real person. It improves quality of users generated content on your website by proving that the content is not from spambots.',
                        'cleantalk-spam-protect'
                    ),
                    'long_description' => true,
                ),
                'data__email_decoder__status'        => array(
                    'type'        => 'custom_html',
                    'title'       => __('Encode contact data', 'cleantalk-spam-protect'),
                    'long_description' => true,
                ),
            ),
        ),

        // Links to open other sections below
        'spoilers_links' => array(
            'fields' => array(),
            'html_before' => apbct_get_spoilers_links()
        ),

        //Description of advanced settings
        'advanced_settings'     => array(
            'fields' => array(),
            'notification'      => __('The default settings correspond to the optimal work of the service and their change is required only in special cases.', 'cleantalk-spam-protect'),
            'html_before'       => '<div id="apbct_settings__before_advanced_settings"></div>'
            . '<div id="apbct_settings__advanced_settings" style="display: none;">'
            . '<div id="apbct_settings__advanced_settings_inner">',
        ),

        // Forms protection
        'forms_protection'      => array(
            'title'          => __('Forms to protect', 'cleantalk-spam-protect'),
            'section'        => 'hidden_section',
            'fields'         => array(
                'forms__registrations_test'             => array(
                    'title'       => __('Registration Forms', 'cleantalk-spam-protect'),
                    'description' => __(
                        'WordPress, BuddyPress, bbPress, S2Member, WooCommerce.',
                        'cleantalk-spam-protect'
                    ),
                ),
                'forms__comments_test'                  => array(
                    'title'       => __('Comments form', 'cleantalk-spam-protect'),
                    'description' => __('WordPress, JetPack, WooCommerce.', 'cleantalk-spam-protect'),
                ),
                'forms__contact_forms_test'             => array(
                    'title'       => __('Contact forms', 'cleantalk-spam-protect'),
                    'description' => __(
                        'Contact Form 7, Formidable forms, JetPack, Fast Secure Contact Form, WordPress Landing Pages, Gravity Forms and Everest forms.',
                        'cleantalk-spam-protect'
                    ),
                    'childrens'   => array('forms__flamingo_save_spam')
                ),
                'forms__flamingo_save_spam'             => array(
                    'title'       => __('Save Flamingo spam entries', 'cleantalk-spam-protect'),
                    'description' => __(
                        'Spam Contact Form 7 entries will be saved into Flamingo if the option is enabled',
                        'cleantalk-spam-protect'
                    ),
                    'class'       => 'apbct_settings-field_wrapper--sub',
                    'parent'      => 'forms__contact_forms_test',
                    'display'     => apbct_is_plugin_active('contact-form-7/wp-contact-form-7.php') && apbct_is_plugin_active('flamingo/flamingo.php'),
                ),
                'forms__gravityforms_save_spam'             => array(
                    'title'       => __('Save Gravity Forms spam entries', 'cleantalk-spam-protect'),
                    'description' => __(
                        'Spam Gravity Forms entries will be saved into Gravity Forms spam entries if the option is enabled',
                        'cleantalk-spam-protect'
                    ),
                    'class'       => 'apbct_settings-field_wrapper--sub',
                    'parent'      => 'forms__contact_forms_test',
                    'display'     => apbct_is_plugin_active('gravityforms/gravityforms.php'),
                ),
                'forms__general_contact_forms_test'     => array(
                    'title'       => __('Custom contact forms', 'cleantalk-spam-protect'),
                    'description' => __(
                        'Anti-Spam test for any WordPress themes or contacts forms.',
                        'cleantalk-spam-protect'
                    ),
                ),
                'forms__search_test'                    => array(
                    'title'       => __('Test default WordPress search form for spam', 'cleantalk-spam-protect'),
                    'description' =>
                        __('Spam protection for Search form.', 'cleantalk-spam-protect')
                        //HANDLE LINK
                        . (! $apbct->white_label || is_main_site() ?
                            sprintf(
                                __('Read more about %sspam protection for Search form%s on our blog. The "noindex" tag will be placed in the meta directive on the search results page.', 'cleantalk-spam-protect'),
                                '<a href="https://blog.cleantalk.org/how-to-protect-website-search-from-spambots/" target="_blank">',
                                '</a>'
                            ) : '')
                ),
                'forms__check_external'                 => array(
                    'title'       => __('Protect external forms', 'cleantalk-spam-protect'),
                    'description' => __(
                        'Turn this option on to protect forms on your WordPress that send data to third-part servers (like MailChimp).',
                        'cleantalk-spam-protect'
                    ),
                    'childrens'   => array('forms__check_external__capture_buffer'),
                ),
                'forms__check_external__capture_buffer' => array(
                    'title'       => __('Capture buffer', 'cleantalk-spam-protect'),
                    'description' => __(
                        'This option provides more sophisticated and enhanced protection for external forms. However, it can break other plugins that use the webserver buffer like Ninja Forms, and moreover, it can also cause issues with cache plugins.',
                        'cleantalk-spam-protect'
                    )
                    . '<br />'
                    . __('СAUTION! Enable this option if you have missed spam from external forms', 'cleantalk-spam-protect'),
                    'class'       => 'apbct_settings-field_wrapper--sub',
                    'parent'      => 'forms__check_external',
                ),
                'forms__check_internal'                 => array(
                    'title'       => __('Protect internal forms', 'cleantalk-spam-protect'),
                    'description' => __(
                        'This option will enable protection for custom (hand-made) AJAX forms with PHP scripts handlers on your WordPress.',
                        'cleantalk-spam-protect'
                    ),
                ),
                'data__honeypot_field' => array(
                    'title'           => __(
                        'Add a honeypot field',
                        'cleantalk-spam-protect'
                    ),
                    'description'     => __(
                        'This option adds a honeypot field to the forms.',
                        'cleantalk-spam-protect'
                    ),
                    'options'         => array(
                        array('val' => 1, 'label' => __('On')),
                        array('val' => 0, 'label' => __('Off')),
                    ),
                    'long_description' => true,
                ),
                'forms__force_protection' => array(
                    'title'       => __('Force protection', 'cleantalk-spam-protect'),
                    'description' => __(
                        'This option will enable pre-check protection for iframe, internal and external forms on your WordPress. To avoid spam from bots without javascript. This option affects the reflection of the page by checking the user and adds a cookie "apbct_force_protection_check", which serves as an indicator of successful or unsuccessful verification. If the check is successful, it will no longer run.',
                        'cleantalk-spam-protect'
                    ),
                ),
            ),
        ),

        // Comments and Messages
        'wc'                    => array(
            'title'  => __('WooCommerce', 'cleantalk-spam-protect'),
            'section' => 'hidden_section',
            'fields' => array(
                'forms__wc_checkout_test'       => array(
                    'title'           => __('WooCommerce checkout form', 'cleantalk-spam-protect'),
                    'description'     => __('Anti-Spam test for WooCommerce checkout form.', 'cleantalk-spam-protect'),
                    'childrens'       => array('forms__wc_register_from_order'),
                    'reverse_trigger' => true,
                    'options'         => array(
                        array('val' => 1, 'label' => __('On'), 'childrens_enable' => 0,),
                        array('val' => 0, 'label' => __('Off'), 'childrens_enable' => 1,),
                    ),
                ),
                'forms__wc_register_from_order' => array(
                    'title'           => __('Spam test for registration during checkout', 'cleantalk-spam-protect'),
                    'description'     => __(
                        'Enable Anti-Spam test for registration process which during woocommerce\'s checkout.',
                        'cleantalk-spam-protect'
                    ),
                    'parent'          => 'forms__wc_checkout_test',
                    'class'           => 'apbct_settings-field_wrapper--sub',
                    'reverse_trigger' => true,
                ),
                'forms__wc_add_to_cart'         => array(
                    'title'           => __(
                        'Check anonymous users when they add new items to the cart',
                        'cleantalk-spam-protect'
                    ),
                    'description'     => __(
                        'All anonymous users will be checked for spam if they add a new item to their shopping cart.',
                        'cleantalk-spam-protect'
                    ),
                    'reverse_trigger' => false,
                    'class'           => 'apbct_settings-field_wrapper--sub',
                    'options'         => array(
                        array('val' => 1, 'label' => __('On')),
                        array('val' => 0, 'label' => __('Off')),
                    ),
                ),
                'data__wc_store_blocked_orders' => array(
                    'title' => __('Store blocked orders', 'cleantalk-spam-protect'),
                    'description' => __('The orders which was blocked by the Anti-Spam will be stored and could be restored manually later if its needed.', 'cleantalk-spam-protect'),
                    'class' => 'apbct_settings-field_wrapper--sub',
                    'options' => array(
                        array('val' => 1, 'label' => __('On')),
                        array('val' => 0, 'label' => __('Off')),
                    ),
                ),
            ),
        ),

        // Comments and Messages
        'comments_and_messages' => array(
            'title'  => __('Comments and Messages', 'cleantalk-spam-protect'),
            'section' => 'hidden_section',
            'fields' => array(
                'comments__disable_comments__all'          => array(
                    'title'       => __('Disable all comments', 'cleantalk-spam-protect'),
                    'description' => __('Disabling comments for all types of content.', 'cleantalk-spam-protect'),
                    'childrens'   => array(
                        'comments__disable_comments__posts',
                        'comments__disable_comments__pages',
                        'comments__disable_comments__media',
                    ),
                    'options'     => array(
                        array('val' => 1, 'label' => __('On'), 'childrens_enable' => 0,),
                        array('val' => 0, 'label' => __('Off'), 'childrens_enable' => 1,),
                    ),
                ),
                'comments__disable_comments__posts'        => array(
                    'title'           => __('Disable comments for all posts', 'cleantalk-spam-protect'),
                    'class'           => 'apbct_settings-field_wrapper--sub',
                    'reverse_trigger' => true,
                ),
                'comments__disable_comments__pages'        => array(
                    'title'           => __('Disable comments for all pages', 'cleantalk-spam-protect'),
                    'class'           => 'apbct_settings-field_wrapper--sub',
                    'reverse_trigger' => true,
                ),
                'comments__disable_comments__media'        => array(
                    'title'           => __('Disable comments for all media', 'cleantalk-spam-protect'),
                    'class'           => 'apbct_settings-field_wrapper--sub',
                    'reverse_trigger' => true,
                ),
                'comments__bp_private_messages'            => array(
                    'title'       => __('BuddyPress Private Messages', 'cleantalk-spam-protect'),
                    'description' => __('Check buddyPress private messages.', 'cleantalk-spam-protect'),
                ),
                'comments__remove_old_spam'                => array(
                    'title'       => __('Automatically delete spam comments', 'cleantalk-spam-protect'),
                    'description' => sprintf(
                        __('Delete spam comments older than %d days.', 'cleantalk-spam-protect'),
                        $apbct->data['spam_store_days']
                    ),
                ),
                'comments__remove_comments_links'          => array(
                    'title'       => __('Remove links from approved comments', 'cleantalk-spam-protect'),
                    'description' => __(
                        'Remove links from approved comments. Replace it with "[Link deleted]"',
                        'cleantalk-spam-protect'
                    ),
                ),
                'comments__show_check_links'               => array(
                    'title'       => __('Show links to check Emails, IPs for spam', 'cleantalk-spam-protect'),
                    'description' =>
                        sprintf(
                            /* translators: Brand name */
                            __('Shows a little icon next to IP and email addresses allowing you to click it and check the addresses via the database of %s.', 'cleantalk-spam-protect'),
                            $apbct->data["wl_brandname_short"]
                        )
                        . ' '
                        . sprintf(
                            /* translators: 1: Comments list URL, 2: Users list URL */
                            __('For example, in the menus <a href="%1$s">Comments</a> and <a href="%2$s">Users</a>', 'cleantalk-spam-protect'),
                            get_admin_url(null, 'edit-comments.php'),
                            get_admin_url(null, 'users.php')
                        ),
                    'display'     => ! $apbct->white_label,
                ),
                'comments__manage_comments_on_public_page' => array(
                    'title'       => __('Manage comments on public pages', 'cleantalk-spam-protect'),
                    'description' => __(
                        'Allows administrators to manage comments on public post\'s pages with small interactive menu.',
                        'cleantalk-spam-protect'
                    ),
                    'display'     => ! $apbct->white_label,
                ),
            ),
        ),

        // Data Processing
        'data_processing'       => array(
            'title'  => __('Data Processing', 'cleantalk-spam-protect'),
            'section' => 'hidden_section',
            'fields' => array(
                'data__protect_logged_in'              => array(
                    'title'       => __("Protect logged in Users", 'cleantalk-spam-protect'),
                    'description' => __(
                        'Turn this option on to check for spam any submissions (comments, contact forms and etc.) from registered Users.',
                        'cleantalk-spam-protect'
                    ),
                ),
                'comments__check_comments_number'      => array(
                    'title'       => __("Don't check trusted user's comments", 'cleantalk-spam-protect'),
                    'description' => sprintf(
                        __("Don't check comments for users with above %d comments.", 'cleantalk-spam-protect'),
                        defined('CLEANTALK_CHECK_COMMENTS_NUMBER') ? CLEANTALK_CHECK_COMMENTS_NUMBER : 3
                    ),
                ),
                'data__use_ajax'                       => array(
                    'title'       => __('Use AJAX for JavaScript check', 'cleantalk-spam-protect'),
                    'description' => __(
                        'Options helps protect WordPress against spam with any caching plugins. Turn this option on to avoid issues with caching plugins.',
                        'cleantalk-spam-protect'
                    ),
                    'childrens'   => array('data__ajax_type_checking_js')
                ),
                'data__ajax_type_checking_js' => array(
                    'display'    => $apbct->settings['data__use_ajax'] == 1,
                    'callback' => 'apbct_settings__ajax_handler_type_notification'
                ),
                'data__use_static_js_key'              => array(
                    'title'       => __('Use static keys for JavaScript check', 'cleantalk-spam-protect'),
                    'description' => __(
                        'Could help if you have cache for AJAX requests and you are dealing with false positives. Slightly decreases protection quality. Auto - Static key will be used if caching plugin is spotted.',
                        'cleantalk-spam-protect'
                    ),
                    'options'     => array(
                        array('val' => 1, 'label' => __('On'),),
                        array('val' => 0, 'label' => __('Off'),),
                        array('val' => -1, 'label' => __('Auto'),),
                    ),
                ),
                'data__general_postdata_test'          => array(
                    'title'       => __('Check all post data', 'cleantalk-spam-protect'),
                    'description' =>
                        __('Check all POST submissions from website visitors. Enable this option if you have spam misses on website.', 'cleantalk-spam-protect')
                        . ( ! $apbct->white_label && ! $apbct->data["wl_mode_enabled"] ?
                            __(' Or you don`t have records about missed spam here:', 'cleantalk-spam-protect')
                            . '&nbsp;'
                            //HANDLE LINK
                            . '<a href="https://cleantalk.org/my/?user_token='
                            . $apbct->user_token . '&utm_source=wp-backend&utm_medium=admin-bar&cp_mode=antispam" target="_blank">'
                            . __('CleanTalk Dashboard', 'cleantalk-spam-protect')
                            . '</a>.' : '' )
                        . '<br />'
                        . __('СAUTION! Option can catch POST requests in WordPress backend', 'cleantalk-spam-protect'),
                ),
                'data__set_cookies'                    => array(
                    'title'       => __("Set cookies", 'cleantalk-spam-protect'),
                    'description' =>
                        __(
                            'The "On" mode means usual cookies in visitor`s browsers. If you use cache plugins, some visitor parameters may be transmitted from the cache and this will lead to inaccurate spam filtering.',
                            'cleantalk-spam-protect'
                        )
                        . '<br>'
                        . __(
                            'The "Alternative mechanism" mode means that visitor data will be stored entirely in the site database. Database resource usage may vary depending on site traffic.',
                            'cleantalk-spam-protect'
                        )
                        . '<br>'
                        . __(
                            'The "Auto" mode (default setting) uses the "Off" mode and switches to the "Alternative mechanism" in case of server cache detection (e.g. Varnish, Siteground).',
                            'cleantalk-spam-protect'
                        )
                        . '<br>'
                        . __(
                            'The "Off" mode combines partial data storage in the site database, partial data storage in a browser`s localstorage.',
                            'cleantalk-spam-protect'
                        ),
                    'long_description' => true,
                    'input_type'  => 'radio',
                    'options'     => array(
                        array('val' => 1, 'label' => __('On', 'cleantalk-spam-protect'), 'childrens_enable' => 0,),
                        array(
                            'val'              => 2,
                            'label'            => __(
                                'Store data in the website database (alternative mechanism)',
                                'cleantalk-spam-protect'
                            ),
                            'childrens_enable' => 1,
                        ),
                        array('val' => 3, 'label' => __('Auto', 'cleantalk-spam-protect'), 'childrens_enable' => 0,),
                        array('val' => 0, 'label' => __('Off', 'cleantalk-spam-protect'), 'childrens_enable' => 0,),
                    ),
                    'childrens'   => array('data__ajax_type')
                ),
                'data__ajax_type' => array(
                    'display'    => $apbct->data['cookies_type'] === 'alternative',
                    'callback' => 'apbct_settings__check_alt_cookies_types'
                ),
                //bot detector
                'data__bot_detector_enabled' => array(
                    'title' => __('Use ', 'cleantalk-spam-protect')
                               . $apbct->data['wl_brandname']
                               . __(' JavaScript library', 'cleantalk-spam-protect'),
                    'description' => __('This option includes external ', 'cleantalk-spam-protect')
                               . $apbct->data['wl_brandname']
                               . __(' JavaScript library to getting visitors info data', 'cleantalk-spam-protect'),
                    'childrens' => array('exclusions__bot_detector')
                ),
                'exclusions__bot_detector' => array(
                    'title' => __('JavaScript Library Exclusions', 'cleantalk-spam-protect'),
                    'childrens' => array(
                        'exclusions__bot_detector__form_attributes',
                        'exclusions__bot_detector__form_children_attributes',
                        'exclusions__bot_detector__form_parent_attributes',
                    ),
                    'description' => __(
                        'Regular expression. Use to skip a HTML form from special service field attach.',
                        'cleantalk-spam-protect'
                    ),
                    'parent' => 'data__bot_detector_enabled',
                ),
                'exclusions__bot_detector__form_attributes'             => array(
                    'type'        => 'text',
                    'title'       => __('Exclude any forms that has attribute matches.', 'cleantalk-spam-protect'),
                    'parent' => 'exclusions__bot_detector',
                    'class' => 'apbct_settings-field_wrapper--sub',
                    'long_description' => true,
                ),
                'exclusions__bot_detector__form_children_attributes'             => array(
                    'type'        => 'text',
                    'title'       => __('Exclude any forms that includes a child element with attribute matches.', 'cleantalk-spam-protect'),
                    'parent' => 'exclusions__bot_detector',
                    'class' => 'apbct_settings-field_wrapper--sub',
                    'long_description' => true,
                ),
                'exclusions__bot_detector__form_parent_attributes'             => array(
                    'type'        => 'text',
                    'title'       => __('Exclude any forms that includes a parent element with attribute matches.', 'cleantalk-spam-protect'),
                    'parent' => 'exclusions__bot_detector',
                    'class' => 'apbct_settings-field_wrapper--sub',
                    'long_description' => true,
                ),
                'wp__use_builtin_http_api'             => array(
                    'title'       => __("Use WordPress HTTP API", 'cleantalk-spam-protect'),
                    'description' => __(
                        'Alternative way to connect the Cloud. Use this if you have connection problems.',
                        'cleantalk-spam-protect'
                    ),
                ),
                'data__pixel'                          => array(
                    'title'       => __('Add a ' . $apbct->data["wl_brandname_short"] . ' Pixel to improve IP-detection', 'cleantalk-spam-protect'),
                    'description' =>
                        __(
                            'Upload small graphic file from ' . $apbct->data["wl_brandname_short"] . '\'s server to improve IP-detection.',
                            'cleantalk-spam-protect'
                        )
                        . '<br>'
                        . __(
                            '"Auto" use JavaScript option if cache solutions are found.',
                            'cleantalk-spam-protect'
                        )
                        . '<br>'
                        . __(
                            'If the "Auto" mode is enabled and the "Anti-Spam by CleanTalk JavaScript library" is enabled, the pixel setting will be disabled.',
                            'cleantalk-spam-protect'
                        ),
                    'long_description' => true,
                    'options'     => array(
                        array('val' => 1, 'label' => __('Via direct output', 'cleantalk-spam-protect'),),
                        array('val' => 2, 'label' => __('Via JavaScript', 'cleantalk-spam-protect'),),
                        array('val' => 3, 'label' => __('Auto', 'cleantalk-spam-protect'),),
                        array('val' => 0, 'label' => __('Off', 'cleantalk-spam-protect'),),
                    ),
                ),
                'data__email_check_before_post'        => array(
                    'title'       => __('Check email before POST request', 'cleantalk-spam-protect'),
                    'description' => __('Check email address before sending form data', 'cleantalk-spam-protect'),
                ),
                'data__email_check_exist_post'        => array(
                    'title'       => __('Show email existence alert when filling in the field', 'cleantalk-spam-protect'),
                    'description' => __('Check email address exist before sending form data', 'cleantalk-spam-protect'),
                ),
            ),
        ),

        // Data Processing
        'contact_data_encoding'       => array(
            'title'  => __('Contact Data Encoding', 'cleantalk-spam-protect'),
            'section' => 'hidden_section',
            'fields' => array(
                'data__email_decoder'        => array(
                    'title' => __('Encode contact data', 'cleantalk-spam-protect'),
                    'description' => EmailEncoder::getEncoderOptionDescription(),
                    'childrens' => array(
                        'data__email_decoder_buffer',
                        'data__email_decoder_obfuscation_mode',
                        'data__email_decoder_obfuscation_custom_text',
                        'data__email_decoder_encode_phone_numbers',
                        'data__email_decoder_encode_email_addresses'
                    ),
                    'long_description' => true,
                ),
                'data__email_decoder_encode_email_addresses'        => array(
                    'title' => __('Encode email addresses', 'cleantalk-spam-protect'),
                    'description' => EmailEncoder::getEmailsEncodingDescription(),
                    'class'           => 'apbct_settings-field_wrapper--sub',
                    'parent'            => 'data__email_decoder',
                ),
                'data__email_decoder_encode_phone_numbers'        => array(
                    'title' => __('Encode phone numbers', 'cleantalk-spam-protect'),
                    'description' => EmailEncoder::getPhonesEncodingDescription(),
                    'class'           => 'apbct_settings-field_wrapper--sub',
                    'parent'            => 'data__email_decoder',
                    'long_description' => true,
                ),
                'data__email_decoder_obfuscation_mode'        => array(
                    'title'             => __('Encoder obfuscation mode', 'cleantalk-spam-protect'),
                    'description'       => EmailEncoder::getObfuscationModesDescription(),
                    'parent'            => 'data__email_decoder',
                    'class'             => 'apbct_settings-field_wrapper--sub',
                    'options'  => EmailEncoder::getObfuscationModesOptionsArray(),
                    'childrens' => array('data__email_decoder_obfuscation_custom_text'),
                    'long_description' => true,
                ),
                'data__email_decoder_obfuscation_custom_text'             => array(
                    'type'        => 'textarea',
                    'title'       => __('Custom text to replace email', 'cleantalk-spam-protect'),
                    'value' => EmailEncoder::getDefaultReplacingText(),
                    'description'       => __('If appropriate mode selected, this text will be shown instead of an email.', 'cleantalk-spam-protect'),
                    'parent' => 'data__email_decoder_obfuscation_mode',
                    'class' => 'apbct_settings-field_wrapper--sub',
                ),
                'data__email_decoder_buffer'        => array(
                    'title'       => __('Use the output buffer', 'cleantalk-spam-protect'),
                    'description' => EmailEncoder::getBufferUsageOptionDescription(),
                    'parent'          => 'data__email_decoder',
                    'class'           => 'apbct_settings-field_wrapper--sub',
                ),
            )
        ),

        // Exclusions
        'exclusions'            => array(
            'title'  => __('Exclusions', 'cleantalk-spam-protect'),
            'section' => 'hidden_section',
            'fields' => array(
                'exclusions__log_excluded_requests'        => array(
                    'title'       => __('Log excluded requests', 'cleantalk-spam-protect'),
                    'description' => __('Enable the option to log some types of the excluded requests, like comments from approved authors or POST requests without an Email address', 'cleantalk-spam-protect'),
                ),
                'exclusions__urls'               => array(
                    'type'        => 'textarea',
                    'title'       => __('URL exclusions', 'cleantalk-spam-protect'),
                    'description' => __(
                        'You could type here a part of the URL you want to exclude. Use commas or new lines as separator. Exclusion value will be sliced to 128 chars, number of exclusions is restricted by 20 values.',
                        'cleantalk-spam-protect'
                    ),
                ),
                'exclusions__urls__use_regexp'   => array(
                    'type'  => 'checkbox',
                    'title' => __('Use Regular Expression in URL Exclusions', 'cleantalk-spam-protect'),
                ),
                'exclusions__fields'             => array(
                    'type'        => 'textarea',
                    'title'       => __('Field Name Exclusions', 'cleantalk-spam-protect'),
                    'description' => __(
                        'You could type here field names you want to exclude. These fields will be excluded, other 
                        fields will be passed to the Anti-Spam check. Use commas as separator. Exclusion value will be 
                        sliced to 128 chars, number of exclusions is restricted by 20 values.',
                        'cleantalk-spam-protect'
                    ),
                ),
                'exclusions__fields__use_regexp' => array(
                    'type'  => 'checkbox',
                    'title' => __('Use Regular Expression in Field Exclusions', 'cleantalk-spam-protect'),
                ),
                'exclusions__form_signs'             => array(
                    'type'        => 'textarea',
                    'title'       => __('Form Signs Exclusions', 'cleantalk-spam-protect'),
                    'description' => __(
                        'Regular expression. If the form contains any of these signs in POST array keys or in value of "action" key, the 
                        whole form submission is excluded from spam checking. See more details in long description, 
                        just click question mark near the option header.',
                        'cleantalk-spam-protect'
                    ),
                    'long_description' => true
                ),
                //roles
                'exclusions__roles'              => array(
                    'type'                    => 'select',
                    'title' => __('Roles Exclusions', 'cleantalk-spam-protect'),
                    'multiple'                => true,
                    'options_callback'        => 'apbct_get_all_roles',
                    'description'             => __(
                        'Roles which bypass spam test. Hold CTRL to select multiple roles.',
                        'cleantalk-spam-protect'
                    ),
                ),
            ),
        ),

        // Admin bar
        'admin_bar'             => array(
            'title'          => __('Admin bar', 'cleantalk-spam-protect'),
            'default_params' => array(),
            'description'    => '',
            'html_before'    => '',
            'html_after'     => '',
            'section'        => 'hidden_section',
            'fields'         => array(
                'admin_bar__show'             => array(
                    'title'       => __('Show statistics in admin bar', 'cleantalk-spam-protect'),
                    'description' => __(
                        'Show/hide icon in top level menu in WordPress backend. The number of submissions is being counted for past 24 hours.',
                        'cleantalk-spam-protect'
                    ),
                    'childrens'   => array(
                        'admin_bar__all_time_counter',
                        'admin_bar__daily_counter',
                        'admin_bar__sfw_counter'
                    ),
                ),
                'admin_bar__all_time_counter' => array(
                    'title'       => __('Show All-time counter', 'cleantalk-spam-protect'),
                    'description' => __(
                        'Display all-time requests counter in the admin bar. Counter displays number of requests since plugin installation.',
                        'cleantalk-spam-protect'
                    ),
                    'parent'      => 'admin_bar__show',
                    'class'       => 'apbct_settings-field_wrapper--sub',
                ),
                'admin_bar__daily_counter'    => array(
                    'title'       => __('Show 24 hours counter', 'cleantalk-spam-protect'),
                    'description' => __(
                        'Display daily requests counter in the admin bar. Counter displays number of requests of the past 24 hours.',
                        'cleantalk-spam-protect'
                    ),
                    'parent'      => 'admin_bar__show',
                    'class'       => 'apbct_settings-field_wrapper--sub',
                ),
                'admin_bar__sfw_counter'      => array(
                    'title'       => __('SpamFireWall counter', 'cleantalk-spam-protect'),
                    'description' => __(
                        'Display SpamFireWall requests in the admin bar. Counter displays number of requests since plugin installation.',
                        'cleantalk-spam-protect'
                    ),
                    'parent'      => 'admin_bar__show',
                    'class'       => 'apbct_settings-field_wrapper--sub',
                ),
            ),
        ),

        // SFW features
        'sfw_features'          => array(
            'title'          => __('SpamFireWall features', 'cleantalk-spam-protect'),
            'default_params' => array(),
            'description'    => '',
            'html_before'    => '',
            'html_after'     => '',
            'section'        => 'hidden_section',
            'fields'         => array(
                'sfw__enabled' => array(
                    'type'        => 'checkbox',
                    'title'       => 'SpamFireWall', // Do not to localize this phrase
                    'description' =>
                        __(
                            "This option allows to filter spam bots before they access website. Also reduces CPU usage on hosting server and accelerates pages load time.",
                            'cleantalk-spam-protect'
                        )
                        . '<br>'
                        . esc_html__(
                            'If the setting is turned on, plugin will automatically add IP address for each session with administration rights to Personal list in the cloud.',
                            'cleantalk-spam-protect'
                        )
                        . $additional_sfw_description,
                    'childrens'   => array('sfw__anti_flood', 'sfw__anti_crawler', 'sfw__random_get', 'misc__force_sfw_update_button'),
                    'long_description' => true,
                ),
                'sfw__random_get'             => array(
                    'type'        => 'radio',
                    'options'     => array(
                        array('val' => 1, 'label' => __('On'),),
                        array('val' => 0, 'label' => __('Off'),),
                        array('val' => -1, 'label' => __('Auto'),),
                    ),
                    'title'       => __('Uniq GET option', 'cleantalk-spam-protect'),
                    'class'       => 'apbct_settings-field_wrapper',
                    'parent'      => 'sfw__enabled',
                    'description' => __(
                        'If a visitor gets the SpamFireWall page, the plugin will put a unique GET variable in the URL to avoid issues with caching plugins. Example: https://SITE.COM/?sfw=pass1629985735',
                        'cleantalk-spam-protect'
                    ),
                ),
                'sfw__custom_logo' => array(
                    'callback'    => 'apbct_settings__custom_logo',
                    'title'       => __('Custom logo on SpamFireWall blocking pages', 'cleantalk-spam-protect'),
                    'parent'      => 'sfw__enabled',
                ),
                'sfw__anti_crawler'           => array(
                    'type'        => 'checkbox',
                    'title'       => 'Anti-Crawler' . $additional_ac_title, // Do not to localize this phrase
                    'class'       => 'apbct_settings-field_wrapper',
                    'parent'      => 'sfw__enabled',
                    'description' =>
                        __(
                            'Plugin shows SpamFireWall stop page for any bot, except allowed bots (Google, Yahoo and etc).',
                            'cleantalk-spam-protect'
                        )
                        . '<br>'
                        . __(
                            'Anti-Crawler includes blocking bots by the User-Agent. Use Personal lists in the Dashboard to filter specific User-Agents.',
                            'cleantalk-spam-protect'
                        ),
                    'long_description' => true,
                ),
                'sfw__anti_flood'             => array(
                    'type'        => 'checkbox',
                    'title'       => 'Anti-Flood', // Do not to localize this phrase
                    'class'       => 'apbct_settings-field_wrapper',
                    'parent'      => 'sfw__enabled',
                    'childrens'   => array('sfw__anti_flood__view_limit',),
                    'description' => __(
                        'Shows the SpamFireWall page for bots trying to crawl your site. Look at the page limit setting below.',
                        'cleantalk-spam-protect'
                    ),
                    'long_description' => true,
                ),
                'sfw__anti_flood__view_limit' => array(
                    'type'        => 'text',
                    'title'       => 'Anti-Flood ' . __('Page Views Limit', 'cleantalk-spam-protect'), // Do not to localize this phrase
                    'class'       => 'apbct_settings-field_wrapper--sub',
                    'parent'      => 'sfw__anti_flood',
                    'description' => __(
                        'Count of page view per 1 minute before plugin shows SpamFireWall page. SpamFireWall page active for 30 second after that valid visitor (with JavaScript) passes the page to the demanded page of the site.',
                        'cleantalk-spam-protect'
                    ),
                ),
                'misc__force_sfw_update_button' => array(
                    'callback' => 'apbct_sfw_force_sfw_update_button',
                    'display' => defined('APBCT_IS_LOCALHOST') && APBCT_IS_LOCALHOST,
                    'parent' => 'sfw__enabled',
                ),
            ),
        ),

        // Misc
        'misc'                  => array(
            'title'      => __('Miscellaneous', 'cleantalk-spam-protect'),
            'section'    => 'hidden_section',
            'html_after' => '</div><div id="apbct_hidden_section_nav">{HIDDEN_SECTION_NAV}<div class="apbct_hidden_section_nav_mob_btn"></div></div></div>',
            'fields'     => array(
                'misc__send_connection_reports' => array(
                    'type'        => 'checkbox',
                    'title'       => __('Send connection reports', 'cleantalk-spam-protect'),
                    'description' => __("Checking this box you allow plugin to send the information about your connection. These reports could contain next info:", 'cleantalk-spam-protect')
                        . '<br>'
                        . __(' - connection status to ' . $apbct->data["wl_brandname_short"] . ' cloud during Anti-Spam request', 'cleantalk-spam-protect')
                        . $send_connection_reports__sfw_text
                    ),
                'misc__async_js'                => array(
                    'type'        => 'checkbox',
                    'title'       => __('Async JavaScript loading', 'cleantalk-spam-protect'),
                    'description' => __(
                        'Use async loading for scripts. Warning: This could reduce filtration quality.',
                        'cleantalk-spam-protect'
                    ),
                ),
                'misc__store_urls'              => array(
                    'type'        => 'checkbox',
                    'title'       => __('Store visited URLs', 'cleantalk-spam-protect'),
                    'description' => __(
                        "Plugin stores last 5 visited URLs (HTTP REFERRERS) before visitor submits form on the site. You can see stored visited URLS for each visitor in your Dashboard. Turn the option on to improve Anti-Spam protection.",
                        'cleantalk-spam-protect'
                    ),
                ),
                'wp__comment_notify'            => array(
                    'type'        => 'checkbox',
                    'title'       => __(
                        'Notify users with selected roles about new approved comments. Hold CTRL to select multiple roles.',
                        'cleantalk-spam-protect'
                    ),
                    'description' => sprintf(
                        __(
                            "If enabled, overrides similar WordPress %sdiscussion settings%s.",
                            'cleantalk-spam-protect'
                        ),
                        '<a href="options-discussion.php">',
                        '</a>'
                    ),
                    'childrens'   => array('wp__comment_notify__roles'),
                ),
                'wp__comment_notify__roles'     => array(
                    'type'                    => 'select',
                    'multiple'                => true,
                    'parent'                  => 'wp__comment_notify',
                    'options_callback'        => 'apbct_get_all_roles',
                    'class'                   => 'apbct_settings-field_wrapper--sub',
                ),
                'wp__dashboard_widget__show'    => array(
                    'type'  => 'checkbox',
                    'title' => __('Show Dashboard Widget', 'cleantalk-spam-protect'),
                ),
                'misc__action_adjust'        => array(
                    'callback' => 'apbct_settings_field__action_adjust',
                    'display' => apbct_is_plugin_active('w3-total-cache/w3-total-cache.php') || apbct_is_plugin_active('litespeed-cache/litespeed-cache.php')
                ),
                'misc__complete_deactivation'   => array(
                    'type'        => 'checkbox',
                    'title'       => __('Complete deactivation', 'cleantalk-spam-protect'),
                    'description' => __('Leave no trace in the system after deactivation.', 'cleantalk-spam-protect'),
                ),

            ),
        ),

        // Trust text, affiliate settings
        'trusted_and_affiliate'                    => array(
            'title'  => __('Trust text, affiliate settings', 'cleantalk-spam-protect'),
            'display' => ! $apbct->data["wl_mode_enabled"],
            //'section' => 'hidden_section',
            'fields' => array(
                'trusted_and_affiliate__shortcode'       => array(
                    'title'           => __('Shortcode', 'cleantalk-spam-protect'),
                    'description' => __(
                        'You can place this shortcode anywhere on your website. Adds trust text stating that the website is protected from spam by CleanTalk Anti-Spam protection',
                        'cleantalk-spam-protect'
                    ),
                    'childrens' => array('trusted_and_affiliate__shortcode_tag'),
                    'type' => 'checkbox'
                ),
                'trusted_and_affiliate__shortcode_tag'                    => array(
                    'type'        => 'affiliate_shortcode',
                    'title'       => __('<- Copy this text and place shortcode wherever you need.', 'cleantalk-spam-protect'),
                    'parent'      => 'trusted_and_affiliate__shortcode',
                    'class'       => 'apbct_settings-field_wrapper--sub',
                    'disabled' => true
                ),
                'trusted_and_affiliate__footer' => array(
                    'title'           => __('Add to the footer', 'cleantalk-spam-protect'),
                    'description'     => __(
                        'Adds trust text stating that the website is protected from spam by CleanTalk Anti-Spam protection to the footer of your website.',
                        'cleantalk-spam-protect'
                    ),
                    'parent'          => '',
                    //'class'           => 'apbct_settings-field_wrapper--sub',
                    'reverse_trigger' => true,
                    'type' => 'checkbox'
                ),
                'trusted_and_affiliate__under_forms' => array(
                    'title'           => __(
                        'Add under forms.',
                        'cleantalk-spam-protect'
                    ),
                    'description'     => __(
                        'Adds trust text stating that the website is protected from spam by CleanTalk Anti-Spam protection under web form on your website.',
                        'cleantalk-spam-protect'
                    ),
                    'reverse_trigger' => true,
                    'type' => 'checkbox'
                ),
                'trusted_and_affiliate__add_id'         => array(
                    'title'           => __(
                        'Append your affiliate ID',
                        'cleantalk-spam-protect'
                    ),
                    'description'     => __(
                        'Enable this option to append your specific affiliate ID to the trust text created by the options above ("Shortcode" or "Add to the footer"). Terms and your affiliate ID of the {CT_AFFILIATE_TERMS}.',
                        'cleantalk-spam-protect'
                    ),
                    'reverse_trigger' => false,
                    'type' => 'checkbox'
                ),
            ),
        ),

    );

    return $fields;
}

function apbct_settings__set_fields__network($fields)
{
    global $apbct;

    $prepared_links = array(
        'help_wl_multisite' => LinkConstructor::buildCleanTalkLink(
            'help_wl_multisite',
            'help/anti-spam-white-label-multisite'
        )
    );

    $additional_fields = array(
        'wpms_settings' => array(
            'default_params' => array(),
            'description'    => '',
            'html_before'    => '<br><hr><br>'
                                . '<span id="ct_adv_showhide">'
                                . '<a href="#" class="apbct_color--gray" onclick="event.preventDefault(); apbctExceptedShowHide(\'apbct_settings__dwpms_settings\');">'
                                . __('WordPress Multisite (WPMS) settings', 'cleantalk-spam-protect')
                                . '</a>'
                                . '</span>'
                                . '<div id="apbct_settings__dwpms_settings" style="display: block;">',
            'html_after'     => '</div>',
            'fields'         => array(
                'multisite__work_mode'                                          => array(
                    'type'             => 'select',
                    'options'          => array(
                        array(
                            'val'             => 1,
                            'label'           => __(
                                'Mutual Account, Individual Access Keys',
                                'cleantalk-spam-protect'
                            ),
                            'children_enable' => 1,
                        ),
                        array(
                            'val'             => 2,
                            'label'           => __('Mutual Account, Mutual Access Key', 'cleantalk-spam-protect'),
                            'children_enable' => 0,
                        ),
                        array(
                            'val'             => 3,
                            'label'           => __(
                                'Individual accounts, individual Access keys',
                                'cleantalk-spam-protect'
                            ),
                            'children_enable' => 0,
                        ),
                    ),
                    'title'            => __('WordPress Multisite Work Mode', 'cleantalk-spam-protect'),
                    'description'      => __(
                        'You can choose the work mode here for the child blogs and how they will operate with the CleanTalk Cloud. Press "?" for the detailed description.',
                        'cleantalk-spam-protect'
                    ),
                    'long_description' => true,
                    'display'          => APBCT_WPMS && is_main_site(),
                    'childrens'        => array('multisite__hoster_api_key', 'multisite__white_label'),
                    'network'          => true,
                ),
                'multisite__hoster_api_key'                                     => array(
                    'type'             => 'text',
                    'required'         => true,
                    'title'            => __('Hoster Access key', 'cleantalk-spam-protect'),
                    'description'      => sprintf(
                        __('Copy the Access key from your %sCleanTalk Profile%s', 'cleantalk-spam-protect'),
                        //HANDLE LINK
                        '<a href="https://cleantalk.org/my/profile#api_keys" target="_blank">',
                        '</a>'
                    ),
                    'class'            => 'apbct_settings-field_wrapper--sub',
                    'display'          => APBCT_WPMS && is_main_site(),
                    'disabled'         => ! isset($apbct->network_settings['multisite__work_mode']) || $apbct->network_settings['multisite__work_mode'] != 1,
                    'parent'           => 'multisite__work_mode',
                    'network'          => true,
                ),
                'multisite__service_utilization'                                => array(
                    'type'     => 'field',
                    'class'    => 'apbct_settings-field_wrapper--sub',
                    'callback' => 'apbct_field_service_utilization',
                    'display'  => APBCT_WPMS && is_main_site() && $apbct->network_settings['multisite__work_mode'] == 1,
                ),
                'multisite__white_label'                                        => array(
                    'type'        => 'checkbox',
                    'title'       => __('Enable White Label Mode', 'cleantalk-spam-protect'),
                    'description' => sprintf(
                        __("Learn more information %shere%s.", 'cleantalk-spam-protect'),
                        //HANDLE LINK
                        '<a target="_blank" href="' . $prepared_links['help_wl_multisite'] . '">',
                        '</a>'
                    ),
                    'childrens'   => array('multisite__white_label__plugin_name'),
                    'disabled'    => defined('CLEANTALK_ACCESS_KEY') ||
                                     ! isset($apbct->network_settings['multisite__work_mode']) ||
                                     $apbct->network_settings['multisite__work_mode'] != 1,
                    'parent'      => 'multisite__work_mode',
                    'class'       => 'apbct_settings-field_wrapper--sub',
                    'network'     => true,
                ),
                'multisite__white_label__plugin_name'                           => array(
                    'title'       => __('Plugin name', 'cleantalk-spam-protect'),
                    'description' => sprintf(
                        __(
                            "Specify plugin name. Leave empty for deafult %sAnti-Spam by CleanTalk%s",
                            'cleantalk-spam-protect'
                        ),
                        '<b>',
                        '</b>'
                    ),
                    'type'        => 'text',
                    'parent'      => 'multisite__white_label',
                    'class'       => 'apbct_settings-field_wrapper--sub',
                    'network'     => true,
                ),
                'multisite__allow_custom_settings'                              => array(
                    'type'        => 'checkbox',
                    'title'       => __('Allow users to manage plugin settings', 'cleantalk-spam-protect'),
                    'description' => __('Allow to change settings on child sites.', 'cleantalk-spam-protect'),
                    'display'     => APBCT_WPMS && is_main_site(),
                    'disabled'    => $apbct->network_settings['multisite__work_mode'] == 2,
                    'network'     => true,
                ),
                'multisite__use_settings_template'                              => array(
                    'type'        => 'checkbox',
                    'title'       => __('Use settings template', 'cleantalk-spam-protect'),
                    'description' => __("Use the current settings template for child sites.", 'cleantalk-spam-protect'),
                    'childrens'   => array(
                        'multisite__use_settings_template_apply_for_new',
                        'multisite__use_settings_template_apply_for_current'
                    ),
                    'network'     => true,
                ),
                'multisite__use_settings_template_apply_for_new'                => array(
                    'type'        => 'checkbox',
                    'title'       => __('Apply for newly added sites.', 'cleantalk-spam-protect'),
                    'description' => __(
                        "The newly added site will have the same preset settings template.",
                        'cleantalk-spam-protect'
                    ),
                    'parent'      => 'multisite__use_settings_template',
                    'class'       => 'apbct_settings-field_wrapper--sub',
                    'network'     => true,
                ),
                'multisite__use_settings_template_apply_for_current'            => array(
                    'type'        => 'checkbox',
                    'title'       => __('Apply for current sites.', 'cleantalk-spam-protect'),
                    'description' => __(
                        "Apply current settings template for selected sites.",
                        'cleantalk-spam-protect'
                    ),
                    'parent'      => 'multisite__use_settings_template',
                    'childrens'   => array('multisite__use_settings_template_apply_for_current_list_sites'),
                    'class'       => 'apbct_settings-field_wrapper--sub',
                    'network'     => true,
                ),
                'multisite__use_settings_template_apply_for_current_list_sites' => array(
                    'type'                    => 'select',
                    'multiple'                => true,
                    'options_callback'        => 'apbct_get_all_child_domains',
                    'options_callback_params' => array(true),
                    'class'                   => 'apbct_settings-field_wrapper--sub',
                    'parent'                  => 'multisite__use_settings_template_apply_for_current',
                    'description'             => __(
                        'Sites to apply settings. Hold CTRL to select multiple sites.',
                        'cleantalk-spam-protect'
                    ),
                    'network'                 => true,
                ),
            )
        )
    );

    $fields = array_merge_recursive($fields, $additional_fields);

    return $fields;
}

function apbct_settings__add_groups_and_fields($fields)
{
    global $apbct;

    $apbct->settings_fields_in_groups = $fields;

    $field_default_params = array(
        'callback'        => 'apbct_settings__field__draw',
        'type'            => 'radio',
        'options'         => array(
            array('val' => 1, 'label' => __('On', 'cleantalk-spam-protect'), 'childrens_enable' => 1,),
            array('val' => 0, 'label' => __('Off', 'cleantalk-spam-protect'), 'childrens_enable' => 0,),
        ),
        'def_class'       => 'apbct_settings-field_wrapper',
        'class'           => '',
        'parent'          => '',
        'childrens'       => array(),
        'hide'            => array(),
        // 'title'           => 'Default title',
        // 'description'     => 'Default description',
        'display'         => true,
        // Draw settings or not
        'reverse_trigger' => false,
        // How to allow child settings. Childrens are opened when the parent triggered "ON". This is overrides by this option
        'multiple'        => false,
        'description'     => '',
        'network'         => false,
        'disabled'        => false,
        'required'        => false,
    );

    foreach ( $apbct->settings_fields_in_groups as $group_name => $group ) {
        if ( isset($group['display']) && ! $group['display'] ) {
            continue;
        }

        add_settings_section('apbct_section__' . $group_name, '', '', 'cleantalk-spam-protect');

        foreach ( $group['fields'] as $field_name => $field ) {
            // Normalize $field['options'] from callback function to this type  array( array( 'val' => 1, 'label'  => __('On'), ), )
            if ( ! empty($field['options_callback']) ) {
                $options = call_user_func_array(
                    $field['options_callback'],
                    ! empty($field['options_callback_params']) ? $field['options_callback_params'] : array()
                );
                foreach ( $options as &$option ) {
                    if ( is_array($option) ) {
                        $option = array(
                            'val'   => isset($option['val']) ? $option['val'] : current($option),
                            'label' => isset($option['label']) ? $option['label'] : end($option)
                        );
                    } else {
                        $option = array('val' => $option, 'label' => $option);
                    }
                }
                unset($option);
                $field['options'] = $options;
            }

            $params = ! empty($group['default_params'])
                ? array_merge($group['default_params'], $field)
                : array_merge($field_default_params, $field);

            $params['name'] = $field_name;

            if ( isset($params['display']) && ! $params['display'] ) {
                continue;
            }

            if (isset($params['callback']) && is_callable($params['callback'])) {
                add_settings_field(
                    'apbct_field__' . $field_name,
                    '',
                    $params['callback'],
                    'cleantalk',
                    'apbct_section__' . $group_name,
                    $params
                );
            }
        }
    }
}

/**
 * Admin callback function - Displays plugin options page
 */
function apbct_settings__display()
{
    global $apbct;

    $actual_plugin_name = $apbct->plugin_name;
    if (isset($apbct->data['wl_brandname']) && $apbct->data['wl_brandname'] !== APBCT_NAME) {
        $actual_plugin_name = $apbct->data['wl_brandname'];
    }

    // Title
    echo '<h2 class="apbct_settings-title">' . __($actual_plugin_name, 'cleantalk-spam-protect') . '</h2>';

    // Subtitle for IP license
    if ( $apbct->moderate_ip ) {
        echo '<h4 class="apbct_settings-subtitle apbct_color--gray">' .
            __('Hosting Anti-Spam', 'cleantalk-spam-protect') . '</h4>';
    }

    echo '<form action="options.php" method="post" class="apbct_settings-page">';
    apbct_settings__error__output();

    // Output spam count
    if ( $apbct->key_is_ok && apbct_api_key__is_correct() ) {
        if ( $apbct->spam_count > 0 ) {
            echo '<div class="apbct_settings-subtitle" style="top: 0; margin-bottom: 10px; width: 200px;">'
                 . '<br>'
                 . '<span>'
                 . sprintf(
                     __('%s  has blocked <b>%s</b> spam.', 'cleantalk-spam-protect'),
                     Escape::escHtml($actual_plugin_name),
                     number_format($apbct->spam_count, 0, ',', ' ')
                 )
                 . '</span>'
                 . '<br>'
                 . '<br>'
                 . '</div>';
        }
    }

    //generate and output top info
    //echo Escape::escKsesPreset(apbct_settings__get_top_info());
    echo apbct_settings__get_top_info();

    echo '<div class="apbct_settings_top_info__btn">';
    // Output spam count
    if ( $apbct->key_is_ok && apbct_api_key__is_correct() && ! $apbct->data["wl_mode_enabled"] ) {
        if ( $apbct->network_settings['multisite__work_mode'] != 2 || is_main_site() ) {
            // CP button
            //HANDLE LINK
            echo '<a class="cleantalk_link cleantalk_link-manual" target="__blank" href="https://cleantalk.org/my?user_token=' . Escape::escHtml($apbct->user_token) . '&cp_mode=antispam">'
                 . __('Click here to get Anti-Spam statistics', 'cleantalk-spam-protect')
                 . '</a>';
        }
    }

    if (
        (apbct_api_key__is_correct() || apbct__is_hosting_license()) &&
        ($apbct->network_settings['multisite__work_mode'] != 2 || is_main_site())
    ) {
        // Sync button
        if ( (apbct_api_key__is_correct($apbct->api_key) && $apbct->key_is_ok) || apbct__is_hosting_license() ) {
            echo '<button type="button" class="cleantalk_link cleantalk_link-auto" id="apbct_button__sync" title="' . esc_html__('Synchronizing account status, SpamFireWall database, all kind of journals', 'cleantalk-spam-protect') . '">'
                . '<i class="apbct-icon-upload-cloud"></i>'
                . __('Synchronize with Cloud', 'cleantalk-spam-protect')
                . '<img style="margin-left: 10px;" class="apbct_preloader_button" src="' . Escape::escUrl(APBCT_URL_PATH . '/inc/images/preloader2.gif') . '" />'
                . '<img style="margin-left: 10px;" class="apbct_success --hide" src="' . Escape::escUrl(APBCT_URL_PATH . '/inc/images/yes.png') . '" />'
                . '</button>';
        } else {
            echo '<button type="button" class="--invisible" id="apbct_button__sync"></button><br>';
            echo '<div class="key_changed_wrapper">'
                . '<p class="key_changed_sync">'
                . __('Syncing data with the cloud. Usually, it takes a few seconds.', 'cleantalk-spam-protect')
                . '<img class="apbct_preloader" src="' . Escape::escUrl(APBCT_URL_PATH . '/inc/images/preloader2.gif') . '" />'
                . '</p>'
                . '<p class="key_changed_success --hide">'
                . sprintf(
                    __('Done! Ready to protect %s.', 'cleantalk-spam-protect'),
                    '<span class="--upper-case">' . Escape::escUrl(TT::toString(Server::get('HTTP_HOST'))) . '</span>'
                )
                . '</p>'
                . '</div>';
        }
    }

    // Output spam count
    if ( $apbct->key_is_ok && apbct_api_key__is_correct() ) {
        if ( $apbct->network_settings['multisite__work_mode'] != 2 || is_main_site() ) {
            // Support button
            echo '<a class="cleantalk_link cleantalk_link-auto" target="__blank" href="' . $apbct->data['wl_support_url'] . '" style="text-align: center;">' .
                 __('Support', 'cleantalk-spam-protect') . '</a>';
        }
    }
    echo '</div>';
    settings_fields('cleantalk_settings');
    do_settings_fields('cleantalk', 'cleantalk_section_settings_main');

    $hidden_groups = '<ul>';
    $hidden_groups .= '<li><div class="apbct_hidden_section_nav_mob_btn-close"></div></li>';
    foreach ( $apbct->settings_fields_in_groups as $group_name => $group ) {
        if ( isset($group['section']) && $group['section'] === 'hidden_section' ) {
            $hidden_groups .= '<li><a href="#apbct_setting_group__' . $group_name . '">' .
            (isset($group['title']) ? $group['title'] : __('Untitled Group', 'cleantalk-spam-protect')) .
            '</a></li>';
        }
    }
    $hidden_groups .= '</ul>';
    $hidden_groups .= '<div id="apbct_settings__button_section"><button name="submit" class="cleantalk_link cleantalk_link-manual" value="save_changes" onclick="apbctShowRequiredGroups(event,\'apbct_settings__button_section\')">'
                           . __('Save Changes')
                           . '</button></div>';

    foreach ( $apbct->settings_fields_in_groups as $group_name => $group ) {
        if ( $group_name === 'trusted_and_affiliate' ) {
            continue;
        }
        //html_before
        $out = ! empty($group['html_before']) ? $group['html_before'] : '';
        echo Escape::escKsesPreset($out, 'apbct_settings__display__groups');

        //title
        $out = ! empty($group['title']) ? '<hr><h3 style="text-align: center" id="apbct_setting_group__' . $group_name . '">' . $group['title'] . '</h3><hr>' : '';
        echo Escape::escKsesPreset($out, 'apbct_settings__display__groups');

        //notification
        $out = ! empty($group['notification']) ? '<div style="text-align: center" class="apbct_notification__' . $group_name . '">' . $group['notification'] . '</div>' : '';
        echo Escape::escKsesPreset($out, 'apbct_settings__display__groups');

        do_settings_fields('cleantalk', 'apbct_section__' . $group_name);

        //html_after
        if ( ! empty($group['html_after']) && strpos($group['html_after'], '{HIDDEN_SECTION_NAV}') !== false ) {
            $group['html_after'] = str_replace('{HIDDEN_SECTION_NAV}', $hidden_groups, $group['html_after']);
        }

        $out = ! empty($group['html_after']) ? $group['html_after'] : '';

        echo Escape::escKsesPreset($out, 'apbct_settings__display__groups');
    }

    echo '<div id="apbct_settings__after_advanced_settings">';
    /**
     * Affiliate section start
     */
    $group = $apbct->settings_fields_in_groups['trusted_and_affiliate'];
    //html_before
    $out = ! empty($group['html_before']) ? $group['html_before'] : '';
    echo Escape::escKsesPreset($out, 'apbct_settings__display__groups');

    //title
    $out = ! empty($group['title']) ? '<h3 style="text-align: center;" id="apbct_setting_group__' . (isset($group_name) ? $group_name : 'trusted_and_affiliate') . '">' . $group['title'] . '</h3>' : '';
    $out = '<span id="trusted_and_affiliate__special_span" style="display: none">' . $out;
    echo Escape::escKsesPreset($out, 'apbct_settings__display__groups');

    do_settings_fields('cleantalk', 'apbct_section__trusted_and_affiliate');

    //html_after
    if ( ! empty($group['html_after']) && strpos($group['html_after'], '{HIDDEN_SECTION_NAV}') !== false ) {
        $group['html_after'] = str_replace('{HIDDEN_SECTION_NAV}', $hidden_groups, $group['html_after']);
    }

    $out = ! empty($group['html_after']) ? $group['html_after'] : '';
    $out .= '</span>';
    echo Escape::escKsesPreset($out, 'apbct_settings__display__groups');
    /**
     * Affiliate end
     */
    echo '</div>';

    echo '<div id="apbct_settings__block_main_save_button">';
    echo '<button id="apbct_settings__main_save_button" name="submit" class="cleantalk_link cleantalk_link-manual" value="save_changes" onclick="apbctShowRequiredGroups(event,\'apbct_settings__main_save_button\')">'
         . __('Save Changes')
         . '</button>';
    echo '<br>';
    echo '</div>';

    echo "</form>";

    //the form should be here! button code is placed in apbct_sfw_force_sfw_update_button
    echo '<form id="debug__cron_set" method="POST"></form>';

    if ( ! $apbct->white_label ) {
        // Translate banner for non EN locale
        if (substr(get_locale(), 0, 2) != 'en' ) {
            require_once(CLEANTALK_PLUGIN_DIR . 'templates/translate_banner.php');
            $out = sprintf($ct_translate_banner_template, substr(get_locale(), 0, 2));
            echo Escape::escKsesPreset($out, 'apbct_settings__display__banner_template');
        }
    }

    if ( $apbct->key_is_ok && !empty($apbct->api_key) ) {
        require_once(CLEANTALK_PLUGIN_DIR . 'templates/apbct_settings__footer.php');
    }
}

/**
 * Retrieves the top information for the settings page.
 *
 * This function generates the top information for the settings page, including support brand, support link,
 * plugin homepage text and URL, test email chunk, trademark, feedback request, and get premium request badge.
 * It also handles the case when the plugin is in white label mode.
 *
 * @global object $apbct The APBCT object.
 * @return string The top information for the settings page.
 * @psalm-suppress TypeDoesNotContainNull
 */
function apbct_settings__get_top_info()
{
    global $apbct;
    require_once CLEANTALK_PLUGIN_DIR . 'templates/settings/settings_top_info.php';
    if (!isset($top_info_tmp)) {
        return '';
    }
    // Top info
    $support_brand = $apbct->plugin_name;
    $support_link = $apbct->data['wl_support_url'];
    $support_url_text = 'WordPress.org';
    $plugin_homepage_text = __('Plugin Homepage at', 'cleantalk-spam-protect');
    $plugin_homepage_url = LinkConstructor::buildCleanTalkLink(
        'settings_top_info'
    );
    $plugin_homepage_url_text = $apbct->data['wl_url'];
    $test_email_chunk = __('Use stop_email@example.com to test plugin in any WordPress form.', 'cleantalk-spam-protect');
    $trademark = $apbct->plugin_name . __(' is registered Trademark. All rights reserved.', 'cleantalk-spam-protect');

    $feedback_format = '%s %s? %s%s%s';
    $feedback_request = sprintf(
        $feedback_format,
        __('Do you like', 'cleantalk-spam-protect'),
        $apbct->plugin_name,
        '<a href="https://wordpress.org/support/plugin/cleantalk-spam-protect/reviews/#new-post" target="_blank">',
        __('Post your feedback here', 'cleantalk-spam-protect'),
        '</a>'
    );
    $get_premium_request_badge = apbct_admin__badge__get_premium('top_info');

    if ($apbct->data['wl_mode_enabled'] || $apbct->white_label) {
        $support_brand = $apbct->data['wl_brandname_short'];
        $support_url_text = $apbct->data['wl_support_url'];
        $trademark = '';
        $feedback_request = '';
        $get_premium_request_badge = '';
        $plugin_homepage_url = $apbct->data['wl_url'];
    }

    $support_brand .= __('\'s tech support:', 'cleantalk-spam-protect');

    $top_info_tmp = str_replace('%SUPPORT_BRAND%', $support_brand, $top_info_tmp);
    $top_info_tmp = str_replace('%SUPPORT_LINK%', $support_link, $top_info_tmp);
    $top_info_tmp = str_replace('%SUPPORT_URL_TEXT%', $support_url_text, $top_info_tmp);
    $top_info_tmp = str_replace('%PLUGIN_HOMEPAGE_TEXT%', $plugin_homepage_text, $top_info_tmp);
    $top_info_tmp = str_replace('%PLUGIN_HOMEPAGE_URL%', $plugin_homepage_url, $top_info_tmp);
    $top_info_tmp = str_replace('%PLUGIN_HOMEPAGE_URL_TEXT%', $plugin_homepage_url_text, $top_info_tmp);
    $top_info_tmp = str_replace('%TEST_EMAIL_CHUNK%', $test_email_chunk, $top_info_tmp);
    $top_info_tmp = str_replace('%TRADEMARK%', $trademark, $top_info_tmp);
    $top_info_tmp = str_replace('%FEEDBACK_REQUEST%', $feedback_request, $top_info_tmp);
    $top_info_tmp = str_replace('%GET_PREMIUM_REQUEST%', $get_premium_request_badge, $top_info_tmp);

    return $top_info_tmp;
}

function apbct_settings__display__network()
{
    // If it's network admin dashboard
    if ( is_network_admin() ) {
        $site_url = get_site_option('siteurl');
        $site_url = preg_match('/\/$/', $site_url) ? $site_url : $site_url . '/';
        $link     = $site_url . 'wp-admin/options-general.php?page=cleantalk';
        printf(
            "<h2>" . __(
                "Please, enter the %splugin settings%s in main site dashboard.",
                'cleantalk-spam-protect'
            ) . "</h2>",
            '<a href="' . Escape::escUrl($link) . '">',
            "</a>"
        );

        return;
    }
}

function apbct_settings__error__output($return = false)
{
    global $apbct;

    // If have error message output error block.

    $out = '';

    if ( ! empty($apbct->errors) && ! defined('CLEANTALK_ACCESS_KEY') ) {
        $errors = $apbct->errors;

        $error_texts = array(
            // Misc
            'key_invalid'       => __('Error occurred while Access key validating. ', 'cleantalk-spam-protect'),
            'key_get'           => __(
                'Error occurred while automatically get Access key. ',
                'cleantalk-spam-protect'
            ),
            'sfw_send_logs'     => __(
                'Error occurred while sending SpamFireWall logs. ',
                'cleantalk-spam-protect'
            ),
            'sfw_update'        => __(
                'Error occurred while updating SpamFireWall local base. ',
                'cleantalk-spam-protect'
            ),
            'ua_update'         => __(
                'Error occurred while updating User-Agents local base. ',
                'cleantalk-spam-protect'
            ),
            'account_check'     => __(
                'Error occurred while checking account status. ',
                'cleantalk-spam-protect'
            ),
            'api'               => __('Error occurred while executing API call. ', 'cleantalk-spam-protect'),
            'cron'              => __('Error occurred while executing CleanTalk Cron job. ', 'cleantalk-spam-protect'),
            'sfw_outdated'        => __(
                'Error occurred on last SpamFireWall check. ',
                'cleantalk-spam-protect'
            ),
            'email_encoder'           => __('Email encoder:', 'cleantalk-spam-protect'),

            // Validating settings
            'settings_validate' => 'Validate Settings',
            'exclusions_urls'   => 'URL Exclusions',
            'exclusions_fields' => 'Field Exclusions',

            // Unknown
            'unknown'           => __('Unknown error type: ', 'cleantalk-spam-protect'),
        );

        $errors_out = array();

        $errors = apbct_settings__prepare_errors((array)$errors);

        foreach ( $errors as $type => $error ) {
            if ( ! empty($error) ) {
                if ( count($error) > 2 || ( ! isset($error['error'], $error['error_time']) ) ) {
                    foreach ( $error as $sub_type => $sub_error ) {
                        if ( $sub_type === 'error' || $sub_type === 'error_time' ) {
                            continue;
                        }
                        if ( isset($sub_error['error']) && strpos($sub_error['error'], 'SFW_IS_DISABLED') !== false ) {
                            continue;
                        }

                        $errors_out[$sub_type] = '';
                        if (isset($sub_error['error_time']) && is_numeric($sub_error['error_time'])) {
                            $formatted_date = date('M d Y H:i:s', (int)$sub_error['error_time']);
                            if ($formatted_date !== false) {
                                $errors_out[$sub_type] .= $formatted_date . ': ';
                            } else {
                                $errors_out[$sub_type] .= __('Invalid date', 'cleantalk-spam-protect') . ': ';
                            }
                        }
                        $errors_out[$sub_type] .= (isset($error_texts[$type]) ? $error_texts[$type] : ucfirst($type)) . ': ';
                        $errors_out[$sub_type] .= isset($error_texts[$sub_type])
                            ? $error_texts[$sub_type]
                            : (TT::getArrayValueAsString($error_texts, 'unknown') . $sub_type . ' ');

                        if (isset($sub_error['error'])) {
                            $errors_out[$sub_type] .= ' ' . $sub_error['error'];
                        }
                    }
                }

                if (
                    ! empty($type) &&
                    $apbct->white_label &&
                    ! is_main_site() &&
                    in_array($type, array('sfw_update', 'key_invalid', 'account_check'))
                ) {
                    continue;
                }

                if (! empty($type) &&
                    apbct__is_hosting_license() &&
                    in_array($type, array('sfw_update', 'sfw_send_logs', 'key_invalid', 'account_check'))
                ) {
                    continue;
                }

                if ( isset($error['error']) && strpos($error['error'], 'SFW_IS_DISABLED') !== false ) {
                    continue;
                }

                $errors_out[$type] = '';

                if ( isset($error['error_time']) ) {
                    $formatted_date = date('M d Y H:i:s', (int)$error['error_time']);
                    if ($formatted_date !== false) {
                        $errors_out[$type] .= $formatted_date . ': ';
                    } else {
                        $errors_out[$type] .= __('Invalid date', 'cleantalk-spam-protect') . ': ';
                    }
                }

                $errors_out[$type] .= (isset($error_texts[$type]) ? $error_texts[$type] : (TT::getArrayValueAsString($error_texts, 'unknown'))) . ' ' . (isset($error['error']) ? $error['error'] : '');
            }
        }

        if ( ! empty($errors_out) ) {
            $out .= '<div id="apbctTopWarning" class="notice apbct-plugin-errors" style="position: relative;">'
                    . '<h3 style="display: inline-block;">' . __('Notifications', 'cleantalk-spam-protect') . '</h3>';
            foreach ( $errors_out as $key => $value ) {
                switch ($key) {
                    case 'sfw_outdated':
                        $icon = '<span class="dashicons dashicons-update" style="color: steelblue;"></span>';
                        break;
                    case 'key_invalid':
                        $icon = '<span class="dashicons dashicons-post-status" style="color: orange;"></span>';
                        break;
                    default:
                        $icon = '<span class="dashicons dashicons-hammer" style="color: red;"></span>';
                }
                $out .= '<h4>' . $icon . ' ' . apbct_render_links_to_tag($value) . '</h4>';
            }

            $link_to_support = 'https://wordpress.org/support/plugin/cleantalk-spam-protect';
            if (!empty($apbct->data['wl_support_url'])) {
                $link_to_support = esc_url($apbct->data['wl_support_url']);
            }

            $out .= (! $apbct->white_label || !empty($apbct->data['wl_support_url']))
                ? '<h4 style="text-align: unset;">' . sprintf(
                    __('You can get support any time here: %s.', 'cleantalk-spam-protect'),
                    '<a target="blank" href="' . $link_to_support . '">' . $link_to_support . '</a>'
                ) . '</h4>'
                : '';
            $out .= '</div>';
        }
    }

    if ( $return ) {
        return $out;
    } else {
        echo Escape::escKses(
            $out,
            array(
                'div' => array(
                    'class'  => true,
                    'style' => true,
                ),
                'h3'     => array(
                    'class'  => true,
                ),
                'h4'     => array(
                    'class'  => true,
                ),
                'a'     => array(
                    'target'  => true,
                    'href'  => true,
                ),
                'span' => array(
                    'class'  => true,
                    'style' => true
                )
            )
        );
    }
}

/**
 * Get only last error from each error types from errors array
 *
 * @param array $errors
 *
 * @return array
 */
function apbct_settings__prepare_errors($errors)
{
    $prepared_errors = array();

    if ( is_array($errors) ) {
        foreach ( $errors as $type => $error ) {
            if ( is_array($error) ) {
                foreach ( $error as $key => $error_info ) {
                    if ( is_string($key) ) {
                        $prepared_errors[$type][$key] =  is_array($error_info) ? end($error_info) : $error_info;
                    } else {
                        $prepared_errors[$type] =  $error_info;
                    }
                }
            }
        }
    }

    return $prepared_errors;
}

function apbct_settings__field__state()
{
    global $apbct;

    $path_to_img = plugin_dir_url(__FILE__) . "images/";

    $img         = $path_to_img . "yes.png";
    $img_no      = $path_to_img . "no.png";
    $color       = "black";

    if ( ! $apbct->key_is_ok ) {
        $img    = $path_to_img . "no.png";
        $img_no = $path_to_img . "no.png";
        $color  = "black";
    }

    if ( ! apbct_api_key__is_correct($apbct->api_key) ) {
        $img    = $path_to_img . "yes_gray.png";
        $img_no = $path_to_img . "no_gray.png";
        $color  = "gray";
    }

    if ( $apbct->moderate_ip ) {
        $img    = $path_to_img . "yes.png";
        $img_no = $path_to_img . "no.png";
        $color  = "black";
    }

    if ( $apbct->moderate == 0 ) {
        $img    = $path_to_img . "no.png";
        $img_no = $path_to_img . "no.png";
        $color  = "black";
    }

    print '<div class="apbct_settings-field_wrapper" style="color:' . $color . '">';

    print '<h2>' . __('Protection is active', 'cleantalk-spam-protect') . '</h2>';

    echo '<img class="apbct_status_icon" src="' . ($apbct->settings['forms__registrations_test'] == 1 ? Escape::escUrl($img) : Escape::escUrl($img_no)) . '"/>' . __(
        'Registration forms',
        'cleantalk-spam-protect'
    );
    echo '<img class="apbct_status_icon" src="' . ($apbct->settings['forms__comments_test'] == 1 ? Escape::escUrl($img) : Escape::escUrl($img_no)) . '"/>' . __(
        'Comments forms',
        'cleantalk-spam-protect'
    );
    echo '<img class="apbct_status_icon" src="' . ($apbct->settings['forms__contact_forms_test'] == 1 ? Escape::escUrl($img) : Escape::escUrl($img_no)) . '"/>' . __(
        'Contact forms',
        'cleantalk-spam-protect'
    );
    echo '<img class="apbct_status_icon" src="' . ($apbct->settings['forms__general_contact_forms_test'] == 1 ? Escape::escUrl($img) : Escape::escUrl($img_no)) . '"/>' . __(
        'Custom contact forms',
        'cleantalk-spam-protect'
    );
    if ( ! $apbct->white_label || is_main_site() ) {
        //HANDLE LINK
        echo '<img class="apbct_status_icon" src="' . ($apbct->data['moderate'] == 1 ? Escape::escUrl($img) : Escape::escUrl($img_no)) . '"/>'
             . '<a style="color: black" href="https://blog.cleantalk.org/real-time-email-address-existence-validation/">' . __(
                 'Validate email for existence',
                 'cleantalk-spam-protect'
             ) . '</a>';
    }

    // WooCommerce
    if ( class_exists('WooCommerce') ) {
        echo '<img class="apbct_status_icon" src="' . ($apbct->settings['forms__wc_checkout_test'] == 1 ? Escape::escUrl($img) : Escape::escUrl($img_no)) . '"/>' . __(
            'WooCommerce checkout form',
            'cleantalk-spam-protect'
        );
    }
    if ( apbct__is_hosting_license() ) {
        print "<br /><br />The Anti-Spam service is paid by your hosting provider. License #" . $apbct->data['ip_license'] . ".<br />";
        if ( $apbct->api_key ) {
            print esc_html__('The Access key is not required.', 'cleantalk-spam-protect');
        }
    }

    print "</div>";
}

/**
 * Admin callback function - Displays inputs of 'apikey' plugin parameter
 */
function apbct_settings__field__apikey()
{
    global $apbct;

    if (apbct__is_hosting_license()) {
        return;
    }

    $template = @file_get_contents(CLEANTALK_PLUGIN_DIR . 'templates/settings/settings_key_wrapper.html');

    $define_key_is_provided_by_admin = APBCT_WPMS && ! is_main_site() && ( ! $apbct->allow_custom_key || defined('CLEANTALK_ACCESS_KEY'));
    $define_show_key_field = ! (apbct_api_key__is_correct($apbct->api_key) && isset($apbct->data["key_changed"]) && $apbct->data["key_changed"]);
    $define_show_deobfuscating_href = apbct_api_key__is_correct($apbct->api_key) && $apbct->key_is_ok && (!isset($apbct->data["key_changed"]) || !$apbct->data["key_changed"]);

    $replaces = [
        'wpms_admin_provided' => '',
        'key_label_display' => 'style="display:none"',
        'key_label_text' => __('Access key is', 'cleantalk-spam-protect'),
        'key_input_type' => 'hidden',
        'key_input_value' => '',
        'key_input_key' => '',
        'key_input_placeholder' => __('Enter an Access key', 'cleantalk-spam-protect'),
        'key_input_hint' => __('Get an Access key by clicking the blue button or copy/paste your key from https://cleantalk.org/my.', 'cleantalk-spam-protect'),
        'account_name_ob' => '',
        'deobfuscating_href_display' => 'style="display:none"',
        'deobfuscating_href_text' => __('Show the Access key', 'cleantalk-spam-protect'),
        'get_key_auto_wrapper_display' => 'style="display:none"',
        'get_key_auto_button_display' => 'style="display:none"',
        'get_key_auto_button_text' => __('GET ACCESS KEY', 'cleantalk-spam-protect'),
        'get_key_auto_preloader_src' => Escape::escUrl(APBCT_URL_PATH . '/inc/images/preloader2.gif'),
        'get_key_auto_success_icon_src' => Escape::escUrl(APBCT_URL_PATH . '/inc/images/yes.png'),
        'get_key_manual_chunk' => '',
        'get_key_manual_chunk_display' => empty($apbct->settings['apikey']) ? '' : 'style="display:none"',
        'save_changes_button_text' => __('Save the Access key', 'cleantalk-spam-protect'),
        'trying_to_set_bad_key_notice' => __('Please, insert a correct access key before saving changes! Key should contain at least 8 symbols.', 'cleantalk-spam-protect'),
        'public_offer_display' => 'style="display:none"',
        'public_offer_link' => '',
        'need_accept_agreement_notice' => __('You should accept the License Agreement', 'cleantalk-spam-protect'),
    ];

    //WPMS KEY CASE
    // Using the Access key from Main site, or from CLEANTALK_ACCESS_KEY constant
    if ( $define_key_is_provided_by_admin ) {
        $replaces['wpms_admin_provided'] = '<h3>' . __('Access key is provided by network administrator', 'cleantalk-spam-protect') . '</h3>';
        foreach ($replaces as $key => $value) {
            $template = str_replace('%' . strtoupper($key) . '%', TT::toString($value), $template);
        }
        echo $template;
        return;
    }

    //LABEL AND FIELD
    $replaces['key_label_display'] = $define_show_key_field ? '' : 'style="display:none"';
    $replaces['key_input_type'] = $define_show_key_field ? 'text' : 'hidden';
    $replaces['key_input_value'] = $apbct->key_is_ok ? str_repeat('*', strlen($apbct->api_key)) : Escape::escHtml($apbct->api_key);
    $replaces['key_input_key'] = $apbct->api_key;
    $replaces['deobfuscating_href_display'] = $define_show_deobfuscating_href ? '' : 'style="display:none"';

    //ACCOUNT NAME
    $replaces['account_name_ob'] = sprintf(
        __('Account at cleantalk.org is %s.', 'cleantalk-spam-protect'),
        '<b>' . Escape::escHtml($apbct->data['account_name_ob']) . '</b>'
    );
    //GET KEY AUTO
    $replaces['get_key_auto_wrapper_display'] = $define_show_key_field && empty($apbct->api_key)
        ? ''
        : 'style="display:none"';
    $replaces['get_key_auto_button_display'] = !$apbct->ip_license ? '' : 'style="display:none"';

    //GET KEY MANUAL CHUNK
    $register_link = LinkConstructor::buildCleanTalkLink('get_access_key_link', 'wordpress-anti-spam-plugin');
    $link_template = __(
        'The admin email %s %s will be used to obtain a key and as the email for signing up at CleanTalk.org.',
        'cleantalk-spam-protect'
    );
    $link_template .= '<br>';
    $link_template .= __('As a backup, use the %sCleanTalk Dashboard%s to copy and paste your key.', 'cleantalk-spam-protect');
    $href = '<a class="apbct_color--gray" target="__blank" id="apbct-key-manually-link" href="'
            . sprintf(
                $register_link . '&platform=wordpress&email=%s&website=%s',
                urlencode(ct_get_admin_email()),
                urlencode(get_bloginfo('url'))
            )
            . '">';
    $replaces['get_key_manual_chunk'] = sprintf(
        $link_template,
        '<span id="apbct-account-email">' . ct_get_admin_email() . '</span>',
        apbct_settings__btn_change_account_email_html(),
        $href,
        '</a>'
    );

    //PUBLIC OFFER
    $replaces['public_offer_display'] = !$apbct->ip_license ? '' : 'style="display:none"';
    $replaces['public_offer_link'] = sprintf(
        '<label for="apbct_license_agreed">' .
        __('I accept %sLicense Agreement%s.', 'cleantalk-spam-protect'),
        '<a id="apbct_license_agreed_href" class = "apbct_color--gray" href="https://cleantalk.org/publicoffer" target="_blank">',
        '</a></label>'
    );

    //DO REPLACE
    foreach ($replaces as $key => $value) {
        $template = str_replace('%' . strtoupper($key) . '%', TT::toString($value), $template);
    }

    echo $template;
}

function apbct_field_service_utilization()
{
    global $apbct;

    echo '<div class="apbct_wrapper_field">';

    if ( $apbct->services_count && $apbct->services_max && $apbct->services_utilization ) {
        echo sprintf(
            __('Hoster account utilization: %s%% ( %s of %s websites ).', 'cleantalk-spam-protect'),
            $apbct->services_utilization * 100,
            Escape::escHtml($apbct->services_count),
            Escape::escHtml($apbct->services_max)
        );

        // Link to the dashboard, so user could extend your subscription for more sites
        if ( $apbct->services_utilization * 100 >= 90 ) {
            echo '&nbsp';
            echo sprintf(
                __('You could extend your subscription %shere%s.', 'cleantalk-spam-protect'),
                '<a href="' . Escape::escUrl($apbct->dashboard_link) . '" target="_blank">',
                '</a>'
            );
        }
    } else {
        _e(
            'Enter the Hoster Access key and synchronize with cloud to find out your hoster account utilization.',
            'cleantalk-spam-protect'
        );
    }

    echo '</div>';
}

function apbct_settings__field__action_buttons()
{
    global $apbct;

    add_filter('apbct_settings_action_buttons', function ($buttons_array) {
        $buttons_array[] =
            '<a href="edit-comments.php?page=ct_check_spam" class="ct_support_link">'
            . __('Check comments for spam', 'cleantalk-spam-protect')
            . '</a>';
        $buttons_array[] =
            '<a href="users.php?page=ct_check_users" class="ct_support_link">'
            . __('Check users for spam', 'cleantalk-spam-protect')
            . '</a>';
        return $buttons_array;
    });

    if ( apbct_is_plugin_active('woocommerce/woocommerce.php') ) {
        add_filter('apbct_settings_action_buttons', function ($buttons_array) {
            $buttons_array[] =
                '<a href="admin.php?page=apbct_wc_spam_orders" class="ct_support_link" title="Bulk spam orders removal tool.">'
                . __('WooCommerce spam orders', 'cleantalk-spam-protect')
                . '</a>';
            return $buttons_array;
        });
    }

    add_filter('apbct_settings_action_buttons', function ($buttons_array) {
        $buttons_array[] =
            '<a href="#" class="ct_support_link" onclick="apbctShowHideElem(\'apbct_statistics\')">'
            . __('Statistics & Reports', 'cleantalk-spam-protect')
            . '</a>';
        return $buttons_array;
    });

    $links = apply_filters('apbct_settings_action_buttons', array());

    echo '<div class="apbct_settings-field_wrapper apbct_settings_top_info__sub_btn">';

    if ( apbct_api_key__is_correct($apbct->api_key) && $apbct->key_is_ok ) {
        foreach ( $links as $link ) {
            echo Escape::escKsesPreset($link, 'apbct_settings__display__groups');
        }
    } elseif ( apbct__is_hosting_license() ) {
        echo '<a href="#" class="ct_support_link" onclick="apbctShowHideElem(\'apbct_statistics\')">'
             . __('Statistics & Reports', 'cleantalk-spam-protect')
             . '</a>';
    }

    echo '</div>';
}

function apbct_settings__field__statistics()
{
    global $apbct;

    echo '<div id="apbct_statistics" class="apbct_settings-field_wrapper" style="display: none;">';

    // Last request
    // Get the server information
    $server = isset($apbct->stats['last_request']['server']) && $apbct->stats['last_request']['server']
        ? Escape::escUrl($apbct->stats['last_request']['server'])
        : __('unknown', 'cleantalk-spam-protect');

    // Get the time information
    $time = isset($apbct->stats['last_request']['time']) && $apbct->stats['last_request']['time']
        ? date('M d Y H:i:s', $apbct->stats['last_request']['time'])
        : __('unknown', 'cleantalk-spam-protect');

    // Output the result
    printf(
        __('Last spam check request to %s server was at %s.', 'cleantalk-spam-protect'),
        $server ? $server : __('unknown', 'cleantalk-spam-protect'),
        $time ? $time : __('unknown', 'cleantalk-spam-protect')
    );
    echo '<br>';

    // Average time request
    // Get the earliest date from the requests stats
    $earliest_date = null;
    if (!empty($apbct->stats['requests'])) {
        $request_keys = array_keys($apbct->stats['requests']);
        if (!empty($request_keys)) {
            $earliest_date = min($request_keys);
        }
    }

    // Get the average time for the earliest date, if it exists
    $average_time = null;
    if ($earliest_date !== null && isset($apbct->stats['requests'][$earliest_date]['average_time'])) {
        $average_time = $apbct->stats['requests'][$earliest_date]['average_time'];
    }

    // Format the average time
    $formatted_time = $average_time !== null
        ? round($average_time, 3)
        : __('unknown', 'cleantalk-spam-protect');

    // Output the result
    printf(
        __('Average request time for past 7 days: %s seconds.', 'cleantalk-spam-protect'),
        $formatted_time
    );
    echo '<br>';

    // SFW last die
    $last_sfw_block_ip = isset($apbct->stats['last_sfw_block']['ip']) && $apbct->stats['last_sfw_block']['ip']
        ? $apbct->stats['last_sfw_block']['ip']
        : __('unknown', 'cleantalk-spam-protect');

    $last_sfw_block_time = isset($apbct->stats['last_sfw_block']['time']) && $apbct->stats['last_sfw_block']['time']
        ? date('M d Y H:i:s', $apbct->stats['last_sfw_block']['time'])
        : __('unknown', 'cleantalk-spam-protect');

    printf(
        __('Last time SpamFireWall was triggered for %s IP at %s', 'cleantalk-spam-protect'),
        $last_sfw_block_ip,
        $last_sfw_block_time ? $last_sfw_block_time : __('unknown', 'cleantalk-spam-protect')
    );
    echo '<br>';

    // SFW last update
    $last_update_time = isset($apbct->stats['sfw']['last_update_time']) && $apbct->stats['sfw']['last_update_time']
        ? date('M d Y H:i:s', $apbct->stats['sfw']['last_update_time'])
        : __('unknown', 'cleantalk-spam-protect');

    printf(
        __('SpamFireWall was updated %s. Now contains %s entries.', 'cleantalk-spam-protect'),
        $last_update_time ? $last_update_time : __('unknown', 'cleantalk-spam-protect'),
        isset($apbct->stats['sfw']['entries']) ? (int)$apbct->stats['sfw']['entries'] : __('unknown', 'cleantalk-spam-protect')
    );
    echo $apbct->fw_stats['firewall_updating_id']
        ? ' ' . __('Under updating now:', 'cleantalk-spam-protect') . ' ' . (int)$apbct->fw_stats['firewall_update_percent'] . '%'
        : '';
    echo '<br>';

    // SFW last sent logs
    $last_send_time = $apbct->stats['sfw']['last_send_time'] ? date('M d Y H:i:s', $apbct->stats['sfw']['last_send_time']) : __(
        'unknown',
        'cleantalk-spam-protect'
    );

    printf(
        __('SpamFireWall sent %s events at %s.', 'cleantalk-spam-protect'),
        $apbct->stats['sfw']['last_send_amount'] ? (int)$apbct->stats['sfw']['last_send_amount'] : __(
            'unknown',
            'cleantalk-spam-protect'
        ),
        $last_send_time ? $last_send_time : __('unknown', 'cleantalk-spam-protect')
    );
    echo '<br>';
    echo 'Plugin version: ' . APBCT_VERSION;
    echo '<br>';

    // Connection reports
    $connection_reports = $apbct->getConnectionReports();
    if ( ! $connection_reports->hasNegativeReports() ) {
        _e('There are no failed connections to server.', 'cleantalk-spam-protect');
    } else {
        $reports_html = $connection_reports->prepareNegativeReportsHtmlForSettingsPage();
        //escaping and echoing html
        echo Escape::escKses(
            $reports_html,
            array(
                'tr' => array(
                    'style' => true
                ),
                'td' => array(),
                'th' => array(
                    'colspan' => true
                ),
                'b' => array(),
                'br' => array(),
                'div' => array(
                    'id' => true
                ),
                'table' => array(
                    'id' => true
                ),
            )
        );
        //if no unsent reports show caption, in another case show the button
        if ( ! $connection_reports->hasUnsentReports() ) {
            _e('All the reports already have been sent.', 'cleantalk-spam-protect');
        } else {
            echo '<button'
                . ' name="submit"'
                . ' class="cleantalk_link cleantalk_link-manual"'
                . ' value="ct_send_connection_report"'
                . (! $apbct->settings['misc__send_connection_reports'] ? ' disabled="disabled"' : '')
                . '>'
                . __('Send new report', 'cleantalk-spam-protect')
                . '</button>';
            if ( ! $apbct->settings['misc__send_connection_reports'] ) {
                echo '<br><br>';
                _e(
                    'Please, enable "Send connection reports" setting to be able to send reports',
                    'cleantalk-spam-protect'
                );
            }
        }
    }

    echo '<br/>';
    echo '</div>';
}

function apbct_discussion_settings__field__moderation()
{
    $output  = '<label for="cleantalk_allowed_moderation">';
    $output .= '<input 
                type="checkbox" 
                name="cleantalk_allowed_moderation" 
                id="cleantalk_allowed_moderation" 
                value="1" ' .
                checked('1', get_option('cleantalk_allowed_moderation', 1), false) .
                '/> ';
    $output .= esc_html__('Skip manual approving for the very first comment if a comment has been allowed by CleanTalk Anti-Spam protection', 'cleantalk-spam-protect');
    $output .= '</label>';
    echo $output;
}

function apbct_get_all_child_domains($except_main_site = false)
{
    global $wpdb;
    $blogs    = array();
    $wp_blogs = $wpdb->get_results('SELECT blog_id, site_id FROM ' . $wpdb->blogs, OBJECT_K);

    if ( $except_main_site ) {
        foreach ( $wp_blogs as $blog ) {
            if ( $blog->blog_id != $blog->site_id ) {
                $blog_details = get_blog_details(array('blog_id' => $blog->blog_id));
                if ($blog_details && is_object($blog_details)) {
                    $blogs[] = array(
                        'val'   => $blog_details->blog_id,
                        'label' => '#' . $blog_details->blog_id . ' ' . (isset($blog_details->blogname) ? $blog_details->blogname : '')
                    );
                }
            }
        }
    }

    return $blogs;
}

/**
 * Get all current WordPress roles
 *
 * @return array
 */
function apbct_get_all_roles()
{
    $wp_roles = new WP_Roles();

    return $wp_roles->get_names();
}

function apbct_settings__field__draw($params = array())
{
    $field = new SettingsField($params);
    $field->draw();
}

/**
 * Admin callback function - Plugin parameters validator
 *
 * @param array $settings Array with passed settings
 *
 * @return array Array with processed settings
 * @global \Cleantalk\ApbctWP\State $apbct
 */
function apbct_settings__validate($settings)
{
    global $apbct;

    // If user is not allowed to manage settings. Get settings from the storage
    if (
        ! $apbct->network_settings['multisite__allow_custom_settings'] &&
        //  Skip if templates applying for subsites is not set
        empty($settings['multisite__use_settings_template_apply_for_current_list_sites']) &&
        ! is_main_site() &&
        current_filter() === 'sanitize_option_cleantalk_settings' // Do in only if settings were saved
    ) {
        foreach ( $apbct->settings as $key => $setting ) {
            // Do not reset apikey to default is allow_custom_key is active
            if ( $key === 'apikey' && $apbct->allow_custom_key ) {
                continue;
            }
            $settings[$key] = $setting;
        }
    }

    // Set missing settings.
    foreach ( $apbct->default_settings as $setting => $value ) {
        if ( ! isset($settings[$setting]) ) {
            $settings[$setting] = null;
            settype($settings[$setting], gettype($value));
            if ($setting === 'data__email_decoder_obfuscation_mode') {
                $settings[$setting] = $value;
            }
        }
    }
    unset($setting, $value);

    // Set missing network settings.
    $stored_network_options = get_site_option($apbct->option_prefix . '_network_settings', array());
    foreach ( $apbct->default_network_settings as $setting => $value ) {
        if ( ! isset($settings[$setting]) ) {
            if ( ! array_key_exists($setting, $stored_network_options) ) {
                $settings[$setting] = $value;
            }
            settype($settings[$setting], gettype($value));
        }
    }
    unset($setting, $value);

    // Actions with toggle SFW settings
    // SFW was enabled
    if ( ! $apbct->settings['sfw__enabled'] && isset($settings['sfw__enabled']) && $settings['sfw__enabled'] ) {
        $cron = new Cron();
        $cron->updateTask('sfw_update', 'apbct_sfw_update__init', 86400, time() + 180);
        // SFW was disabled
    } elseif ( $apbct->settings['sfw__enabled'] && ( isset($settings['sfw__enabled']) && ! $settings['sfw__enabled'] ) ) {
        apbct_sfw__clear();
    }

    //Sanitizing sfw__anti_flood__view_limit setting
    if (isset($settings['sfw__anti_flood__view_limit'])) {
        $settings['sfw__anti_flood__view_limit'] = floor(intval($settings['sfw__anti_flood__view_limit']));
    } else {
        // Set a default value or handle the case when the key doesn't exist
        $settings['sfw__anti_flood__view_limit'] = 20; // or any other default value
    }

    // Ensure the value is at least 5
    $settings['sfw__anti_flood__view_limit'] = max(5, $settings['sfw__anti_flood__view_limit']);

    // Validating Access key
    if (isset($settings['apikey'])) {
        $settings['apikey'] = strpos($settings['apikey'], '*') === false ? $settings['apikey'] : $apbct->settings['apikey'];
    } else {
        $settings['apikey'] = $apbct->settings['apikey'];
    }

    $apbct->data['key_changed'] = $settings['apikey'] !== $apbct->settings['apikey'];

    $settings['apikey'] = ! empty($settings['apikey']) ? trim($settings['apikey']) : '';
    $settings['apikey'] = defined('CLEANTALK_ACCESS_KEY') ? CLEANTALK_ACCESS_KEY : $settings['apikey'];
    $settings['apikey'] = ! is_main_site() && $apbct->white_label && $apbct->settings['apikey'] ? $apbct->settings['apikey'] : $settings['apikey'];
    $settings['apikey'] = is_main_site() || $apbct->allow_custom_key || $apbct->white_label ? $settings['apikey'] : $apbct->network_settings['apikey'];
    $settings['apikey'] = is_main_site() || ! isset($settings['multisite__white_label']) || ! $settings['multisite__white_label']
        ? $settings['apikey']
        : $apbct->settings['apikey'];

    // Show notice if the Access key is empty
    if ( ! apbct_api_key__is_correct() && ! apbct__is_hosting_license() ) {
        $apbct->data['key_is_ok']   = false;
        $apbct->data['notice_show'] = 1;
    } else {
        // Key is good by default
        $apbct->data['key_is_ok'] = true;
    }

    // Sanitize setting values
    foreach ( $settings as &$setting ) {
        if ( is_string($setting) ) {
            $setting = preg_replace('/[<"\'>]/', '', trim($setting));
        } // Make HTML code inactive
    }

    // Validate Exclusions
    // URLs
    $is_exclusions_url_like = apbct_settings__sanitize__exclusions(
        isset($settings['exclusions__urls']) ? $settings['exclusions__urls'] : '',
        false,
        true
    );

    if ( empty($apbct->settings['exclusions__urls']) || $is_exclusions_url_like) {
        // Legacy: If the field is empty, the new way checking by URL will be activated.
        $apbct->data['check_exclusion_as_url'] = true;
        $apbct->saveData();
    }

    $result = apbct_settings__sanitize__exclusions(
        isset($settings['exclusions__urls']) ? $settings['exclusions__urls'] : '',
        isset($settings['exclusions__urls__use_regexp']) ? $settings['exclusions__urls__use_regexp'] : false,
        $apbct->data['check_exclusion_as_url']
    );
    $result === false
        ? $apbct->errorAdd(
            'exclusions_urls',
            'is not valid: "' . (isset($settings['exclusions__urls']) ? $settings['exclusions__urls'] : '') . '"',
            'settings_validate'
        )
        : $apbct->errorDelete('exclusions_urls', true, 'settings_validate');
    $settings['exclusions__urls'] = $result ? $result : '';

    // Fields
    $result = apbct_settings__sanitize__exclusions(
        isset($settings['exclusions__fields']) ? $settings['exclusions__fields'] : '',
        isset($settings['exclusions__fields__use_regexp']) ? $settings['exclusions__fields__use_regexp'] : false
    );
    $result === false
        ? $apbct->errorAdd(
            'exclusions_fields',
            'is not valid: "' . (isset($settings['exclusions__fields']) ? $settings['exclusions__fields'] : '') . '"',
            'settings_validate'
        )
        : $apbct->errorDelete('exclusions_fields', true, 'settings_validate');
    $settings['exclusions__fields'] = $result ? $result : '';

    // Form signs exclusions
    $result = apbct_settings__sanitize__exclusions(
        isset($settings['exclusions__form_signs']) ? $settings['exclusions__form_signs'] : '',
        true
    );
    $result === false
        ? $apbct->errorAdd(
            'exclusions_fields',
            'is not valid: "' . (isset($settings['exclusions__form_signs']) ? $settings['exclusions__form_signs'] : '') . '"',
            'settings_validate'
        )
        : $apbct->errorDelete('exclusions_fields', true, 'settings_validate');
    $settings['exclusions__form_signs'] = $result ? $result : '';

    //Bot detector form
    $result = apbct_settings__sanitize__exclusions(
        isset($settings['exclusions__bot_detector__form_attributes']) ? $settings['exclusions__bot_detector__form_attributes'] : '',
        true
    );
    $result === false
        ? $apbct->errorAdd(
            'exclusions_fields',
            'is not valid: "' . (isset($settings['exclusions__bot_detector__form_attributes']) ? $settings['exclusions__bot_detector__form_attributes'] : '') . '"',
            'settings_validate'
        )
        : $apbct->errorDelete('exclusions_fields', true, 'settings_validate');
    $settings['exclusions__bot_detector__form_attributes'] = $result ? $result : '';

    //Bot detector parent
    $result = apbct_settings__sanitize__exclusions(
        isset($settings['exclusions__bot_detector__form_parent_attributes']) ? $settings['exclusions__bot_detector__form_parent_attributes'] : '',
        true
    );
    $result === false
        ? $apbct->errorAdd(
            'exclusions_fields',
            'is not valid: "' . (isset($settings['exclusions__bot_detector__form_parent_attributes']) ? $settings['exclusions__bot_detector__form_parent_attributes'] : '') . '"',
            'settings_validate'
        )
        : $apbct->errorDelete('exclusions_fields', true, 'settings_validate');
    $settings['exclusions__bot_detector__form_parent_attributes'] = $result ? $result : '';

    //Bot detector child
    $result = apbct_settings__sanitize__exclusions(
        isset($settings['exclusions__bot_detector__form_children_attributes']) ? $settings['exclusions__bot_detector__form_children_attributes'] : '',
        true
    );
    $result === false
        ? $apbct->errorAdd(
            'exclusions_fields',
            'is not valid: "' . (isset($settings['exclusions__bot_detector__form_children_attributes']) ? $settings['exclusions__bot_detector__form_children_attributes'] : '') . '"',
            'settings_validate'
        )
        : $apbct->errorDelete('exclusions_fields', true, 'settings_validate');
    $settings['exclusions__bot_detector__form_children_attributes'] = $result ? $result : '';


    $network_settings = array();
    // WPMS Logic.
    if ( APBCT_WPMS && is_main_site() ) {
        $network_settings = array(
            'multisite__allow_custom_settings'                              => isset($settings['multisite__allow_custom_settings']) ? $settings['multisite__allow_custom_settings'] : 0,
            'multisite__white_label'                                        => isset($settings['multisite__white_label']) ? $settings['multisite__white_label'] : 0,
            'multisite__white_label__plugin_name'                           => isset($settings['multisite__white_label__plugin_name']) ? $settings['multisite__white_label__plugin_name'] : '',
            'multisite__use_settings_template'                              => isset($settings['multisite__use_settings_template']) ? $settings['multisite__use_settings_template'] : 0,
            'multisite__use_settings_template_apply_for_new'                => isset($settings['multisite__use_settings_template_apply_for_new']) ? $settings['multisite__use_settings_template_apply_for_new'] : 0,
            'multisite__use_settings_template_apply_for_current'            => isset($settings['multisite__use_settings_template_apply_for_current']) ? $settings['multisite__use_settings_template_apply_for_current'] : 0,
            'multisite__use_settings_template_apply_for_current_list_sites' => isset($settings['multisite__use_settings_template_apply_for_current_list_sites']) ? $settings['multisite__use_settings_template_apply_for_current_list_sites'] : 0,
        );
        unset($settings['multisite__white_label'], $settings['multisite__white_label__plugin_name']);

        if ( isset($settings['multisite__hoster_api_key']) ) {
            $network_settings['multisite__hoster_api_key'] = $settings['multisite__hoster_api_key'];
        }

        if ( isset($settings['multisite__work_mode']) ) {
            $network_settings['multisite__work_mode'] = $settings['multisite__work_mode'];
        }
    }

    // Send connection reports
    if ( Post::get('submit') === 'ct_send_connection_report' ) {
        $apbct->getConnectionReports()->sendUnsentReports();
        return $settings;
    }

    // Ajax type
    $available_ajax_type = apbct_settings__get_ajax_type();
    $apbct->data['ajax_type'] = $available_ajax_type ?: 'admin_ajax';

    if (
        $apbct->data['cookies_type'] === 'alternative' ||
        (isset($settings['data__use_ajax']) && $settings['data__use_ajax'] == 1)
    ) {
        if ( $available_ajax_type === false ) {
            // There is no available alt cookies types. Cookies will be disabled.
            // There is no available ajax types. AJAX js will be disabled.
            $settings['data__set_cookies'] = 0;
            $settings['data__use_ajax'] = 0;
        }
    }

    // Banner notice_email_decoder_changed
    if (
        (
            isset($apbct->settings['data__email_decoder'], $settings['data__email_decoder']) &&
            ((int)$apbct->settings['data__email_decoder'] !== (int)$settings['data__email_decoder'])
        ) ||
        (
            isset($apbct->settings['data__email_decoder_encode_phone_numbers'], $settings['data__email_decoder_encode_phone_numbers']) &&
            ((int)$apbct->settings['data__email_decoder_encode_phone_numbers'] !== (int)$settings['data__email_decoder_encode_phone_numbers'])
        ) ||
        (
            isset($apbct->settings['data__email_decoder_encode_email_addresses'], $settings['data__email_decoder_encode_email_addresses']) &&
            ((int)$apbct->settings['data__email_decoder_encode_email_addresses'] !== (int)$settings['data__email_decoder_encode_email_addresses'])
        )
    ) {
        $apbct->data['notice_email_decoder_changed'] = 1;
    }

    $apbct->save('data');

    // WPMS Logic.
    if ( APBCT_WPMS ) {
        if ( is_main_site() ) {
            // Network settings
            $network_settings['apikey'] = isset($settings['apikey']) ? $settings['apikey'] : '';
            $apbct->network_settings    = $network_settings;
            $apbct->saveNetworkSettings();

            // Network data
            $apbct->network_data = array(
                'key_is_ok'   => $apbct->data['key_is_ok'],
                'moderate'    => $apbct->data['moderate'],
                'valid'       => isset($apbct->data['valid']) ? $apbct->data['valid'] : 0,
                'user_token'  => $apbct->data['user_token'],
                'service_id'  => $apbct->data['service_id'],
                'user_id'  => $apbct->data['user_id'],
            );
            $apbct->saveNetworkData();
            if ( isset($settings['multisite__use_settings_template_apply_for_current_list_sites'])
                && !empty($settings['multisite__use_settings_template_apply_for_current_list_sites']) ) {
                //remove filter to avoid multiple validation
                remove_filter('sanitize_option_cleantalk_settings', 'apbct_settings__validate');
                apbct_update_blogs_options($settings);
            }
        } else {
            // compare non-main site blog key with the validating key
            $blog_settings = get_option('cleantalk_settings');
            $key_from_blog_settings = !empty($blog_settings['apikey']) ? $blog_settings['apikey'] : '';
            if ( isset($settings['apikey']) && (trim($settings['apikey']) !== trim($key_from_blog_settings)) ) {
                $blog_key_changed = true;
            }
            $apbct->data['key_changed'] = empty($blog_key_changed) ? false : $blog_key_changed;
            $apbct->save('data');
        }
        if ( ! $apbct->white_label && ! is_main_site() && ! $apbct->allow_custom_key ) {
            $settings['apikey'] = '';
        }
    }

    // Alt sessions table clearing
    if ( $apbct->data['cookies_type'] !== 'alternative' ) {
        \Cleantalk\ApbctWP\Variables\AltSessions::wipe();
    }

    //email encoder obfuscation custom text validation
    if (
            isset($settings['data__email_decoder_obfuscation_mode'])
            && $settings['data__email_decoder_obfuscation_mode'] === 'replace'
    ) {
        if (empty($settings['data__email_decoder_obfuscation_custom_text'])) {
            $apbct->errorDelete('email_encoder', true, 'settings_validate');
            $settings['data__email_decoder_obfuscation_custom_text'] = EmailEncoder::getDefaultReplacingText();
            $apbct->errorAdd(
                'email_encoder',
                'custom text can not be empty, default value applied.',
                'settings_validate'
            );
        } else {
            $settings['data__email_decoder_obfuscation_custom_text'] = sanitize_textarea_field($settings['data__email_decoder_obfuscation_custom_text']);
            $apbct->errorDelete('email_encoder', true, 'settings_validate');
        }
    } else {
        $apbct->errorDelete('email_encoder', true, 'settings_validate');
        $settings['data__email_decoder_obfuscation_custom_text'] = EmailEncoder::getDefaultReplacingText();
    }

    /**
     * Triggered before returning the settings
     */
    do_action('apbct_before_returning_settings', $settings);

    return $settings;
}

function apbct_settings__sync($direct_call = false)
{
    if ( ! $direct_call ) {
        AJAXService::checkAdminNonce();
    }

    global $apbct;

    //Clearing all errors
    $apbct->errorDeleteAll(true);

    // Feedback with app_agent
    ct_send_feedback('0:' . APBCT_AGENT); // 0 - request_id, agent version.

    // Key is good by default
    $apbct->data['key_is_ok'] = true;

    // Checking account status
    $result = ct_account_status_check($apbct->settings['apikey']);

    // Is key valid?
    if ( $result ) {
        // Deleting errors about invalid key
        $apbct->errorDelete('key_invalid key_get', 'save');

        // SFW actions
        if ( $apbct->settings['sfw__enabled'] == 1 ) {
            $result = apbct_sfw_update__init(5);
            if ( ! empty($result['error']) ) {
                $apbct->errorAdd('sfw_update', $result['error']);
            }

            $result = ct_sfw_send_logs($apbct->settings['apikey']);
            if ( ! empty($result['error']) ) {
                $apbct->errorAdd('sfw_send_logs', $result['error']);
            }
        }

        // Updating brief data for dashboard widget
        cleantalk_get_brief_data($apbct->settings['apikey']);
        // Key is not valid
    } else {
        $apbct->data['key_is_ok'] = false;
        $apbct->errorAdd(
            'key_invalid',
            __('Testing is failed. Please check the Access key.', 'cleantalk-spam-protect')
        );
    }

    // WPMS Logic.
    if ( APBCT_WPMS ) {
        if ( is_main_site() ) {
            // Network settings
            $apbct->network_settings['apikey'] = $apbct->settings['apikey'];
            $apbct->saveNetworkSettings();

            // Network data
            $apbct->network_data = array(
                'key_is_ok'   => $apbct->data['key_is_ok'],
                'moderate'    => $apbct->data['moderate'],
                'valid'       => $apbct->data['valid'],
                'user_token'  => $apbct->data['user_token'],
                'service_id'  => $apbct->data['service_id'],
                'user_id'  => $apbct->data['user_id'],
            );

            if ( $apbct->network_settings['multisite__work_mode'] == 1 ) {
                $apbct->data['services_count ']      = isset($result['services_count']) ? $result['services_count'] : '';
                $apbct->data['services_max']         = isset($result['services_max']) ? $result['services_max'] : '';
                $apbct->data['services_utilization'] = isset($result['services_utilization']) ? $result['services_utilization'] : '';
            }

            $apbct->saveNetworkData();
            if ( isset($apbct->settings['multisite__use_settings_template_apply_for_current_list_sites']) && ! empty($apbct->settings['multisite__use_settings_template_apply_for_current_list_sites']) ) {
                apbct_update_blogs_options($apbct->settings);
            }
        }
        if ( ! $apbct->white_label && ! is_main_site() && ! $apbct->allow_custom_key ) {
            $apbct->settings['apikey'] = '';
        }
    }

    if ( $apbct->data['key_is_ok'] == false && $apbct->data['moderate_ip'] == 0 ) {
        // Notices
        $apbct->data['notice_show']        = 1;
        $apbct->data['notice_renew']       = 0;
        $apbct->data['notice_trial']       = 0;
        $apbct->data['notice_review']      = 0;

        // Other
        $apbct->data['service_id']      = 0;
        $apbct->data['user_id']      = 0;
        $apbct->data['valid']           = 0;
        $apbct->data['moderate']        = 0;
        $apbct->data['ip_license']      = 0;
        $apbct->data['moderate_ip']     = 0;
        $apbct->data['spam_count']      = 0;
        $apbct->data['user_token']      = '';
        $apbct->data['license_trial']   = 0;
        $apbct->data['account_name_ob'] = '';
    }

    $out = array(
        'success' => true,
        'reload'  => isset($apbct->data['key_changed']) ? $apbct->data['key_changed'] : 0,
    );

    $apbct->data['key_changed'] = false;

    $apbct->saveData();

    if ( $direct_call ) {
        return $out;
    }

    // Try to adjust to environment
    $adjust = new AdjustToEnvironmentHandler();
    $adjust->handle();

    die(json_encode($out));
}

/**
 * @param bool $direct_call
 *
 * @return array|bool|false[]|mixed|string|string[]|void
 * @psalm-suppress RedundantCondition
 */
function apbct_settings__get_key_auto($direct_call = false)
{
    if ( ! $direct_call ) {
        AJAXService::checkAdminNonce();
    }

    global $apbct;

    $website        = parse_url(get_option('home'), PHP_URL_HOST) . parse_url(get_option('home'), PHP_URL_PATH);
    $platform       = 'wordpress';
    $user_ip        = Helper::ipGet('real', false);
    $timezone       = filter_input(INPUT_POST, 'ct_admin_timezone');
    $language       = Server::get('HTTP_ACCEPT_LANGUAGE');
    $wpms           = APBCT_WPMS && defined('SUBDOMAIN_INSTALL') && ! SUBDOMAIN_INSTALL ? true : false;
    $white_label    = $apbct->network_settings['multisite__white_label'] ? true : false;
    $hoster_api_key = $apbct->network_settings['multisite__hoster_api_key'];
    $admin_email    = ct_get_admin_email();

    /**
     * Filters the email to get Access key
     *
     * @param string email to get Access key
     */
    $filtered_admin_email = apply_filters('apbct_get_api_key_email', $admin_email);

    $language = is_string($language) ? $language : null;

    $result = \Cleantalk\ApbctWP\API::methodGetApiKey(
        'antispam',
        $filtered_admin_email,
        $website,
        $platform,
        $timezone,
        $language,
        $user_ip,
        $wpms,
        $white_label,
        $hoster_api_key,
        $filtered_admin_email !== $admin_email
    );

    if ( ! empty($result['error']) ) {
        $apbct->errorAdd(
            'key_get',
            $result['error']
            . ($apbct->white_label
                ? ' <button name="submit" type="button" id="apbct_button__get_key_auto" class="cleantalk_link cleantalk_link-manual" value="get_key_auto">'
                : ''
            )
        );
        $apbct->saveErrors();
        $out = array(
            'success' => true,
            'reload'  => false,
            'error' => isset($result['error_message']) ? esc_html($result['error_message']) : esc_html($result['error'])
        );
    } elseif (isset($result['error_no']) && $result['error_no'] == '403') {
        $out = array(
            'success' => true,
            'reload'  => false,
            'error' => isset($result['error_message']) ? esc_html($result['error_message']) : esc_html('Our service is not available in your region.'),
        );
    } elseif ( ! isset($result['auth_key']) ) {
        //HANDLE LINK
        $out = array(
            'success' => true,
            'reload'  => false,
            'error' => sprintf(
                __('Please, get the Access Key from CleanTalk Control Panel %s and insert it in the Access Key field', 'cleantalk-spam-protect'),
                'https://cleantalk.org/my/?cp_mode=antispam'
            )
        );
    } else {
        if ( isset($result['user_token']) ) {
            $apbct->data['user_token'] = $result['user_token'];
        }

        if ( ! empty($result['auth_key']) && apbct_api_key__is_correct($result['auth_key']) ) {
            $apbct->data['key_changed'] = trim($result['auth_key']) !== $apbct->settings['apikey'];
            $apbct->settings['apikey'] = trim($result['auth_key']);
        }

        $templates = '';
        if ( ! $direct_call && isset($result['auth_key']) ) {
            $templates = \Cleantalk\ApbctWP\CleantalkSettingsTemplates::getOptionsTemplate($result['auth_key']);
        }

        if ( ! empty($templates) && isset($result['auth_key']) ) {
            $templatesObj = new \Cleantalk\ApbctWP\CleantalkSettingsTemplates($result['auth_key']);
            $out          = array(
                'success'      => true,
                'getTemplates' => $templatesObj->getHtmlContent(true),
            );
        } else {
            $out = array(
                'success' => true,
                'reload'  => true,
            );
        }
    }

    $apbct->saveSettings();
    $apbct->saveData();

    if ( $direct_call ) {
        return $result;
    } else {
        die(json_encode($out));
    }
}

function apbct_settings__update_account_email()
{
    global $apbct;

    AJAXService::checkNonceRestrictingNonAdmins('_ajax_nonce');

    $account_email = Post::get('accountEmail');

    // not valid email
    if (!$account_email || !filter_var(Post::get('accountEmail'), FILTER_VALIDATE_EMAIL)) {
        die(
            json_encode(
                array(
                    'error' => 'Please, enter valid email.'
                )
            )
        );
    }

    // protection against accidental request from a child site in the shared account mode
    if (!is_main_site() && isset($apbct->network_settings['multisite__work_mode']) && $apbct->network_settings['multisite__work_mode'] != 3) {
        die(
            json_encode(
                array(
                    'error' => 'Please, enter valid email.'
                )
            )
        );
    }

    // email not changed
    if (isset($apbct->data['account_email']) && $account_email === $apbct->data['account_email']) {
        die(
            json_encode(
                array(
                    'success' => 'ok'
                )
            )
        );
    }

    $apbct->data['account_email'] = $account_email;
    $apbct->saveData();

    // Link GET ACCESS KEY MANUALLY
    //HANDLE LINK
    $register_link = LinkConstructor::buildCleanTalkLink('get_access_key_link', 'wordpress-anti-spam-plugin');
    $manually_link = sprintf(
        $register_link . '&platform=wordpress&email=%s&website=%s',
        urlencode(ct_get_admin_email()),
        urlencode(get_bloginfo('url'))
    );

    die(
        json_encode(
            array(
                'success' => 'ok',
                'manuallyLink' => $manually_link
            )
        )
    );
}

function apbct_update_blogs_options($settings)
{
    global $wpdb;

    if ( isset($settings['apikey']) ) {
        unset($settings['apikey']);
    }

    $blog_ids = $settings['multisite__use_settings_template_apply_for_current_list_sites'] ?: array();

    $wp_blogs = $wpdb->get_results('SELECT blog_id FROM ' . $wpdb->blogs, OBJECT_K);
    foreach ( $wp_blogs as $blog ) {
        if ( in_array($blog->blog_id, $blog_ids) ) {
            $current_blog_settings = get_blog_option($blog->blog_id, 'cleantalk_settings');
            if ( isset($current_blog_settings['apikey']) ) {
                $settings['apikey'] = $current_blog_settings['apikey'];
            }
            update_blog_option($blog->blog_id, 'cleantalk_settings', $settings);
        }
    }
}

/**
 * Sanitize and validate exclusions.
 * Explode given string by commas and trim each string.
 * Cut first 20 entities if more than 20 given. Remove duplicates.
 * Skip element if it's empty. Validate entity as URL. Cut first 128 chars if more than 128 given
 *
 * Return false if exclusion is bad
 * Return sanitized string if all is ok
 *
 * @param string $exclusions
 * @param bool $regexp
 *
 * @return bool|string
 */
function apbct_settings__sanitize__exclusions($exclusions, $regexp = false, $urls = false)
{
    if ( ! is_string($exclusions) ) {
        return false;
    }

    $result = array();
    $type   = 0;

    if ( ! empty($exclusions) ) {
        if ( strpos($exclusions, "\r\n") !== false ) {
            $exclusions = explode("\r\n", $exclusions);
            $type       = 2;
        } elseif ( strpos($exclusions, "\n") !== false ) {
            $exclusions = explode("\n", $exclusions);
            $type       = 1;
        } else {
            $exclusions = explode(',', $exclusions);
        }
        //Drop duplicates first (before cut)
        $exclusions = array_unique($exclusions);
        //Take first 20 exclusions entities
        $exclusions = array_slice($exclusions, 0, 20);
        //Sanitizing
        foreach ($exclusions as $exclusion) {
            //Cut exclusion if more than 128 symbols gained
            $sanitized_exclusion = substr($exclusion, 0, 128);
            $sanitized_exclusion = trim($sanitized_exclusion);

            if ( ! empty($sanitized_exclusion) ) {
                if ( $regexp ) {
                    if ( ! Validate::isRegexp($exclusion) ) {
                        return false;
                    }
                } elseif ( $urls ) {
                    if ( ! Validate::isUrl($exclusion) ) {
                        return false;
                    }
                }
                $result[] = $sanitized_exclusion;
            }
        }
    }
    switch ( $type ) {
        case 0:
        default:
            return implode(',', $result);
        case 1:
            return implode("\n", $result);
        case 2:
            return implode("\r\n", $result);
    }
}

function apbct_settings__get__long_description()
{
    global $apbct;
    AJAXService::checkAdminNonce();

    $setting_id = TT::toString(Post::get('setting_id', null, 'word'));

    $link_exclusion_by_form_signs = LinkConstructor::buildCleanTalkLink(
        'exclusion_by_form_signs',
        'help/exclusion-by-form-signs'
    );

    $descriptions = array(
        'multisite__work_mode'      => array(
            'title' => __('Wordpress Multisite Work Mode', 'cleantalk-spam-protect'),
            'desc'  => __(
                '<h4>Mutual Account, Individual Access Keys</h4>'
                . '<span>Each blog uses a separate key from the network administrator account. Each blog has its own separate security log, settings, personal lists. Key will be provided automatically to each blog once it is created or during the plugin activation process. The key could be changed only by the network administrator.</span>'
                . '<h4>Mutual Account, Mutual Access Key</h4>'
                . '<span>All blogs use one mutual key. They also share security logs, settings and personal lists with each other. Network administrator holds the key.</span>'
                . '<h4>Individual accounts, individual Access keys</h4>'
                . '<span>Each blog uses its own account and its own key. Separate security logs, settings, personal lists. Blog administrator can change the key on his own.</span>',
                'cleantalk-spam-protect'
            )
        ),
        'data__set_cookies' => array(
            'title' => __('Cookies setting', 'cleantalk-spam-protect'),
            //HANDLE LINK
            'desc'  => sprintf(
                __('It determines what methods of using the HTTP cookies the Anti-Spam plugin for WordPress should switch to. It is necessary for the plugin to work properly. All CleanTalk cookies contain technical data. Data of the current website visitor is encrypted with the MD5 algorithm and being deleted when the browser session ends. %s', 'cleantalk-spam-protect'),
                '<a href="https://cleantalk.org/help/set-cookies-option{utm_mark}" target="_blank">' . __('Learn more about suboptions', 'cleantalk-spam-protect') . '</a>'
            )
        ),
        'comments__hide_website_field' => array(
            'title' => __('Hide the "Website" field', 'cleantalk-spam-protect'),
            //HANDLE LINK
            'desc'  => sprintf(
                __('This «Website» field is frequently used by spammers to place spam links in it. ' . esc_html__($apbct->data['wl_brandname']) . ' helps you protect your WordPress website comments by hiding this field off. %s', 'cleantalk-spam-protect'),
                '<a href="https://cleantalk.org/help/how-to-hide-website-field-in-wordpress-comments{utm_mark}" target="_blank">' . __('Learn more.', 'cleantalk-spam-protect') . '</a>'
            )
        ),
        'comments__the_real_person' => array(
            'title' => __('The Real Person Badge!', 'cleantalk-spam-protect'),
            //HANDLE LINK
            'desc'  => sprintf(
                __('Plugin shows special benchmark for author of a comment or review, that the author passed all anti-spam filters and acts as a real person. It improves quality of users generated content on your website by proving that the content is not from spambots. %s', 'cleantalk-spam-protect'),
                '<a href="' . esc_attr(LinkConstructor::buildCleanTalkLink('trp_learn_more_link', 'the-real-person')) . '" target="_blank">' . __('Learn more.', 'cleantalk-spam-protect') . '</a>'
            )
        ),
        'sfw__anti_crawler' => array(
            'title' => 'Anti-Crawler', // Do not to localize this phrase
            //HANDLE LINK
            'desc'  => sprintf(
                __(esc_html__($apbct->data['wl_brandname']) . ' Anti-Crawler — this option is meant to block all types of bots visiting website pages that can search vulnerabilities on a website, attempt to hack a site, collect personal data, price parsing or content and images, generate 404 error pages, or aggressive website scanning bots. %s', 'cleantalk-spam-protect'),
                '<a href="https://cleantalk.org/help/anti-flood-and-anti-crawler{utm_mark}#anticrawl" target="_blank">' . __('Learn more.', 'cleantalk-spam-protect') . '</a>'
            )
        ),
        'sfw__anti_flood' => array(
            'title' => 'Anti-Flood', // Do not to localize this phrase
            //HANDLE LINK
            'desc'  => sprintf(
                __(esc_html__($apbct->data['wl_brandname']) . ' Anti-Flood — this option is meant to block aggressive bots. You can set the maximum number of website pages your visitors can click on within 1 minute. If any IP exceeds the set number it will get the ' . $apbct->data['wl_brandname'] . ' blocking screen for 30 seconds. It\'s impossible for the IP to open any website pages while the 30-second timer takes place. %s', 'cleantalk-spam-protect'),
                '<a href="https://cleantalk.org/help/anti-flood-and-anti-crawler{utm_mark}#antiflood" target="_blank">' . __('Learn more.', 'cleantalk-spam-protect') . '</a>'
            )
        ),
        'data__pixel' => array(
            'title' => __(esc_html__($apbct->data['wl_brandname']) . ' Pixel', 'cleantalk-spam-protect'),
            //HANDLE LINK
            'desc'  => sprintf(
                __('It is an «invisible» 1×1px image that the Anti-Spam plugin integrates to your WordPress website. And when someone visits your website the Pixel is triggered and reports this visit and some other data including true IP address. %s', 'cleantalk-spam-protect'),
                '<a href="https://blog.cleantalk.org/introducing-cleantalk-pixel{utm_mark}" target="_blank">' . __('Learn more.', 'cleantalk-spam-protect') . '</a>'
            )
        ),
        'data__honeypot_field' => array(
            'title' => __('Honeypot field', 'cleantalk-spam-protect'),
            //HANDLE LINK
            'desc'  => sprintf(
                esc_html__('The option helps to block bots . The honeypot field option adds a hidden field to the form. When spambots come to a website form, they can fill out each input field. Enable this option to make the protection stronger on these forms. Learn more about supported forms %s', 'cleantalk-spam-protect'),
                '<a href="https://cleantalk.org/help/honeypot-field{utm_mark}" target="_blank">' . __('here.', 'cleantalk-spam-protect') . '</a>'
            )
        ),
        'sfw__enabled' => array(
            'title' => 'SpamFireWall',
            //HANDLE LINK
            'desc'  => sprintf(
                '<p>' . esc_html__('SpamFireWall is a part of the anti-spam service and blocks the most spam active bots before the site pages load.', 'cleantalk-spam-protect') . '</p>'
                    . '<p>' . esc_html__('Anti-Crawler is an add-on to SFW and helps to strengthen the protection against spam bots. Disabled by default.', 'cleantalk-spam-protect') . '</p>'
                    . '<p>' . esc_html__($apbct->data['wl_brandname'] . ' Anti-Flood is also an add-on to SFW and limits the number of pages visited per minute. Disabled by default.', 'cleantalk-spam-protect') . '</p>'
                    . '<p>' . esc_html__('You can read more about SFW modes %s', 'cleantalk-spam-protect') . '</p>'
                    . '<p>' . esc_html__('Read out the article if you are using Varnish on your server.', 'cleantalk-spam-protect'),
                '<a href="https://cleantalk.org/help/anti-flood-and-anti-crawler{utm_mark}" target="_blank">' . __('here.', 'cleantalk-spam-protect') . '</a>'
            )
        ),
        'exclusions__form_signs' => array(
            'title' => __('Form Signs Exclusions', 'cleantalk-spam-protect'),
            'desc'  => __('The plugin will check the POST array to find regular expressions matches. Usually, field\'s 
            "name" attribute passed to the POST array as array keys. To skip any of 
            these signs add name or action to the textarea. Example of an exclusion record:', 'cleantalk-spam-protect') .
                '<p><code>.*name_of_your_field+</code></p>' .
                '<p>' .
                __('Also, the plugin check POST array for key action and it\'s value. This could be useful for AJAX 
                forms if you want to skip the form by action. Value will be checked for regexp match:', 'cleantalk-spam-protect') .
                '</p><p><code>.*value_of_action_key_in_post+</code></p>' .
                '<p>' .
                __('Please, note, you can exclude the form by adding a special hidden input or another HTML tag. 
                Then you need to add the exclusion string. Example:', 'cleantalk-spam-protect') .
                '</p>' .
                '<p>Tag:</p>' .
                '<p><code>' . htmlspecialchars('<div style="display: none"><input type="text" name="apbct_skip_this_form"></div>') . '</code></p>' .
                '<p>Exclusion:</p>' .
                '<p><code>.*apbct_skip_this_form+</code></p>' .
                //HANDLE LINK
                sprintf(
                    __('See details on the page linked below. %s', 'cleantalk-spam-protect'),
                    '</p><a href="' . $link_exclusion_by_form_signs . '" target="_blank">' . __('Learn more.', 'cleantalk-spam-protect') . '</a>'
                )
        ),
        'exclusions__bot_detector__form_attributes' => array(
            'title' => esc_html__('Exclude the form by their attribute', 'cleantalk-spam-protect'),
            'desc' => 'If your form tag have any html attribute. like:
            <p><code>' . htmlspecialchars('<form id="my-form" method="get"></form>') . '</code></p>
            you can exclude the same form using attribute name
            <p><code>^my-form$</code> or <code>^get$</code></p>'
        ),
        'exclusions__bot_detector__form_children_attributes' => array(
            'title' => 'Exclude the form by their child html-element attribute',
            'desc' => 'If your form tag have any html attribute. like:
            <p><code>' . htmlspecialchars('<form id="my-form" method="get"><div id="my-child-div"></div></form>') . '</code></p>
            you can exclude the same form using attribute name
            <p><code>^my-child-div$</code></p>',
        ),
        'exclusions__bot_detector__form_parent_attributes' => array(
            'title' => 'Exclude the form by their parent html-element attribute',
            'desc' => 'If your form tag have any html attribute. like:
            <p><code>' . htmlspecialchars('<div id="my-parent-div"><form id="my-form" method="get"></form></div>') . '</code></p>
            you can exclude the same form using attribute name
            <p><code>^my-parent-div$</code></p>',
        ),
        'data__email_decoder_obfuscation_mode' => array(
            'title' => __('Contact data encoding: obfuscation modes', 'cleantalk-spam-protect'),
            'desc'  => EmailEncoder::getObfuscationModesLongDescription(),
        ),
        'data__email_decoder_encode_phone_numbers' => array(
            'title' => __('Contact data encoding: phone numbers', 'cleantalk-spam-protect'),
            'desc'  => EmailEncoder::getPhonesEncodingLongDescription(),
        ),
        'data__email_decoder' => array(
            'title' => __('Contact data encoding', 'cleantalk-spam-protect'),
            'desc'  => EmailEncoder::getEmailEncoderCommonLongDescription(),
        ),
    );

    if (!empty($setting_id) && isset($descriptions[$setting_id])) {
        $utm = '?utm_source=apbct_hint_' . esc_attr($setting_id) . '&utm_medium=WordPress&utm_campaign=ABPCT_Settings';
        $descriptions[$setting_id]['desc'] = str_replace('{utm_mark}', $utm, $descriptions[$setting_id]['desc']);
        die(json_encode($descriptions[$setting_id]));
    } else {
        die(json_encode(['error' => 'Invalid setting ID']));
    }
}

function apbct_settings__check_renew_banner()
{
    global $apbct;

    AJAXService::checkAdminNonce();

    die(
        json_encode(
            array('close_renew_banner' => ($apbct->data['notice_trial'] == 0 && $apbct->data['notice_renew'] == 0) ? true : false)
        )
    );
}

function apbct_settings__check_alt_cookies_types()
{
    echo '<div class="apbct_settings-field_wrapper apbct_settings-field_wrapper--sub">';

    echo sprintf(
        esc_html__('Alternative cookies type was set on %s', 'cleantalk-spam-protect'),
        '<strong>' . Escape::escHtml(apbct_data__get_ajax_type()) . '</strong><br>'
    );

    echo '</div>';
}

function apbct_settings__ajax_handler_type_notification()
{
    echo '<div class="apbct_settings-field_wrapper apbct_settings-field_wrapper--sub">';

    echo sprintf(
        esc_html__('JavaScript check was set on %s', 'cleantalk-spam-protect'),
        '<strong>' . Escape::escHtml(apbct_data__get_ajax_type()) . '</strong><br>'
    );

    echo '</div>';
}

function apbct_data__get_ajax_type()
{
    global $apbct;

    switch ( $apbct->data['ajax_type'] ) {
        case 'rest':
            return esc_html__('REST API', 'cleantalk-spam-protect');
        case 'admin_ajax':
            return esc_html__('WP AJAX handler', 'cleantalk-spam-protect');
        default:
            return esc_html__('UNKNOWN', 'cleantalk-spam-protect');
    }
}

/**
 * Show button for changed account email
 */
function apbct_settings__btn_change_account_email_html()
{
    global $apbct;

    if (
        ! is_main_site() &&
        isset($apbct->network_settings['multisite__work_mode']) &&
        $apbct->network_settings['multisite__work_mode'] == 1) {
        return '';
    }

    return '(<button type="button"
                id="apbct-change-account-email"
                class="apbct-btn-as-link"
                style="background: #fff"
                data-default-text="'
                    . __('change email', 'cleantalk-spam-protect') .
                    '"
                data-save-text="'
                    . __('save', 'cleantalk-spam-protect') .
                    '">'
                . __('change email', 'cleantalk-spam-protect') .
            '</button>)';
}

/**
 * Staff thing - draw sfw_update cron task button
 */
function apbct_sfw_force_sfw_update_button()
{
    global $apbct;

    printf(
        '<div class="apbct_settings-field_wrapper" id="apbct-action-adjust-env">
        <b>Debug: </b>
        <input form="debug__cron_set" type="hidden" name="spbc_remote_call_action" value="cron_update_task" />
        <input form="debug__cron_set" type="hidden" name="plugin_name" value="apbct" />
        <input form="debug__cron_set" type="hidden" name="spbc_remote_call_token" value="%s" />
        <input form="debug__cron_set" type="hidden" name="task" value="sfw_update" />
        <input form="debug__cron_set" type="hidden" name="handler" value="apbct_sfw_update__init" />
        <input form="debug__cron_set" type="hidden" name="period" value="%s" />
        <input form="debug__cron_set" type="hidden" name="first_call" value="%d" />
        <input form="debug__cron_set" type="submit" value="Set SFW update to 60 seconds from now" />
    </div>',
        md5($apbct->api_key),
        $apbct->stats['sfw']['update_period'],
        time() + 60
    );
}

/**
 * Implementation of service_update_local_settings functionality
 */
add_action('apbct_before_returning_settings', 'apbct__send_local_settings_to_api');

function apbct__send_local_settings_to_api($settings)
{
    // Current Access key
    $api_key  = $settings['apikey'] ?: '';

    // Settings to JSON
    $settings = json_encode($settings);

    // Hostname
    $hostname = preg_replace('/^(https?:)?(\/\/)?(www\.)?/', '', get_site_url());

    \Cleantalk\ApbctWP\API::methodSendLocalSettings($api_key, $hostname, $settings);
}

/**
 * Custom logo for die pages setting
 */
function apbct_settings__custom_logo()
{
    global $apbct;

    $value = isset($apbct->settings['cleantalk_custom_logo']) ? $apbct->settings['cleantalk_custom_logo'] : false;
    $default = APBCT_IMG_ASSETS_PATH . '/placeholder.png';

    $src = $default;
    if ($value && ($image_attributes = wp_get_attachment_image_src($value, array(150, 110)))) {
        if ( isset($image_attributes[0]) ) {
            $src = $image_attributes[0];
        }
    }

    ?>
    <div class="apbct_settings-field_wrapper apbct_settings-field_wrapper">
        <h4 class="apbct_settings-field_title apbct_settings-field_title--radio">
            <?php echo esc_html__('Custom logo on SpamFireWall blocking pages', 'cleantalk-spam-protect'); ?>
        </h4>
        <div class="apbct_settings-field_content apbct_settings-field_content--radio">
            <img data-src="<?php echo esc_url($default); ?>" src="<?php echo esc_url(TT::toString($src)); ?>" width="150" alt="" />
            <div>
                <input type="hidden" name="cleantalk_settings[cleantalk_custom_logo]" id="cleantalk_custom_logo" value="<?php echo esc_url(TT::toString($value)); ?>" />
                <button type="button" id="apbct-custom-logo-open-gallery" class="button">
                    <?php echo esc_html__('Image', 'cleantalk-spam-protect'); ?>
                </button>
                <button type="button" id="apbct-custom-logo-remove-image" class="button">×</button>
            </div>

        </div>
    </div>
    <?php
}

function apbct_render_links_to_tag($value)
{
    $pattern = "/(https?:\/\/[^\s]+)/";
    $value = preg_replace($pattern, '<a target="_blank" href="$1">$1</a>', $value);
    return Escape::escKsesPreset($value, 'apbct_settings__display__notifications');
}

function apbct_get_spoilers_links()
{
    global $apbct;

    $advanced_settings = '<span id="ct_adv_showhide" class="apbct_bottom_links--left">'
                         . '<a href="#" class="apbct_color--gray" onclick="'
                         . 'event.preventDefault();'
                         . 'apbctExceptedShowHide(\'apbct_settings__advanced_settings\');'
                         . '">'
                         . __('Advanced settings', 'cleantalk-spam-protect')
                         . '</a>'
                         . '</span>';

    $import_export = '';
    if (! $apbct->data['wl_mode_enabled'] &&
        (is_main_site() ||
            ($apbct->network_settings['multisite__work_mode'] == 1 &&
            $apbct->network_settings['multisite__allow_custom_settings'])
        )
    ) {
        $import_export = '<span class="apbct_bottom_links--other">'
          . '<a href="#" class="apbct_color--gray" onclick="cleantalkModal.open()">'
          . __('Import/Export settings', 'cleantalk-spam-protect')
          . '</a>'
          . '</span>';
    }

    $affiliate_section = ! $apbct->data['wl_mode_enabled']
        ? '<span id="ct_trusted_text_showhide" class="apbct_bottom_links--other">'
          . '<a href="#" class="apbct_color--gray" onclick="'
          . 'return apbctExceptedShowHide(\'trusted_and_affiliate__special_span\');'
          . '">'
          . __('Trust text, affiliate settings', 'cleantalk-spam-protect')
          . '</a>'
          . '</span>'
        : '';

    return '<br><div class="apbct_settings_top_info__sub_btn">' . $advanced_settings . $import_export . $affiliate_section . '</div>';
}

/**
 * @return void
 */
function apbct_settings_field__action_adjust()
{
    $res = AdjustToEnvironmentSettings::render();
    echo $res;
}
