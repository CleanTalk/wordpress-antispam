import React from 'react';
import LogoSvgForWizard from '../LogoSvgForWizard';

const {__, sprintf} = wp.i18n;

export default function SuccessInterface({settingsLink}) {
    const successTitle = __('Everything is good!', 'cleantalk-spam-protect');
    const successText = sprintf(
        __('The plugin is ready to protect %s. After closing this window, your site will be fully protected against spam.', 'cleantalk-spam-protect'),
        window.location.host,
    );

    return (
        <div className="apbct-signup-wizard">
            <div className="apbct-signup-wizard-card apbct-signup-wizard-card-success">
                <div className={'apbct-signup-wizard-success-head'}>
                    <LogoSvgForWizard />
                    <a href={settingsLink} className="apbct-signup-wizard-cloze-window">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M18 6L6 18" stroke="#646464" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
                            <path d="M6 6L18 18" stroke="#646464" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
                        </svg>
                    </a>
                </div>
                <div className={'apbct-signup-wizard-card-inner'}>
                    <p className={'apbct-signup-wizard-title'}>
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <g clipPath="url(#clip0_6505_11913)">
                                <path d="M16.1089 14.6546L1.21853 19.6303C1.10022 19.6699 0.973204 19.6758 0.851754 19.6472C0.730304 19.6186 0.619229 19.5567 0.531004 19.4685C0.44278 19.3803 0.380901 19.2692 0.352318 19.1477C0.323735 19.0263 0.32958 18.8993 0.369196 18.781L5.34486 3.89062L16.1089 14.6546Z" fill="#FDC70E"/>
                                <path d="M16.1091 14.658C16.1091 14.658 13.9161 14.1613 9.88113 10.1223C5.95313 6.19796 5.37313 4.00896 5.34546 3.89463V3.89062C5.34546 3.89062 7.54246 4.38762 11.5788 8.42396C15.6151 12.4603 16.1091 14.658 16.1091 14.658Z" fill="#D39518"/>
                                <path d="M6.48664 17.8723L4.27998 18.609C3.81664 18.1956 3.32331 17.7323 2.79664 17.2056C2.26998 16.679 1.80664 16.1856 1.39331 15.7223L2.12998 13.5156C2.68998 14.1923 3.37331 14.949 4.21331 15.789C5.05331 16.629 5.80998 17.3123 6.48664 17.8723Z" fill="#2167D8"/>
                                <path d="M12.4933 15.8645L10.1233 16.6545C8.8199 15.7182 7.60081 14.6698 6.48001 13.5211C5.33138 12.4003 4.28294 11.1813 3.34668 9.87781L4.13668 7.50781C5.20137 9.18511 6.46376 10.7284 7.89668 12.1045C9.27277 13.5374 10.8161 14.7998 12.4933 15.8645Z" fill="#D3374E"/>
                            </g>
                            <defs>
                                <clipPath id="clip0_6505_11913">
                                    <rect width="20" height="20" fill="white"/>
                                </clipPath>
                            </defs>
                        </svg>
                        {successTitle}
                    </p>
                    <p>
                        {successText}
                    </p>
                    <div className={'apbct-signup-wizard-form-group'}>
                        <a href={settingsLink} className={'apbct-signup-wizard-link-button'}>
                            {__('Go to settings', 'cleantalk-spam-protect')}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    );
}
