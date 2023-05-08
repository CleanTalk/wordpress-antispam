<?php

use Cleantalk\ApbctWP\Escape;
use Cleantalk\ApbctWP\Localize\LocalizeHandler;
use Cleantalk\ApbctWP\Sanitize;
use Cleantalk\ApbctWP\Variables\Cookie;
use Cleantalk\ApbctWP\Variables\Get;
use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\ApbctWP\Variables\Request;
use Cleantalk\ApbctWP\Variables\Server;

/**
 * Init functions
 *
 * @throws Exception
 * @psalm-suppress UnusedVariable
 * @psalm-suppress RedundantCondition
 */
function apbct_init()
{
    global $ct_jp_comments, $apbct;

    // Pixel
    if ( $apbct->settings['data__pixel'] && empty($apbct->pixel_url) ) {
        $apbct->pixel_url = apbct_get_pixel_url__ajax(true);
    }

    // Localize data
    add_action('wp_head', array(LocalizeHandler::class, 'handle'), 1);
    add_action('login_head', array(LocalizeHandler::class, 'handle'), 1);

    //Search form hook init
    if ( $apbct->settings['forms__search_test'] ) {
        add_filter('get_search_form', 'apbct_form_search__add_fields', 999);
    }

    //fix for EPM registration form
    if ( Post::get('reg_email') && shortcode_exists('epm_registration_form') ) {
        unset($_POST['ct_checkjs_register_form']);
    }

    if ( Post::get('_wpnonce-et-pb-contact-form-submitted') !== '' ) {
        add_shortcode('et_pb_contact_form', 'ct_contact_form_validate');
    }

    if ( $apbct->settings['forms__check_external'] ) {
        // Fixing form and directs it this site
        if (
            $apbct->settings['forms__check_external__capture_buffer'] &&
            ! is_admin() &&
            ! apbct_is_ajax() &&
            ! apbct_is_post() &&
            apbct_is_user_enable() &&
            ! (defined('DOING_CRON') && DOING_CRON) &&
            ! (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST)
        ) {
            if (
                defined('CLEANTALK_CAPTURE_BUFFER_SPECIFIC_URL') &&
                is_string(CLEANTALK_CAPTURE_BUFFER_SPECIFIC_URL)
            ) {
                $catch_buffer = false;
                $urls         = explode(',', CLEANTALK_CAPTURE_BUFFER_SPECIFIC_URL);
                foreach ( $urls as $url ) {
                    if ( apbct_is_in_uri($url) ) {
                        $catch_buffer = true;
                    }
                }
            } else {
                $catch_buffer = true;
            }

            if ( $catch_buffer ) {
                add_action('wp', 'apbct_buffer__start');
                add_action('shutdown', 'apbct_buffer__end', 0);
                add_action('shutdown', 'apbct_buffer__output', 2);
            }
        }

        // Check and redirect
        if ( apbct_is_post()
             && Post::get('cleantalk_hidden_method') !== ''
             && Post::get('cleantalk_hidden_action') !== ''
        ) {
            $action = Escape::escHtml(Post::get('cleantalk_hidden_action'));
            $method = Escape::escHtml(Post::get('cleantalk_hidden_method'));
            unset($_POST['cleantalk_hidden_action']);
            unset($_POST['cleantalk_hidden_method']);
            ct_contact_form_validate();
            if ( ! apbct_is_ajax() ) {
                print "<html lang=''><body><form method='$method' action='$action'>";
                ct_print_form($_POST, '');
                print "</form></body></html>";
                print "<script " . (class_exists('Cookiebot_WP') ? 'data-cookieconsent="ignore"' : '') . ">
					if(document.forms[0].submit !== 'undefined'){
						var objects = document.getElementsByName('submit');
						if(objects.length > 0)
							document.forms[0].removeChild(objects[0]);
					}
					document.forms[0].submit();
				</script>";
                die();
            }
        }
    }

    if (
        Post::get('quform_ajax') &&
        Post::get('quform_csrf_token') &&
        Post::get('quform_form_id')
    ) {
        require_once(CLEANTALK_PLUGIN_DIR . 'inc/cleantalk-ajax.php');
        ct_ajax_hook();
    }

    /**hooks for cm answers pro */
    if ( defined('CMA_PLUGIN_FILE') ) {
        add_action('wp', 'ct_ajax_hook', 1);
    }

    /** VFB_Pro integration */
    if (
        ! empty($_POST) &&
        $apbct->settings['forms__contact_forms_test'] == 1 &&
        empty(Post::get('ct_checkjs_cf7')) &&
        apbct_is_plugin_active('vfb-pro/vfb-pro.php') &&
        ! empty(Post::get('_vfb-form-id'))
    ) {
        ct_contact_form_validate();
    }

    /** Optima Express integration */
    if (
        ! empty($_POST) &&
        $apbct->settings['forms__contact_forms_test'] == 1 &&
        empty(Post::get('ct_checkjs_cf7')) &&
        apbct_is_plugin_active('optima-express/iHomefinder.php') &&
        Post::get('actionType') === 'create' &&
        !empty(Post::get('newEmail'))
    ) {
        ct_contact_form_validate();
    }

    //hook for Anonymous Post
    if ( $apbct->settings['data__general_postdata_test'] == 1 && empty(Post::get('ct_checkjs_cf7')) ) {
        add_action('init', 'ct_contact_form_validate_postdata', 1000);
    }

    if ( $apbct->settings['forms__general_contact_forms_test'] == 1 && empty(Post::get('ct_checkjs_cf7')) && ! apbct_is_direct_trackback() ) {
        add_action('CMA_custom_post_type_nav', 'ct_contact_form_validate_postdata', 1);
        add_action('init', 'ct_contact_form_validate', 999);
        if (
            Post::get('reg_redirect_link') !== '' &&
            Post::get('tmpl_registration_nonce_field') !== ''
        ) {
            unset($_POST['ct_checkjs_register_form']);
            add_action('init', 'ct_contact_form_validate', 999);
        }
    }

    if ( $apbct->settings['data__general_postdata_test'] == 1 && empty(Post::get('ct_checkjs_cf7')) ) {
        add_action('CMA_custom_post_type_nav', 'ct_contact_form_validate_postdata', 1);
    }

    // Fast Secure contact form
    if ( defined('FSCF_VERSION') ) {
        add_filter('si_contact_display_after_fields', 'ct_si_contact_display_after_fields');
        add_filter('si_contact_form_validate', 'ct_si_contact_form_validate');
    }

    // WooCommerce registration
    if ( class_exists('WooCommerce') ) {
        if ( !$apbct->settings['forms__wc_register_from_order'] && Request::get('wc-ajax') === 'checkout' ) {
            remove_filter('woocommerce_registration_errors', 'ct_registration_errors', 1);
        } else {
            add_filter('woocommerce_registration_errors', 'ct_registration_errors', 1, 3);
        }
        if ( $apbct->settings['forms__wc_checkout_test'] == 1 ) {
            add_action('woocommerce_after_checkout_validation', 'ct_woocommerce_checkout_check', 1, 2);
            add_action('woocommerce_checkout_update_order_meta', 'apbct_woocommerce__add_request_id_to_order_meta');
        }

        //Woocommerce add_to_cart action
        add_action('woocommerce_add_to_cart', 'apbct_wc__add_to_cart_unlogged_user', 10, 6);
    }

    // WooCommerce whishlist
    if ( class_exists('WC_Wishlists_Wishlist') ) {
        add_filter('wc_wishlists_create_list_args', 'ct_woocommerce_wishlist_check', 1, 1);
    }


    // JetPack Contact form
    if ( defined('JETPACK__VERSION') ) {
        // Checking Jetpack contact form
        add_filter('contact_form_is_spam', 'ct_contact_form_is_spam');
        add_filter('jetpack_contact_form_is_spam', 'ct_contact_form_is_spam_jetpack', 50, 2);
        add_filter('grunion_contact_form_field_html', 'ct_grunion_contact_form_field_html', 10, 2);

        // Checking Jetpack comments form
        $jetpack_active_modules = get_option('jetpack_active_modules');
        if (
            class_exists('Jetpack', false) &&
            $jetpack_active_modules &&
            in_array('comments', $jetpack_active_modules)
        ) {
            $ct_jp_comments = true;
        }
    }

    // WP Maintenance Mode (wpms)
    add_action('wpmm_head', 'apbct_form__wpmm__addField', 1);

    // Contact Form7
    if ( defined('WPCF7_VERSION') ) {
        add_filter('wpcf7_posted_data', function ($posted_data) {
            if ( isset($posted_data['apbct_visible_fields']) ) {
                unset($posted_data['apbct_visible_fields']);
            }
            if ( isset($posted_data['apbct__email_id__wp_contact_form_7']) ) {
                unset($posted_data['apbct__email_id__wp_contact_form_7']);
            }
            return $posted_data;
        });
        add_filter('wpcf7_form_elements', 'apbct_form__contactForm7__addField');
        add_filter('wpcf7_validate', 'apbct_form__contactForm7__tesSpam__before_validate', 999, 2);
        $hook    = WPCF7_VERSION >= '3.0.0' ? 'wpcf7_spam' : 'wpcf7_acceptance';
        $num_arg = WPCF7_VERSION >= '5.3.0' ? 2 : 1;
        add_filter($hook, 'apbct_form__contactForm7__testSpam', 9999, $num_arg);
    }

    // BuddyPress
    if ( class_exists('BuddyPress') ) {
        add_action('bp_before_registration_submit_buttons', 'ct_register_form', 1);
        add_action('messages_message_before_save', 'apbct_integration__buddyPres__private_msg_check', 1);
        add_filter('bp_signup_validate', 'ct_registration_errors', 1);
        add_filter('bp_signup_validate', 'ct_check_registration_errors', 999999);
    }

    if ( defined('PROFILEPRESS_SYSTEM_FILE_PATH') ) {
        add_filter('pp_registration_validation', 'ct_registration_errors_ppress', 11, 2);
    }

    // bbPress
    if ( class_exists('bbPress') ) {
        add_filter('bbp_new_topic_pre_title', 'ct_bbp_get_topic', 1);
        add_filter('bbp_new_topic_pre_content', 'ct_bbp_new_pre_content', 1);
        add_filter('bbp_new_reply_pre_content', 'ct_bbp_new_pre_content', 1);
        add_action('bbp_theme_before_topic_form_content', 'ct_comment_form');
        add_action('bbp_theme_before_reply_form_content', 'ct_comment_form');
    }

    //Custom Contact Forms
    if ( defined('CCF_VERSION') ) {
        add_filter('ccf_field_validator', 'ct_ccf', 1, 4);
    }

    add_action('comment_form', 'ct_comment_form');

    // intercept WordPress Landing Pages POST
    if ( defined('LANDINGPAGES_CURRENT_VERSION') && ! empty($_POST) ) {
        if ( Post::get('action') === 'inbound_store_lead' ) { // AJAX action(s)
            ct_check_wplp();
        } elseif ( Post::get('inbound_submitted') === '1' ) {
            // Final submit
            ct_check_wplp();
        }
    }

    // S2member. intercept POST
    if ( defined('WS_PLUGIN__S2MEMBER_PRO_VERSION') ) {
        $post_keys = array_keys($_POST);
        foreach ( $post_keys as $post_key ) {
            // Detect POST keys like /s2member_pro.*registration/
            if ( strpos((string)$post_key, 's2member') !== false && strpos((string)$post_key, 'registration') !== false ) {
                ct_s2member_registration_test($post_key);
                break;
            }
        }
    }

    // New user approve hack
    // https://wordpress.org/plugins/new-user-approve/
    if ( ct_plugin_active('new-user-approve/new-user-approve.php') ) {
        add_action('register_post', 'ct_register_post', 1, 3);
    }

    // Wilcity theme registration validation fix
    add_filter(
        'wilcity/filter/wiloke-listing-tools/validate-before-insert-account',
        'apbct_wilcity_reg_validation',
        10,
        2
    );

    // Gravity forms
    if ( defined('GF_MIN_WP_VERSION') ) {
        add_filter('gform_get_form_filter', 'apbct_form__gravityForms__addField', 10, 2);
        add_filter('gform_entry_is_spam', 'apbct_form__gravityForms__testSpam', 999, 3);
        add_filter('gform_confirmation', 'apbct_form__gravityForms__showResponse', 999, 4);
    }

    //Pirate forms
    if (
        defined('PIRATE_FORMS_VERSION') &&
        Post::get('pirate-forms-contact-name') &&
        Post::get('pirate-forms-contact-email')
    ) {
        apbct_form__piratesForm__testSpam();
    }

    // QForms integration
    add_filter('quform_post_validate', 'ct_quform_post_validate', 10, 2);

    // Ultimate Members
    if ( class_exists('UM') ) {
        add_action('um_main_register_fields', 'ct_register_form', 100); // Add hidden fileds
        add_action('um_submit_form_register', 'apbct_registration__UltimateMembers__check', 9, 1); // Check submition
    }

    // Paid Memberships Pro integration
    add_filter('pmpro_required_user_fields', function ($pmpro_required_user_fields) {
        if (
            ! empty($pmpro_required_user_fields['username']) &&
            ! empty($pmpro_required_user_fields['bemail']) &&
            ! empty($pmpro_required_user_fields['bconfirmemail']) &&
            $pmpro_required_user_fields['bemail'] == $pmpro_required_user_fields['bconfirmemail']
        ) {
            $check = ct_test_registration(
                $pmpro_required_user_fields['username'],
                $pmpro_required_user_fields['bemail']
            );
            if ( $check['allow'] == 0 && function_exists('pmpro_setMessage') ) {
                pmpro_setMessage($check['comment'], 'pmpro_error');
            }
        }

        return $pmpro_required_user_fields;
    });

    // UsersWP plugin integration
    add_filter('uwp_validate_result', 'apbct_form__uwp_validate', 3, 10);

    //
    // Load JS code to website footer
    //
    if ( ! (defined('DOING_AJAX') && DOING_AJAX) ) {
        add_action('wp_head', 'apbct_hook__wp_head__set_cookie__ct_checkjs', 1);
        add_action('wp_footer', 'apbct_hook__wp_footer', 1);
    }

    if ( $apbct->settings['trusted_and_affiliate__footer'] === '1' ) {
        add_action('wp_footer', 'apbct_hook__wp_footer_trusted_text', 999);
    }

    if ( $apbct->settings['data__protect_logged_in'] != 1 && is_user_logged_in() ) {
        add_action('init', 'ct_contact_form_validate', 999);
    }

    if ( apbct_is_user_enable() ) {
        if ( $apbct->settings['forms__general_contact_forms_test'] == 1 && ! Post::get('comment_post_ID') && ! Get::get('for') && ! apbct_is_direct_trackback() ) {
            add_action('init', 'ct_contact_form_validate', 999);
        }
        if ( apbct_is_post() &&
             $apbct->settings['data__general_postdata_test'] == 1 &&
             ! Post::get('ct_checkjs_cf7') &&
             ! is_admin() &&
             ! apbct_is_user_role_in(array('administrator', 'moderator'))
        ) {
            add_action('init', 'ct_contact_form_validate_postdata', 1000);
        }
    }

    /**
     * Integration with custom forms
     */
    if ( ! empty($_POST) && apbct_custom_forms_trappings() ) {
        add_action('init', 'ct_contact_form_validate', 999);
    }

    /**
     * Internal Forms - Sendinblue Integration https://wordpress.org/plugins/mailin/
     */
    if (
        (int)$apbct->settings['forms__check_internal'] === 1
        && !empty($_POST)
        && Post::equal('sib_form_action', 'subscribe_form_submit')
        && apbct_is_plugin_active('mailin/sendinblue.php')
    ) {
        ct_contact_form_validate();
    }

    if ( $apbct->settings['trusted_and_affiliate__shortcode'] === '1' ) {
        add_shortcode('cleantalk_affiliate_link', 'apbct_trusted_text_shortcode_handler');
    }
}

