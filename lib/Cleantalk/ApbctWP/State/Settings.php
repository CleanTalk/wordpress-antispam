<?php

namespace Cleantalk\ApbctWP\State;

use Cleantalk\Common\State\Options;

class Settings extends Options
{
    /**
     * @inheritDoc
     */
    protected function setDefaults()
    {
        return array(
            'apikey'                                   => '',

            // SpamFireWall settings
            'sfw__enabled'                             => 1,
            'sfw__anti_flood'                          => 0,
            'sfw__anti_flood__view_limit'              => 20,
            'sfw__anti_crawler'                        => 0,
            'sfw__use_delete_to_clear_table'           => 0,
            'sfw__random_get'                          => -1,

            // Forms for protection
            'forms__registrations_test'                => 1,
            'forms__comments_test'                     => 1,
            'forms__contact_forms_test'                => 1,
            'forms__general_contact_forms_test'        => 1,
            // Antispam test for unsupported and untested contact forms
            'forms__wc_checkout_test'                  => 1,
            // WooCommerce checkout default test
            'forms__wc_register_from_order'            => 1,
            // Woocommerce registration during checkout
            'forms__wc_add_to_cart'                    => 0,
            // Woocommerce honeypot
            'forms__wc_honeypot'                       => 1,
            // Woocommerce add to cart
            'forms__search_test'                       => 1,
            // Test default Wordpress form
            'forms__check_external'                    => 0,
            'forms__check_external__capture_buffer'    => 0,
            'forms__check_internal'                    => 0,

            // Comments and messages
            'comments__disable_comments__all'          => 0,
            'comments__disable_comments__posts'        => 0,
            'comments__disable_comments__pages'        => 0,
            'comments__disable_comments__media'        => 0,
            'comments__bp_private_messages'            => 1,
            //buddyPress private messages test => ON
            'comments__check_comments_number'          => 1,
            'comments__remove_old_spam'                => 0,
            'comments__remove_comments_links'          => 0,
            // Removes links from approved comments
            'comments__show_check_links'               => 1,
            // Shows check link to Cleantalk's DB.
            'comments__manage_comments_on_public_page' => 0,
            // Allows to control comments on public page.
            'comments__hide_website_field'             => 0,
            // Hide website field from comment form

            // Data processing
            'data__protect_logged_in'                  => 1,
            // Do anti-spam tests to for logged in users.
            'data__use_ajax'                           => 1,
            'data__use_static_js_key'                  => -1,
            'data__general_postdata_test'              => 0,
            //CAPD
            'data__set_cookies'                        => 1,
            // Set cookies: Disable - 0 / Enable - 1 / Use Alternative cookies - 2.
            'data__set_cookies__alt_sessions_type'     => 0,
            // Alternative cookies handler type: REST API - 0 / custom AJAX - 1 / WP AJAX - 2
            'data__ssl_on'                             => 0,
            // Secure connection to servers
            'data__pixel'                              => '3',
            'data__email_check_before_post'            => 1,

            // Exclusions
            'exclusions__urls'                         => '',
            'exclusions__urls__use_regexp'             => 0,
            'exclusions__fields'                       => '',
            'exclusions__fields__use_regexp'           => 0,
            'exclusions__roles'                        => array('Administrator'),

            // Administrator Panel
            'admin_bar__show'                          => 1,
            // Show the admin bar.
            'admin_bar__all_time_counter'              => 0,
            'admin_bar__daily_counter'                 => 0,
            'admin_bar__sfw_counter'                   => 0,

            // GDPR
            'gdpr__enabled'                            => 0,
            'gdpr__text'                               => 'By using this form you agree with the storage and processing of your data by using the Privacy Policy on this website.',

            // Msic
            'misc__collect_details'                    => 0,
            // Collect details about browser of the visitor.
            'misc__send_connection_reports'            => 0,
            // Send connection reports to Cleantalk servers
            'misc__async_js'                           => 0,
            'misc__store_urls'                         => 1,
            'misc__complete_deactivation'              => 0,
            'misc__debug_ajax'                         => 0,

            // WordPress
            'wp__use_builtin_http_api'                 => 1,
            // Using Wordpress HTTP built in API
            'wp__comment_notify'                       => 1,
            'wp__comment_notify__roles'                => array('administrator'),
            'wp__dashboard_widget__show'               => 1,

        );
    }
}
