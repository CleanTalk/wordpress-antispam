<?php

namespace Cleantalk\Antispam;

class Integrations
{
    private $integrations;

    /**
     * Integrations constructor.
     *
     * @param array $integrations
     * @param array $settings
     */
    public function __construct($integrations, $settings)
    {
        $this->integrations = $integrations;

        foreach ( $this->integrations as $_integration_name => $integration_info ) {
            if ( empty($settings[$integration_info['setting']]) ) {
                continue;
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
            } else {
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
     *
     * @psalm-suppress UnusedVariable
     */
    public function checkSpam($argument)
    {
        global $cleantalk_executed;

        // Getting current integration name
        $current_integration = $this->getCurrentIntegrationTriggered(current_filter());
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

                    return;
                }
                // Run data collecting for spam checking
                $data = $integration->getDataForChecking($argument);
                if ( ! is_null($data) ) {
                    // Go spam checking
                    $base_call_result = apbct_base_call(
                        array(
                            'message'         => ! empty($data['message']) ? json_encode($data['message']) : '',
                            'sender_email'    => ! empty($data['email']) ? $data['email'] : '',
                            'sender_nickname' => ! empty($data['nickname']) ? $data['nickname'] : '',
                            'sender_info'     => ! empty($data['sender_url']) ? array('sender_url' => $data['sender_url']) : '',
                            'post_info'       => array(
                                'comment_type' => 'contact_form_wordpress_' . strtolower($current_integration),
                                'post_url'     => apbct_get_server_variable('HTTP_REFERER'),
                                // Page URL must be an previous page
                            ),
                        ),
                        isset($data['register']) ? true : false
                    );

                    $ct_result = $base_call_result['ct_result'];

                    $cleantalk_executed = true;

                    if ( $ct_result->allow == 0 ) {
                        // Do blocking if it is a spam
                        return $integration->doBlock($ct_result->comment);
                    }

                    if ( $ct_result->allow != 0 && method_exists($integration, 'allow') ) {
                        return $integration->allow();
                    }
                } else {
                    // @ToDo have to handle an error
                }
            }
        }
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
