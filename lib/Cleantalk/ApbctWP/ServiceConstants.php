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
    /**
     * @var ApbctConstant
     * @psalm-suppress PossiblyUnusedProperty
     */
    public $self_owned_access_key;
     /**
     * @var ApbctConstant
     * @psalm-suppress PossiblyUnusedProperty
     */
    public $place_public_js_scripts_in_footer;
    /**
     * @var ApbctConstant
     * @psalm-suppress PossiblyUnusedProperty
     */
    public $whitelabel_faq_link;
    /**
     * @var ApbctConstant
     * @psalm-suppress PossiblyUnusedProperty
     */
    public $whitelabel_plugin_description;
    /**
     * @var ApbctConstant
     * @psalm-suppress PossiblyUnusedProperty
     * @deprecated
     */
    public $whitelabel_enabled;
    /**
     * @var ApbctConstant
     * @psalm-suppress PossiblyUnusedProperty
     * @deprecated
     */
    public $whitelabel_product_name;
    /**
     * @var ApbctConstant
     * @psalm-suppress PossiblyUnusedProperty
     */
    public $sfw_force_direct_update;
    /**
     * @var ApbctConstant
     * @psalm-suppress PossiblyUnusedProperty
     */
    public $disable_blocking_title;
    /**
     * @var ApbctConstant
     * @psalm-suppress PossiblyUnusedProperty
     */
    public $skip_on_approved_comments_number;
    /**
     * @var ApbctConstant
     * @psalm-suppress PossiblyUnusedProperty
     */
    public $predefined_api_url;
    /**
     * @var ApbctConstant
     * @psalm-suppress PossiblyUnusedProperty
     */
    public $do_not_collect_frontend_data_logs;

    public function __construct()
    {
        $this->disable_empty_email_exception = new ApbctConstant(
            array(
                'APBCT_SERVICE__DISABLE_EMPTY_EMAIL_EXCEPTION',
            ),
            'bool',
            'If set, do not skip POST data from check if no email address found'
        );
        $this->skip_anticrawler_on_rss_feed = new ApbctConstant(
            array(
                'APBCT_SERVICE__SKIP_ANTICRAWLER_ON_RSS_FEED',
                'APBCT_ANTICRAWLER_EXLC_FEED',
            ),
            'bool',
            'Pass anti-crawler check on RSS feed service'
        );
        $this->set_ajax_route_type = new ApbctConstant(
            array(
                'APBCT_SERVICE__SET_AJAX_ROUTE_TYPE',
                'APBCT_SET_AJAX_ROUTE_TYPE',
            ),
            'string',
            'Provides AJAX route type'
        );
        $this->self_owned_access_key = new ApbctConstant(
            array(
                'APBCT_SERVICE__SELF_OWNED_ACCESS_KEY',
                'CLEANTALK_ACCESS_KEY',
            ),
            'string',
            'Provides hardcoded CleanTalk access key to use.'
        );
        $this->place_public_js_scripts_in_footer = new ApbctConstant(
            array(
                'APBCT_SERVICE__PLACE_PUBLIC_JS_SCRIPTS_IN_FOOTER',
                'CLEANTALK_PLACE_PUBLIC_JS_SCRIPTS_IN_FOOTER',
            ),
            'bool',
            'If isset, any public scripts will be placed in a page footer.'
        );
        $this->whitelabel_faq_link = new ApbctConstant(
            array(
                'APBCT_SERVICE__WHITELABEL_FAQ_LINK',
                'APBCT_WHITELABEL_FAQ_LINK',
            ),
            'string',
            'Provides whitelabel-mode FAQ link'
        );
        $this->whitelabel_plugin_description = new ApbctConstant(
            array(
                'APBCT_SERVICE__WHITELABEL_PLUGIN_DESCRIPTION',
                'APBCT_WHITELABEL_PLUGIN_DESCRIPTION',
            ),
            'string',
            'Provides whitelabel-mode plugin description.'
        );
        $this->sfw_force_direct_update = new ApbctConstant(
            array(
                'APBCT_SERVICE__SFW_FORCE_DIRECT_UPDATE',
                'APBCT_SFW_FORCE_DIRECT_UPDATE',
            ),
            'bool',
            'If defined, SFW update mode is always DIRECT. Helpful if update queue fails due remote calls.'
        );
        $this->disable_blocking_title = new ApbctConstant(
            array(
                'APBCT_SERVICE__DISABLE_BLOCKING_TITLE',
                'CLEANTALK_DISABLE_BLOCKING_TITLE',
            ),
            'bool',
            'If defined, no title will be provided for blocking page.'
        );
        $this->skip_on_approved_comments_number = new ApbctConstant(
            array(
                'APBCT_SERVICE__SKIP_ON_APPROVED_COMMENTS_NUMBER',
                'CLEANTALK_CHECK_COMMENTS_NUMBER',
            ),
            'int',
            'Redefine how many comments should be approved before skip checking.'
        );
        $this->whitelabel_enabled = new ApbctConstant(
            array(
                'APBCT_SERVICE__WHITELABEL_ENABLED',
                'APBCT_WHITELABEL',
            ),
            'bool',
            'If defined, plugin will be in whitelabel mode.'
        );
        $this->whitelabel_product_name = new ApbctConstant(
            array(
                'APBCT_SERVICE__WHITELABEL_PRODUCT_NAME',
                'APBCT_WHITELABEL_NAME',
            ),
            'string',
            'Provides product name for whitelabel mode.'
        );
//        todo this won't work because constant called before State is initialized
//        $this->predefined_cleantalk_server_url= new ApbctConstant(
//            array(
//                'APBCT_SERVICE__PREDEFINED_CLEANTALK_SERVER_URL',
//                'CLEANTALK_SERVER',
//            ),
//            'Provides own URL of API server.'
//        );
        $this->do_not_collect_frontend_data_logs = new ApbctConstant(
            array(
                'APBCT_SERVICE__DO_NOT_COLLECT_FRONTEND_DATA_LOGS',
                'APBCT_DO_NOT_COLLECT_FRONTEND_DATA_LOGS',
            ),
            'bool',
            'If defined, no frontend-data logs will be collected. Debugging case usage.'
        );
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
