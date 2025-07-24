<?php

namespace Cleantalk\ApbctWP;

/**
 * Test logger class.
 * Look for logs there: wp-content/uploads/cleantalk_logs/
 */
class ApbctLog
{
    const HOOKS_PREFIX = 'apbct_log_';
    public static function originPOST()
    {
        static $singleton;
        if ( ! $singleton ) {
            $singleton = new ApbctLogService(self::HOOKS_PREFIX . __FUNCTION__, function ($_post) {
                if ( empty($_post) ) {
                    return;
                }
                if ( isset($_post['action']) ) {
                    if ( $_post['action'] === 'spbc_get_authorized_admins' ) {
                        return; // Skip debug action
                    }
                    if ( $_post['action'] === 'heartbeat' ) {
                        return; // Skip debug action
                    }
                }
                if ( isset($_post['cookies']) ) {
                    return; // Skip debug action
                }
                return print_r($_post, true);
            });
        }
        return $singleton;
    }

    public static function customVar()
    {
        static $singleton;
        if ( ! $singleton ) {
            $singleton = new ApbctLogService(self::HOOKS_PREFIX . __FUNCTION__, function ($_var) {
                if ( empty($_var) ) {
                    return;
                }
                return print_r($_var, true);
            });
        }
        return $singleton;
    }
}
