<?php

use Cleantalk\Antispam\Cleantalk;
use Cleantalk\Antispam\CleantalkRequest;
use Cleantalk\ApbctWP\AdjustToEnvironmentModule\AdjustToEnvironmentHandler;
use Cleantalk\ApbctWP\AJAXService;
use Cleantalk\ApbctWP\Antispam\EmailEncoder;
use Cleantalk\ApbctWP\ApbctEnqueue;
use Cleantalk\ApbctWP\CleantalkSettingsTemplates;
use Cleantalk\ApbctWP\Escape;
use Cleantalk\ApbctWP\Variables\Get;
use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\ApbctWP\Variables\Server;
use Cleantalk\ApbctWP\LinkConstructor;
use Cleantalk\Common\TT;

require_once('cleantalk-settings.php');

// Add buttons to comments list table
add_action('manage_comments_nav', 'apbct_add_buttons_to_comments_and_users', 10, 1);
add_action('manage_users_extra_tablenav', 'apbct_add_buttons_to_comments_and_users', 10, 1);

// Check renew banner
add_action('wp_ajax_apbct_settings__check_renew_banner', 'apbct_settings__check_renew_banner');

// Crunch for Anti-Bot
add_action('admin_head', 'apbct_admin_set_cookie_for_anti_bot');

// Catch comment status change
add_action('comment_approved_to_unapproved', 'apbct_comment__remove_meta_approved', 10, 1);
add_action('comment_spam_to_unapproved', 'apbct_comment__remove_meta_approved', 10, 1);
add_action('comment_trash_to_unapproved', 'apbct_comment__remove_meta_approved', 10, 1);

/**
 * Crunch for Anti-Bot
 * Hooked by 'admin_head'
 */
function apbct_admin_set_cookie_for_anti_bot()
{
    global $apbct;

    if ( $apbct->data['key_is_ok'] ) {
        echo
            '<script ' . (class_exists('Cookiebot_WP') ? 'data-cookieconsent="ignore"' : '') . '>
                var ctSecure = location.protocol === "https:" ? "; secure" : "";
                document.cookie = "wordpress_apbct_antibot=' . hash('sha256', $apbct->api_key . $apbct->data['salt']) . '; path=/; expires=0; samesite=lax" + ctSecure;
            </script>';
    }
}


/**
 * Add buttons to comments list table
 * Hooked by 'manage_comments_nav' and 'manage_users_extra_tablenav'
 *
 * @param $_unused_argument
 */
function apbct_add_buttons_to_comments_and_users($_unused_argument)
{
    global $apbct;

    if ( is_null($current_screen = get_current_screen()) ) {
        return;
    }

    if ( 'users' === $current_screen->base ) {
        $button_url__check  = $current_screen->base . '.php?page=ct_check_users';
        $button_description = 'users';
    } elseif ( 'edit-comments' === $current_screen->base ) {
        $button_url__check  = $current_screen->base . '.php?page=ct_check_spam';
        $button_description = 'comments';
    } else {
        return;
    }

    echo '
    <a href="' . Escape::escAttr($button_url__check) . '" class="button" style="margin:1px 0 0 0; display: inline-block;">
        <img src="' . Escape::escUrl($apbct->logo__small__colored) . '" alt="CleanTalk Anti-Spam logo"  height="" style="width: 17px; vertical-align: text-bottom;" />
        ' . sprintf(__('Find spam %s', 'cleantalk-spam-protect'), $button_description) . '
    </a>
    ';
}

/**
 * Adding widget
 * Hooked by 'wp_dashboard_setup'
 *
 * @psalm-suppress UndefinedFunction
 */
function ct_dashboard_statistics_widget()
{
    global $apbct;

    $actual_plugin_name = $apbct->plugin_name;
    if (isset($apbct->data['wl_brandname']) && $apbct->data['wl_brandname'] !== APBCT_NAME) {
        $actual_plugin_name = $apbct->data['wl_brandname'];
    }
    /**
     * Hook. List of allowed user roles for the Dashboard widget.
     * add_filter('apbct_hook_dashboard_widget_allowed_roles_list', function($roles_list) {
     *  $roles_list[] = 'editor';
     *  return $roles_list;
     * });
     */
    $roles_list = apply_filters('apbct_hook_dashboard_widget_allowed_roles_list', array('administrator'));

    if (is_array($roles_list) && apbct_is_user_role_in($roles_list) ) {
        wp_add_dashboard_widget(
            'ct_dashboard_statistics_widget',
            $actual_plugin_name,
            'ct_dashboard_statistics_widget_output'
        );
    }
}

/**
 * Outputs statistics widget content
 *
 * @param $_post
 * @param $_callback_args
 */
