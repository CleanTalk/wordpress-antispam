/**
 * Config for ShadowRoot integrations
 */
const ApbctShadowRootConfig = {
    'mailchimp': {
        selector: '.mcforms-wrapper',
        urlPattern: 'mcf-integrations-mcmktg.mlchmpcompprduse2.iks2.a.intuit.com/gateway/receive',
        externalForm: true,
        action: 'cleantalk_force_mailchimp_shadowroot_check',
        callbackAllow: false,
        callbackBlock: ApbctShadowRootCallbacks.mailchimpBlock,
    },
};