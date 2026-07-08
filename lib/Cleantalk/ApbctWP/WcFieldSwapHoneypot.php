<?php

namespace Cleantalk\ApbctWP;

use Cleantalk\ApbctWP\Variables\Request;

/**
 * Bee-style field swap honeypot for WooCommerce classic checkout.
 *
 * Replaces billing_email name with a site-specific secret and adds a hidden trap
 * input that keeps the canonical billing_email name for bots.
 */
class WcFieldSwapHoneypot
{
    private const FIELD_BILLING_EMAIL = 'billing_email';

    /**
     * @var array{status: int|null, value: string|null, source: string|null}|null
     */
    private static $checkout_result;

    public static function registerHooks()
    {
        if ( ! self::isActive() ) {
            return;
        }

        add_filter('woocommerce_checkout_fields', array(self::class, 'swapCheckoutFields'), 20);
        add_action('woocommerce_after_checkout_billing_form', array(self::class, 'renderTrapAndScript'));
        add_filter('woocommerce_checkout_posted_data', array(self::class, 'captureCheckoutResult'), 0);
    }

    public static function isActive()
    {
        global $apbct;

        return ! empty($apbct->settings['data__honeypot_field'])
            && ! apbct_exclusions_check__url()
            && ! apbct_is_amp_request();
    }

    /**
     * @param array $fields
     * @return array
     * @psalm-suppress PossiblyUnusedReturnValue
     */
    public static function swapCheckoutFields($fields)
    {
        if ( ! self::isActive() || self::isCheckoutBlocksPage() ) {
            return $fields;
        }

        if ( empty($fields['billing'][self::FIELD_BILLING_EMAIL]) ) {
            return $fields;
        }

        $secret = self::getSecretName(self::FIELD_BILLING_EMAIL);

        $fields['billing'][$secret] = $fields['billing'][self::FIELD_BILLING_EMAIL];
        $fields['billing'][$secret]['id'] = $secret;
        unset($fields['billing'][self::FIELD_BILLING_EMAIL]);

        return $fields;
    }

    public static function renderTrapAndScript()
    {
        if ( ! self::isActive() || self::isCheckoutBlocksPage() ) {
            return;
        }

        echo self::renderTrapField(self::FIELD_BILLING_EMAIL, 'email');
        echo self::renderIdSwapScript(self::FIELD_BILLING_EMAIL);
    }

    /**
     * @param string $canonical_name
     * @param string $input_type
     * @return string
     */
    public static function renderTrapField($canonical_name, $input_type)
    {
        $trap_style = 'padding:0 !important;clip:rect(1px, 1px, 1px, 1px) !important;'
            . 'position:absolute !important;white-space:nowrap !important;'
            . 'height:1px !important;width:1px !important;overflow:hidden !important;';

        return sprintf(
            '<input type="%s" id="%s" name="%s" aria-hidden="true" tabindex="-1" autocomplete="off"'
            . ' class="apbct-wc-swap-trap" value="" style="%s" />',
            esc_attr($input_type),
            esc_attr($canonical_name),
            esc_attr($canonical_name),
            esc_attr($trap_style)
        );
    }

    /**
     * @param string $field_key
     * @return string
     */
    public static function renderIdSwapScript($field_key)
    {
        $secret    = self::getSecretName($field_key);
        $random_id = 'a' . substr(md5((string) time()), 0, 31);

        return '<script data-noptimize>(function(){'
            . 'const trap=document.getElementById("' . esc_js($field_key) . '");'
            . 'const real=document.getElementById("' . esc_js($secret) . '");'
            . 'if(!trap||!real){return;}'
            . 'trap.setAttribute("id","' . esc_js($random_id) . '");'
            . 'real.setAttribute("id","' . esc_js($field_key) . '");'
            . '})();</script>';
    }

    /**
     * Stores honeypot status without changing posted data keys — WC validates by secret field name.
     *
     * @param array $data
     * @return array
     * @psalm-suppress PossiblyUnusedReturnValue
     */
    public static function captureCheckoutResult($data)
    {
        if ( ! self::isActive() || ! self::isCheckoutSubmitRequest() ) {
            return $data;
        }

        self::$checkout_result = self::evaluateFromPostedData(self::getRawPostedData());

        return $data;
    }

