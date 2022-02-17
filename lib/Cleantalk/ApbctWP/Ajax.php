<?php

namespace Cleantalk\ApbctWP;

class Ajax
{
    public function __construct()
    {
        define('DOING_AJAX', true);
        define('SHORTINIT', true);

        $dir = $this->getWpDir();

        if ( $dir === false ) {
            // Not found WP directory
            http_response_code(404);
            die('0');
        }

        require_once($dir . '/wp-load.php');
        require_once($dir . '/wp-includes/capabilities.php');
        require_once($dir . '/wp-includes/kses.php');
        require_once($dir . '/wp-includes/rest-api.php');
        require_once($dir . '/wp-includes/class-wp-role.php');
        require_once($dir . '/wp-includes/class-wp-roles.php');
        require_once($dir . '/wp-includes/user.php');
        require_once($dir . '/wp-includes/class-wp-user.php');
        require_once($dir . '/wp-includes/option.php');
        require_once($dir . '/wp-includes/default-constants.php');
        require_once($dir . '/wp-includes/class-wp-session-tokens.php');
        require_once($dir . '/wp-includes/class-wp-user-meta-session-tokens.php');
        wp_plugin_directory_constants();
        wp_cookie_constants();
        require_once($dir . '/wp-includes/pluggable.php');
        require_once('../../../inc/cleantalk-pluggable.php');

        $this->checkRequest();

        $this->setHeaders();

        $this->handleRequest($_REQUEST);
    }

    private function checkRequest()
    {
        if ( empty($_REQUEST['action']) ) {
            http_response_code(400);
            die('0');
        }

        $this->checkAjaxReferer('ct_secret_stuff');
    }

    private function setHeaders()
    {
        header('Content-Type: text/html;');
        header('X-Robots-Tag: noindex');
        send_nosniff_header();
        nocache_headers();
    }

    private function handleRequest($request)
    {
        require_once(__DIR__ . '/../../../inc/cleantalk-ajax-handlers.php');

        global $apbct;

        switch ( $request['action'] ) {
            case 'apbct_js_keys__get':
                apbct_js_keys__get();
                break;
            case 'apbct_get_pixel_url':
                apbct_get_pixel_url();
                break;
            case 'apbct_email_check_before_post':
                if ( $apbct->settings['data__email_check_before_post'] ) {
                    apbct_email_check_before_post_from_custom_ajax();
                }
                break;
            case 'apbct_alt_session__save__AJAX':
                // Using alternative sessions with ajax
                if ( $apbct->data['cookies_type'] === 'alternative' && $apbct->data['ajax_type'] === 'custom_ajax' ) {
                    apbct_alt_session__save__AJAX();
                }
                break;
            case 'apbct_alt_session__get__AJAX':
                // Using alternative sessions with ajax
                if ( $apbct->data['cookies_type'] === 'alternative' && $apbct->data['ajax_type'] === 'custom_ajax' ) {
                    apbct_alt_session__get__AJAX();
                }
                break;
            default:
                break;
        }
    }


    /**
     * Verifies the Ajax request to prevent processing requests external of the blog.
     * @inheritDoc check_ajax_referer()
     */
    private function checkAjaxReferer($action, $query_arg = false)
    {
        $nonce = '';

        if ( $query_arg && isset($_REQUEST[$query_arg]) ) {
            $nonce = $_REQUEST[$query_arg];
        } elseif ( isset($_REQUEST['_ajax_nonce']) ) {
            $nonce = $_REQUEST['_ajax_nonce'];
        } elseif ( isset($_REQUEST['_wpnonce']) ) {
            $nonce = $_REQUEST['_wpnonce'];
        }

        $result = $this->wpVerifyNonce($nonce, $action);

        if ( false === $result ) {
            http_response_code(403);
            die(-1);
        }

        return $result;
    }

    /**
     * Verifies that a correct security nonce was used with time limit.
     * @inheritDoc wp_verify_nonce()
     */
    private function wpVerifyNonce($nonce, $action)
    {
        $nonce = (string)$nonce;
        $user  = wp_get_current_user();
        $uid   = is_null($user) ? 0 : $user->ID;
        if ( ! $uid ) {
            /**
             * Filters whether the user who generated the nonce is logged out.
             *
             * @param int $uid ID of the nonce-owning user.
             * @param string $action The nonce action.
             *
             * @psalm-suppress TooManyArguments
             * @since 3.5.0
             *
             */
            $uid = apply_filters('nonce_user_logged_out', $uid, $action);
        }

        if ( empty($nonce) ) {
            return false;
        }

        $token = $this->wpGetSessionToken();
        $i     = $this->wpNonceTick();

        // Nonce generated 0-12 hours ago.
        $expected = substr(wp_hash($i . '|' . $action . '|' . $uid . '|' . $token, 'nonce'), -12, 10);
        if ( hash_equals($expected, $nonce) ) {
            return 1;
        }

        // Nonce generated 12-24 hours ago.
        $expected = substr(wp_hash(($i - 1) . '|' . $action . '|' . $uid . '|' . $token, 'nonce'), -12, 10);
        if ( hash_equals($expected, $nonce) ) {
            return 2;
        }

        // Invalid nonce.
        return false;
    }

    /**
     * Returns the time-dependent variable for nonce creation.
     * @inheritDoc wp_nonce_tick()
     */
    private function wpNonceTick()
    {
        if ( defined('CLEANTALK_NONCE_LIFETIME') && is_int(CLEANTALK_NONCE_LIFETIME) ) {
            $nonce_lifetime = CLEANTALK_NONCE_LIFETIME;
        } else {
            $nonce_lifetime = DAY_IN_SECONDS;
        }
        $nonce_life = apply_filters('nonce_life', $nonce_lifetime);

        return ceil(time() / ($nonce_life / 2));
    }

    private function wpGetSessionToken()
    {
        $cookie = wp_parse_auth_cookie('', 'logged_in');

        return ! empty($cookie['token']) ? $cookie['token'] : '';
    }

    /**
     * Trying to find WordPress core directory
     *
     * @return false|string
     */
    private function getWpDir()
    {
        // Try to find WP in the DOCUMENT ROOT
        $dir = $_SERVER['DOCUMENT_ROOT'];
        if ( file_exists($dir . '/wp-load.php') ) {
            return $dir;
        }

        // Try to find WP in the relative path
        $dir = '../../../../../..';
        if ( file_exists($dir . '/wp-load.php') ) {
            return $dir;
        }

        // Parse index.php and try to find WP in the includes
        if ( file_exists($dir . '/index.php') ) {
            $index_content = file_get_contents($dir . '/index.php');
            if ( preg_match("@'\S*wp-blog-header\.php'@", $index_content, $matches) ) {
                $blog_header = trim($matches[0], "'");
                $dir = $_SERVER['DOCUMENT_ROOT'] . str_replace('/wp-blog-header.php', '', $blog_header);
                if ( file_exists($dir . '/wp-load.php') ) {
                    return $dir;
                }
            }
        }

        // WP directory not found
        return false;
    }
}

new Ajax();