function ct_dashboard_statistics_widget_output($_post, $_callback_args)
{
    global $apbct, $current_user;

    $actual_plugin_name = $apbct->plugin_name;
    if (isset($apbct->data['wl_brandname']) && $apbct->data['wl_brandname'] !== APBCT_NAME) {
        $actual_plugin_name = $apbct->data['wl_brandname'];
    }

    echo "<div id='ct_widget_wrapper'>";
    ?>
    <div class='ct_widget_top_links'>
        <img src="<?php echo Escape::escUrl(APBCT_IMG_ASSETS_PATH . '/preloader.gif'); ?>" class='ct_preloader'>
        <?php
        echo sprintf(
            __("%sRefresh%s", 'cleantalk-spam-protect'),
            "<a href='#ct_widget' class='ct_widget_refresh_link'>",
            "</a>"
        ); ?>
        <?php
        echo sprintf(
            __("%sConfigure%s", 'cleantalk-spam-protect'),
            "<a href='{$apbct->settings_link}' class='ct_widget_settings_link'>",
            "</a>"
        ); ?>
    </div>
    <form id='ct_refresh_form' method='POST' action='#ct_widget'>
        <input type='hidden' name='ct_brief_refresh' value='1'>
    </form>
    <h4 class='ct_widget_block_header' style='margin-left: 12px;'><?php
        _e('7 days Anti-Spam and SpamFireWall stats', 'cleantalk-spam-protect'); ?></h4>
    <div class='ct_widget_block ct_widget_chart_wrapper'>
        <canvas id='ct_widget_chart' ></canvas>
    </div>
    <h4 class='ct_widget_block_header'><?php
        _e('Top 5 spam IPs blocked', 'cleantalk-spam-protect'); ?></h4>
    <hr class='ct_widget_hr'>
    <?php
    if (
            ! apbct_api_key__is_correct() ||
            (isset($apbct->data['brief_data']['error_no']) && $apbct->data['brief_data']['error_no'] == 6)
    ) {
        ?>
        <div class='ct_widget_block'>
            <form action='<?php
            echo $apbct->settings_link; ?>' method='POST'>
                <h2 class='ct_widget_activate_header'><?php
                    _e('Get Access key to activate Anti-Spam protection!', 'cleantalk-spam-protect'); ?></h2>
                <input class='ct_widget_button ct_widget_activate_button' type='submit' name='get_apikey_auto'
                       value='ACTIVATE'/>
            </form>
        </div>
        <?php
    } elseif ( ! empty($apbct->data['brief_data']['error']) ) {
        echo '<div class="ct_widget_block">'
             . '<h2 class="ct_widget_activate_header">'
             . sprintf(
                 __('Something went wrong! Error: "%s".', 'cleantalk-spam-protect'),
                 "<u>{$apbct->brief_data['error']}</u>"
             )
             . '</h2>';
        if (apbct_is_user_role_in(array('administrator')) && $apbct->user_token && ! $apbct->white_label ) {
            $link = LinkConstructor::buildCleanTalkLink(
                'anti_crawler_inactive',
                'my',
                array(
                    'user_token' => $apbct->user_token,
                    'cp_mode' => 'antispam'
                )
            );
            echo '<h2 class="ct_widget_activate_header">'
                 . __('Please, visit your Dashboard.', 'cleantalk-spam-protect')
                 . '</h2>'
                 . '<a target="_blank" href="' . $link . '">'
                 . '<input class="ct_widget_button ct_widget_activate_button ct_widget_resolve_button" type="button" value="VISIT CONTROL PANEL">'
                 . '</a>';
        }
        echo '</div>';
    }

    if ( apbct_api_key__is_correct() && empty($apbct->data['brief_data']['error']) ) {
        ?>
        <div class='ct_widget_block'>
            <table cellspacing="0">
                <tr>
                    <th><?php
                        _e('IP', 'cleantalk-spam-protect'); ?></th>
                    <th><?php
                        _e('Country', 'cleantalk-spam-protect'); ?></th>
                    <th><?php
                        _e('Block Count', 'cleantalk-spam-protect'); ?></th>
                </tr>
                <?php
                foreach ( $apbct->brief_data['top5_spam_ip'] as $val ) { ?>
                    <tr>
                        <td><?php
                            echo Escape::escHtml($val[0]); ?></td>

                        <td class="ct_widget_block__country_cell">
                            <?php
                            echo $val[1]
                                ? "<img src='" . Escape::escHtml(APBCT_URL_PATH) . "/inc/images/flags/countries_collection.svg#" . strtolower(isset($val[1]['country_code']) ? Escape::escHtml($val[1]['country_code']) : 'xx') . "'>"
                                : ''; ?>
                            <?php
                            echo isset($val[1]['country_name']) ? Escape::escHtml($val[1]['country_name']) : 'Unknown'; ?>
                        </td>

                        <td style='text-align: center;'><?php
                            echo Escape::escHtml($val[2]); ?></td>
                    </tr>
                    <?php
                } ?>
            </table>
            <?php
            if (apbct_is_user_role_in(array('administrator')) && $apbct->user_token && ! $apbct->data["wl_mode_enabled"] ) {
                $link = LinkConstructor::buildCleanTalkLink(
                    'dashboard_widget_all_data_link',
                    'my/show_requests',
                    array(
                        'user_token' => Escape::escHtml($apbct->user_token)
                    )
                );
                ?>
                <a target='_blank' href='<?php echo $link; ?>'>
                    <input class='ct_widget_button' id='ct_widget_button_view_all' type='button' value='View all'>
                </a>
                <?php
            } ?>
        </div>

        <?php
    }
    // Notice at the bottom
    if ( $apbct->spam_count && $apbct->spam_count > 0 ) {
        $cp_total_stats = '';
        //Link to CP is only for admins due the token provided
        if ( apbct_is_user_role_in(array('administrator')) ) {
            $link = LinkConstructor::buildCleanTalkLink(
                'dashboard_widget_go_to_cp',
                'my',
                array(
                    'user_token' => $apbct->user_token,
                    'cp_mode'    => 'antispam'
                )
            );
            $cp_total_stats =
                ($apbct->data["wl_mode_enabled"] ? '' : '<img src="' . Escape::escUrl($apbct->logo__small__colored) . '" class="ct_widget_small_logo"/>')
                . '<span title="'
                . sprintf(
                    __(
                        'This is the count from the %s\'s cloud and could be different to admin bar counters',
                        'cleantalk-spam-protect'
                    ) . '">',
                    $actual_plugin_name
                )
                . sprintf(
                /* translators: %s: Number of spam messages */
                    __(
                        '%s%s%s has blocked %s spam for past year. The statistics are automatically updated every 24 hours.',
                        'cleantalk-spam-protect'
                    ),
                    ! $apbct->data["wl_mode_enabled"] ? '<a href="' . $link . '" target="_blank">' : '',
                    $actual_plugin_name,
                    ! $apbct->data["wl_mode_enabled"] ? '</a>' : '',
                    number_format($apbct->data['spam_count'], 0, ',', ' ')
                )
                . '</span>';
        }
        echo '<div class="ct_widget_wprapper_total_blocked">'
             . $cp_total_stats
             . (! $apbct->white_label && ! $apbct->data["wl_mode_enabled"]
                ? '<br><br>'
                  . '<b style="font-size: 16px;">'
                  . sprintf(
                      __('Do you like CleanTalk? %sPost your feedback here%s.', 'cleantalk-spam-protect'),
                      '<u><a href="https://wordpress.org/support/plugin/cleantalk-spam-protect/reviews/#new-post" target="_blank">',
                      '</a></u>'
                  )
                  . '</b>'
                : ''
             )
             . '</div>';
    }
    echo '</div>';
}

/**
 * Admin action 'admin_init' - Add the admin settings and such
 *
 * @psalm-suppress UndefinedFunction
 */
function apbct_admin__init()
{
    global $apbct, $spbc;

    // TODO: need to find another way to be compatible with WP Rocket and WPEngine
    if (defined('WP_ROCKET_VERSION') &&
        Server::get('IS_WPE') &&
        strpos(TT::toString(Server::get('REQUEST_URI')), 'wp-admin/admin-ajax.php') === false
    ) {
        ob_start(function ($buffer) {
            $pattern_admin_js = '/<script\s+type="rocketlazyloadscript"[^>]*cleantalk-admin\.min\.js[^>]*>/i';
            $pattern_checkusers_js = '/<script\s+type="rocketlazyloadscript"[^>]*cleantalk-users-checkspam\.min\.js[^>]*>/i';
            $pattern_checkspam_js = '/<script\s+type="rocketlazyloadscript"[^>]*cleantalk-comments-checkspam\.min\.js[^>]*>/i';

            $buffer = preg_replace($pattern_admin_js, '<script src="' . APBCT_JS_ASSETS_PATH . '/cleantalk-admin.min.js' .
                '?ver=' . APBCT_VERSION . '" id="ct_admin_common-js"></script>', $buffer);
            $buffer = preg_replace($pattern_checkusers_js, '<script src="' . APBCT_JS_ASSETS_PATH . '/cleantalk-users-checkspam.min.js' .
                '?ver=' . APBCT_VERSION . '" id="ct_check_users-js"></script>', $buffer);
            $buffer = preg_replace($pattern_checkspam_js, '<script src="' . APBCT_JS_ASSETS_PATH . '/cleantalk-comments-checkspam.min.js' .
                '?ver=' . APBCT_VERSION . '" id="ct_check_spam-js"></script>', $buffer);

            return $buffer;
        });
    }

    // Admin bar
    $apbct->admin_bar_enabled = $apbct->settings['admin_bar__show'] &&
                                current_user_can('activate_plugins');

    if ( $apbct->admin_bar_enabled ) {
        if (
            ! has_action('admin_bar_menu', 'apbct_admin__admin_bar__add_structure') &&
            ! has_action('admin_bar_menu', 'spbc_admin__admin_bar__add_structure')
        ) {
            add_action('admin_bar_menu', 'apbct_admin__admin_bar__add_structure', 999);
        }

        add_filter('cleantalk_admin_bar__parent_node__before', 'apbct_admin__admin_bar__prepare_counters');
        add_filter('cleantalk_admin_bar__add_icon_to_parent_node', 'apbct_admin__admin_bar__prepare_counters');
        // Temporary disable the icon
        //add_filter( 'cleantalk_admin_bar__parent_node__before', 'apbct_admin__admin_bar__add_parent_icon', 10, 1 );
        add_filter('cleantalk_admin_bar__parent_node__after', 'apbct_admin__admin_bar__add_counter', 10, 1);

        add_action('admin_bar_menu', 'apbct_admin__admin_bar__add_child_nodes', 1000);
        if ( ! $spbc ) {
            add_filter('admin_bar_menu', 'apbct_spbc_admin__admin_bar__add_child_nodes', 1001);
        }
    }

    // Getting dashboard widget statistics
    if ( Post::getInt('ct_brief_refresh') === 1 ) {
        cleantalk_get_brief_data($apbct->api_key);
    }

    // Getting Access key like a hoster. Only once!
    if (
            ! is_main_site() &&
            $apbct->white_label &&
            (empty($apbct->api_key) || $apbct->settings['apikey'] == $apbct->network_settings['apikey'])
    ) {
        $res = apbct_settings__get_key_auto(true);
        if ( isset($res['auth_key'], $res['user_token']) ) {
            $settings       = apbct_settings__validate(array(
                'apikey' => $res['auth_key'],
            ));
            $apbct->api_key = isset($settings['apikey']) ? $settings['apikey'] : null;
            $apbct->save('settings');
        }
    }

    // Settings
    add_action(
        'wp_ajax_apbct_settings__get__long_description',
        'apbct_settings__get__long_description'
    ); // Long description

    add_action('wp_ajax_apbct_sync', 'apbct_settings__sync');

    add_action('wp_ajax_apbct_get_key_auto', 'apbct_settings__get_key_auto');

    add_action('wp_ajax_apbct_update_account_email', 'apbct_settings__update_account_email');

    // Settings Templates
    if (
        ! $apbct->data['wl_mode_enabled'] &&
        ! is_multisite() ||
        is_main_site() ||
        ( ! is_main_site() && $apbct->network_settings['multisite__allow_custom_settings'])
    ) {
        new CleantalkSettingsTemplates($apbct->api_key);
    }

    // Check compatibility
    do_action('apbct__check_compatibility');
}