    /**
     * Adds billing_email for CleanTalk field parsing; does not alter keys used by WooCommerce.
     *
     * @param array $input
     * @return array
     */
    public static function enrichInputArrayForCleanTalk(array $input)
    {
        if ( ! self::isActive() || ! self::isCheckoutSubmitRequest() ) {
            return $input;
        }

        $secret = self::getSecretName(self::FIELD_BILLING_EMAIL);

        if ( empty($input[$secret]) ) {
            return $input;
        }

        if ( empty($input[self::FIELD_BILLING_EMAIL]) ) {
            $input[self::FIELD_BILLING_EMAIL] = $input[$secret];
        }

        return $input;
    }

    /**
     * @return array{status: int|null, value: string|null, source: string|null}
     */
    public static function getCheckoutResult()
    {
        if ( self::$checkout_result !== null ) {
            return self::$checkout_result;
        }

        if ( ! self::isActive() || ! self::isCheckoutSubmitRequest() ) {
            return self::emptyResult();
        }

        self::$checkout_result = self::evaluateFromPostedData(self::getRawPostedData());

        return self::$checkout_result;
    }

    /**
     * Raw POST including trap field (not present in WC checkout posted_data).
     *
     * @return array
     */
    private static function getRawPostedData()
    {
        if ( empty($_POST) || ! is_array($_POST) ) {
            return array();
        }

        $post = wp_unslash($_POST);

        return is_array($post) ? $post : array();
    }

    /**
     * @param array $data
     * @return array{status: int|null, value: string|null, source: string|null}
     */
    public static function evaluateFromPostedData(array $data)
    {
        $field_key = self::FIELD_BILLING_EMAIL;
        $secret    = self::getSecretName($field_key);

        $trap_present  = array_key_exists($field_key, $data);
        $secret_present = array_key_exists($secret, $data);

        if ( ! $trap_present && ! $secret_present ) {
            return self::emptyResult();
        }

        $trap_value = '';
        if ( $trap_present && isset($data[$field_key]) ) {
            $trap_value = trim((string) $data[$field_key]);
        }

        if ( $trap_value !== '' ) {
            return array(
                'status' => 0,
                'value'  => $trap_value,
                'source' => $field_key,
            );
        }

        return array(
            'status' => 1,
            'value'  => null,
            'source' => $field_key,
        );
    }

    /**
     * @param string $field_key
     * @return string
     */
    public static function getSecretName($field_key)
    {
        $salt = defined('NONCE_SALT') ? NONCE_SALT : ABSPATH;
        $secret = substr(sha1(md5('apbct-wc-' . $field_key . $salt)), 0, 10);
        $secret = self::ensureSecretStartsWithLetter($secret);

        return $secret;
    }

    /**
     * @param string $secret
     * @return string
     */
    private static function ensureSecretStartsWithLetter($secret)
    {
        $first_char = substr($secret, 0, 1);

        if ( is_numeric($first_char) ) {
            return chr((int) $first_char + 97) . substr($secret, 1);
        }

        return $secret;
    }

    /**
     * @return array{status: int|null, value: string|null, source: string|null}
     */
    private static function emptyResult()
    {
        return array(
            'status' => null,
            'value'  => null,
            'source' => null,
        );
    }

    private static function isCheckoutSubmitRequest()
    {
        if ( apbct_is_rest() ) {
            return false;
        }

        if ( Request::get('wc-ajax') === 'checkout' ) {
            return true;
        }

        if ( empty($_POST) ) {
            return false;
        }

        $secret = self::getSecretName(self::FIELD_BILLING_EMAIL);

        return isset($_POST['woocommerce_checkout_place_order'])
            || isset($_POST[self::FIELD_BILLING_EMAIL])
            || isset($_POST[$secret]);
    }

    private static function isCheckoutBlocksPage()
    {
        if ( ! function_exists('is_checkout') || ! is_checkout() ) {
            return false;
        }

        global $post;

        if ( ! $post || ! function_exists('has_block') ) {
            return false;
        }

        return has_block('woocommerce/checkout', $post);
    }
}
