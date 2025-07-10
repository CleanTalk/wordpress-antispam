<?php

namespace Cleantalk\ApbctWP;

class ServiceConstants
{
    /**
     * @var ApbctConstant
     * @psalm-suppress PossiblyUnusedProperty
     */
    public $disable_empty_email_exception;
    /**
     * @var ApbctConstant
     * @psalm-suppress PossiblyUnusedProperty
     */
    public $skip_anticrawler_on_rss_feed;
    /**
     * @var ApbctConstant
     * @psalm-suppress PossiblyUnusedProperty
     */
    public $set_ajax_route_type;

    public function __construct()
    {
        $this->disable_empty_email_exception = new ApbctConstant(
            array(
                'APBCT_SERVICE__DISABLE_EMPTY_EMAIL_EXCEPTION',
            ),
            'If set, do not skip POST data from check if no email address found'
        );
        $this->skip_anticrawler_on_rss_feed = new ApbctConstant(
            array(
                'APBCT_SERVICE__SKIP_ANTICRAWLER_ON_RSS_FEED',
                'APBCT_ANTICRAWLER_EXLC_FEED',
            ),
            'Pass anti-crawler check on RSS feed service'
        );
        $this->set_ajax_route_type = new ApbctConstant(
            array(
                'APBCT_SERVICE__SET_AJAX_ROUTE_TYPE',
                'APBCT_SET_AJAX_ROUTE_TYPE',
            ),
            'Provides AJAX route type'
        );
//        $accepted_constants = array(
//            // needs to be refactored
//            'APBCT_SERVICE__SELF_OWNED_ACCESS_KEY' => array(
//                'deprecated_name' => 'CLEANTALK_ACCESS_KEY',
//                'description' => 'Provides user own access key.',
//            ),
//            'APBCT_SERVICE__PLACE_PUBLIC_JS_SCRIPTS_IN_FOOTER' => array(
//                'deprecated_name' => 'CLEANTALK_PLACE_PUBLIC_JS_SCRIPTS_IN_FOOTER',
//                'description' => 'If defined, public scripts will be placed in footer instead of header',
//            ),
//            'APBCT_SERVICE__WHITELABEL_FAQ_LINK' => array(
//                'deprecated_name' => 'APBCT_WHITELABEL_FAQ_LINK',
//                'description' => 'Provides whitelabel-mode FAQ link',
//            ),
//            'APBCT_SERVICE__WHITELABEL_PLUGIN_DESCRIPTION' => array(
//                'deprecated_name' => 'APBCT_WHITELABEL_PLUGIN_DESCRIPTION',
//                'description' => 'Provides whitelabel-mode plugin description',
//            ),
//            'APBCT_SERVICE__SFW_FORCE_DIRECT_UPDATE' => array(
//                'deprecated_name' => 'APBCT_SFW_FORCE_DIRECT_UPDATE',
//                'description' => 'If defined, SFW update mode is always DIRECT',
//            ),
//            'APBCT_SERVICE__DISABLE_BLOCKING_TITLE' => array(
//                'deprecated_name' => 'CLEANTALK_DISABLE_BLOCKING_TITLE',
//                'description' => 'If defined, no title will be provided for blocking page.',
//            ),
//            'APBCT_SERVICE__CHECK_COMMENTS_NUMBER' => array(
//                'deprecated_name' => 'CLEANTALK_CHECK_COMMENTS_NUMBER',
//                'description' => 'Provides how many comments should be approved before skip checking',
//            ),
//            'APBCT_SERVICE__WHITELABEL' => array(
//                'deprecated_name' => 'APBCT_WHITELABEL',
//                'description' => 'If defined, plugin will be in whitelabel mode',
//            ),
//            'APBCT_SERVICE__WHITELABEL_NAME' => array(
//                'deprecated_name' => 'APBCT_WHITELABEL_NAME',
//                'description' => 'Provides product name for whitelabel mode',
//            ),
//            'APBCT_SERVICE__API_URL' => array(
//                'deprecated_name' => 'CLEANTALK_API_URL',
//                'description' => 'Provides own URL of API server',
//            ),
//            'APBCT_SERVICE__DO_NOT_COLLECT_FRONTEND_DATA_LOGS' => array(
//                'deprecated_name' => 'APBCT_DO_NOT_COLLECT_FRONTEND_DATA_LOGS',
//                'description' => 'If defined, no frontend-data logs will be collected. Debugging case usage.',
//            ),
//        );
    }

    /**
     * Get all service constants definitions
     * @return array[]
     */
    public function getDefinitions()
    {
        $result = [];
        foreach (get_object_vars($this) as $_key => $value) {
            if ($value instanceof ApbctConstant) {
                $result[] = $value->getData();
            }
        }
        return $result;
    }

    /**
     * Return active definitions.
     * @return array
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function getDefinitionsActive()
    {
        $active = array();
        foreach ($this->getDefinitions() as $constant) {
            if (!empty($constant['is_defined'])) {
                $active[] = $constant;
            }
        }
        return $active;
    }
}
