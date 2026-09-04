<?php

namespace Cleantalk\ApbctWP;

use Cleantalk\ApbctWP\RequestParameters\RequestParameters;
use Cleantalk\ApbctWP\Variables\Cookie;
use Cleantalk\ApbctWP\Variables\Get;
use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\Common\TT;

class BotDetectorService
{
    private static $default_browser_state = array(
        'frontend_data_log' => [],
        'botd_logic_loaded' => 0,
        'botd_wrapper_loaded' => 0,
    );

    public static function getWrapperUrl(): string
    {
        return 'https://moderate-next.cleantalk.org/dev/ct-bot-detector.min.js';
    }

    /**
     * @return bool
     */
    public static function isEnabled()
    {
        global $apbct;

        // Constant is preferred
        if ( Constant::is(Constant::APBCT_SERVICE__BOT_DETECTOR_ENABLED) ) {
            return (bool) Constant::getValue(Constant::APBCT_SERVICE__BOT_DETECTOR_ENABLED);
        }
        // Check by $apbct->data
        if ( isset($apbct->data['bot_detector_enabled']) ) {
            return (bool) $apbct->data['bot_detector_enabled'];
        }
        // By default - enabled
        return true;
    }

    /**
     * @return array
     */
    public static function getCustomExclusionsFromStateSettings()
    {
        global $apbct;

        $exlusion_format = array(
            'exclusion_id' => '',
            'signs_to_check' => array(
                'form_attributes'               => '',
                'form_children_attributes'      => '',
                'form_parent_attributes'        => ''
            )
        );

        $exclusions = array();
        if (!$apbct->settings['exclusions__bot_detector']) {
            return $exclusions;
        }

        foreach ($exlusion_format['signs_to_check'] as $sign => $_val) {
            $setting_name = 'exclusions__bot_detector__' . $sign;
            if (!empty($apbct->settings[$setting_name])) {
                $regexps = explode(',', $apbct->settings[$setting_name]);
                for ( $i = 0; $i < count($regexps); $i++ ) {
                    $form_exclusion = $exlusion_format;
                    $form_exclusion['exclusion_id'] = 'exclusion_' . $i;
                    $form_exclusion['signs_to_check'][$sign] = $regexps[$i];
                    $exclusions[] = $form_exclusion;
                }
            }
        }
        return $exclusions;
    }

    /**
     * Do prepare exclusions for skippping bot-detector event token field.
     * @return string JSON encoded array of valid exclusions. If no valid exclusions, returns '{}'.
     */
    public static function getPreparedExclusions()
    {
        global $apbct;
        $bot_detector_exclusions = array();

        //start exclusion there

        //todo if do need to add a built-ib exclusion, use $exlusion_format
        //set regexp to chek within attributes
        //    $exlusion_format = array(
        //        'exclusion_id' => '',
        //        'signs_to_check' => array(
        //            'form_attributes'               => '',
        //            'form_children_attributes'      => '',
        //            'form_parent_attributes'        => ''
        //        )
        //    );
        if ($apbct->settings['exclusions__bot_detector']) {
            $bot_detector_exclusions = array_merge(
                $bot_detector_exclusions,
                self::getCustomExclusionsFromStateSettings()
            );
        }

        //start validate
        $bot_detector_exclusions_valid = array();
        foreach ($bot_detector_exclusions as $exclusion) {
            if (
                empty($exclusion['exclusion_id']) ||
                (
                    empty($exclusion['signs_to_check']['form_attributes']) &&
                    empty($exclusion['signs_to_check']['form_children_attributes']) &&
                    empty($exclusion['signs_to_check']['form_parent_attributes'])
                )
            ) {
                continue;
            }
            $bot_detector_exclusions_valid[] = $exclusion;
        }

        //prepare for early localize
        $bot_detector_exclusions_valid = json_encode($bot_detector_exclusions_valid);
        return $bot_detector_exclusions_valid !== false ? $bot_detector_exclusions_valid : '{}';
    }

    /**
     * Get the list of fired bot detector exclusions from the cookie.
     *
     * @return string The value of the 'ct_bot_detector_form_exclusion' cookie, or an empty string if the cookie is not set.
     */
    public static function getFiredExclusions()
    {
        return Cookie::getString('ct_bot_detector_form_exclusion');
    }

