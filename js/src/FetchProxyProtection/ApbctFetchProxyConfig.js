/**
 * Config for FetchProxy integrations
 */
const ApbctFetchProxyConfig = {
    'mailchimp': {
        selector: '.mcforms-wrapper',
        urlPattern: 'mcf-integrations-mcmktg.mlchmpcompprduse2.iks2.a.intuit.com/gateway/receive',
        externalForm: true,
        action: 'cleantalk_force_mailchimp_shadowroot_check',
        callbackAllow: false,
        callbackBlock: ApbctFetchProxyCallbacks.mailchimpBlock,
    },
    'otterform': {
        selector: '.otter-form__container',
        urlPattern: 'otter/v1/form/frontend',
        externalForm: false,
        action: 'cleantalk_force_otterform_check',
        callbackAllow: false,
        callbackBlock: false,
    },
};