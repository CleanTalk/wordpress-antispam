<?php

use Cleantalk\ApbctWP\Helper;
use Cleantalk\Variables\Post;

/**
 * Admin action 'admin_menu' - Add the admin options page
 */
function apbct_settings_add_page()
{
    global $apbct, $pagenow;

    $parent_slug = is_network_admin() ? 'settings.php' : 'options-general.php';
    $callback    = is_network_admin() ? 'apbct_settings__display__network' : 'apbct_settings__display';

    // Adding settings page
    add_submenu_page(
        $parent_slug,
        $apbct->plugin_name . ' ' . __('settings'),
        $apbct->plugin_name,
        'manage_options',
        'cleantalk',
        $callback
    );

    if ( ! in_array($pagenow, array('options.php', 'options-general.php', 'settings.php', 'admin.php')) ) {
        return;
    }

    register_setting(
        'cleantalk_settings',
        'cleantalk_settings',
        array('type' => 'string', 'sanitize_callback' => 'apbct_settings__validate', 'default' => null)
    );

    $fields = apbct_settings__set_fileds();
    $fields = APBCT_WPMS && is_main_site() ? apbct_settings__set_fileds__network($fields) : $fields;
    apbct_settings__add_groups_and_fields($fields);
}

function apbct_settings__set_fileds()
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

        'debug'                 => array(
            'title'          => '',
            'default_params' => array(),
            'description'    => '',
            'html_before'    => '',
            'html_after'     => '',
            'fields'         => array(
                'state' => array(
                    'callback' => 'apbct_settings__field__debug',
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
                'sfw__enabled' => array(
                    'type'        => 'checkbox',
                    'title'       => __('SpamFireWall', 'cleantalk-spam-protect'),
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
                    'childrens'   => array('sfw__anti_flood', 'sfw__anti_crawler', 'sfw__use_delete_to_clear_table'),
                ),
            ),
        ),

        // Forms protection
        'forms_protection'      => array(
            'title'          => __('Forms to protect', 'cleantalk-spam-protect'),
            'default_params' => array(),
            'description'    => '',
            'html_before'    => '<hr><br>'
                                . '<span id="ct_adv_showhide">'
                                . '<a href="#" class="apbct_color--gray" onclick="event.preventDefault(); apbct_show_hide_elem(\'apbct_settings__davanced_settings\');">'
                                . __('Advanced settings', 'cleantalk-spam-protect')
                                . '</a>'
                                . '</span>'
                                . '<div id="apbct_settings__davanced_settings" style="display: none;">',
            'html_after'     => '',
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
                        'Contact Form 7, Formidable forms, JetPack, Fast Secure Contact Form, WordPress Landing Pages, Gravity Forms.',
                        'cleantalk-spam-protect'
                    ),
                ),
                'forms__general_contact_forms_test'     => array(
                    'title'       => __('Custom contact forms', 'cleantalk-spam-protect'),
                    'description' => __(
                        'Anti spam test for any WordPress themes or contacts forms.',
                        'cleantalk-spam-protect'
                    ),
                ),
                'forms__search_test'                    => array(
                    'title'       => __('Test default Wordpress search form for spam', 'cleantalk-spam-protect'),
                    'description' =>
                        __('Spam protection for Search form.', 'cleantalk-spam-protect')
                        . (! $apbct->white_label || is_main_site() ?
                            sprintf(
                                __('Read more about %sspam protection for Search form%s on our blog. “noindex” tag will be placed in meta derictive on search page.', 'cleantalk-spam-protect'),
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
                        'This setting gives you more sophisticated and strengthened protection for external forms. But it could break plugins which use a buffer like Ninja Forms.',
                        'cleantalk-spam-protect'
                    ),
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
            ),
        ),

        // Comments and Messages
        'wc'                    => array(
            'title'  => __('WooCommerce', 'cleantalk-spam-protect'),
            'fields' => array(
                'forms__wc_checkout_test'       => array(
                    'title'           => __('WooCommerce checkout form', 'cleantalk-spam-protect'),
                    'description'     => __('Anti spam test for WooCommerce checkout form.', 'cleantalk-spam-protect'),
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
                        'Enable anti spam test for registration process which during woocommerce\'s checkout.',
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
                'forms__wc_honeypot'         => array(
                    'title'           => __(
                        'Add a honeypot field',
                        'cleantalk-spam-protect'
                    ),
                    'description'     => __(
                        'This option adds a honeypot to the order form to improve spam protection. Enable this option if you have passed spam on the order form.',
                        'cleantalk-spam-protect'
                    ),
                    'class'           => 'apbct_settings-field_wrapper--sub',
                    'options'         => array(
                        array('val' => 1, 'label' => __('On')),
                        array('val' => 0, 'label' => __('Off')),
                    ),
                ),
            ),
        ),

        // Comments and Messages
        'comments_and_messages' => array(
            'title'  => __('Comments and Messages', 'cleantalk-spam-protect'),
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
                    'description' => __(
                        'Shows little icon near IP addresses and Emails allowing you to check it via CleanTalk\'s database.',
                        'cleantalk-spam-protect'
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
                'comments__hide_website_field'             => array(
                    'title'       => __('Hide the "Website" field', 'cleantalk-spam-protect'),
                    'description' => __(
                        'This option hides the "Website" field on the comment form.',
                        'cleantalk-spam-protect'
                    ),
                    'display'     => ! $apbct->white_label,
                ),
            ),
        ),

        // Data Processing
        'data_processing'       => array(
            'title'  => __('Data Processing', 'cleantalk-spam-protect'),
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
                        'Options helps protect WordPress against spam with any caching plugins. Turn this option on to avoid issues with caching plugins. Turn off this option and SpamFireWall to be compatible with Accelerated mobile pages (AMP).',
                        'cleantalk-spam-protect'
                    ),
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
                        . (! $apbct->white_label ?
                            __(' Or you don`t have records about missed spam here:', 'cleantalk-spam-protect')
                            . '&nbsp;'
                            . '<a href="https://cleantalk.org/my/?user_token='
                            . $apbct->user_token . '&utm_source=wp-backend&utm_medium=admin-bar&cp_mode=antispam" target="_blank">'
                            . __('CleanTalk dashboard', 'cleantalk-spam-protect')
                            . '</a>.' : '')
                        . '<br />'
                        . __('СAUTION! Option can catch POST requests in WordPress backend', 'cleantalk-spam-protect'),
                ),
                'data__set_cookies'                    => array(
                    'title'       => __("Set cookies", 'cleantalk-spam-protect'),
                    'description' =>
                        __(
                            'Turn this option off or use alternative mechanism for cookies to forbid the plugin generate any cookies on website\'s front-end.',
                            'cleantalk-spam-protect'
                        )
                        . '<br>'
                        . __(
                            'This option is helpful if you are using Varnish. Most contact forms will have poor protection if the option is turned off!',
                            'cleantalk-spam-protect'
                        )
                        . '<br>'
                        . __(
                            'Alternative mechanism will store data in database and will not set cookies in browser, so the cache solutions will work just fine.',
                            'cleantalk-spam-protect'
                        )
                        . '<br><b>'
                        . __(
                            'Warning: We strongly recommend you keep the setting on, otherwise it could cause false positives spam detection.',
                            'cleantalk-spam-protect'
                        )
                        . '</b>',
                    'input_type'  => 'radio',
                    'options'     => array(
                        array('val' => 1, 'label' => __('On', 'cleantalk-spam-protect'), 'childrens_enable' => 0,),
                        array('val' => 0, 'label' => __('Off', 'cleantalk-spam-protect'), 'childrens_enable' => 0,),
                        array(
                            'val'              => 2,
                            'label'            => __(
                                'Use alternative mechanism for cookies',
                                'cleantalk-spam-protect'
                            ),
                            'childrens_enable' => 1,
                        ),
                    ),
                    'childrens'   => array('data__set_cookies__alt_sessions_type')
                ),
                'data__set_cookies__alt_sessions_type' => array(
                    'display'    => $apbct->settings['data__set_cookies'] == 2,
                    'callback' => 'apbct_settings__check_alt_cookies_types'
                ),
                'data__ssl_on'                         => array(
                    'title'       => __("Use SSL", 'cleantalk-spam-protect'),
                    'description' => __(
                        'Turn this option on to use encrypted (SSL) connection with servers.',
                        'cleantalk-spam-protect'
                    ),
                ),
                'wp__use_builtin_http_api'             => array(
                    'title'       => __("Use Wordpress HTTP API", 'cleantalk-spam-protect'),
                    'description' => __(
                        'Alternative way to connect the Cloud. Use this if you have connection problems.',
                        'cleantalk-spam-protect'
                    ),
                ),
                'sfw__use_delete_to_clear_table'       => array(
                    'title'       => __(
                        "Use DELETE SQL-command instead TRUNCATE to clear tables",
                        'cleantalk-spam-protect'
                    ),
                    'description' => __(
                        'Could help if you have blocked SpamFireWall tables in your database.',
                        'cleantalk-spam-protect'
                    ),
                    'parent'      => 'sfw__enabled',
                ),
                'data__pixel'                          => array(
                    'title'       => __('Add a CleanTalk Pixel to improve IP-detection', 'cleantalk-spam-protect'),
                    'description' =>
                        __(
                            'Upload small graphic file from Cleantalk\'s server to improve IP-detection.',
                            'cleantalk-spam-protect'
                        )
                        . '<br>'
                        . __(
                            '"Auto" use JavaScript option if cache solutions are found.',
                            'cleantalk-spam-protect'
                        ),
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
            ),
        ),

        // Exclusions
        'exclusions'            => array(
            'title'  => __('Exclusions', 'cleantalk-spam-protect'),
            'fields' => array(
                'exclusions__urls'               => array(
                    'type'        => 'textarea',
                    'title'       => __('URL exclusions', 'cleantalk-spam-protect'),
                    'description' => __(
                        'You could type here a part of the URL you want to exclude. No need to type whole URL with "www" and protocol. Use comma or new lines as separator.',
                        'cleantalk-spam-protect'
                    ),
                ),
                'exclusions__urls__use_regexp'   => array(
                    'type'  => 'checkbox',
                    'title' => __('Use Regular Expression in URL Exclusions', 'cleantalk-spam-protect'),
                ),
                'exclusions__fields'             => array(
                    'type'        => 'text',
                    'title'       => __('Field name exclusions', 'cleantalk-spam-protect'),
                    'description' => __(
                        'You could type here fields names you want to exclude. Use comma as separator.',
                        'cleantalk-spam-protect'
                    ),
                ),
                'exclusions__fields__use_regexp' => array(
                    'type'  => 'checkbox',
                    'title' => __('Use Regular Expression in Field Exclusions', 'cleantalk-spam-protect'),
                ),
                'exclusions__roles'              => array(
                    'type'                    => 'select',
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
            'fields'         => array(
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
                'sfw__anti_crawler'           => array(
                    'type'        => 'checkbox',
                    'title'       => __('Anti-Crawler', 'cleantalk-spam-protect') . $additional_ac_title,
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
                ),
                'sfw__anti_flood'             => array(
                    'type'        => 'checkbox',
                    'title'       => __('Anti-Flood', 'cleantalk-spam-protect'),
                    'class'       => 'apbct_settings-field_wrapper',
                    'parent'      => 'sfw__enabled',
                    'childrens'   => array('sfw__anti_flood__view_limit',),
                    'description' => __(
                        'Shows the SpamFireWall page for bots trying to crawl your site. Look at the page limit setting below.',
                        'cleantalk-spam-protect'
                    ),
                ),
                'sfw__anti_flood__view_limit' => array(
                    'type'        => 'text',
                    'title'       => __('Anti-Flood Page Views Limit', 'cleantalk-spam-protect'),
                    'class'       => 'apbct_settings-field_wrapper--sub',
                    'parent'      => 'sfw__anti_flood',
                    'description' => __(
                        'Count of page view per 1 minute before plugin shows SpamFireWall page. SpamFireWall page active for 30 second after that valid visitor (with JavaScript) passes the page to the demanded page of the site.',
                        'cleantalk-spam-protect'
                    ),
                ),
            ),
        ),

        // Misc
        'misc'                  => array(
            'title'      => __('Miscellaneous', 'cleantalk-spam-protect'),
            'html_after' => '</div><br>',
            'fields'     => array(
                'misc__collect_details'         => array(
                    'type'        => 'checkbox',
                    'title'       => __('Collect details about browsers', 'cleantalk-spam-protect'),
                    'description' => __(
                        "Checking this box you allow plugin store information about screen size and browser plugins of website visitors. The option in a beta state.",
                        'cleantalk-spam-protect'
                    ),
                ),
                'misc__send_connection_reports' => array(
                    'type'        => 'checkbox',
                    'title'       => __('Send connection reports', 'cleantalk-spam-protect'),
                    'description' => __(
                        "Checking this box you allow plugin to send the information about your connection. The option in a beta state.",
                        'cleantalk-spam-protect'
                    ),
                ),
                'misc__async_js'                => array(
                    'type'        => 'checkbox',
                    'title'       => __('Async JavaScript loading', 'cleantalk-spam-protect'),
                    'description' => __(
                        'Use async loading for scripts. Warning: This could reduce filtration quality.',
                        'cleantalk-spam-protect'
                    ),
                ),
                'gdpr__enabled'                 => array(
                    'type'        => 'checkbox',
                    'title'       => __('Allow to add GDPR notice via shortcode', 'cleantalk-spam-protect'),
                    'description' => __(
                        ' Adds small checkbox under your website form. To add it you should use the shortcode on the form\'s page: [cleantalk_gdpr_form id="FORM_ID"]',
                        'cleantalk-spam-protect'
                    ),
                    'childrens'   => array('gdpr__text'),
                ),
                'gdpr__text'                    => array(
                    'type'        => 'text',
                    'title'       => __('GDPR text notice', 'cleantalk-spam-protect'),
                    'description' => __(
                        'This text will be added as a description to the GDPR checkbox.',
                        'cleantalk-spam-protect'
                    ),
                    'parent'      => 'gdpr__enabled',
                    'class'       => 'apbct_settings-field_wrapper--sub',
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
                            "If enabled, overrides similar Wordpress %sdiscussion settings%s.",
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
                'misc__complete_deactivation'   => array(
                    'type'        => 'checkbox',
                    'title'       => __('Complete deactivation', 'cleantalk-spam-protect'),
                    'description' => __('Leave no trace in the system after deactivation.', 'cleantalk-spam-protect'),
                ),

            ),
        ),
    );

    return $fields;
}

function apbct_settings__set_fileds__network($fields)
{
    global $apbct;

    $additional_fields = array(
        'wpms_settings' => array(
            'default_params' => array(),
            'description'    => '',
            'html_before'    => '<br>'
                                . '<span id="ct_adv_showhide">'
                                . '<a href="#" class="apbct_color--gray" onclick="event.preventDefault(); apbct_show_hide_elem(\'apbct_settings__dwpms_settings\');">'
                                . __('WordPress Multisite (WPMS) settings', 'cleantalk-spam-protect')
                                . '</a>'
                                . '</span>'
                                . '<div id="apbct_settings__dwpms_settings" style="display: block;">',
            'html_after'     => '</div><br>',
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
                    'title'            => __('Wordpress Multisite Work Mode', 'cleantalk-spam-protect'),
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
                    'title'            => __('Hoster API key', 'cleantalk-spam-protect'),
                    'description'      => sprintf(
                        __('Copy the key from your %sCleantalk Profile%s', 'cleantalk-spam-protect'),
                        '<a href="https://cleantalk.org/my/profile#api_keys" target="_blank">',
                        '</a>'
                    ),
                    'class'            => 'apbct_settings-field_wrapper--sub',
                    'long_description' => true,
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
                        '<a target="_blank" href="https://cleantalk.org/help/hosting-white-label">',
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
                            "Specify plugin name. Leave empty for deafult %sAnti-spam by Cleantalk%s",
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

            if ( ! $params['display'] ) {
                continue;
            }

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

/**
 * Admin callback function - Displays plugin options page
 */
function apbct_settings__display()
{
    global $apbct;

    // Title
    echo '<h2 class="apbct_settings-title">' . __($apbct->plugin_name, 'cleantalk-spam-protect') . '</h2>';

    // Subtitle for IP license
    if ( $apbct->moderate_ip ) {
        echo '<h4 class="apbct_settings-subtitle apbct_color--gray">' .
            __('Hosting AntiSpam', 'cleantalk-spam-protect') . '</h4>';
    }

    echo '<form action="options.php" method="post">';

    apbct_settings__error__output();

    // Top info
    if ( ! $apbct->white_label ) {
        echo '<div style="float: right; padding: 15px 15px 5px 15px; font-size: 13px; position: relative; background: #f1f1f1;">';

        echo __('CleanTalk\'s tech support:', 'cleantalk-spam-protect')
            . '&nbsp;'
            . '<a target="_blank" href="https://wordpress.org/support/plugin/cleantalk-spam-protect">Wordpress.org</a>.'
            . '<br>';
        echo __('Plugin Homepage at', 'cleantalk-spam-protect') .
             ' <a href="https://cleantalk.org" target="_blank">cleantalk.org</a>.<br/>';
        echo '<span id="apbct_gdpr_open_modal" style="text-decoration: underline;">' . __(
            'GDPR compliance',
            'cleantalk-spam-protect'
        ) . '</span><br/>';
        echo __('Use s@cleantalk.org to test plugin in any WordPress form.', 'cleantalk-spam-protect') . '<br>';
        echo __('CleanTalk is registered Trademark. All rights reserved.', 'cleantalk-spam-protect') . '<br/>';
        if ( $apbct->key_is_ok ) {
            echo '<b style="display: inline-block; margin-top: 10px;">' . sprintf(
                __('Do you like CleanTalk? %sPost your feedback here%s.', 'cleantalk-spam-protect'),
                '<a href="https://wordpress.org/support/plugin/cleantalk-spam-protect/reviews/#new-post" target="_blank">',
                '</a>'
            ) . '</b><br />';
        }
        apbct_admin__badge__get_premium();
        echo '<div id="gdpr_dialog" style="display: none; padding: 7px;">';
        apbct_settings_show_gdpr_text('print');
        echo '</div>';
        echo '</div>';
    }

    // Output spam count
    if ( $apbct->key_is_ok && apbct_api_key__is_correct() ) {
        if ( $apbct->spam_count > 0 ) {
            echo '<div class="apbct_settings-subtitle" style="top: 0; margin-bottom: 10px; width: 200px;">'
                 . '<br>'
                 . '<span>'
                 . sprintf(
                     __('%s  has blocked <b>%s</b> spam.', 'cleantalk-spam-protect'),
                     $apbct->plugin_name,
                     number_format($apbct->spam_count, 0, ',', ' ')
                 )
                 . '</span>'
                 . '<br>'
                 . '<br>'
                 . '</div>';
        }
    }


    // Output spam count
    if ( $apbct->key_is_ok && apbct_api_key__is_correct() ) {
        if ( $apbct->network_settings['multisite__work_mode'] != 2 || is_main_site() ) {
            // CP button
            echo '<a class="cleantalk_link cleantalk_link-manual" target="__blank" href="https://cleantalk.org/my?user_token=' . $apbct->user_token . '&cp_mode=antispam">'
                 . __('Click here to get anti-spam statistics', 'cleantalk-spam-protect')
                 . '</a>';
            echo '&nbsp;&nbsp;';
        }
    }

    if ( apbct_api_key__is_correct() && ($apbct->network_settings['multisite__work_mode'] != 2 || is_main_site()) ) {
        // Sync button
        echo '<button type="button" class="cleantalk_link cleantalk_link-auto" id="apbct_button__sync" title="Synchronizing account status, SpamFireWall database, all kind of journals.">'
             . '<i class="icon-upload-cloud"></i>&nbsp;&nbsp;'
             . __('Synchronize with Cloud', 'cleantalk-spam-protect')
             . '<img style="margin-left: 10px;" class="apbct_preloader_button" src="' . APBCT_URL_PATH . '/inc/images/preloader2.gif" />'
             . '<img style="margin-left: 10px;" class="apbct_success --hide" src="' . APBCT_URL_PATH . '/inc/images/yes.png" />'
             . '</button>';
        echo '&nbsp;&nbsp;';
    }

    // Output spam count
    if ( $apbct->key_is_ok && apbct_api_key__is_correct() ) {
        if ( $apbct->network_settings['multisite__work_mode'] != 2 || is_main_site() ) {
            // Support button
            echo '<a class="cleantalk_link cleantalk_link-auto" target="__blank" href="https://wordpress.org/support/plugin/cleantalk-spam-protect">' .
                 __('Support', 'cleantalk-spam-protect') . '</a>';
            echo '&nbsp;&nbsp;';
            echo '<br>'
                 . '<br>';
        }
    }

    settings_fields('cleantalk_settings');
    do_settings_fields('cleantalk', 'cleantalk_section_settings_main');

    foreach ( $apbct->settings_fields_in_groups as $group_name => $group ) {
        echo ! empty($group['html_before']) ? $group['html_before'] : '';
        echo ! empty($group['title']) ? '<h3 style="margin-left: 220px;">' . $group['title'] . '</h3>' : '';

        do_settings_fields('cleantalk', 'apbct_section__' . $group_name);

        echo ! empty($group['html_after']) ? $group['html_after'] : '';
    }

    echo '<br>';
    echo '<button name="submit" class="cleantalk_link cleantalk_link-manual" value="save_changes">' .
         __('Save Changes') . '</button>';

    echo "</form>";

    if ( ! $apbct->white_label ) {
        // Translate banner for non EN locale
        if ( substr(get_locale(), 0, 2) != 'en' ) {
            require_once(CLEANTALK_PLUGIN_DIR . 'templates/translate_banner.php');
            printf($ct_translate_banner_template, substr(get_locale(), 0, 2));
        }
    }
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
            "<a href='$link'>",
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
            'key_invalid'       => __('Error occurred while API key validating. Error: ', 'cleantalk-spam-protect'),
            'key_get'           => __(
                'Error occurred while automatically gettings access key. Error: ',
                'cleantalk-spam-protect'
            ),
            'sfw_send_logs'     => __(
                'Error occurred while sending SpamFireWall logs. Error: ',
                'cleantalk-spam-protect'
            ),
            'sfw_update'        => __(
                'Error occurred while updating SpamFireWall local base. Error: ',
                'cleantalk-spam-protect'
            ),
            'ua_update'         => __(
                'Error occurred while updating User-Agents local base. Error: ',
                'cleantalk-spam-protect'
            ),
            'account_check'     => __(
                'Error occurred while checking account status. Error: ',
                'cleantalk-spam-protect'
            ),
            'api'               => __('Error occurred while executing API call. Error: ', 'cleantalk-spam-protect'),
            'sfw_outdated'        => __(
                'Error occurred on last SpamFireWall check. Error: ',
                'cleantalk-spam-protect'
            ),

            // Validating settings
            'settings_validate' => 'Validate Settings',
            'exclusions_urls'   => 'URL Exclusions',
            'exclusions_fields' => 'Field Exclusions',

            // Unknown
            'unknown'           => __('Unknown error type: ', 'cleantalk-spam-protect'),
        );

        $errors_out = array();

        foreach ( $errors as $type => $error ) {
            if ( ! empty($error) ) {
                if ( is_array(current($error)) ) {
                    foreach ( $error as $sub_type => $sub_error ) {
                        if ( isset($sub_error['error']) && strpos($sub_error['error'], 'SFW_IS_DISABLED') !== false ) {
                            continue;
                        }

                        $errors_out[$sub_type] = '';
                        if ( isset($sub_error['error_time']) ) {
                            $errors_out[$sub_type] .= date('Y-m-d H:i:s', $sub_error['error_time']) . ': ';
                        }
                        $errors_out[$sub_type] .= (isset($error_texts[$type]) ? $error_texts[$type] : ucfirst($type)) . ': ';
                        $errors_out[$sub_type] .= (isset($error_texts[$sub_type]) ? $error_texts[$sub_type] : ( $error_texts['unknown'] . $sub_type . ' ' . __('Error: ', 'cleantalk-spam-protect') ) . ' ' . $sub_error['error'] );
                    }
                    continue;
                }

                if (
                    ! empty($type) &&
                    $apbct->white_label &&
                    ! is_main_site() &&
                    in_array($type, array('sfw_update', 'key_invalid', 'account_check'))
                ) {
                    continue;
                }

                if ( isset($error['error']) && strpos($error['error'], 'SFW_IS_DISABLED') !== false ) {
                    continue;
                }

                $errors_out[$type] = '';

                if ( isset($error['error_time']) ) {
                    $errors_out[$type] .= date('Y-m-d H:i:s', $error['error_time']) . ': ';
                }

                $errors_out[$type] .= (isset($error_texts[$type]) ? $error_texts[$type] : $error_texts['unknown']) . ' ' . (isset($error['error']) ? $error['error'] : '');
            }
        }

        if ( ! empty($errors_out) ) {
            $out .= '<div id="apbctTopWarning" class="error" style="position: relative;">'
                    . '<h3 style="display: inline-block;">' . __('Errors:', 'cleantalk-spam-protect') . '</h3>';
            foreach ( $errors_out as $value ) {
                $out .= '<h4>' . $value . '</h4>';
            }
            $out .= ! $apbct->white_label
                ? '<h4 style="text-align: unset;">' . sprintf(
                    __('You can get support any time here: %s.', 'cleantalk-spam-protect'),
                    '<a target="blank" href="https://wordpress.org/support/plugin/cleantalk-spam-protect">https://wordpress.org/support/plugin/cleantalk-spam-protect</a>'
                ) . '</h4>'
                : '';
            $out .= '</div>';
        }
    }

    if ( $return ) {
        return $out;
    } else {
        echo $out;
    }
}

function apbct_settings__field__debug()
{
    global $apbct;

    if ( $apbct->debug ) {
        echo '<hr /><h2>Debug:</h2>';
        echo '<h4>Constants:</h4>';
        echo 'CLEANTALK_AJAX_USE_BUFFER ' .
             (defined('CLEANTALK_AJAX_USE_BUFFER') ?
                 var_export(CLEANTALK_AJAX_USE_BUFFER, true) :
                 'NOT_DEFINED') .
             "<br>";
        echo 'CLEANTALK_AJAX_USE_FOOTER_HEADER ' .
             (defined('CLEANTALK_AJAX_USE_FOOTER_HEADER') ?
                 var_export(CLEANTALK_AJAX_USE_FOOTER_HEADER, true) :
                 'NOT_DEFINED') .
             "<br>";
        echo 'CLEANTALK_ACCESS_KEY ' .
             (defined('CLEANTALK_ACCESS_KEY') ?
                 var_export(CLEANTALK_ACCESS_KEY, true) :
                 'NOT_DEFINED') .
             "<br>";
        echo 'CLEANTALK_CHECK_COMMENTS_NUMBER ' .
             (defined('CLEANTALK_CHECK_COMMENTS_NUMBER') ?
                 var_export(CLEANTALK_CHECK_COMMENTS_NUMBER, true) :
                 'NOT_DEFINED') .
             "<br>";
        echo 'CLEANTALK_CHECK_MESSAGES_NUMBER ' .
             (defined('CLEANTALK_CHECK_MESSAGES_NUMBER') ?
                 var_export(CLEANTALK_CHECK_MESSAGES_NUMBER, true) :
                 'NOT_DEFINED') .
             "<br>";
        echo 'CLEANTALK_PLUGIN_DIR ' .
             (defined('CLEANTALK_PLUGIN_DIR') ?
                 var_export(CLEANTALK_PLUGIN_DIR, true) :
                 'NOT_DEFINED') .
             "<br>";
        echo 'WP_ALLOW_MULTISITE ' .
             (defined('WP_ALLOW_MULTISITE') ?
                 var_export(WP_ALLOW_MULTISITE, true) :
                 'NOT_DEFINED') .
             "<br>";

        echo '<h4><button type="submit" name="apbct_debug__check_connection" value="1">Check connection to API servers</button></h4>';
        echo "<h4>Debug log: <button type='submit' value='debug_drop' name='submit' style='font-size: 11px; padding: 1px;'>Drop debug data</button></h4>";
        echo "<div style='height: 500px; width: 80%; overflow: auto;'>";

        $output = print_r($apbct->debug, true);
        $output = str_replace("\n", "<br>", $output);
        $output = preg_replace("/[^\S]{4}/", "&nbsp;&nbsp;&nbsp;&nbsp;", $output);
        echo "$output";

        echo "</div>";
    }
}

function apbct_settings__field__state()
{
    global $apbct;

    $path_to_img = plugin_dir_url(__FILE__) . "images/";

    $img         = $path_to_img . "yes.png";
    $img_no      = $path_to_img . "no.png";
    $img_no_gray = $path_to_img . "no_gray.png";
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

    echo '<img class="apbct_status_icon" src="' . ($apbct->settings['forms__registrations_test'] == 1 ? $img : $img_no) . '"/>' . __(
        'Registration forms',
        'cleantalk-spam-protect'
    );
    echo '<img class="apbct_status_icon" src="' . ($apbct->settings['forms__comments_test'] == 1 ? $img : $img_no) . '"/>' . __(
        'Comments forms',
        'cleantalk-spam-protect'
    );
    echo '<img class="apbct_status_icon" src="' . ($apbct->settings['forms__contact_forms_test'] == 1 ? $img : $img_no) . '"/>' . __(
        'Contact forms',
        'cleantalk-spam-protect'
    );
    echo '<img class="apbct_status_icon" src="' . ($apbct->settings['forms__general_contact_forms_test'] == 1 ? $img : $img_no) . '"/>' . __(
        'Custom contact forms',
        'cleantalk-spam-protect'
    );
    if ( ! $apbct->white_label || is_main_site() ) {
        echo '<img class="apbct_status_icon" src="' . ($apbct->data['moderate'] == 1 ? $img : $img_no) . '"/>'
             . '<a style="color: black" href="https://blog.cleantalk.org/real-time-email-address-existence-validation/">' . __(
                 'Validate email for existence',
                 'cleantalk-spam-protect'
             ) . '</a>';
    }
    // Autoupdate status
    if ( $apbct->notice_auto_update && ( ! $apbct->white_label || is_main_site()) ) {
        echo '<img class="apbct_status_icon" src="' . ($apbct->auto_update == 1 ? $img : ($apbct->auto_update == -1 ? $img_no : $img_no_gray)) . '"/>' . __(
            'Auto update',
            'cleantalk-spam-protect'
        )
             . ' <sup><a href="https://cleantalk.org/help/cleantalk-auto-update" target="_blank">?</a></sup>';
    }

    // WooCommerce
    if ( class_exists('WooCommerce') ) {
        echo '<img class="apbct_status_icon" src="' . ($apbct->settings['forms__wc_checkout_test'] == 1 ? $img : $img_no) . '"/>' . __(
            'WooCommerce checkout form',
            'cleantalk-spam-protect'
        );
    }
    if ( $apbct->moderate_ip ) {
        print "<br /><br />The anti-spam service is paid by your hosting provider. License #" . $apbct->data['ip_license'] . ".<br />";
    }

    print "</div>";
}

/**
 * Admin callback function - Displays inputs of 'apikey' plugin parameter
 */
function apbct_settings__field__apikey()
{
    global $apbct;

    echo '<div id="cleantalk_apikey_wrapper" class="apbct_settings-field_wrapper">';

    // Using key from Main site, or from CLEANTALK_ACCESS_KEY constant
    if ( APBCT_WPMS && ! is_main_site() && ( ! $apbct->allow_custom_key || defined('CLEANTALK_ACCESS_KEY')) ) {
        _e('<h3>Access key is provided by network administrator</h3>', 'cleantalk-spam-protect');

        return;
    }

    echo '<label class="apbct_settings__label" for="cleantalk_apkey">' . __(
        'Access key',
        'cleantalk-spam-protect'
    ) . '</label>';

    echo '<input
			id="apbct_setting_apikey"
			class="apbct_setting_text apbct_setting---apikey"
			type="text"
			name="cleantalk_settings[apikey]"
			value="'
         . ($apbct->key_is_ok
            ? str_repeat('*', strlen($apbct->api_key))
            : $apbct->api_key
         )
         . '"
			key="' . $apbct->api_key . '"
			size="20"
			placeholder="' . __('Enter the key', 'cleantalk-spam-protect') . '"'
         . ' />';

    // Show account name associated with key
    if ( ! empty($apbct->data['account_name_ob']) ) {
        echo '<div class="apbct_display--none">'
             . sprintf(
                 __('Account at cleantalk.org is %s.', 'cleantalk-spam-protect'),
                 '<b>' . $apbct->data['account_name_ob'] . '</b>'
             )
             . '</div>';
    };

    // Show key button
    if ( (apbct_api_key__is_correct($apbct->api_key) && $apbct->key_is_ok) ) {
        echo '<a id="apbct_showApiKey" class="ct_support_link" style="display: block" href="#">'
             . __('Show the access key', 'cleantalk-spam-protect')
             . '</a>';
        // "Auto Get Key" buttons. License agreement
    } else {
        echo '<br /><br />';

        // Auto get key
        if ( ! $apbct->ip_license ) {
            echo '<button class="cleantalk_link cleantalk_link-manual apbct_setting---get_key_auto" id="apbct_button__get_key_auto" name="submit" type="button"  value="get_key_auto">'
                 . __('Get Access Key Automatically', 'cleantalk-spam-protect')
                 . '<img style="margin-left: 10px;" class="apbct_preloader_button" src="' . APBCT_URL_PATH . '/inc/images/preloader2.gif" />'
                 . '<img style="margin-left: 10px;" class="apbct_success --hide" src="' . APBCT_URL_PATH . '/inc/images/yes.png" />'
                 . '</button>';
            echo '<input type="hidden" id="ct_admin_timezone" name="ct_admin_timezone" value="null" />';
            echo '<br />';
            echo '<br />';
        }

        // Warnings and GDPR
        printf(
            __(
                'Admin e-mail (%s) will be used for registration, if you want to use other email please %sGet Access Key Manually%s.',
                'cleantalk-spam-protect'
            ),
            ct_get_admin_email(),
            '<a class="apbct_color--gray" target="__blank" href="'
            . sprintf(
                'https://cleantalk.org/register?platform=wordpress&email=%s&website=%s',
                urlencode(ct_get_admin_email()),
                urlencode(get_bloginfo('url'))
            )
            . '">',
            '</a>'
        );

        // License agreement
        if ( ! $apbct->ip_license ) {
            echo '<div>';
            echo '<input checked type="checkbox" id="license_agreed" onclick="apbctSettingsDependencies(\'apbct_setting---get_key_auto\');"/>';
            echo '<label for="spbc_license_agreed">';
            printf(
                __('I accept %sLicense Agreement%s.', 'cleantalk-spam-protect'),
                '<a class = "apbct_color--gray" href="https://cleantalk.org/publicoffer" target="_blank">',
                '</a>'
            );
            echo "</label>";
            echo '</div>';
        }
    }

    echo '</div>';
}

function apbct_field_service_utilization()
{
    global $apbct;

    echo '<div class="apbct_wrapper_field">';

    if ( $apbct->services_count && $apbct->services_max && $apbct->services_utilization ) {
        echo sprintf(
            __('Hoster account utilization: %s%% ( %s of %s websites ).', 'cleantalk-spam-protect'),
            $apbct->services_utilization * 100,
            $apbct->services_count,
            $apbct->services_max
        );

        // Link to the dashboard, so user could extend your subscription for more sites
        if ( $apbct->services_utilization * 100 >= 90 ) {
            echo '&nbsp';
            echo sprintf(
                __('You could extend your subscription %shere%s.', 'cleantalk-spam-protect'),
                '<a href="' . $apbct->dashboard_link . '" target="_blank">',
                '</a>'
            );
        }
    } else {
        _e(
            'Enter the Hoster API key and synchronize with cloud to find out your hoster account utilization.',
            'cleantalk-spam-protect'
        );
    }

    echo '</div>';
}

function apbct_settings__field__action_buttons()
{
    global $apbct;

    $links = apply_filters(
        'apbct_settings_action_buttons',
        array(
            '<a href="edit-comments.php?page=ct_check_spam" class="ct_support_link">' . __(
                'Check comments for spam',
                'cleantalk-spam-protect'
            ) . '</a>',
            '<a href="users.php?page=ct_check_users" class="ct_support_link">' . __(
                'Check users for spam',
                'cleantalk-spam-protect'
            ) . '</a>',
            '<a href="#" class="ct_support_link" onclick="apbct_show_hide_elem(\'apbct_statistics\')">' . __(
                'Statistics & Reports',
                'cleantalk-spam-protect'
            ) . '</a>',
        )
    );

    echo '<div class="apbct_settings-field_wrapper">';

    if ( apbct_api_key__is_correct($apbct->api_key) && $apbct->key_is_ok ) {
        echo '<div>';
        foreach ( $links as $link ) {
            echo $link . '&nbsp;&nbsp;&nbsp;&nbsp;';
        }
        echo '</div>';
    }

    echo '</div>';
}

function apbct_settings__field__statistics()
{
    global $apbct;

    echo '<div id="apbct_statistics" class="apbct_settings-field_wrapper" style="display: none;">';

    // Last request
    printf(
        __('Last spam check request to %s server was at %s.', 'cleantalk-spam-protect'),
        $apbct->stats['last_request']['server'] ? $apbct->stats['last_request']['server'] : __(
            'unknown',
            'cleantalk-spam-protect'
        ),
        $apbct->stats['last_request']['time'] ? date('M d Y H:i:s', $apbct->stats['last_request']['time']) : __(
            'unknown',
            'cleantalk-spam-protect'
        )
    );
    echo '<br>';

    // Avarage time request
    printf(
        __('Average request time for past 7 days: %s seconds.', 'cleantalk-spam-protect'),
        $apbct->stats['requests'][min(array_keys($apbct->stats['requests']))]['average_time']
            ? round($apbct->stats['requests'][min(array_keys($apbct->stats['requests']))]['average_time'], 3)
            : __('unknown', 'cleantalk-spam-protect')
    );
    echo '<br>';

    // SFW last die
    printf(
        __('Last time SpamFireWall was triggered for %s IP at %s', 'cleantalk-spam-protect'),
        $apbct->stats['last_sfw_block']['ip'] ? $apbct->stats['last_sfw_block']['ip'] : __(
            'unknown',
            'cleantalk-spam-protect'
        ),
        $apbct->stats['last_sfw_block']['time'] ? date('M d Y H:i:s', $apbct->stats['last_sfw_block']['time']) : __(
            'unknown',
            'cleantalk-spam-protect'
        )
    );
    echo '<br>';

    // SFW last update
    printf(
        __('SpamFireWall was updated %s. Now contains %s entries.', 'cleantalk-spam-protect'),
        $apbct->stats['sfw']['last_update_time'] ? date('M d Y H:i:s', $apbct->stats['sfw']['last_update_time']) : __(
            'unknown',
            'cleantalk-spam-protect'
        ),
        $apbct->stats['sfw']['entries']
    );
    echo $apbct->fw_stats['firewall_updating_id'] ? ' ' . __(
        'Under updating now:',
        'cleantalk-spam-protect'
    ) . ' ' . $apbct->fw_stats['firewall_update_percent'] . '%' : '';
    echo '<br>';

    // SFW last sent logs
    printf(
        __('SpamFireWall sent %s events at %s.', 'cleantalk-spam-protect'),
        $apbct->stats['sfw']['last_send_amount'] ? $apbct->stats['sfw']['last_send_amount'] : __(
            'unknown',
            'cleantalk-spam-protect'
        ),
        $apbct->stats['sfw']['last_send_time'] ? date('M d Y H:i:s', $apbct->stats['sfw']['last_send_time']) : __(
            'unknown',
            'cleantalk-spam-protect'
        )
    );
    echo '<br>';

    // Connection reports
    if ( $apbct->connection_reports ) {
        if ( $apbct->connection_reports['negative'] == 0 ) {
            _e('There are no failed connections to server.', 'cleantalk-spam-protect');
        } else {
            echo "<table id='negative_reports_table''>
					<tr>
						<td>#</td>
						<td><b>Date</b></td>
						<td><b>Page URL</b></td>
						<td><b>Report</b></td>
						<td><b>Server IP</b></td>
					</tr>";
            foreach ( $apbct->connection_reports['negative_report'] as $key => $report ) {
                echo '<tr>'
                     . '<td>' . ($key + 1) . '.</td>'
                     . '<td>' . $report['date'] . '</td>'
                     . '<td>' . $report['page_url'] . '</td>'
                     . '<td>' . $report['lib_report'] . '</td>'
                     . '<td>' . $report['work_url'] . '</td>'
                     . '</tr>';
            }
            echo "</table>";
            echo '<br/>';
            echo '<button'
                 . ' name="submit"'
                 . ' class="cleantalk_link cleantalk_link-manual"'
                 . ' value="ct_send_connection_report"'
                 . (! $apbct->settings['misc__send_connection_reports'] ? ' disabled="disabled"' : '')
                 . '>'
                 . __('Send report', 'cleantalk-spam-protect')
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
    echo 'Plugin version: ' . APBCT_VERSION;

    echo '</div>';
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
                $blogs[]      = array(
                    'val'   => $blog_details->id,
                    'label' => '#' . $blog_details->id . ' ' . $blog_details->blogname
                );
            }
        }
    }

    return $blogs;
}

/**
 * Get all current Wordpress roles
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
    global $apbct;

    $value        = $params['network'] ? $apbct->network_settings[$params['name']] : $apbct->settings[$params['name']];
    $value_parent = $params['parent']
        ? ($params['network'] ? $apbct->network_settings[$params['parent']] : $apbct->settings[$params['parent']])
        : false;

    // Is element is disabled
    $disabled = $params['parent'] && ! $value_parent ? ' disabled="disabled"' : '';        // Strait
    $disabled = $params['parent'] && $params['reverse_trigger'] && ! $value_parent ? '' : $disabled; // Reverse logic
    $disabled = $params['disabled'] ? ' disabled="disabled"' : $disabled; // Direct disable from params
    $disabled =
        ! is_main_site() &&
        $apbct->network_settings &&
        ( ! $apbct->network_settings['multisite__allow_custom_settings'] || $apbct->network_settings['multisite__work_mode'] == 2 )
            ? ' disabled="disabled"'
            : $disabled; // Disabled by super admin on sub-sites

    $childrens = $params['childrens'] ? 'apbct_setting---' . implode(",apbct_setting---", $params['childrens']) : '';
    $hide      = $params['hide'] ? implode(",", $params['hide']) : '';

    echo '<div class="' . $params['def_class'] . (isset($params['class']) ? ' ' . $params['class'] : '') . '">';

    switch ( $params['type'] ) {
        // Checkbox type
        case 'checkbox':
            echo '<input
					type="checkbox"
					name="cleantalk_settings[' . $params['name'] . ']"
					id="apbct_setting_' . $params['name'] . '"
					value="1" '
                 . " class='apbct_setting_{$params['type']} apbct_setting---{$params['name']}'"
                 . ($value == '1' ? ' checked' : '')
                 . $disabled
                 . ($params['required'] ? ' required="required"' : '')
                 . ($params['childrens'] ? ' apbct_children="' . $childrens . '"' : '')
                 . ' onchange="'
                 . ($params['childrens'] ? ' apbctSettingsDependencies(\'' . $childrens . '\');' : '')
                 . ($params['hide'] ? ' apbct_show_hide_elem(\'' . $hide . '\');' : '')
                 . '"'
                 . ' />'
                 . '<label for="apbct_setting_' . $params['name'] . '" class="apbct_setting-field_title--' . $params['type'] . '">'
                 . $params['title']
                 . '</label>';
            echo isset($params['long_description'])
                ? '<i setting="' . $params['name'] . '" class="apbct_settings-long_description---show icon-help-circled"></i>'
                : '';
            echo '<div class="apbct_settings-field_description">'
                 . $params['description']
                 . '</div>';
            break;

        // Radio type
        case 'radio':
            // Title
            echo isset($params['title'])
                ? '<h4 class="apbct_settings-field_title apbct_settings-field_title--' . $params['type'] . '">' . $params['title'] . '</h4>'
                : '';

            // Popup description
            echo isset($params['long_description'])
                ? '<i setting="' . $params['name'] . '" class="apbct_settings-long_description---show icon-help-circled"></i>'
                : '';

            echo '<div class="apbct_settings-field_content apbct_settings-field_content--' . $params['type'] . '">';

            echo '<div class="apbct_switchers" style="direction: ltr">';
            foreach ( $params['options'] as $option ) {
                echo '<input'
                     . ' type="radio"'
                     . " class='apbct_setting_{$params['type']} apbct_setting---{$params['name']}'"
                     . " id='apbct_setting_{$params['name']}__{$option['label']}'"
                     . ' name="cleantalk_settings[' . $params['name'] . ']"'
                     . ' value="' . $option['val'] . '"'
                     . $disabled
                     . ($params['childrens']
                        ? ' onchange="apbctSettingsDependencies(\'' . $childrens . '\', ' . $option['childrens_enable'] . ')"'
                        : ''
                     )
                     . ($value == $option['val'] ? ' checked' : '')
                     . ($params['required'] ? ' required="required"' : '')
                     . ' />';
                echo '<label for="apbct_setting_' . $params['name'] . '__' . $option['label'] . '"> ' . $option['label'] . '</label>';
                echo '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
            }
            echo '</div>';

            echo isset($params['description'])
                ? '<div class="apbct_settings-field_description">' . $params['description'] . '</div>'
                : '';

            echo '</div>';
            break;

        // Dropdown list type
        case 'select':
            echo isset($params['title'])
                ? '<h4 class="apbct_settings-field_title apbct_settings-field_title--' . $params['type'] . '">' . $params['title'] . '</h4>'
                : '';
            echo '<select'
                 . ' id="apbct_setting_' . $params['name'] . '"'
                 . " class='apbct_setting_{$params['type']} apbct_setting---{$params['name']}'"
                 . ' name="cleantalk_settings[' . $params['name'] . ']' . ($params['multiple'] ? '[]"' : '"')
                 . ($params['multiple'] ? ' size="' . count($params['options']) . '""' : '')
                 . ($params['multiple'] ? ' multiple="multiple"' : '')
                 . ($params['childrens']
                    ? ' onchange="apbctSettingsDependencies(\'' . $childrens . '\', jQuery(this).find(\'option:selected\').data(\'children_enable\'))"'
                    : ''
                 )
                 . $disabled
                 . ($params['required'] ? ' required="required"' : '')
                 . ' >';

            foreach ( $params['options'] as $option ) {
                echo '<option'
                     . ' value="' . $option['val'] . '"'
                     . (isset($option['children_enable']) ? ' data-children_enable=' . $option['children_enable'] . ' ' : ' ')
                     . ($params['multiple']
                        ? (! empty($value) && in_array($option['val'], $value) ? ' selected="selected"' : '')
                        : ($value == $option['val'] ? 'selected="selected"' : '')
                     )
                     . '>'
                     . $option['label']
                     . '</option>';
            }

            echo '</select>';
            echo isset($params['long_description'])
                ? '<i setting="' . $params['name'] . '" class="apbct_settings-long_description---show icon-help-circled"></i>'
                : '';
            echo isset($params['description'])
                ? '<div class="apbct_settings-field_description">' . $params['description'] . '</div>'
                : '';

            break;

        // Text type
        case 'text':
            echo '<input
					type="text"
					id="apbct_setting_' . $params['name'] . '"
					name="cleantalk_settings[' . $params['name'] . ']"'
                 . " class='apbct_setting_{$params['type']} apbct_setting---{$params['name']}'"
                 . ' value="' . $value . '" '
                 . $disabled
                 . ($params['required'] ? ' required="required"' : '')
                 . ($params['childrens'] ? ' onchange="apbctSettingsDependencies(\'' . $childrens . '\')"' : '')
                 . ' />'
                 . '&nbsp;'
                 . '<label for="apbct_setting_' . $params['name'] . '" class="apbct_setting-field_title--' . $params['type'] . '">'
                 . $params['title']
                 . '</label>';
            echo '<div class="apbct_settings-field_description">'
                 . $params['description']
                 . '</div>';
            break;

        // Textarea type
        case 'textarea':
            echo '<label for="apbct_setting_' . $params['name'] . '" class="apbct_setting-field_title--' . $params['type'] . '">'
                 . $params['title']
                 . '</label></br>';
            echo '<textarea
					id="apbct_setting_' . $params['name'] . '"
					name="cleantalk_settings[' . $params['name'] . ']"'
                 . " class='apbct_setting_{$params['type']} apbct_setting---{$params['name']}'"
                 . $disabled
                 . ($params['required'] ? ' required="required"' : '')
                 . ($params['childrens'] ? ' onchange="apbctSettingsDependencies(\'' . $childrens . '\')"' : '')
                 . '>' . $value . '</textarea>'
                 . '&nbsp;';
            echo '<div class="apbct_settings-field_description">'
                 . $params['description']
                 . '</div>';
            break;
    }

    echo '</div>';
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
        ! is_main_site() &&
        ! $apbct->network_settings['multisite__allow_custom_settings'] &&
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
    foreach ( $apbct->def_settings as $setting => $value ) {
        if ( ! isset($settings[$setting]) ) {
            $settings[$setting] = null;
            settype($settings[$setting], gettype($value));
        }
    }
    unset($setting, $value);

    // Set missing network settings.
    foreach ( $apbct->def_network_settings as $setting => $value ) {
        if ( ! isset($settings[$setting]) ) {
            $settings[$setting] = null;
            settype($settings[$setting], gettype($value));
        }
    }
    unset($setting, $value);

    // Actions with toggle SFW settings
    // SFW was enabled
    if ( ! $apbct->settings['sfw__enabled'] && $settings['sfw__enabled'] ) {
        apbct_sfw_update__init(3);
        // SFW was disabled
    } elseif ( $apbct->settings['sfw__enabled'] && ! $settings['sfw__enabled'] ) {
        apbct_sfw__clear();
    }

    //Sanitizing sfw__anti_flood__view_limit setting
    $settings['sfw__anti_flood__view_limit'] = floor(intval($settings['sfw__anti_flood__view_limit']));
    $settings['sfw__anti_flood__view_limit'] = ($settings['sfw__anti_flood__view_limit'] == 0 ? 20 : $settings['sfw__anti_flood__view_limit']); // Default if 0 passed
    $settings['sfw__anti_flood__view_limit'] = ($settings['sfw__anti_flood__view_limit'] < 5 ? 5 : $settings['sfw__anti_flood__view_limit']); //

    // Validating API key
    $settings['apikey'] = strpos($settings['apikey'], '*') === false ? $settings['apikey'] : $apbct->settings['apikey'];

    $apbct->data['key_changed'] = $settings['apikey'] !== $apbct->settings['apikey'];

    $settings['apikey'] = ! empty($settings['apikey']) ? trim($settings['apikey']) : '';
    $settings['apikey'] = defined('CLEANTALK_ACCESS_KEY') ? CLEANTALK_ACCESS_KEY : $settings['apikey'];
    $settings['apikey'] = ! is_main_site() && $apbct->white_label && $apbct->settings['apikey'] ? $apbct->settings['apikey'] : $settings['apikey'];
    $settings['apikey'] = is_main_site() || $apbct->allow_custom_key || $apbct->white_label ? $settings['apikey'] : $apbct->network_settings['apikey'];
    $settings['apikey'] = is_main_site() || ! $settings['multisite__white_label'] ? $settings['apikey'] : $apbct->settings['apikey'];

    // Show notice if the api key is empty
    if ( ! apbct_api_key__is_correct() ) {
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
    $result = apbct_settings__sanitize__exclusions(
        $settings['exclusions__urls'],
        $settings['exclusions__urls__use_regexp']
    );
    $result === false
        ? $apbct->errorAdd(
            'exclusions_urls',
            'is not valid: "' . $settings['exclusions__urls'] . '"',
            'settings_validate'
        )
        : $apbct->errorDelete('exclusions_urls', true, 'settings_validate');
    $settings['exclusions__urls'] = $result ? $result : '';

    // Fields
    $result = apbct_settings__sanitize__exclusions(
        $settings['exclusions__fields'],
        $settings['exclusions__fields__use_regexp']
    );
    $result === false
        ? $apbct->errorAdd(
            'exclusions_fields',
            'is not valid: "' . $settings['exclusions__fields'] . '"',
            'settings_validate'
        )
        : $apbct->errorDelete('exclusions_fields', true, 'settings_validate');
    $settings['exclusions__fields'] = $result ? $result : '';

    // WPMS Logic.
    if ( APBCT_WPMS && is_main_site() ) {
        $network_settings = array(
            'multisite__allow_custom_settings'                              => $settings['multisite__allow_custom_settings'],
            'multisite__white_label'                                        => $settings['multisite__white_label'],
            'multisite__white_label__plugin_name'                           => $settings['multisite__white_label__plugin_name'],
            'multisite__use_settings_template'                              => $settings['multisite__use_settings_template'],
            'multisite__use_settings_template_apply_for_new'                => $settings['multisite__use_settings_template_apply_for_new'],
            'multisite__use_settings_template_apply_for_current'            => $settings['multisite__use_settings_template_apply_for_current'],
            'multisite__use_settings_template_apply_for_current_list_sites' => $settings['multisite__use_settings_template_apply_for_current_list_sites'],
        );
        unset($settings['multisite__white_label'], $settings['multisite__white_label__plugin_name']);

        if ( isset($settings['multisite__hoster_api_key']) ) {
            $network_settings['multisite__hoster_api_key'] = $settings['multisite__hoster_api_key'];
            unset($settings['multisite__hoster_api_key']);
        }

        if ( isset($settings['multisite__work_mode']) ) {
            $network_settings['multisite__work_mode'] = $settings['multisite__work_mode'];
            unset($settings['multisite__work_mode']);
        }
    }

    // Drop debug data
    if ( Post::get('submit') === 'debug_drop' ) {
        $apbct->debug = false;
        $apbct->deleteOption('debug', true);
        return $settings;
    }

    // Test connections to servers
    if ( Post::get('apbct_debug__check_connection') ) {
        $result = apbct_test_connection();
        apbct_log($result);
    }

    // Send connection reports
    if ( Post::get('submit') === 'ct_send_connection_report' ) {
        ct_mail_send_connection_report();

        return $settings;
    }

    $apbct->save('data');

    // WPMS Logic.
    if ( APBCT_WPMS ) {
        if ( is_main_site() ) {
            // Network settings
            $network_settings['apikey'] = $settings['apikey'];
            $apbct->network_settings    = $network_settings;
            $apbct->saveNetworkSettings();

            // Network data
            $apbct->network_data = array(
                'key_is_ok'   => $apbct->data['key_is_ok'],
                'moderate'    => $apbct->data['moderate'],
                'valid'       => isset($apbct->data['valid']) ? $apbct->data['valid'] : 0,
                'auto_update' => $apbct->data['auto_update'],
                'user_token'  => $apbct->data['user_token'],
                'service_id'  => $apbct->data['service_id'],
            );
            $apbct->saveNetworkData();
            if ( isset($settings['multisite__use_settings_template_apply_for_current_list_sites']) && ! empty($settings['multisite__use_settings_template_apply_for_current_list_sites']) ) {
                apbct_update_blogs_options($settings);
            }
        }
        if ( ! $apbct->white_label && ! is_main_site() && ! $apbct->allow_custom_key ) {
            $settings['apikey'] = '';
        }
    }

    // Alt sessions table clearing
    if ( $settings['data__set_cookies'] != 2 ) {
        \Cleantalk\ApbctWP\Variables\AltSessions::wipe();
    }

    // Set type of the alt cookies
    $settings['data__set_cookies__alt_sessions_type'] = $apbct->settings['data__set_cookies__alt_sessions_type'];
    if ( $apbct->settings['data__set_cookies'] != 2 && $settings['data__set_cookies'] == 2 ) {
        $alt_cookies_type = apbct_settings__get_alt_cookies_type();
        if ( $alt_cookies_type === false ) {
            // There is no available alt cookies types. Cookies will be disabled.
            $settings['data__set_cookies'] = 0;
        } else {
            $settings['data__set_cookies__alt_sessions_type'] = $alt_cookies_type;
        }
    }

    return $settings;
}

function apbct_settings__sync($direct_call = false)
{
    if ( ! $direct_call ) {
        check_ajax_referer('ct_secret_nonce');
    }

    global $apbct;

    //Clearing all errors
    $apbct->errorDeleteAll('and_save_data');

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
                'auto_update' => $apbct->data['auto_update'],
                'user_token'  => $apbct->data['user_token'],
                'service_id'  => $apbct->data['service_id'],
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
        $apbct->data['notice_auto_update'] = 0;

        // Other
        $apbct->data['service_id']      = 0;
        $apbct->data['valid']           = 0;
        $apbct->data['moderate']        = 0;
        $apbct->data['ip_license']      = 0;
        $apbct->data['moderate_ip']     = 0;
        $apbct->data['spam_count']      = 0;
        $apbct->data['auto_update']     = 0;
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

    die(json_encode($out));
}

function apbct_settings__get_key_auto($direct_call = false)
{
    if ( ! $direct_call ) {
        check_ajax_referer('ct_secret_nonce');
    }

    global $apbct;

    $website        = parse_url(get_option('siteurl'), PHP_URL_HOST) . parse_url(get_option('siteurl'), PHP_URL_PATH);
    $platform       = 'wordpress';
    $user_ip        = \Cleantalk\ApbctWP\Helper::ipGet('real', false);
    $timezone       = filter_input(INPUT_POST, 'ct_admin_timezone');
    $language       = apbct_get_server_variable('HTTP_ACCEPT_LANGUAGE');
    $wpms           = APBCT_WPMS && defined('SUBDOMAIN_INSTALL') && ! SUBDOMAIN_INSTALL ? true : false;
    $white_label    = $apbct->network_settings['multisite__white_label'] ? true : false;
    $hoster_api_key = $apbct->network_settings['multisite__hoster_api_key'];
    $admin_email    = $apbct->network_settings['multisite__work_mode'] == 1 ? get_site_option(
        'admin_email'
    ) : get_option('admin_email');
    if ( function_exists('is_multisite') && is_multisite() && $apbct->white_label ) {
        $admin_email = get_site_option('admin_email');
    }
    $result = \Cleantalk\ApbctWP\API::methodGetApiKey(
        'antispam',
        $admin_email,
        $website,
        $platform,
        $timezone,
        $language,
        $user_ip,
        $wpms,
        $white_label,
        $hoster_api_key
    );

    if ( empty($result['error']) ) {
        if ( isset($result['user_token']) ) {
            $apbct->data['user_token'] = $result['user_token'];
        }

        if ( ! empty($result['auth_key']) ) {
            // @ToDo we have to sanitize only api key. Not need to sanitize every settings here.
            $settings                  = apbct_settings__validate(array(
                'apikey' => $result['auth_key'],
            ));
            $apbct->settings['apikey'] = $settings['apikey'];
        }

        $templates = \Cleantalk\ApbctWP\CleantalkSettingsTemplates::getOptionsTemplate($result['auth_key']);

        if ( ! empty($templates) ) {
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
    } else {
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
        );
    }

    $apbct->saveSettings();
    $apbct->saveData();

    if ( $direct_call ) {
        return $result;
    } else {
        die(json_encode($out));
    }
}

function apbct_update_blogs_options($settings)
{
    global $wpdb;

    $blog_names = $settings['multisite__use_settings_template_apply_for_current_list_sites'] ?: array();

    $wp_blogs = $wpdb->get_results('SELECT blog_id, site_id FROM ' . $wpdb->blogs, OBJECT_K);

    foreach ( $wp_blogs as $blog ) {
        $blog_name = get_blog_details(array('blog_id' => $blog->blog_id))->blogname;
        if ( in_array($blog_name, $blog_names) ) {
            update_blog_option($blog->blog_id, 'cleantalk_settings', $settings);
        }
    }
}

/**
 * Sanitize and validate exclusions.
 * Explode given string by commas and trim each string.
 * Skip element if it's empty.
 *
 * Return false if exclusion is bad
 * Return sanitized string if all is ok
 *
 * @param string $exclusions
 * @param bool $regexp
 *
 * @return bool|string
 */
function apbct_settings__sanitize__exclusions($exclusions, $regexp = false)
{
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
        foreach ( $exclusions as $exclusion ) {
            $sanitized_exclusion = trim($exclusion, " \t\n\r\0\x0B/\/");
            if ( ! empty($sanitized_exclusion) ) {
                if ( $regexp && ! apbct_is_regexp($exclusion) ) {
                    return false;
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

function apbct_settings_show_gdpr_text($print = false)
{
    $out = wpautop('The notice requirements remain and are expanded. They must include the retention time for personal data, and contact information for data controller and data protection officer has to be provided.
	Automated individual decision-making, including profiling (Article 22) is contestable, similarly to the Data Protection Directive (Article 15). Citizens have rights to question and fight significant decisions that affect them that have been made on a solely-algorithmic basis. Many media outlets have commented on the introduction of a "right to explanation" of algorithmic decisions, but legal scholars have since argued that the existence of such a right is highly unclear without judicial tests and is limited at best.
	To be able to demonstrate compliance with the GDPR, the data controller should implement measures, which meet the principles of data protection by design and data protection by default. Privacy by design and by default (Article 25) require data protection measures to be designed into the development of business processes for products and services. Such measures include pseudonymising personal data, by the controller, as soon as possible (Recital 78).
	It is the responsibility and the liability of the data controller to implement effective measures and be able to demonstrate the compliance of processing activities even if the processing is carried out by a data processor on behalf of the controller (Recital 74).
	Data Protection Impact Assessments (Article 35) have to be conducted when specific risks occur to the rights and freedoms of data subjects. Risk assessment and mitigation is required and prior approval of the national data protection authorities (DPAs) is required for high risks. Data protection officers (Articles 37–39) are required to ensure compliance within organisations.
	They have to be appointed:')
    . '<ul style="padding: 0px 25px; list-style: disc;">'
    . '<li>for all public authorities, except for courts acting in their judicial capacity</li>'
    . '<li>if the core activities of the controller or the processor are:</li>'
    . '<ul style="padding: 0px 25px; list-style: disc;">'
    . '<li>processing operations, which, by virtue of their nature, their scope and/or their purposes, require regular and systematic monitoring of data subjects on a large scale</li>'
    . '<li>processing on a large scale of special categories of data pursuant to Article 9 and personal data relating to criminal convictions and offences referred to in Article 10;</li>'
    . '</ul>'
    . '</li>'
    . '</ul>';

    if ( $print ) {
        echo $out;
    } else {
        return $out;
    }
}

function apbct_settings__get__long_description()
{
    check_ajax_referer('ct_secret_nonce');

    $setting_id = $_POST['setting_id'] ?: '';

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
        'multisite__hoster_api_key' => array(
            'title' => __('Hoster API key', 'cleantalk-spam-protect'),
            'desc'  => __(
                'You could find it here:<br><a href ="https://cleantalk-screenshots.s3.amazonaws.com/help/hosting-antispam/hapi-ru.png"><img src="https://cleantalk-screenshots.s3.amazonaws.com/help/hosting-antispam/hapi-ru.png"></a><br>Press on the screenshot to zoom.',
                'cleantalk-spam-protect'
            )
        ),
    );

    die(json_encode($descriptions[$setting_id]));
}

function apbct_settings__check_renew_banner()
{
    global $apbct;

    check_ajax_referer('ct_secret_nonce');

    die(
        json_encode(
            array('close_renew_banner' => ($apbct->data['notice_trial'] == 0 && $apbct->data['notice_renew'] == 0) ? true : false)
        )
    );
}

/**
 * Checking availability of the handlers and return alt cookies type
 *
 * @return int|false
 */
function apbct_settings__get_alt_cookies_type()
{
    // Check custom ajax availability
    $res_custom_ajax = Helper::httpRequestGetResponseCode(esc_url(APBCT_URL_PATH . '/lib/Cleantalk/ApbctWP/Ajax.php'));
    if ( $res_custom_ajax == 400 ) {
        return 1;
    }

    // Check rest availability
    $res_rest = Helper::httpRequestGetResponseCode(esc_url(apbct_get_rest_url()));
    if ( $res_rest == 200 ) {
        return 0;
    }

    // Check WP ajax availability
    $res_ajax = Helper::httpRequestGetResponseCode(admin_url('admin-ajax.php'));
    if ( $res_ajax == 400 ) {
        return 2;
    }

    return false;
}

function apbct_settings__check_alt_cookies_types()
{
    global $apbct;

    switch ( $apbct->settings['data__set_cookies__alt_sessions_type'] ) {
        case '0':
            $alt_cookies_type = esc_html__('REST API', 'cleantalk-spam-protect');
            break;
        case '1':
            $alt_cookies_type = esc_html__('CleanTalk AJAX handler', 'cleantalk-spam-protect');
            break;
        case '2':
            $alt_cookies_type = esc_html__('WP AJAX handler', 'cleantalk-spam-protect');
            break;
        default:
            $alt_cookies_type = esc_html__('UNKNOWN', 'cleantalk-spam-protect');
            break;
    }

    echo '<div class="apbct_settings-field_wrapper apbct_settings-field_wrapper--sub">';
    echo sprintf(
        esc_html__('Alternative cookies type was set on %s', 'cleantalk-spam-protect'),
        '<strong>' . $alt_cookies_type . '</strong><br>'
    );

    echo '</div>';
}