function apbct_buffer__start()
{
    ob_start();
}

function apbct_buffer__end()
{
    if ( ! ob_get_level() ) {
        return;
    }

    global $apbct;
    $apbct->buffer = ob_get_contents();
    ob_end_clean();
}

/**
 * Outputs changed buffer
 *
 * @global $apbct
 */
function apbct_buffer__output()
{
    global $apbct;

    if ( empty($apbct->buffer) ) {
        return;
    }

    if ( apbct_is_plugin_active('flow-flow/flow-flow.php') || apbct_is_theme_active('epico') ) {
        $output = apbct_buffer_modify_by_string();
    } else {
        $output = apbct_buffer_modify_by_dom();
    }

    die($output);
}

function apbct_buffer_modify_by_string()
{
    global $apbct, $wp;

    $site_url   = get_option('home');
    $site__host = parse_url($site_url, PHP_URL_HOST);

    preg_match_all('/<form\s*.*>\s*.*<\/form>/', $apbct->buffer, $matches, PREG_SET_ORDER);

    if ( count($matches) > 0 ) {
        foreach ( $matches as $match ) {
            preg_match('/action="(\S*)"/', $match[0], $group_action);
            $action = count($group_action) > 0 ? $group_action[1] : $site_url;

            $action__host = parse_url($action, PHP_URL_HOST);
            if ( $action__host !== null && $site__host != $action__host ) {
                preg_match('/method="(\S*)"/', $match[0], $group_method);
                $method = count($group_method) > 0 ? $group_method[1] : 'get';

                $hidden_fields = '<input type="hidden" name="cleantalk_hidden_action" value="' . $action . '">';
                $hidden_fields .= '<input type="hidden" name="cleantalk_hidden_method" value="' . $method . '">';

                $modified_match = preg_replace(
                    '/action="\S*"/',
                    'action="' . home_url(add_query_arg(array(), $wp->request)) . '"',
                    $match[0]
                );
                $modified_match = preg_replace('/method="\S*"/', 'method="POST"', $modified_match);
                $modified_match = str_replace('</form>', $hidden_fields . '</form>', $modified_match);
                $apbct->buffer  = str_replace($match[0], $modified_match, $apbct->buffer);
            }
        }
    }

    return $apbct->buffer;
}