/**
 * Manage links in plugins list
 *
 * @param $links
 * @param $_file
 *
 * @return array
 */
function apbct_admin__plugin_action_links($links, $_file)
{
    global $apbct;

    $settings_link = '<a href="' . $apbct->settings_link . '">' . __('Settings') . '</a>';

    array_unshift($links, $settings_link); // before other links

    return $links;
}

/**
 * Change th plugin description on all plugins page.
 * @param $all_plugins
 * @return array
 */
function apbct_admin__change_plugin_description($all_plugins)
{
    global $apbct;
    if (
        $apbct->data["wl_mode_enabled"] &&
        isset($all_plugins['cleantalk-spam-protect/cleantalk.php']) &&
        $apbct->data["wl_antispam_description"]
    ) {
        $all_plugins['cleantalk-spam-protect/cleantalk.php']['Description'] = $apbct->data["wl_antispam_description"];
    }
    return $all_plugins;
}

/**
 * Manage links and plugins page
 *
 * @param $links
 * @param $file
 * @param $plugin_data
 *
 * @return array
 */
function apbct_admin__register_plugin_links($links, $file, $plugin_data)
{
    global $apbct;
    $plugin_name = $plugin_data['Name'] ?: APBCT_NAME;

    //Return if it's not our plugin
    if ( $file != $apbct->base_name ) {
        return $links;
    }

    $actual_plugin_name = $apbct->plugin_name;
    if (isset($apbct->data['wl_brandname']) && $apbct->data['wl_brandname'] !== APBCT_NAME) {
        $actual_plugin_name = $apbct->data['wl_brandname'] . "&nbsp; Anti-Spam";
    }

    if ( $apbct->white_label || $apbct->data["wl_mode_enabled"] ) {
        $links   = array_slice($links, 0, 1);
        if (isset($links[0])) {
            $links[0] .= "<script " . (class_exists('Cookiebot_WP') ? 'data-cookieconsent="ignore"' : '') . ">
            function changedPluginName(){
                jQuery('.plugin-title strong').each(function(i, item){
                if(jQuery(item).html() == '{$plugin_name}')
                    jQuery(item).html('{$actual_plugin_name}');
                });
            }
            changedPluginName();
            jQuery( document ).ajaxComplete(function() {
                changedPluginName();
            });
            </script>";
        }
    }

    if ( substr(get_locale(), 0, 2) != 'en' ) {
        $links[] = '<a class="ct_meta_links ct_translate_links" href="'
                   . sprintf(
                       'https://translate.wordpress.org/locale/%s/default/wp-plugins/cleantalk-spam-protect',
                       substr(get_locale(), 0, 2)
                   )
                   . '" target="_blank">'
                   . __('Translate', 'cleantalk-spam-protect')
                   . '</a>';
    }

    $links[] = '<a class="ct_meta_links" href="' . $apbct->settings_link . '" target="_blank">'
               . __('Start here', 'cleantalk-spam-protect') . '</a>';
    $links[] = '<a class="ct_meta_links ct_faq_links" href="' . $apbct->data['wl_support_faq'] . '" target="_blank">'
               . __('FAQ', 'cleantalk-spam-protect') . '</a>';
    $links[] = '<a class="ct_meta_links ct_support_links" href="' . $apbct->data['wl_support_url'] . '" target="_blank">'
               . __('Support', 'cleantalk-spam-protect') . '</a>';
    $trial   = apbct_admin__badge__get_premium('plugins_listing');
    if ( ! empty($trial) && !$apbct->data["wl_mode_enabled"]) {
        $links[] = $trial;
    }

    return $links;
}

/**
 * Admin action 'admin_enqueue_scripts' - Enqueue admin script of reloading admin page after needed AJAX events
 *
 * @param string $hook URL of hooked page
 */
