<?php

namespace Cleantalk\Antispam;

use Cleantalk\ApbctWP\Variables\Server;

class Integrations
{
    private $integrations;

    /**
     * Integrations constructor.
     *
     * @param array $integrations
     * @param array $apbct_settings
     */
    public function __construct($integrations, $apbct_settings)
    {
        $this->integrations = $integrations;

        foreach ($this->integrations as $_integration_name => $integration_info) {
            //validate apbct settings required to run integration
            $integration_settings = $integration_info['setting'] ?: array();
            if ( is_scalar($integration_settings) ) {
                $integration_settings = array($integration_settings);
            }
            foreach ($integration_settings as $setting) {
                //if any option is disabled, skip integration run
                if ( empty($apbct_settings[$setting]) ) {
                    continue(2);
                }
            }

            if ( $integration_info['ajax'] ) {
                if ( is_array($integration_info['hook']) ) {
                    foreach ( $integration_info['hook'] as $hook ) {
                        add_action('wp_ajax_' . $hook, array($this, 'checkSpam'), 1);
                        add_action('wp_ajax_nopriv_' . $hook, array($this, 'checkSpam'), 1);
                    }
                } else {
                    add_action('wp_ajax_' . $integration_info['hook'], array($this, 'checkSpam'), 1);
                    add_action('wp_ajax_nopriv_' . $integration_info['hook'], array($this, 'checkSpam'), 1);
                }
            }

            if ( !$integration_info['ajax'] || !empty($integration_info['ajax_and_post']) ) {
                if ( is_array($integration_info['hook']) ) {
                    foreach ( $integration_info['hook'] as $hook ) {
                        add_action($hook, array($this, 'checkSpam'));
                    }
                } else {
                    add_action($integration_info['hook'], array($this, 'checkSpam'));
                }
            }
        }
    }

    /**
     * @param $argument
     * @param string $set_current_integration
     * @return true|mixed
     * @psalm-suppress UnusedVariable
     */
    public function checkSpam($argument, $set_current_integration = '')
    {
        global $cleantalk_executed;

        $return_arg = null;

        // Getting current integration name
        $current_integration = !empty($set_current_integration)
            ? $set_current_integration
            : $this->getCurrentIntegrationTriggered(current_filter());
        if ( $current_integration ) {
            // Instantiate the integration object
            $class = '\\Cleantalk\\Antispam\\Integrations\\' . $current_integration;
            if ( class_exists($class) ) {
                $integration = new $class();
                if ( ! ($integration instanceof \Cleantalk\Antispam\Integrations\IntegrationBase) ) {
                    // @ToDo have to handle an error
                    do_action(
                        'apbct_skipped_request',
                        __FILE__ . ' -> ' . __FUNCTION__ . '():' . __LINE__,
                        array('Integration is not instanse of IntegrationBase class.')
                    );

                    return true;
                }

                /**
                 * Run prepare actions.
                 */
                $prepare_actions_result = $integration->doPrepareActions($argument);
                if ( !is_bool($prepare_actions_result) ) {
                    //if integration returns not a bool value on this state - exit and return modified argument
                    return $prepare_actions_result;
                }

                if ( $prepare_actions_result === false ) {
                    //if integration returns false on this state - exit and return income argument
                    return $argument;
                }

                /**
                 * Data collection
                 */
                // If integration provided it's own method - run this
                $integration_base_call_data = $integration->collectBaseCallData();

                // old way legacy
                $data = $integration->getDataForChecking($argument);

                if ( ! is_null($data) ) {
                    /**
                     * Run actions before base call
                     */
                    $integration->doActionsBeforeBaseCall($argument);

                    /**
                     * Select base call data source
                     */
                    if (!empty($integration_base_call_data)) {
                        // if integration has very own way to get complete base_call_data
                        $base_call_data = $integration_base_call_data;
                    } else {
                        $sender_info = [];
                        if ( ! empty($data['sender_url']) ) {
                            $sender_info['sender_url'] = $data['sender_url'];
                        }
                        if ( ! empty($data['emails_array']) ) {
                            $sender_info['sender_emails_array'] = $data['emails_array'];
                        }
                        // common case
                        $base_call_data = array(
                            'message'         => ! empty($data['message']) ? json_encode($data['message']) : '',
                            'sender_email'    => ! empty($data['email']) ? $data['email'] : '',
                            'sender_nickname' => ! empty($data['nickname']) ? $data['nickname'] : '',
                            'sender_info'     => $sender_info,
                            'event_token' => ! empty($data['event_token']) ? $data['event_token'] : '',
                            'post_info'       => array(
                                'comment_type' => 'contact_form_wordpress_' . strtolower($current_integration),
                                'post_url'     => Server::get('HTTP_REFERER'),
                                // Page URL must be an previous page
                            ),
                        );
                    }

                    // Set registration flag - will be used to select method
                    $reg_flag = (
                        !empty($base_call_data['register']) ||
                        (
                            isset($data['register']) &&
                            $data['register'] === true
                        )
                    );

                    /**
                     * Run base call
                     */
                    $base_call_result = apbct_base_call($base_call_data, $reg_flag);

                    // Provide $base_call_result to integration
                    $integration->base_call_result = $base_call_result;


                    $ct_result = isset($base_call_result['ct_result']) ? $base_call_result['ct_result'] : '';
                    $cleantalk_executed = true;

                    /**
                     * Run actions before allow/deny logic run
                     */
                    $integration->doActionsBeforeAllowDeny($argument);

                    /**
                     * Actions on deny.
                     */
                    if (
                        is_object($ct_result) && $ct_result->allow == 0
                    ) {
                        // Do blocking if it is a spam
                        $return_arg =  $integration->doBlock($ct_result->comment);
                    }

                    /**
                     * Actions on allow.
                     */
                    if ( is_object($ct_result) && $ct_result->allow != 0 && method_exists($integration, 'allow') ) {
                        $return_arg = $integration->allow();
                    }
                } else {
                    // @ToDo have to handle an error
                }

                /**
                 * Return arg if provided.
                 */
                $return_arg = isset($return_arg) ? $return_arg : $argument;

                /**
                 *  Final actions
                 */
                return $integration->doFinalActions($return_arg);
            }
        }
        return true;
    }

    private function getCurrentIntegrationTriggered($hook)
    {
        if ( $hook !== false ) {
            foreach ( $this->integrations as $integration_name => $integration_info ) {
                if ( is_array($integration_info['hook']) ) {
                    foreach ( $integration_info['hook'] as $integration_hook ) {
                        if ( strpos($hook, $integration_hook) !== false ) {
                            return $integration_name;
                        }
                    }
                } else {
                    if ( strpos($hook, $integration_info['hook']) !== false ) {
                        return $integration_name;
                    }
                }
            }
        }

        return false;
    }
}
