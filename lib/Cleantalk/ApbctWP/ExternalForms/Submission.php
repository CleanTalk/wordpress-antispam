<?php

namespace Cleantalk\ApbctWP\ExternalForms;

class Submission
{
    const JS_ACTION = 'cleantalk_hidden_action';
    const JS_METHOD = 'cleantalk_hidden_method';
    const BUFFER_ACTION = 'cleantalk_buffer_action';
    const BUFFER_METHOD = 'cleantalk_buffer_method';
    const AJAX_ACTION = 'cleantalk_force_ajax_check';

    /**
     * Field presence, including empty or malformed values, identifies a relay flow.
     *
     * @param bool $ajax Whether the caller is the direct AJAX integration.
     * @return bool
     */
    public static function hasMixedFlows($ajax = false)
    {
        $js = array_key_exists(self::JS_ACTION, $_POST) || array_key_exists(self::JS_METHOD, $_POST);
        $buffer = array_key_exists(self::BUFFER_ACTION, $_POST) || array_key_exists(self::BUFFER_METHOD, $_POST);
        $ajax = $ajax || (isset($_POST['action']) && $_POST['action'] === self::AJAX_ACTION);

        return (int)$js + (int)$buffer + (int)$ajax > 1;
    }

    /**
     * @param bool $ajax Whether the caller is the direct AJAX integration.
     * @return bool
     */
    public static function validate($ajax = false)
    {
        if ( ! self::hasMixedFlows($ajax) ) {
            return true;
        }

        $message = __('Conflicting external form submission fields.', 'cleantalk-spam-protect');
        if ( $ajax || apbct_is_ajax() || (isset($_POST['action']) && $_POST['action'] === self::AJAX_ACTION) ) {
            wp_send_json(
                array(
                    'apbct' => array(
                        'blocked' => true,
                        'comment' => $message,
                        'stop_script' => apbct__stop_script_after_ajax_checking(),
                    ),
                ),
                400
            );
        } else {
            wp_die(esc_html($message), '', array('response' => 400));
        }

        return false;
    }
}