function apbct_admin__enqueue_scripts($hook)
{
    global $apbct;

    // Scripts to all admin pages
    ApbctEnqueue::getInstance()->js('apbct-public--3--cleantalk-modal.js', array('jquery'));
    ApbctEnqueue::getInstance()->js('cleantalk-admin.js', array('apbct-public--3--cleantalk-modal-js', 'jquery'));
    ApbctEnqueue::getInstance()->css('cleantalk-admin.css');
    ApbctEnqueue::getInstance()->css('cleantalk-icons.css');
    ApbctEnqueue::getInstance()->css('cleantalk-email-decoder.css');

    $data = array(
        '_ajax_nonce'        => $apbct->ajax_service->getAdminNonce(),
        '_ajax_url'          => admin_url('admin-ajax.php', 'relative'),
        'plugin_name'        => $apbct->plugin_name,
        'logo'               => '<img src="' . Escape::escUrl($apbct->logo) . '" alt=""  height="" style="width: 17px; vertical-align: text-bottom;" />',
        'logo_small'         => '<img src="' . Escape::escUrl($apbct->logo__small) . '" alt=""  height="" style="width: 17px; vertical-align: text-bottom;" />',
        'logo_small_colored' => '<img src="' . Escape::escUrl($apbct->logo__small__colored) . '" alt=""  height="" style="width: 17px; vertical-align: text-bottom;" />',
        'notice_when_deleting_user_text' => esc_html__('Warning! Users are deleted without the possibility of restoring them, you can only restore them from a site backup.', 'cleantalk-spam-protect'),
        'apbctNoticeDismissSuccess'       => esc_html__('Thank you for the review! We strive to make our Anti-Spam plugin better every day.', 'cleantalk-spam-protect'),
        'apbctNoticeForceProtectionOn'       => esc_html__('This option affects the reflection of the page by checking the user and adds a cookie "apbct_force_protection_check", which serves as an indicator of successful or unsuccessful verification. If the check is successful, it will no longer run.', 'cleantalk-spam-protect'),
    );
    $data = array_merge($data, EmailEncoder::getLocalizationText());
    wp_localize_script('cleantalk-admin-js', 'ctAdminCommon', $data);

    /**
     * Hook. List of allowed user roles for the Dashboard widget.
     * add_filter('apbct_hook_dashboard_widget_allowed_roles_list', function($roles_list) {
     *  $roles_list[] = 'editor';
     *  return $roles_list;
     * });
     */
    $roles_list = apply_filters('apbct_hook_dashboard_widget_allowed_roles_list', array('administrator'));
    // DASHBOARD page JavaScript and CSS
    if (
        $hook == 'index.php' &&
        is_array($roles_list) && apbct_is_user_role_in($roles_list) &&
        $apbct->settings['wp__dashboard_widget__show'] &&
        ! $apbct->moderate_ip
    ) {
        // Enqueue widget scripts if the dashboard widget enabled and not IP license
        // Preparing widget data
        // Parsing brief data 'spam_stat' {"yyyy-mm-dd": spam_count, "yyyy-mm-dd": spam_count} to [["yyyy-mm-dd", "spam_count"], ["yyyy-mm-dd", "spam_count"]]
        $to_chart = array();

        // Crunch. Response contains error.
        if ( ! empty($apbct->data['brief_data']['error']) ) {
            $apbct->data['brief_data'] = array_merge($apbct->data['brief_data'], $apbct->default_data['brief_data']);
        }

        if ( isset($apbct->data['brief_data']['spam_stat']) && is_array($apbct->data['brief_data']['spam_stat']) ) {
            foreach ( $apbct->data['brief_data']['spam_stat'] as $key => $value ) {
                $to_chart[] = array($key, $value);
            }
            unset($key, $value);
        }
        sort($to_chart);

        //hardcode fix to prevent more than 8 elements
        if ( count($to_chart) > 8 ) {
            array_shift($to_chart);
        }

        ApbctEnqueue::getInstance()->css('cleantalk-dashboard-widget.css');
        $widget_chart_handler = ApbctEnqueue::getInstance()->js('cleantalk-dashboard-widget--chartjs.js', array('jquery'));
        $widget_handler = ApbctEnqueue::getInstance()->js('cleantalk-dashboard-widget.js', array($widget_chart_handler));
        wp_localize_script($widget_handler, 'apbctDashboardWidget', array(
            'data' => $to_chart,
        ));
    }

    // SETTINGS's page JavaScript and CSS
    if ( $hook == 'settings_page_cleantalk' ) {
        wp_enqueue_media();

        ApbctEnqueue::getInstance()->js('cleantalk-admin-settings-page.js');
        ApbctEnqueue::getInstance()->css('cleantalk-admin-settings-page.css');

        wp_localize_script('cleantalk-admin-settings-page-js', 'ctSettingsPage', array(
            'ct_subtitle' => $apbct->ip_license ? __('Hosting Anti-Spam', 'cleantalk-spam-protect') : '',
            'ip_license'  => $apbct->ip_license ? true : false,
            'key_changed' => ! empty($apbct->data['key_changed']),
            'key_is_ok'   => ! empty($apbct->key_is_ok) && !empty($apbct->settings['apikey'])
        ));

        ApbctEnqueue::getInstance()->js('apbct-public--3--cleantalk-modal.js');
    }

    // COMMENTS page JavaScript
    if ( $hook == 'edit-comments.php' ) {
        ApbctEnqueue::getInstance()->css('cleantalk-trp.css');
        ApbctEnqueue::getInstance()->js('apbct-public--7--trp.js');
        wp_localize_script(
            'apbct-public--7--trp-js',
            'ctTrpAdminLocalize',
            \Cleantalk\ApbctWP\CleantalkRealPerson::getLocalizingData()
        );
        ApbctEnqueue::getInstance()->js('cleantalk-comments-editscreen.js');
        $link = LinkConstructor::buildCleanTalkLink(
            'public_comments_page_go_to_cp',
            'my',
            array(
                'user_token' => $apbct->user_token,
                'cp_mode' => 'antispam'
            )
        );
        wp_localize_script('cleantalk-comments-editscreen-js', 'ctCommentsScreen', array(
            'ct_ajax_nonce'               => $apbct->ajax_service->getAdminNonce(),
            'spambutton_text'             => __("Find spam comments", 'cleantalk-spam-protect'),
            'ct_feedback_msg_whitelisted' => __("The sender has been whitelisted.", 'cleantalk-spam-protect'),
            'ct_feedback_msg_blacklisted' => __("The sender has been blacklisted.", 'cleantalk-spam-protect'),
            'ct_feedback_msg'             => sprintf(
                __("Feedback has been sent to %sCleanTalk Dashboard%s.", 'cleantalk-spam-protect'),
                $apbct->user_token ? "<a target='_blank' href='$link'>" : '',
                $apbct->user_token ? "</a>" : ''
            ) . ' ' . esc_html__('The service accepts feedback only for requests made no more than 7 or 45 days 
            (if the Extra package is activated) ago.', 'cleantalk-spam-protect'),
            'ct_show_check_links'         => (bool)$apbct->settings['comments__show_check_links'],
            'ct_img_src_new_tab'          => plugin_dir_url(__FILE__) . "images/new_window.gif",
        ));
    }

    // USERS page JavaScript
    if ( $hook == 'users.php' ) {
        ApbctEnqueue::getInstance()->css('cleantalk-icons.css');
        ApbctEnqueue::getInstance()->js('cleantalk-users-editscreen.js');
        wp_localize_script('cleantalk-users-editscreen-js', 'ctUsersScreen', array(
            'spambutton_text'     => __("Find spam-users", 'cleantalk-spam-protect'),
            'ct_show_check_links' => (bool)$apbct->settings['comments__show_check_links'],
            'ct_img_src_new_tab'  => plugin_dir_url(__FILE__) . "images/new_window.gif"
        ));
    }
}

/**
 * Premium badge layout.
 *
 * @param string $placement - where should the layout placed, prefix and utm marks depends on this
 *
 * @return string Escaped string
 */
function apbct_admin__badge__get_premium($placement = null)
{
    global $apbct;

    $out = '';
    $utm_preset = '';
    $prefix = '';

    $placements_available = array(
            'checkers' => array(
                'prefix' => __('Make it right!', 'cleantalk-spam-protect') . ' ',
                'utm_set' => 'renew_checkers'),
            'top_info' => array(
                'prefix' => __('Make it right!', 'cleantalk-spam-protect') . ' ',
                'utm_set' => 'renew_top_info'),
            'plugins_listing' => array(
                'prefix' => '',
                'utm_set' => 'renew_plugins_listing'),
    );

    if ( $apbct->license_trial == 1 && $apbct->user_token ) {
        if (!empty($placement) && isset($placements_available[$placement])) {
            $utm_preset = $placements_available[$placement]['utm_set'];
            $prefix = $placements_available[$placement]['prefix'];
        }
        $link_text = __('Get premium', 'cleantalk-spam-protect');
        $renew_link = LinkConstructor::buildRenewalLinkATag($apbct->user_token, $link_text, 1, $utm_preset);
        $out = $prefix . '<b style="display: inline-block; margin-top: 10px;">' . $renew_link . '</b>';
    }

    return Escape::escKsesPreset($out, 'apbct_get_premium_link');
}

/**
 * Adds structure to the admin bar.
 *
 * This function adds a common parent node to the admin bar for both APBCT and SPBCT products.
 * It also adds individual nodes for APBCT and SPBCT under the common parent node.
 *
 * @param WP_Admin_Bar $wp_admin_bar The admin bar object.
 * @global object $spbc The SPBCT object.
 * @global object $apbct The APBCT object.
 */
function apbct_admin__admin_bar__add_structure($wp_admin_bar)
{
    global $spbc, $apbct;

    //init preparing total counters for both products APBCT/SPBCT
    do_action('cleantalk_admin_bar__prepare_counters');

    // Adding common parent node
    /**
     * Adding common parent node for both products APBCT/SPBCT
     */
    $wp_admin_bar->add_node(array(
        'id' => 'cleantalk_admin_bar__parent_node',
        'title' =>
            apply_filters('cleantalk_admin_bar__add_icon_to_parent_node', '') . // @deprecated
            apply_filters('cleantalk_admin_bar__parent_node__before', '') .
            '<span class="cleantalk_admin_bar__title">' . $apbct->data["wl_brandname_short"] . '</span>' .
            apply_filters('cleantalk_admin_bar__parent_node__after', ''),
        'meta' => array('class' => 'cleantalk-admin_bar--list_wrapper'),
    ));

    /**
     * Adding APBCT bar node
     */

    $apbct_title_node = apbct__admin_bar__get_title_for_apbct($apbct);

    if ( $apbct_title_node ) {
        $wp_admin_bar->add_node($apbct_title_node);
    }

    /**
     * Adding SPBCT bar node
     */

    $spbc_title_node = apbct__admin_bar__get_title_for_spbc($spbc, $apbct->user_token, $apbct->white_label);

    if ( $spbc_title_node ) {
        $wp_admin_bar->add_node($spbc_title_node);
    }

    /**
     * Adding FAQ node
     */
    $faq_title_node = apbct__admin_bar__get_title_for_faq();

    if ( $faq_title_node ) {
        $wp_admin_bar->add_node($faq_title_node);
    }
}

/**
 * Gets the title for the APBCT admin bar node.
 *
 * This function constructs the title for the APBCT admin bar node based on various conditions.
 * The title includes a renewal link if the notice is set to show and either the trial notice or the renew notice is set.
 * An attention mark is added to the title if the notice is set to show.
 *
 * @param object $apbct The APBCT object.
 * @return array The node data for the APBCT admin bar node.
 */
function apbct__admin_bar__get_title_for_apbct($apbct)
{

    $node_data = array(
        'parent' => 'cleantalk_admin_bar__parent_node',
        'id' => 'apbct__parent_node',
        'title' => '',
    );

    $title = '<span><a>' . __('Anti-Spam', 'cleantalk-spam-protect') . '</a></span>';

    if (
        $apbct->notice_show && // needs to show notice
        (
            $apbct->notice_trial || // NPT trial flag
            $apbct->notice_renew // needs to renew
        ) &&
        (
            is_main_site() ||
            $apbct->network_settings['multisite__work_mode'] == 2
        ) // is single site or WPMS network mode 2
    ) {
        $link_text = __('Renew Anti-Spam', 'cleantalk-spam-protect');
        $renew_link = LinkConstructor::buildRenewalLinkATag($apbct->user_token, $link_text, 1, 'renew_admin_bar_apbct');
        $title = '<span>' . $renew_link . '</span>';
    }

    //show the attention mark in any case if the notice show gained
    $attention_mark = $apbct->notice_show ? '<i class="apbct-icon-attention-alt"></i>' : '';

    //construct the final title
    $node_data['title'] = '<div class="cleantalk-admin_bar__parent">' . $title . $attention_mark . '</div>';

    return $node_data;
}

/**
 * Gets the title for the SPBCT admin bar node.
 *
 * This function constructs the title for the SPBCT admin bar node based on various conditions.
 * The title includes a renewal link if the SPBCT object exists, a user token is provided, and the SPBCT trial is set.
 * An attention mark is added to the title if the SPBCT notice is set to show.
 *
 * @param object|null $spbc The SPBCT object. If not provided, defaults to null.
 * @param string $user_token The user token.
 * @param bool $is_apbct_wl_mode Indicates if the APBCT white label mode is enabled.
 * @return array|false The node data for the SPBCT admin bar node, or false if the SPBCT admin bar is not enabled or the APBCT white label mode is enabled.
 */
function apbct__admin_bar__get_title_for_spbc($spbc, $user_token, $is_apbct_wl_mode)
{
    $node_data = array(
        'parent' => 'cleantalk_admin_bar__parent_node',
        'id' => 'spbc__parent_node',
        'title' => '',
    );

    if (
        !$spbc ||
        !$user_token ||
        $spbc->trial !== 1
    ) {
        $node_data['title'] = '<a>' . __('Security', 'security-malware-firewall') . '</a>';
        return $node_data;
    }

    if ( !$spbc->admin_bar_enabled || $is_apbct_wl_mode ) {
        return false;
    }

    $link_text = __('Renew Security', 'cleantalk-spam-protect');
    $renew_link = LinkConstructor::buildRenewalLinkATag($user_token, $link_text, 4, 'renew_admin_bar_spbct');
    $spbc_title = '<span>' . $renew_link . '</span>';

    //show the attention mark in any case if the notice show gained
    $attention_mark = $spbc->notice_show ? '<i class="apbct-icon-attention-alt"></i>' : '';

    //construct the final title
    $node_data['title'] = '<div class="cleantalk-admin_bar__parent">' . $spbc_title . $attention_mark . '</div>';

    return $node_data;
}

function apbct__admin_bar__get_title_for_faq()
{
    $faq_link_url = LinkConstructor::buildCleanTalkLink('faq_admin_bar_apbct', 'help/introduction');
    $faq_link_layout = sprintf(
        '<a href="%s" target="_blank">%s</a>',
        $faq_link_url,
        esc_html__('Manuals and FAQ', 'cleantalk-spam-protect')
    );
    $title = '<div class="cleantalk-admin_bar__parent"><span>' . $faq_link_layout . '</span></div>';
    return array(
        'parent' => 'cleantalk_admin_bar__parent_node',
        'id' => 'faq__parent_node',
        'title' => $title,
    );
}

/**
 * Prepares properties for counters in $apbct
 * Handles counter reset
 *
 * @return void
 */
function apbct_admin__admin_bar__prepare_counters()
{
    global $apbct;

    //Reset or create user counter
    if ( ! empty(Get::get('ct_reset_user_counter')) ) {
        AJAXService::checkNonceRestrictingNonAdmins('security');
        $apbct->data['user_counter']['accepted'] = 0;
        $apbct->data['user_counter']['blocked']  = 0;
        $apbct->data['user_counter']['since']    = date('d M');
        $apbct->saveData();
    }
    //Reset or create all counters
    if ( ! empty(Get::get('ct_reset_all_counters')) ) {
        AJAXService::checkNonceRestrictingNonAdmins('security');
        $apbct->data['admin_bar__sfw_counter']      = array('all' => 0, 'blocked' => 0);
        $apbct->data['admin_bar__all_time_counter'] = array('accepted' => 0, 'blocked' => 0);
        $apbct->data['user_counter']                = array(
            'all'      => 0,
            'accepted' => 0,
            'blocked'  => 0,
            'since'    => date('d M')
        );
        $apbct->data['array_accepted']              = array();
        $apbct->data['array_blocked']               = array();
        $apbct->data['current_hour']                = '';
        $apbct->saveData();
    }

    $apbct->counter__sum = 0;

    $apbct->counter__user = array(
        'accepted' => $apbct->data['user_counter']['accepted'],
        'blocked'  => $apbct->data['user_counter']['blocked'],
        'all'      => $apbct->data['user_counter']['accepted'] + $apbct->data['user_counter']['blocked'],
        'since'    => $apbct->data['user_counter']['since']
    );
    $apbct->counter__sum  += $apbct->counter__user['all'];

    if ( $apbct->settings['admin_bar__all_time_counter'] ) {
        $apbct->counter__all_time = array(
            'accepted' => $apbct->data['admin_bar__all_time_counter']['accepted'],
            'blocked'  => $apbct->data['admin_bar__all_time_counter']['blocked'],
            'all'      => $apbct->data['admin_bar__all_time_counter']['accepted'] + $apbct->data['admin_bar__all_time_counter']['blocked']
        );
        $apbct->counter__sum      += $apbct->counter__all_time['all'];
    }

    if ( $apbct->settings['admin_bar__daily_counter'] ) {
        $apbct->counter__daily = array(
            'accepted' => array_sum($apbct->data['array_accepted']),
            'blocked'  => array_sum($apbct->data['array_blocked']),
            'all'      => array_sum($apbct->data['array_accepted']) + array_sum($apbct->data['array_blocked'])
        );
        $apbct->counter__sum   += $apbct->counter__daily['all'];
    }

    if ( $apbct->settings['admin_bar__sfw_counter'] && $apbct->settings['sfw__enabled'] ) {
        $apbct->counter__sfw = array(
            'all'     => $apbct->data['admin_bar__sfw_counter']['all'],
            'blocked' => $apbct->data['admin_bar__sfw_counter']['blocked']
        );
        $apbct->counter__sum += $apbct->counter__sfw['all'];
    }
}

function apbct_admin__admin_bar__add_parent_icon($icon)
{
    return $icon
           . '<img class="cleantalk_admin_bar__apbct_icon" src="' . Escape::escUrl(APBCT_URL_PATH . '/inc/images/logo.png') . '" alt="">&nbsp;';
}

function apbct_admin__admin_bar__add_counter($after)
{
    global $apbct;

    $counter__sum__layout = ($after ? ' / ' : '<div class="cleantalk_admin_bar__sum_counter">') .
                            '<span title="' . __(
                                'All Anti-Spam events',
                                'cleantalk-spam-protect'
                            ) . '">' . $apbct->counter__sum . '</span>' .
                            '</div>';

    return ($after ? substr($after, 0, -6) : $after)
           . $counter__sum__layout;
}

function apbct_admin__admin_bar__add_child_nodes($wp_admin_bar)
{
    global $apbct;

    $attention_mark = $apbct->notice_show ? '<i class="apbct-icon-attention-alt"></i>' : '';

    $wp_admin_bar->add_node(array(
        'parent' => 'apbct__parent_node',
        'id'     => 'apbct_admin_bar__counter_header',
        'title'  => __('Counters:', 'cleantalk-spam-protect'),
    ));

    // User's counter
    $wp_admin_bar->add_node(array(
        'parent' => 'apbct__parent_node',
        'id'     => 'apbct_admin_bar__counter__user',
        'title'  => '<a>'
                    . __('Since', 'cleantalk-spam-protect') . '&nbsp;' . $apbct->counter__user['since'] . ': '
                    . '<span style="color: green;">' . $apbct->counter__user['accepted'] . '</span> / '
                    . '<span style="color: red;">' . $apbct->counter__user['blocked'] . '</span>'
                    . '<i class="apbct-icon-help-circled" title="'
                    . __(
                        'Shows amount of alllowed and blocked requests since the date.',
                        'cleantalk-spam-protect'
                    ) . '"></i>'
                    . '</a>',
    ));

    // All-time counter
    if ( $apbct->settings['admin_bar__all_time_counter'] ) {
        $wp_admin_bar->add_node(array(
            'parent' => 'apbct__parent_node',
            'id'     => 'apbct_admin_bar__counter__all_time',
            'title'  => '<a>'
                        . '<span>'
                        . __('Since activation', 'cleantalk-spam-protect') . ': '
                        . '<span style="color: white;">' . $apbct->counter__all_time['all'] . '</span> / '
                        . '<span style="color: green;">' . $apbct->counter__all_time['accepted'] . '</span> / '
                        . '<span style="color: red;">' . $apbct->counter__all_time['blocked'] . '</span>'
                        . '</span>'
                        . '<i class="apbct-icon-help-circled" title="' . __(
                            'All / Allowed / Blocked submissions. The number of submissions is being counted since CleanTalk plugin installation.',
                            'cleantalk-spam-protect'
                        ) . '"></i>'
                        . '</a>',
        ));
    }

    // Daily counter
    if ( $apbct->settings['admin_bar__daily_counter'] ) {
        $wp_admin_bar->add_node(array(
            'parent' => 'apbct__parent_node',
            'id'     => 'apbct_admin_bar__counter__daily',
            'title'  => '<a>'
                        . '<span>'
                        . __('Day', 'cleantalk-spam-protect') . ': '
                        . '<span style="color: green;">' . $apbct->counter__daily['accepted'] . '</span> / '
                        . '<span style="color: red;">' . $apbct->counter__daily['blocked'] . '</span>'
                        . '</span>'
                        . '<i class="apbct-icon-help-circled" title="' . __(
                            'Allowed / Blocked submissions. The number of submissions for past 24 hours. ',
                            'cleantalk-spam-protect'
                        ) . '"></i>'
                        . '</a>',
        ));
    }

    // SFW counter
    if ( $apbct->settings['admin_bar__sfw_counter'] && $apbct->settings['sfw__enabled'] ) {
        $wp_admin_bar->add_node(array(
            'parent' => 'apbct__parent_node',
            'id'     => 'apbct_admin_bar__counter__sfw',
            'title'  => '<a>'
                        . '<span>'
                        . __('SpamFireWall', 'cleantalk-spam-protect') . ': '
                        . '<span style="color: white;">' . $apbct->counter__sfw['all'] . '</span> / '
                        . '<span style="color: red;">' . $apbct->counter__sfw['blocked'] . '</span>'
                        . '</span>'
                        . '<i class="apbct-icon-help-circled" title="' . __(
                            'All / Blocked events. Access attempts triggered by SpamFireWall counted since the last plugin activation.',
                            'cleantalk-spam-protect'
                        ) . '"></i>'
                        . '</a>',
        ));
    }

    // User counter reset.
    $wp_admin_bar->add_node(array(
        'parent' => 'apbct__parent_node',
        'id'     => 'ct_reset_counter',
        'title'  =>
            '<hr style="margin-top: 7px; border: 1px solid #888;">'
            . '<a href="?' . http_build_query(array_merge($_GET, array('ct_reset_user_counter' => 1, 'security' => $apbct->ajax_service->getAdminNonce())))
            . '" title="Reset your personal counter of submissions.">'
            . __('Reset first counter', 'cleantalk-spam-protect') . '</a>',
    ));

    // Reset ALL counter
    $wp_admin_bar->add_node(array(
        'parent' => 'apbct__parent_node',
        'id'     => 'ct_reset_counters_all',
        'title'  =>
            '<a href="?' . http_build_query(array_merge($_GET, array('ct_reset_all_counters' => 1, 'security' => $apbct->ajax_service->getAdminNonce())))
            . '" title="' . __('Reset all counters', 'cleantalk-spam-protect') . '">'
            . __('Reset all counters', 'cleantalk-spam-protect') . '</a>',
    ));

    // Counter separator
    if ( $apbct->counter__sum ) {
        $wp_admin_bar->add_node(array(
            'parent' => 'apbct__parent_node',
            'id'     => 'apbct_admin_bar__separator',
            'title'  => '<hr style="margin-top: 7px;" />',
            'meta'   => array('class' => 'cleantalk_admin_bar__separator')
        ));
    }

    $wp_admin_bar->add_node(array(
        'parent' => 'apbct__parent_node',
        'id'     => 'ct_settings_link',
        'title'  => '<a href="' . $apbct->settings_link . '">'
                    . __('Settings', 'cleantalk-spam-protect') . '</a>' . $attention_mark,
    ));

    // Add a child item to our parent item. Bulk checks.
    if ( ! is_network_admin() ) {
        $wp_admin_bar->add_node(
            array(
                'parent' => 'apbct__parent_node',
                'id'     => 'ct_settings_bulk_comments',
                'title'  => '<hr style="margin-top: 7px;" /><a href="edit-comments.php?page=ct_check_spam" title="'
                            . __('Bulk spam comments removal tool.', 'cleantalk-spam-protect') . '">'
                            . __('Check comments for spam', 'cleantalk-spam-protect') . '</a>',
            )
        );
    }

    // Add a child item to our parent item. Bulk checks.
    if ( ! is_network_admin() ) {
        $wp_admin_bar->add_node(
            array(
                'parent' => 'apbct__parent_node',
                'id'     => 'ct_settings_bulk_users',
                'title'  => '<a href="users.php?page=ct_check_users" title="Bulk spam users removal tool.">'
                            . __('Check users for spam', 'cleantalk-spam-protect') . '</a>',
            )
        );
    }

    // Add a child item to our parent item. Bulk checks.
    if ( ! is_network_admin() && apbct_is_plugin_active('woocommerce/woocommerce.php') ) {
        $wp_admin_bar->add_node(
            array(
                'parent' => 'apbct__parent_node',
                'id'     => 'ct_settings_bulk_orders',
                'title'  => '<a href="admin.php?page=apbct_wc_spam_orders" title="Bulk spam orders removal tool.">'
                            . __('WooCommerce spam orders', 'cleantalk-spam-protect') . '</a>',
            )
        );
    }

    // Support link
    $link_to_support = 'https://wordpress.org/support/plugin/cleantalk-spam-protect';
    if (!empty($apbct->data['wl_support_url'])) {
        $link_to_support = esc_url($apbct->data['wl_support_url']);
    }

    if ( ! $apbct->white_label || !empty($apbct->data['wl_support_url']) ) {
        $wp_admin_bar->add_node(
            array(
                'parent' => 'apbct__parent_node',
                'id'     => 'ct_admin_bar_support_link',
                'title'  => '<hr style="margin-top: 7px;" /><a target="_blank" href="' . $link_to_support . '">'
                            . __('Support', 'cleantalk-spam-protect') . '</a>',
            )
        );
    }
}

function apbct_spbc_admin__admin_bar__add_child_nodes($wp_admin_bar)
{
    // Installation link
    $wp_admin_bar->add_node(
        array(
            'parent' => 'spbc__parent_node',
            'id'     => 'apbct_admin_bar__install',
            'title'  => '<a target="_blank" href="plugin-install.php?s=Security%20and%20Malware%20scan%20by%20CleanTalk%20&tab=search">'
                        . __('Install Security by CleanTalk', 'cleantalk-spam-protect') . '</a>',
        )
    );

    $wp_admin_bar->add_node(array(
        'parent' => 'spbc__parent_node',
        'id'     => 'install_separator',
        'title'  => '<hr style="margin-top: 7px;" />',
        'meta'   => array('class' => 'cleantalk_admin_bar__separator')
    ));

    // Counter header
    $wp_admin_bar->add_node(array(
        'parent' => 'spbc__parent_node',
        'id'     => 'spbc_admin_bar__counter_header',
        'title'  => '<a>' . __('Counters:', 'security-malware-firewall') . '</a>',
        'meta'   => array('class' => 'cleantalk_admin_bar__blocked'),
    ));

    // Failed / success login attempts counter
    $wp_admin_bar->add_node(array(
        'parent' => 'spbc__parent_node',
        'id'     => 'spbc_admin_bar__counter__logins',
        'title'  => '<a>'
                    . '<span>' . __('Logins:', 'cleantalk-spam-protect') . '</span>&nbsp;'
                    . '<span style="color: white;">'
                    . '<b style="color: green;">' . 0 . '</b> / '
                    . '<b style="color: red;">' . 0 . '</b>'
                    . '</span>'
                    . '<i class="apbct-icon-help-circled" title="' . __(
                        'Blocked login attempts in the local database for past 24 hours.',
                        'cleantalk-spam-protect'
                    ) . '"></i>'
                    . '</a>',
        'meta'   => array('class' => 'cleantalk_admin_bar__blocked'),
    ));

    // Firewall blocked / allowed counter
    $wp_admin_bar->add_node(array(
        'parent' => 'spbc__parent_node',
        'id'     => 'spbc_admin_bar__counter__firewall',
        'title'  => '<a>'
                    . '<b>' . __('Security Firewall: ', 'cleantalk-spam-protect') . '</b>&nbsp;'
                    . '<b style="color: white;">'
                    . '<b style="color: green;">' . 0 . '</b> / '
                    . '<b style="color: red;">' . 0 . '</b>'
                    . '</b>'
                    . '<i class="apbct-icon-help-circled" title="' . __(
                        'Passed / Blocked requests by Security Firewall for past 24 hours.',
                        'cleantalk-spam-protect'
                    ) . '"></i>'
                    . '</a>',
        'meta'   => array('class' => 'cleantalk_admin_bar__blocked'),
    ));

    // Users online counter
    $wp_admin_bar->add_node(array(
        'parent' => 'spbc__parent_node',
        'id'     => 'spbc_admin_bar__counter__online',
        'title'  => '<a>'
                    . '<span>' . __('Users online:', 'cleantalk-spam-protect') . '</span>'
                    . '&nbsp;<b class="spbc-admin_bar--user_counter">' . 0 . '</b>'
                    . '<i class="apbct-icon-help-circled" title="' . __(
                        'Shows amount of currently logged in administrators. Updates each 10 seconds.',
                        'cleantalk-spam-protect'
                    ) . '"></i>'
                    . '</a>',
        'meta'   => array('class' => 'cleantalk_admin_bar__blocked'),
    ));

    // Counter separator
    $wp_admin_bar->add_node(array(
        'parent' => 'spbc__parent_node',
        'id'     => 'spbc_admin_bar__separator',
        'title'  => '<hr style="margin-top: 7px;" />',
        'meta'   => array('class' => 'cleantalk_admin_bar__separator')
    ));

    // Settings
    $wp_admin_bar->add_node(array(
        'parent' => 'spbc__parent_node',
        'id'     => 'spbc_admin_bar__settings_link',
        'title'  => '<a>' . __('Settings', 'cleantalk-spam-protect') . '</a>',
        'meta'   => array('class' => 'cleantalk_admin_bar__blocked'),
    ));

    // Scanner
    $wp_admin_bar->add_node(array(
        'parent' => 'spbc__parent_node',
        'id'     => 'spbc_admin_bar__scanner_link',
        'title'  => '<a style="display:inline">' . __('Scanner', 'cleantalk-spam-protect') . '</a>'
                    . '/'
                    . '<a style="display:inline">' . __('Start scan', 'cleantalk-spam-protect') . '</a>',
        'meta'   => array('class' => 'cleantalk_admin_bar__blocked'),
    ));

    // Support link
    $wp_admin_bar->add_node(array(
        'parent' => 'spbc__parent_node',
        'title'  => '<hr style="margin-top: 7px;" /><a>' . __('Support', 'cleantalk-spam-protect') . '</a>',
        'id'     => 'spbc_admin_bar__support_link',
        'meta'   => array('class' => 'cleantalk_admin_bar__blocked'),
    ));
}


/**
 * Unmark bad words
 *
 * @param string $message
 *
 * @return string Cleat comment
 */
function apbct_comment__unmark_red($message)
{
    $message = preg_replace("/\<font rel\=\"cleantalk\" color\=\"\#FF1000\"\>(\S+)\<\/font>/iu", '$1', $message);

    return $message;
}

/**
 * Ajax action feedback form comments page.
 *
 * @param null|int $comment_id
 * @param null|string $comment_status
 * @param bool $change_status
 * @param null|bool $direct_call
 */
function apbct_comment__send_feedback(
    $comment_id = null,
    $comment_status = null,
    $change_status = false,
    $direct_call = null
) {
    // For AJAX call
    if ( ! $direct_call ) {
        AJAXService::checkNonceRestrictingNonAdmins('security');
    }

    $comment_id     = Post::get('comment_id') ? Post::getInt('comment_id') : $comment_id;
    $comment_status = Post::get('comment_status') ? Post::getString('comment_status') : $comment_status;
    $change_status  = Post::get('change_status') ? Post::getBool('change_status') : $change_status;

    // If enter params is empty exit
    if ( ! $comment_id || ! $comment_status ) {
        die();
    }

    // $comment = get_comment($comment_id, 'ARRAY_A');
    $hash = get_comment_meta($comment_id, 'ct_hash', true);

    // If we can send the feedback
    if ( $hash ) {
        // Approving
        if ( $comment_status == '1' || $comment_status == 'approve' ) {
            $result = ct_send_feedback($hash . ":1");
            // $comment['comment_content'] = apbct_comment__unmark_red($comment['comment_content']);
            // wp_update_comment($comment);
            $result === true ? 1 : 0;
        }

        // Disapproving
        if ( $comment_status == 'spam' ) {
            $result = ct_send_feedback($hash . ":0");
            $result === true ? 1 : 0;
        }
    } else {
        $result = 'no_hash';
    }

    // Changing comment status(folder) if flag is set. spam || approve
    if ( $change_status !== false ) {
        wp_set_comment_status($comment_id, $comment_status);
    }

    if ( ! $direct_call ) {
        ! empty($result) ? die($result) : die(0);
    }
}

/**
 * Catch comment status change
 *
 * @param WP_Comment $comment Comment object
 *
 * @return void
 */
function apbct_comment__remove_meta_approved($comment)
{
    delete_comment_meta((int)$comment->comment_ID, 'ct_marked_as_approved');
}


/**
 * Ajax action feedback form user page.
 *
 * @param null $user_id
 * @param null $status
 * @param null $direct_call
 */
function apbct_user__send_feedback($user_id = null, $status = null, $direct_call = null)
{
    AJAXService::checkNonceRestrictingNonAdmins('security');

    if ( ! $direct_call ) {
        $user_id = Post::getInt('user_id');
        $status  = Post::getString('status', null, 'word');
    }

    $hash = isset($user_id) ? get_user_meta($user_id, 'ct_hash', true) : null;

    if ( $hash ) {
        if ( $status === 'approve' || $status === '1' ) {
            $result = ct_send_feedback($hash . ":1");
            $result = $result === true ? 1 : 0;
        }
        if ( $status === 'spam' || $status === 'disapprove' || $status === '0' ) {
            $result = ct_send_feedback($hash . ":0");
            $result = $result === true ? 1 : 0;
        }
    } else {
        $result = 'no_hash';
    }

    if ( ! $direct_call ) {
        ! empty($result) ? die($result) : die(0);
    }
}

/**
 * Send feedback when user deleted
 *
 * @param $user_id
 * @param null $_reassign
 *
 * @return null
 */
function apbct_user__delete__hook($user_id, $_reassign = null)
{
    $hash = get_user_meta($user_id, 'ct_hash', true);
    if ( $hash !== '' ) {
        ct_feedback($hash, 0);
    }
}

/**
 * Check compatibility action
 */
add_action('apbct__check_compatibility', 'apbct__check_compatibility_handler');
function apbct__check_compatibility_handler()
{
    new \Cleantalk\Common\Compatibility();
}

/**
 * Function for `wpmu_blogs_columns` filter-hook.
 *
 * @param string[] $sites_columns An array of displayed site columns.
 *
 * @return string[]
 */
function apbct__wpmu_blogs_columns_filter($sites_columns)
{
    $sites_columns['cleantalk_status'] = esc_html__('CleanTalk Status', 'cleantalk-spam-protect');

    return $sites_columns;
}

add_filter('wpmu_blogs_columns', 'apbct__wpmu_blogs_columns_filter');

/**
 * Function for `manage_posts_custom_column` action-hook.
 *
 * @param string $_column_name The name of the column to display.
 * @param int    $site_id     The current post ID.
 *
 * @return void
 */
function apbct__manage_sites_custom_column_action($column_name, $site_id)
{
    if ( $column_name !== 'cleantalk_status' ) {
        return;
    }

    $cleantalk_data = get_blog_option($site_id, 'cleantalk_data');
    $key_is_ok_text = esc_html__('The Access key is set and correct', 'cleantalk-spam-protect');
    $key_is_bad_text = esc_html__('The Access key is not set or is incorrect', 'cleantalk-spam-protect');
    $key_status_caption = '<span style="color: red"">' . $key_is_bad_text . '</span>';

    if (!$cleantalk_data) {
        return;
    }

    $key_is_ok = isset($cleantalk_data['key_is_ok']) ? $cleantalk_data['key_is_ok'] : false;

    if ($key_is_ok) {
        $key_status_caption = '<span style="color: green"">' . $key_is_ok_text . '</span>';
    }

    echo $key_status_caption;
}

add_action('manage_sites_custom_column', 'apbct__manage_sites_custom_column_action', 10, 2);

add_action('wp_ajax_apbct_action_adjust_change', 'apbct_action_adjust_change');
function apbct_action_adjust_change()
{
    AJAXService::checkAdminNonce();

    if (in_array(Post::get('adjust'), array_keys(AdjustToEnvironmentHandler::SET_OF_ADJUST))) {
        try {
            $adjust = Post::getString('adjust');
            $adjust_class = AdjustToEnvironmentHandler::SET_OF_ADJUST[$adjust];
            $adjust_handler = new AdjustToEnvironmentHandler();
            $adjust_handler->handleOne($adjust_class);
        } catch (Exception $exception) {
            error_log('CleanTalk adjusting action error: ' . $exception->getMessage());
        }
    }

    wp_send_json_success();
}

add_action('wp_ajax_apbct_action_adjust_reverse', 'apbct_action_adjust_reverse');
function apbct_action_adjust_reverse()
{
    AJAXService::checkAdminNonce();

    if (in_array(Post::getString('adjust'), array_keys(AdjustToEnvironmentHandler::SET_OF_ADJUST))) {
        $adjust = Post::getString('adjust');
        try {
            $adjust_class = AdjustToEnvironmentHandler::SET_OF_ADJUST[$adjust];
            $adjust_handler = new AdjustToEnvironmentHandler();
            $adjust_handler->reverseAdjust($adjust_class);
        } catch (Exception $exception) {
            error_log('CleanTalk adjusting reverse error: ' . $exception->getMessage());
        }
    }

    wp_send_json_success();
}
