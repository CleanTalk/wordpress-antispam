<?php

namespace Cleantalk\ApbctWP;

use Cleantalk\Common\TT;

class AJAXService
{
    /**
     * Nonce name for admin area.
     * @var string
     */
    public static $admin_nonce_id = 'ct_secret_nonce';
    /**
     * Nonce name for public area.
     * @var string
     */
    public static $public_nonce_id = 'ct_secret_stuff';
    /**
     * List of AJAX hooks and handlers added via addAction().
     * @var array
     */
    private $hooks_list = array();

    /**
     * Returns admin area CleanTalk nonce for AJAX requests.
     * @return false|string
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function getAdminNonce()
    {
        return wp_create_nonce(static::$admin_nonce_id);
    }

    /**
     * Returns public area CleanTalk nonce for AJAX requests.
     * @return false|string
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function getPublicNonce()
    {
        return wp_create_nonce(static::$public_nonce_id);
    }

    /**
     * Checks direct nonce method.
     * @return void
     */
    private static function checkNonce($nonce, $nonce_query_arg_source)
    {
        check_ajax_referer($nonce, $nonce_query_arg_source);
    }

    /**
     * Checks admin nonce method.
     * @param string|false $nonce_query_arg_source
     * @return void
     */
    public static function checkPublicNonce($nonce_query_arg_source = false)
    {
        static::checkNonce(static::$public_nonce_id, $nonce_query_arg_source);
    }

    /**
     * Checks public nonce method.
     * @param string|false $nonce_query_arg_source
     * @return void
     */
    public static function checkAdminNonce($nonce_query_arg_source = false)
    {
        static::checkNonce(static::$admin_nonce_id, $nonce_query_arg_source);
    }

    /**
     * Checks nonce method restricting non admins.
     * @param string|false $nonce_query_arg_source
     * @return void
     */
    public static function checkNonceRestrictingNonAdmins($nonce_query_arg_source = false)
    {
        check_ajax_referer(static::$admin_nonce_id, $nonce_query_arg_source);

        if ( ! current_user_can('manage_options') ) {
            wp_die('-1', 403);
        }
    }

    /**
     * Wrapper for PUBLIC add_action(). See addAction() for more details.
     *
     * @param string $hook_suffix
     * @param string|array  $callback
     * @param string $mode
     * @param int $priority - priority
     * @param int $accepted_args - accepted args
     *
     * @return void
     */
    public function addPublicAction($hook_suffix, $callback, $mode = 'both', $priority = 10, $accepted_args = 1)
    {
        $this->addAction($hook_suffix, $callback, true, $mode, $priority, $accepted_args);
    }

    /**
     * Wrapper for ADMIN add_action(). See addAction() for more details.
     *
     * @param string $hook_suffix
     * @param string|array  $callback
     * @param string $mode
     * @param int $priority - priority
     * @param int $accepted_args - accepted args
     *
     * @return void
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function addAdminAction($hook_suffix, $callback, $mode = 'both', $priority = 10, $accepted_args = 1)
    {
        $this->addAction($hook_suffix, $callback, false, $mode, $priority, $accepted_args);
    }

    /**
     * Wrapper for add_action().
     * @param string $hook_suffix - suffix after `wp_ajax_` and `wp_ajax_nopriv_`
     * @param string|array $callback - callable function or array with class and method
     * @param bool $is_public - is this action public or not
     * @param string $mode - 'priv', 'both'
     * @param int $priority - priority
     * @param int $accepted_args - accepted args
     *
     * @return void
     * @throws \InvalidArgumentException
     */
    private function addAction($hook_suffix, $callback, $is_public, $mode, $priority, $accepted_args)
    {
        $handler_class = false;
        if (is_array($callback) && isset($callback[0], $callback[1])) {
            $handler_class = $callback[0];
            $handler      = TT::toString($callback[1]);
            if (empty($handler_class) || (is_string($handler_class) && !class_exists($handler_class))) {
                throw new \InvalidArgumentException('AJAX handler class does not exist - ' . $handler_class);
            }
            if (!empty($handler) && !method_exists($handler_class, $handler)) {
                throw new \InvalidArgumentException('AJAX handler method does not exist - ' . $handler_class . '::' . $handler);
            }
        } else {
            if (!is_string($callback) || !function_exists($callback)) {
                throw new \InvalidArgumentException('AJAX handler is empty or not callable');
            }
        }

        if (!in_array($mode, array('no_priv', 'priv', 'both'))) {
            $mode = 'both';
        }

        if ($mode === 'both' || $mode === 'priv') {
            $hook = 'wp_ajax_' . $hook_suffix;
            $this->hooks_list[$hook] = array(
                'ajax_handler' => $callback,
                'is_public' => $is_public,
                'handler_class' => $handler_class
            );
            add_action($hook, array($this, 'handleAjaxRequest'), $priority, $accepted_args);
        }

        if ($mode === 'both' || $mode === 'no_priv') {
            $hook_no_private = 'wp_ajax_nopriv_' . $hook_suffix;
            $this->hooks_list[$hook_no_private] = array(
                'ajax_handler' => $callback,
                'is_public' => $is_public,
                'handler_class' => $handler_class
            );
            add_action($hook_no_private, array($this, 'handleAjaxRequest'), $priority, $accepted_args);
        }
    }

    /**
     * Universal AJAX handler for registered actions.
     *
     * Performs a nonce check, determines and calls the required callback (function or method of the class)
     * only when an AJAX request with the appropriate action is received.
     *
     * @param mixed $args
     *
     * @return void
     */
    public function handleAjaxRequest($args)
    {
        $current_hook_data = isset($this->hooks_list[current_action()])
            ? $this->hooks_list[current_action()]
            : null;
        if ( !empty($current_hook_data) && is_array($current_hook_data) ) {
            $ajax_handler = TT::getArrayValueAsString($current_hook_data, 'ajax_handler');
            $is_public = TT::getArrayValueAsBool($current_hook_data, 'is_public');
            $handler_class = TT::getArrayValueAsString($current_hook_data, 'handler_class');
            // nonce check
            $is_public
                ? static::checkPublicNonce()
                : static::checkAdminNonce();

            if (isset($current_hook_data['ajax_handler']) &&
                is_array($current_hook_data['ajax_handler']) &&
                isset($current_hook_data['ajax_handler'][1]) &&
                class_exists($handler_class)
            ) {
                $method = $current_hook_data['ajax_handler'][1];

                // Use Reflection to check if method is static
                $reflection = new \ReflectionMethod($handler_class, $method);
                $is_static = $reflection->isStatic();

                if ($is_static) {
                    $handler_class::$method($args);
                } else {
                    $instance = new $handler_class();
                    $instance->$method($args);
                }
            } else if (is_callable($ajax_handler)) {
                call_user_func($ajax_handler, $args);
            }
        }
    }

    /**
     * List of methods to be called only(!) on AJAX request
     */


    /**
     * Outputs JS key.
     * @return void
     * @psalm-suppress PossiblyUnusedMethod
     */
    public static function getJSKeys()
    {
        die(json_encode(array('js_key' => ct_get_checkjs_value())));
    }
}