function apbct_buffer_modify_by_dom()
{
    global $apbct, $wp;

    $site_url   = get_option('home');
    $site__host = parse_url($site_url, PHP_URL_HOST);

    $dom = new DOMDocument();
    @$dom->loadHTML($apbct->buffer, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

    $forms = $dom->getElementsByTagName('form');

    foreach ( $forms as $form ) {
        $action       = $form->getAttribute('action');
        $action       = $action ?: $site_url;
        $action__host = parse_url($action, PHP_URL_HOST);

        // Check if the form directed to the third party site
        if ( $action__host !== null && $site__host != $action__host ) {
            $method = $form->getAttribute('method');
            $method = $method ?: 'get';
            // Directs form to our site
            $form->setAttribute('method', 'POST');
            $form->setAttribute('action', home_url(add_query_arg(array(), $wp->request)));

            // Add cleantalk_hidden_action
            $new_input = $dom->createElement('input');
            $new_input->setAttribute('type', 'hidden');
            $new_input->setAttribute('name', 'cleantalk_hidden_action');
            $new_input->setAttribute('value', $action);
            $form->appendChild($new_input);

            // Add cleantalk_hidden_method
            $new_input = $dom->createElement('input');
            $new_input->setAttribute('type', 'hidden');
            $new_input->setAttribute('name', 'cleantalk_hidden_method');
            $new_input->setAttribute('value', $method);
            $form->appendChild($new_input);
        }
    }
    unset($form);

    $html = $dom->getElementsByTagName('html');

    return is_object($html) && isset($html[0], $html[0]->childNodes[0]) && $dom->getElementsByTagName('rss')->length == 0
        ? $dom->saveHTML()
        : $apbct->buffer;
}

/**
 * Adds cookie script filed to head
 */
function apbct_hook__wp_head__set_cookie__ct_checkjs()
{
    ct_add_hidden_fields('ct_checkjs', false, true, true);

    return null;
}

/**
 * Adds check_js script to the footer
 * @psalm-suppress UnusedVariable
 */
function apbct_hook__wp_footer()
{
    global $apbct;

    # Return false if page is excluded
    if ( apbct_exclusions_check__url() ) {
        return;
    }

    // Pixel
    if (
        $apbct->settings['data__pixel'] === '1' ||
        ($apbct->settings['data__pixel'] === '3' && ! apbct_is_cache_plugins_exists())
    ) {
        echo '<img alt="Cleantalk Pixel" id="apbct_pixel" style="display: none;" src="' . Escape::escUrl($apbct->pixel_url) . '">';
    }

    if ( $apbct->settings['data__use_ajax'] ) {
        $timeout = $apbct->settings['misc__async_js'] ? 1000 : 0;

        if ( $apbct->data['ajax_type'] == 'rest' ) {
            $send_way_asset = "apbct_public_sendREST(
                                    'js_keys__get',
                                    { callback: apbct_js_keys__set_input_value }";
        } else {
            $send_way_asset = "apbct_public_sendAJAX(	
                                    { action: 'apbct_js_keys__get' },	
                                    { callback: apbct_js_keys__set_input_value }";
        }

        $cookie_bot_asset = (class_exists('Cookiebot_WP')) ? 'data-cookieconsent="ignore"' : '';

        $script =
            '<script ' . $cookie_bot_asset
            . ">				
                    document.addEventListener('DOMContentLoaded', function () {
                        setTimeout(function(){
                            if( document.querySelectorAll('[name^=ct_checkjs]').length > 0 ) {
                                " . $send_way_asset . "
                                )
                            }
                        }," . $timeout . ")					    
                    })				
                </script>";

        echo Escape::escKses(
            $script,
            array(
                'script' => array(
                    'type' => true,
                    'data-cookieconsent' => true
                )
            )
        );
    }
}

