<?php

namespace Cleantalk\ApbctWP\ExternalForms;

use Cleantalk\ApbctWP\Variables\Post;

/**
 * Keep JS relay, Capture Buffer and direct AJAX submissions mutually exclusive.
 *
 * Field names identify a flow; they do not authenticate its sender or destination.
 */
class Submission
{
    const JS_ACTION = 'cleantalk_hidden_action';
    const JS_METHOD = 'cleantalk_hidden_method';
    const BUFFER_ACTION = 'cleantalk_buffer_action';
    const BUFFER_METHOD = 'cleantalk_buffer_method';
    const BUFFER_SIGNATURE = 'cleantalk_buffer_signature';
    const AJAX_ACTION = 'cleantalk_force_ajax_check';

    /**
     * Recognize incomplete buffer requests too, including a signature without action/method.
     *
     * @return bool
     */
    public static function hasBufferFields()
    {
        return Post::has(self::BUFFER_ACTION)
            || Post::has(self::BUFFER_METHOD)
            || Post::has(self::BUFFER_SIGNATURE);
    }

    /**
     * Field presence, including empty or malformed values, identifies a relay flow.
     *
     * The AJAX caller flag also identifies requests whose action has already been removed.
     *
     * @param bool $ajax Whether the caller is the direct AJAX integration.
     * @return bool
     */
    public static function hasMixedFlows($ajax = false)
    {
        $js = Post::has(self::JS_ACTION) || Post::has(self::JS_METHOD);
        $buffer = self::hasBufferFields();
        $ajax = $ajax || Post::getRaw('action') === self::AJAX_ACTION;

        return (int)$js + (int)$buffer + (int)$ajax > 1;
    }

    /**
     * Stop mixed-flow requests with HTTP 400 before their metadata is consumed.
     *
     * Preserve the JSON response contract for AJAX callers and HTML for other requests.
     *
     * @param bool $ajax Whether the caller is the direct AJAX integration.
     * @return bool
     */
    public static function validate($ajax = false)
    {
        if ( ! self::hasMixedFlows($ajax) ) {
            return true;
        }

        $message = __('Conflicting external form submission fields.', 'cleantalk-spam-protect');
        if ( $ajax || apbct_is_ajax() || Post::getRaw('action') === self::AJAX_ACTION ) {
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
    }
}
