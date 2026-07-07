import React from 'react';

const {__} = wp.i18n;

export default function ErrorInterface({error, handleBackToSignup}) {
    const supportLink = 'https://wordpress.org/support/plugin/cleantalk-spam-protect/';

    return (
        <div className="apbct-signup-wizard">
            <div className="apbct-signup-wizard-card apbct-signup-wizard-card-error">
                <div className={'apbct-signup-wizard-error-head'}>
                    <div className="apbct-signup-wizard-error-title">
                        <svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M13.72 5.14303L2.42669 23.9964C2.19385 24.3996 2.07065 24.8568 2.06935 25.3224C2.06804 25.788 2.18868 26.2459 2.41926 26.6504C2.64984 27.0549 2.98233 27.392 3.38364 27.6282C3.78495 27.8643 4.24109 27.9913 4.70669 27.9964H27.2934C27.759 27.9913 28.2151 27.8643 28.6164 27.6282C29.0177 27.392 29.3502 27.0549 29.5808 26.6504C29.8114 26.2459 29.932 25.788 29.9307 25.3224C29.9294 24.8568 29.8062 24.3996 29.5734 23.9964L18.28 5.14303C18.0423 4.75118 17.7077 4.42719 17.3083 4.20235C16.9089 3.9775 16.4583 3.85938 16 3.85938C15.5417 3.85938 15.0911 3.9775 14.6918 4.20235C14.2924 4.42719 13.9577 4.75118 13.72 5.14303Z" fill="#A94442"/>
                            <path d="M16 12V17.3333" stroke="white" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
                            <path d="M16 22.6641H16.0138" stroke="white" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
                        </svg>
                        <h2>{__('Error', 'cleantalk-spam-protect')}</h2>
                    </div>
                    <a href={'#'} className="apbct-signup-wizard-cloze-window" onClick={handleBackToSignup}>
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M18 6L6 18" stroke="#646464" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
                            <path d="M6 6L18 18" stroke="#646464" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"/>
                        </svg>
                    </a>
                </div>
                <div className={'apbct-signup-wizard-card-inner'}>
                    <p className={'apbct-signup-wizard-title'}>
                        {error}
                    </p>
                    <div className={'apbct-signup-wizard-error-links'}>
                        <a target="_blank" href={supportLink} rel="noreferrer">
                            {__('Contact support', 'cleantalk-spam-protect')}
                        </a>
                        <a href={'#'} onClick={handleBackToSignup}>
                            {__('Try again', 'cleantalk-spam-protect')}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    );
}