/**
 * Adds hidden filed to define availability of client's JavaScript
 *
 * @param string $field_name
 * @param bool $return_string
 * @param bool $cookie_check
 * @param bool $no_print
 * @param bool $ajax
 *
 * @return array|false|string|string[]|void
 *
 * @psalm-suppress UnusedVariable
 */
function ct_add_hidden_fields(
    $field_name = 'ct_checkjs',
    $return_string = false,
    $cookie_check = false,
    $no_print = false,
    $ajax = true
) {
    # Return false if page is excluded
    if ( apbct_exclusions_check__url() ) {
        return false;
    }

    global $ct_checkjs_def, $apbct;

    $ct_checkjs_key = ct_get_checkjs_value();
    $field_id_hash  = md5((string)rand(0, 1000));

    // Using only cookies
    if ( $cookie_check ) {
        $html =
            "<script "
            . (class_exists('Cookiebot_WP') ? 'data-cookieconsent="ignore"' : '')
            . ">
                function apbct_attach_event_handler__backend(elem, event, callback) {
                    if(typeof window.addEventListener === \"function\") elem.addEventListener(event, callback);
                    else                                                elem.attachEvent(event, callback);
                }
                apbct_attach_event_handler__backend(document, 'DOMContentLoaded', function(){
                    if (typeof apbctLocalStorage === \"object\" && ctPublic.data__key_is_ok) {
                        apbctLocalStorage.set('{$field_name}', '{$ct_checkjs_key}', true );
                    } else {
                        console.log('APBCT ERROR: apbctLocalStorage object is not loaded.');
                    }  
                });
		    </script>";
        // Using AJAX to get key
    } elseif ( $apbct->settings['data__use_ajax'] && $ajax ) {
        // Fix only for wp_footer -> apbct_hook__wp_head__set_cookie__ct_checkjs()
        if ( $no_print ) {
            return;
        }

        $field_id = $field_name . '_' . $field_id_hash;
        $html     = "<input type=\"hidden\" id=\"{$field_id}\" name=\"{$field_name}\" value=\"{$ct_checkjs_def}\" />";
        // Set KEY from backend
    } else {
        // Fix only for wp_footer -> apbct_hook__wp_head__set_cookie__ct_checkjs()
        if ( $no_print ) {
            return;
        }

        $ct_input_challenge = sprintf("'%s'", $ct_checkjs_key);
        $field_id           = $field_name . '_' . $field_id_hash;
        $html               = "<input type=\"hidden\" id=\"{$field_id}\" name=\"{$field_name}\" value=\"{$ct_checkjs_def}\" />
		<script " . (class_exists('Cookiebot_WP') ? 'data-cookieconsent="ignore"' : '') . ">
			setTimeout(function(){
				var ct_input_name = \"{$field_id}\";
				if (document.getElementById(ct_input_name) !== null) {
					var ct_input_value = document.getElementById(ct_input_name).value;
					document.getElementById(ct_input_name).value = document.getElementById(ct_input_name).value.replace(ct_input_value, {$ct_input_challenge});
				}
			}, 1000);
		</script>";
    }

    // Simplify JS code and Fixing issue with wpautop()
    $html = str_replace(array("\n", "\r", "\t"), '', $html);

    if ( $return_string === true ) {
        return $html;
    } else {
        echo Escape::escKses(
            $html,
            array(
                'script' => array(
                    'type' => true,
                    'data-cookieconsent' => true
                ),
                'input' => array(
                    'type' => true,
                    'id' => true,
                    'name' => true,
                    'value' => true
                )
            )
        );
    }
}

/**
 * Returns HTML of a honeypot hidden field to the form. If $form_method is GET, adds a hidden submit button.
 * @param $form_type
 * @param string $form_method
 * @return string
 */
function ct_add_honeypot_field($form_type, $form_method = 'post')
{
    global $apbct;

    // Honeypot option is OFF
    if ( ! $apbct->settings['data__honeypot_field'] || apbct_exclusions_check__url() ) {
        return '';
    }
    //Generate random suffix to prevent ids duplicate
    $random = mt_rand(0, 100000);

    // Generate the hidden field
    $honeypot = '<input 
        id="apbct__email_id__' . $form_type . '_' . $random . '" 
        class="apbct_special_field apbct__email_id__' . $form_type . '"
        autocomplete="off" 
        name="apbct__email_id__' . $form_type . '_' . $random . '"  
        type="text" 
        value="" 
        size="30" 
        maxlength="200" 
    />';

    if ( $form_method === 'post' ) {
        //add hidden field to set random suffix for the field
        $honeypot .= '<input 
        id="apbct_event_id"
        class="apbct_special_field"
        name="apbct_event_id"
        type="hidden" 
        value="' . $random . '" 
            />';
    }

    //add a submit button if method is get to prevent keyboard send misfunction
    if ( $form_method === 'get' ) {
        $honeypot = '<input 
        id="apbct_submit_id__' . $form_type . '_' . $random . '" 
        class="apbct_special_field apbct__email_id__' . $form_type . '"
        name="apbct_submit_id__' . $form_type . '_' . $random . '"  
        type="submit" 
        apbct_event_id="' . $random . '"
        size="30" 
        maxlength="200" 
        value=""
    />';
    }

    return $honeypot;
}

/**
 * Changes whether notify admin/athor or not.
 *
 * @param bool $maybe_notify notify flag
 * @param int $comment_ID Comment id
 *
 * @return bool flag
 */
function apbct_comment__Wordpress__doNotify($_maybe_notify, $_comment_ID)
{
    return true;
}

/**
 * Change email notification recipients
 *
 * @param array $emails
 * @param integer $comment_id
 *
 * @return array
 * @global \Cleantalk\ApbctWP\State $apbct
 */
function apbct_comment__Wordpress__changeMailNotificationRecipients($emails, $_comment_id)
{
    global $apbct;

    return array_unique(array_merge($emails, (array)json_decode($apbct->comment_notification_recipients, true)));
}

/**
 * Changes email notification for spam comment for native WordPress comment system
 *
 * @param string $notify_message Body of email notification
 * @param $_comment_id
 *
 * @return string Body for email notification
 */
function apbct_comment__Wordpress__changeMailNotification($notify_message, $_comment_id)
{
    global $apbct;

    return PHP_EOL
           . __('CleanTalk Anti-Spam: This message is possible spam.', 'cleantalk-spam-protect')
           . "\n" . __('You could check it in CleanTalk\'s Anti-Spam database:', 'cleantalk-spam-protect')
           . "\n" . 'IP: https://cleantalk.org/blacklists/' . $apbct->sender_ip
           . "\n" . 'Email: https://cleantalk.org/blacklists/' . $apbct->sender_email
           . "\n" . PHP_EOL . sprintf(
               __('Activate protection in your Anti-Spam Dashboard: %s.', 'clentalk'),
               'https://cleantalk.org/my/?cp_mode=antispam&utm_source=newsletter&utm_medium=email&utm_campaign=wp_spam_comment_passed'
               . ($apbct->data['user_token']
                   ? '&iser_token=' . $apbct->data['user_token']
                   : ''
               )
           )
           . PHP_EOL . '---'
           . PHP_EOL
           . PHP_EOL
           . $notify_message;
}

function apbct_comment__wordpress__show_blacklists($notify_message, $comment_id)
{
    $comment_details = get_comments(array('comment__in' => $comment_id));
    $comment_details = $comment_details[0];

    if ( isset($comment_details->comment_author_email) ) {
        $black_list_link = 'https://cleantalk.org/blacklists/';

        $links = PHP_EOL;
        $links .= esc_html__('Check for spam:', 'cleantalk-spam-protect');
        $links .= PHP_EOL;
        $links .= $black_list_link . $comment_details->comment_author_email;
        $links .= PHP_EOL;
        if ( ! empty($comment_details->comment_author_IP) ) {
            $links .= $black_list_link . $comment_details->comment_author_IP;
            $links .= PHP_EOL;
        }

        return $notify_message . $links;
    }

    return $notify_message;
}

/**
 * Set die page with Cleantalk comment.
 *
 * @param null $comment_status
 *
 * @global null $ct_comment
 *   $err_text = '<center><b style="color: #49C73B;">Clean</b><b style="color: #349ebf;">Talk.</b> ' . __('Spam protection', 'cleantalk-spam-protect') . "</center><br><br>\n" . $ct_comment;
 */
function ct_die($_comment_id, $_comment_status)
{
    global $ct_comment, $ct_jp_comments;

    // JCH Optimize caching preventing
    add_filter('jch_optimize_page_cache_set_caching', static function ($_is_cache_active) {
        return false;
    }, 999, 1);

    do_action('apbct_pre_block_page', $ct_comment);

    $message_title = __('Spam protection', 'cleantalk-spam-protect');
    if ( defined('CLEANTALK_DISABLE_BLOCKING_TITLE') && CLEANTALK_DISABLE_BLOCKING_TITLE != true ) {
        $message_title = '<b style="color: #49C73B;">Clean</b><b style="color: #349ebf;">Talk.</b> ' . $message_title;
    }
    if ( Post::get('et_pb_contact_email') ) {
        $message_title = 'Blacklisted';
    }

    $back_link   = '';
    $back_script = '';
    if ( ! $ct_jp_comments ) {
        $back_script = '<script>setTimeout("history.back()", 5000);</script>';
    } elseif ( isset($_SERVER['HTTP_REFERER']) ) {
        $back_link = '<a href="' . Sanitize::cleanUrl(Server::get('HTTP_REFERER')) . '">' . __('Back') . '</a>';
    }

    if ( file_exists(CLEANTALK_PLUGIN_DIR . "templates/lock-pages/lock-page-ct-die.html") ) {
        $ct_die_page = file_get_contents(CLEANTALK_PLUGIN_DIR . "templates/lock-pages/lock-page-ct-die.html");

        // Translation
        $replaces = array(
            '{MESSAGE_TITLE}' => $message_title,
            '{MESSAGE}'       => $ct_comment,
            '{BACK_LINK}'     => $back_link,
            '{BACK_SCRIPT}'   => $back_script
        );

        foreach ( $replaces as $place_holder => $replace ) {
            $ct_die_page = str_replace($place_holder, $replace, $ct_die_page);
        }

        http_response_code(200);
        die($ct_die_page);
    }

    http_response_code(200);
    die("Forbidden. Sender blacklisted. Blocked by CleanTalk");
}

/**
 * Set die page with CleanTalk comment from parameter.
 *
 * @param $comment_body
 */
function ct_die_extended($comment_body)
{
    global $ct_jp_comments;

    // JCH Optimize caching preventing
    add_filter('jch_optimize_page_cache_set_caching', static function ($_is_cache_active) {
        return false;
    }, 999, 1);

    $message_title = __('Spam protection', 'cleantalk-spam-protect');
    if ( defined('CLEANTALK_DISABLE_BLOCKING_TITLE') && CLEANTALK_DISABLE_BLOCKING_TITLE != true ) {
        $message_title = '<b style="color: #49C73B;">Clean</b><b style="color: #349ebf;">Talk.</b> ' . $message_title;
    }

    $back_link   = '';
    $back_script = '';
    if ( ! $ct_jp_comments ) {
        $back_script = '<script>setTimeout("history.back()", 5000);</script>';
    } else {
        $back_link = '<a href="' . Sanitize::cleanUrl(Server::get('HTTP_REFERER')) . '">' . __('Back') . '</a>';
    }

    if ( file_exists(CLEANTALK_PLUGIN_DIR . "templates/lock-pages/lock-page-ct-die.html") ) {
        $ct_die_page = file_get_contents(CLEANTALK_PLUGIN_DIR . "templates/lock-pages/lock-page-ct-die.html");

        // Translation
        $replaces = array(
            '{MESSAGE_TITLE}' => $message_title,
            '{MESSAGE}'       => $comment_body,
            '{BACK_LINK}'     => $back_link,
            '{BACK_SCRIPT}'   => $back_script
        );

        foreach ( $replaces as $place_holder => $replace ) {
            $ct_die_page = str_replace($place_holder, $replace, $ct_die_page);
        }

        http_response_code(200);
        die($ct_die_page);
    }

    http_response_code(200);
    die("Forbidden. Sender blacklisted. Blocked by CleanTalk");
}

/**
 * Validates JavaScript anti-spam test
 *
 * @param string $check_js_value String to checking
 * @param bool $is_cookie
 *
 * @return int|null
 */
function apbct_js_test($check_js_value = '', $is_cookie = false)
{
    global $apbct;

    $out = null;

    if (
        ( ! empty($check_js_value) ) ||
        ( $is_cookie && $apbct->data['cookies_type'] === 'alternative' && Cookie::get('ct_checkjs') )
    ) {
        $js_key = $is_cookie && $apbct->data['cookies_type'] === 'alternative'
            ? Cookie::get('ct_checkjs')
            : trim($check_js_value);

        // Check static key
        if (
            $apbct->settings['data__use_static_js_key'] == 1 ||
            ($apbct->settings['data__use_static_js_key'] == -1 &&
             (apbct_is_cache_plugins_exists() ||
              (apbct_is_post() && isset($apbct->data['cache_detected']) && $apbct->data['cache_detected'] == 1)
             )
            )
        ) {
            $out = ct_get_checkjs_value() === $js_key ? 1 : 0;
            // Random key check
        } else {
            $out = isset($apbct->js_keys[ $js_key ]) ? 1 : 0;
        }
    }

    return $out;
}

/**
 * Get post url
 *
 * @param int|null $comment_id
 * @param int $comment_post_id
 *
 * @return string|null
 */
function ct_post_url($comment_id, $comment_post_id)
{
    if ( empty($comment_post_id) ) {
        return null;
    }

    if ( $comment_id === null ) {
        $last_comment = get_comments('number=1');
        $comment_id   = isset($last_comment[0]->comment_ID) ? (int)$last_comment[0]->comment_ID + 1 : 1;
    }
    $permalink = get_permalink($comment_post_id);

    $post_url = null;
    if ( $permalink !== null ) {
        $post_url = $permalink . '#comment-' . $comment_id;
    }

    return $post_url;
}

/**
 * Public filter 'pre_comment_approved' - Mark comment unapproved always
 * @return    int Zero
 */
function ct_set_not_approved()
{
    return 0;
}

/**
 * Public filter 'pre_comment_approved' - Mark comment approved if it's not 'spam' only
 *
 * @param $approved
 * @param $_comment
 *
 * @return int|string "spam"|1
 */
function ct_set_approved($approved, $_comment)
{
    if ( $approved === 'spam' ) {
        return $approved;
    }

    return 1;
}

/**
 * Public filter 'pre_comment_approved' - Mark comment unapproved always
 * @return    string
 */
function ct_set_comment_spam()
{
    return 'spam';
}

/**
 * Public action 'comment_post' - Store cleantalk hash in comment meta 'ct_hash'
 *
 * @param int $comment_id Comment ID
 * @param mixed $comment_status Approval status ("spam", or 0/1), not used
 */
function ct_set_meta($comment_id, $comment_status)
{
    global $comment_post_id;
    $hash1 = ct_hash();
    if ( ! empty($hash1) ) {
        update_comment_meta($comment_id, 'ct_hash', $hash1);
        if ( function_exists('base64_encode') && isset($comment_status) && $comment_status !== 'spam' ) {
            $post_url = ct_post_url($comment_id, $comment_post_id);
            $post_url = base64_encode($post_url);
            // 01 - URL to approved comment
            $feedback_request = $hash1 . ':' . '01' . ':' . $post_url . ';';
            ct_send_feedback($feedback_request);
        }
    }

    return true;
}

/**
 * Mark bad words
 *
 * @param int $comment_id
 * @param int $comment_status Not use
 *
 * @psalm-suppress UndefinedMethod
 * @global string $ct_stop_words
 */
function ct_mark_red($comment_id, $_comment_status)
{
    global $ct_stop_words;

    $comment = get_comment($comment_id, 'ARRAY_A');
    $message = $comment['comment_content'];
    foreach ( explode(':', $ct_stop_words) as $word ) {
        $message = preg_replace("/($word)/ui", '<font rel="cleantalk" color="#FF1000">' . "$1" . '</font>', $message);
    }
    $comment['comment_content'] = $message;
    kses_remove_filters();
    wp_update_comment($comment);
}

//
//Send post to trash
//
function ct_wp_trash_comment($comment_id, $_comment_status)
{
    wp_trash_comment($comment_id);
}

/**
 * Tests plugin activation status
 * @return bool
 */
function ct_plugin_active($plugin_name)
{
    foreach ( get_option('active_plugins') as $_k => $v ) {
        if ( $plugin_name == $v ) {
            return true;
        }
    }

    return false;
}

/**
 * @psalm-suppress UnusedVariable
 */
function apbct_login__scripts()
{
    global $apbct;

    apbct_enqueue_and_localize_public_scripts();

    $apbct->public_script_loaded = true;
}

/**
 * Inner function - Finds and returns pattern in string
 * @return bool
 */
function ct_get_data_from_submit($value = null, $field_name = null)
{
    if ( ! $value || ! $field_name || ! is_string($value) ) {
        return false;
    }
    if ( preg_match("/[a-z0-9_\-]*" . $field_name . "[a-z0-9_\-]*$/", $value) ) {
        return true;
    }

    return false;
}

/**
 * Sends error notice to admin
 * @return null
 */
function ct_send_error_notice($comment = '')
{
    global $ct_admin_notoice_period, $apbct;

    $timelabel_reg = intval(get_option('cleantalk_timelabel_reg'));
    if ( time() - $ct_admin_notoice_period > $timelabel_reg ) {
        update_option('cleantalk_timelabel_reg', time());

        $blogname = wp_specialchars_decode(get_option('blogname'), ENT_QUOTES);
        $message  = __('Attention, please!', 'cleantalk-spam-protect') . "\r\n\r\n";
        $message  .=
            sprintf(
                __('"%s" plugin error on your site "%s":', 'cleantalk-spam-protect'),
                $apbct->plugin_name,
                $blogname
            )
            . "\r\n\r\n";
        $message  .=
            preg_replace(
                '/^(.*?)<a.*?"(.*?)".*?>(.*?)<.a>(.*)$/',
                '$1. $3: $2?user_token=' . $apbct->user_token . ' $4',
                $comment
            )
            . "\r\n\r\n";
        @wp_mail(
            ct_get_admin_email(),
            sprintf(__('[%s] "%s" error!', 'cleantalk-spam-protect'), $apbct->plugin_name, $blogname),
            $message
        );
    }

    return null;
}

/**
 * Prints form for "protect externals
 *
 * @param $arr
 * @param $k
 */
function ct_print_form($arr, $k)
{
    // Fix for pages04.net forms
    if ( isset($arr['formSourceName']) ) {
        $tmp = array();
        foreach ( $arr as $key => $val ) {
            $tmp_key       = str_replace('_', '+', $key);
            $tmp[$tmp_key] = $val;
        }
        $arr = $tmp;
        unset($tmp, $key, $tmp_key, $val);
    }

    foreach ( $arr as $key => $value ) {
        if ( ! is_array($value) ) {
            print '<textarea
				name="' . esc_attr($k === '' ? $key : $k . '[' . $key . ']') . '"
				style="display:none;">' . esc_textarea(htmlspecialchars($value))
                  . '</textarea>';
        } else {
            ct_print_form($value, $k === '' ? $key : $k . '[' . $key . ']');
        }
    }
}

/**
 * Attaches public scripts and styles.
 * @psalm-suppress UnusedVariable
 */
function ct_enqueue_scripts_public($_hook)
{
    global $current_user, $apbct;

    if ( apbct_exclusions_check__url() ) {
        return;
    }

    if (
        $apbct->settings['forms__registrations_test'] ||
        $apbct->settings['forms__comments_test'] ||
        $apbct->settings['forms__contact_forms_test'] ||
        $apbct->settings['forms__general_contact_forms_test'] ||
        $apbct->settings['forms__wc_checkout_test'] ||
        $apbct->settings['forms__check_external'] ||
        $apbct->settings['forms__check_internal'] ||
        $apbct->settings['comments__bp_private_messages'] ||
        $apbct->settings['data__general_postdata_test']
    ) {
        if ( ! $apbct->public_script_loaded ) {
            apbct_enqueue_and_localize_public_scripts();
        }
    }

    // Show controls for commentaries
    if ( in_array("administrator", $current_user->roles) ) {
        // Admin javascript for managing comments on public pages
        if ( $apbct->settings['comments__manage_comments_on_public_page'] ) {
            $ajax_nonce = wp_create_nonce("ct_secret_nonce");
            wp_enqueue_script(
                'ct_public_admin_js',
                APBCT_JS_ASSETS_PATH . '/cleantalk-public-admin.min.js',
                array('jquery'),
                APBCT_VERSION,
                false /*in header*/
            );

            wp_localize_script('ct_public_admin_js', 'ctPublicAdmin', array(
                'ct_ajax_nonce'       => $ajax_nonce,
                'ajaxurl'             => admin_url('admin-ajax.php', 'relative'),
                'ct_feedback_error'   => __('Error occurred while sending feedback.', 'cleantalk-spam-protect'),
                'ct_feedback_no_hash' => __(
                    'Feedback wasn\'t sent. There is no associated request.',
                    'cleantalk-spam-protect'
                ),
                'ct_feedback_msg'     => sprintf(
                    __("Feedback has been sent to %sCleanTalk Dashboard%s.", 'cleantalk-spam-protect'),
                    $apbct->user_token ? "<a target='_blank' href=https://cleantalk.org/my/show_requests?user_token={$apbct->user_token}&cp_mode=antispam>" : '',
                    $apbct->user_token ? "</a>" : ''
                )
                    . ' '
                    . esc_html__(
                        'The service accepts feedback only for requests made less than 7 (or 45 if the Extra Package is activated) days ago.',
                        'cleantalk-spam-protect'
                    ),
            ));
        }
    }

    // Debug
    if ( $apbct->settings['misc__debug_ajax'] ) {
        wp_enqueue_script(
            'ct_debug_js',
            APBCT_JS_ASSETS_PATH . '/cleantalk-debug-ajax.min.js',
            array('jquery'),
            APBCT_VERSION,
            false /*in header*/
        );

        wp_localize_script('ct_debug_js', 'apbctDebug', array(
            'reload'      => false,
            'reload_time' => 10000,
        ));
    }
}

function ct_enqueue_styles_public()
{
    global $apbct, $current_user;

    if ( apbct_exclusions_check__url() ) {
        return;
    }

    if (
        $apbct->settings['forms__registrations_test'] ||
        $apbct->settings['forms__comments_test'] ||
        $apbct->settings['forms__contact_forms_test'] ||
        $apbct->settings['forms__general_contact_forms_test'] ||
        $apbct->settings['forms__wc_checkout_test'] ||
        $apbct->settings['forms__check_external'] ||
        $apbct->settings['forms__check_internal'] ||
        $apbct->settings['comments__bp_private_messages'] ||
        $apbct->settings['data__general_postdata_test']
    ) {
        // Common public styles
        wp_enqueue_style(
            'ct_public_css',
            APBCT_CSS_ASSETS_PATH . '/cleantalk-public.min.css',
            array(),
            APBCT_VERSION
        );
        // Public admin styles
        if ( in_array("administrator", $current_user->roles) ) {
            // Admin style for managing comments on public pages
            if ( $apbct->settings['comments__manage_comments_on_public_page'] ) {
                 wp_enqueue_style(
                     'ct_public_admin_css',
                     APBCT_CSS_ASSETS_PATH . '/cleantalk-public-admin.min.css',
                     array(),
                     APBCT_VERSION,
                     'all'
                 );
            }
        }
    }
}

function apbct_enqueue_and_localize_public_scripts()
{
    global $apbct;

    // Different JS params
    wp_enqueue_script(
        'ct_public_functions',
        APBCT_URL_PATH . '/js/apbct-public-bundle.min.js',
        array('jquery'),
        APBCT_VERSION
    );

    wp_enqueue_style(
        'ct_public_css',
        APBCT_CSS_ASSETS_PATH . '/cleantalk-public.min.css',
        array(),
        APBCT_VERSION,
        'all'
    );
}

/**
 * Reassign callback function for the bottom of comment output.
 */
function ct_wp_list_comments_args($options)
{
    global $current_user, $apbct;

    if ( in_array("administrator", $current_user->roles) ) {
        if ( $apbct->settings['comments__manage_comments_on_public_page'] ) {
            $theme                   = wp_get_theme();
            $apbct->active_theme     = $theme->get('Name');
            $options['end-callback'] = 'ct_comments_output';
        }
    }

    return $options;
}

/**
 * Callback function for the bottom comment output.
 */
function ct_comments_output($curr_comment, $_param2, $wp_list_comments_args)
{
    global $apbct;

    $email = $curr_comment->comment_author_email;
    $ip    = $curr_comment->comment_author_IP;
    $id    = $curr_comment->comment_ID;

    $settings_link = '/wp-admin/' . (is_network_admin() ? "settings.php?page=cleantalk" : "options-general.php?page=cleantalk");

    $html = "<div class='ct_comment_info'><div class ='ct_comment_titles'>";
    $html .= "<p class='ct_comment_info_title'>" . __('Sender info', 'cleantalk-spam-protect') . "</p>";

    $html .= "<p class='ct_comment_logo_title'>
				" . __('by', 'cleantalk-spam-protect')
         . " <a href='{$settings_link}' target='_blank'><img class='ct_comment_logo_img' src='" . Escape::escUrl(APBCT_IMG_ASSETS_PATH . "/logo_color.png") . "'></a>"
         . " <a href='{$settings_link}' target='_blank'>CleanTalk</a>"
         . "</p></div>";
    // Outputs email if exists
    if ( $email ) {
        $html .= "<a href='https://cleantalk.org/blacklists/$email' target='_blank' title='https://cleantalk.org/blacklists/$email'>"
             . "$email"
             . "&nbsp;<img src='" . Escape::escUrl(APBCT_IMG_ASSETS_PATH . "/new_window.gif") . "' border='0' style='float:none; box-shadow: transparent 0 0 0 !important;'/>"
             . "</a>";
    } else {
        $html .= __('No email', 'cleantalk-spam-protect');
    }
    $html .= "&nbsp;|&nbsp;";

    // Outputs IP if exists
    if ( $ip ) {
        $html .= "<a href='https://cleantalk.org/blacklists/$ip' target='_blank' title='https://cleantalk.org/blacklists/$ip'>"
             . "$ip"
             . "&nbsp;<img src='" . Escape::escUrl(APBCT_IMG_ASSETS_PATH . "/new_window.gif") . "' border='0' style='float:none; box-shadow: transparent 0 0 0 !important;'/>"
             . "</a>";
    } else {
        $html .= __('No IP', 'cleantalk-spam-protect');
    }
    $html .= '&nbsp;|&nbsp;';

    $html .= "<span commentid='$id' class='ct_this_is ct_this_is_spam' href='#'>"
         . __(
             'Mark as spam',
             'cleantalk-spam-protect'
         )
         . '<img style="margin-left: 10px;" class="apbct_preloader_button" src="' . Escape::escUrl(APBCT_URL_PATH . '/inc/images/preloader2.gif') . '" />'
         . "</span>";
    $html .= "<span commentid='$id' class='ct_this_is ct_this_is_not_spam ct_hidden' href='#'>"
         . __(
             'Unspam',
             'cleantalk-spam-protect'
         )
         . '<img style="margin-left: 10px;" class="apbct_preloader_button" src="' . Escape::escUrl(APBCT_URL_PATH . '/inc/images/preloader2.gif') . '" />'
         . "</span>";
    $html .= "<p class='ct_feedback_wrap'>";
    $html .= "<span class='ct_feedback_result ct_feedback_result_spam'>"
         . __(
             'Marked as spam.',
             'cleantalk-spam-protect'
         )
         . "</span>";
    $html .= "<span class='ct_feedback_result ct_feedback_result_not_spam'>"
         . __(
             'Marked as not spam.',
             'cleantalk-spam-protect'
         )
         . "</span>";
    $html .= "&nbsp;<span class='ct_feedback_msg'><span>";
    $html .= "</p>";

    $html .= "</div>";

    // @todo research what such themes and make exception for them
    $ending_tag = isset($wp_list_comments_args['style']) ? $wp_list_comments_args['style'] : null;
    if ( in_array($apbct->active_theme, array('Paperio', 'Twenty Twenty')) ) {
        $ending_tag = is_null($wp_list_comments_args['style']) ? 'div' : $wp_list_comments_args['style'];
    };

    // Ending comment output
    $html .= "</{$ending_tag}>";
    echo Escape::escKses(
        $html,
        array(
            'div' => array(
                'class' => true
            ),
            'p' => array(
                'class' => true
            ),
            'span' => array(
                'class' => true,
                'commentid' => true,
                'href' => true,
            ),
            'img' => array(
                'style' => true,
                'class' => true,
                'src' => true,
                'border' => true,
            ),
            'style' => true,
            'a' => array(
                'href' => true,
                'target' => true,
                'title' => true,
            ),
        )
    );
}

/**
 * Trusted and affiliate text handlers
 */

function apbct_hook__wp_footer_trusted_text()
{
    echo Escape::escKsesPreset(apbct_generate_trusted_text_html(), 'apbct_public__trusted_text');
}

function apbct_trusted_text_shortcode_handler()
{
    return apbct_generate_trusted_text_html('span');
}

function apbct_generate_trusted_text_html($type = 'div')
{
    global $apbct;

    $trusted_text = '';

    $query_data = array(
        'product_name'  => 'anti-spam',
    );

    if ( $apbct->settings['trusted_and_affiliate__add_id'] === '1'
        && !empty($apbct->data['user_id']) ) {
        $query_data['pid'] = $apbct->data['user_id'];
    }

    $css_class = 'apbct-trusted-text--' . $type;
    $cleantalk_tag_with_ref_link = '<a href="https://cleantalk.org/register?'
        . http_build_query($query_data)
        . '" target="_blank" rel="nofollow">'
        . 'CleanTalk Anti-Spam'
        . '</a>';

    if ( $type === 'div' ) {
        $trusted_text = '<div class="' . $css_class . '">'
            . '<p>'
            . 'Protected by '
            . $cleantalk_tag_with_ref_link
            . '</p>'
            . '</div>';
    }
    if ( strpos($type, 'label') !== false ) {
        $trusted_text = '<label for="hidden_trusted_text" type="hidden" class="' . $css_class . '">'
            . 'Protected by '
            . $cleantalk_tag_with_ref_link
            . '</label>'
            . '<input type="hidden" name="hidden_trusted_text" id="hidden_trusted_text">';
    }
    if ( $type === 'span' ) {
        $trusted_text = '<span class="' . $css_class . '">'
            . 'Protected by '
            . $cleantalk_tag_with_ref_link
            . '</span>';
    }
    return $trusted_text;
}