    /**
     * Check if the current request should skip the bot detector flow, e.g. Oxygen builder requests.
     *
     * @return bool True if the request is a NoScript flow, false otherwise.
     */
    public static function isNoScripFlow()
    {
        if (apbct_is_plugin_active('oxygen/functions.php') && Get::getBool('ct_builder')) {
            return true;
        }

        return false;
    }

    /**
     * Return bot detector frontend data log from Alt Sessions if data found.
     * Format: JSON.
     *
     * @return string JSON encoded bot detector frontend data log.
     */
    public static function getFrontendDataLog()
    {
        $result = array(
            'plugin_status' => 'OK',
            'error_msg' => '',
            'apbct_browser_state' => self::$default_browser_state,
        );

        if (Constant::is(Constant::APBCT_SERVICE__DO_NOT_COLLECT_FRONTEND_DATA_LOGS)) {
            $result['error_msg'] = 'bot detector logs collection is disabled via constant definition';
            $json = @json_encode($result);
            return $json ?: 'JSON_ENCODE_ERROR';
        }

        try {
            if ( ! self::isEnabled() ) {
                throw new \Exception('bot detector library usage is disabled');
            }

            $sources = array(
                'request_parameters' => RequestParameters::get('apbct_browser_state'), // NoCookie hidden field or alternative sessions are both covered by RequestParameters
                'post_browser_state' => Post::getString('apbct_browser_state'), // XHR interception transport - look at the POST directly
                'post_data' => Post::getArray('data'), //XHR interception transport - the state could be wrapped to the data[] array
            );

            $browser_state = self::getBrowserState($sources);

            if ( empty($browser_state) ) {
                throw new \Exception('no browser state provided by the transport');
            }

            $result['apbct_browser_state'] = $browser_state;
        } catch (\Exception $e) {
            $result['plugin_status'] = 'ERROR';
            $result['error_msg'] = $e->getMessage();
        }

        // Return the result as a JSON encoded string
        $json = @json_encode($result);
        return $json ?: 'JSON_ENCODE_ERROR';
    }

    /**
     * Get the browser state gathered by the plugin JS.
     *
     * The state comes with the transport the site is currently configured to use:
     * NoCookie hidden field or alternative sessions are both covered by RequestParameters,
     * the XHR interception passes the state as a plain POST field.
     * Native cookies do not store the state in cookies (cookie size limits), but the JS interceptors may still POST it.
     *
     * @return array Empty array if no state provided.
     */
    public static function getBrowserState(array $sources = array())
    {
        $raw_state = null;

        if (!empty($sources['request_parameters'])) {
            $raw_state = $sources['request_parameters'];
        }

        // XHR interception transport - look at the POST directly
        if ( empty($raw_state) ) {
            $raw_state = $sources['post_browser_state'] ?? null;
        }

        // XHR interception transport - the state could be wrapped to the data[] array
        if ( empty($raw_state) ) {
            $post_data = $sources['post_data'] ?? null;
            if ( is_array($post_data) && ! empty($post_data['apbct_browser_state']) ) {
                $raw_state = $post_data['apbct_browser_state'];
            }
        }

        if ( empty($raw_state) ) {
            return array();
        }

        $state = null;

        // request param can be string or array, depending on the transport used.
        // The XHR interception transport passes the state as a plain POST field, which is a string.
        if (is_string($raw_state)) {
            $state = @json_decode($raw_state, true);

            if (null === $state) {
                $raw_state = stripslashes($raw_state);
                $state = @json_decode($raw_state, true);
            }
        } elseif (is_array($raw_state)) {
            $state = $raw_state;
        }

        if ( ! $state ) {
            return array();
        }

        // log is mixed indeed, try to convert to array, if not - return empty array
        $fd_log = array();
        if (isset($state['frontend_data_log'])) {
            if (is_array($state['frontend_data_log'])) {
                $fd_log = $state['frontend_data_log'];
            } elseif (is_string($state['frontend_data_log'])) {
                $fd_log = @json_decode($state['frontend_data_log'], true);
                $fd_log = is_array($fd_log) ? $fd_log : array();
            }
        }

        return array(
            'frontend_data_log' => $fd_log,
            'botd_logic_loaded' => TT::getArrayValueAsInt($state, 'botd_logic_loaded'),
            'botd_wrapper_loaded' => TT::getArrayValueAsInt($state, 'botd_wrapper_loaded'),
        );
    }
}
