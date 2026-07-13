import React from 'react';

export default function BeautifulDigits() {
    const getMonthYear = () => {
        const now = new Date();
        return now.toLocaleString('en-US', {month: 'long', year: 'numeric'});
    };

    return (
        <div className={'apbct-signup-wizard-beautiful-digits'}>
            <p>
                <span className={'apbct-signup-wizard-checked-ico'}></span>
                <span>As of {getMonthYear()}, <strong>1,079,000+</strong> sites trust CleanTalk.</span>
            </p>
            <p>
                <span className={'apbct-signup-wizard-checked-ico'}></span>
                <span><strong>12,450,238,000+</strong> spam messages blocked across all protected websites.</span>
            </p>
            <p>
                <span className={'apbct-signup-wizard-checked-ico'}></span>
                <span><strong>99.9982%</strong> spam detection accuracy with no CAPTCHAs.</span>
            </p>
            <p>
                <span className={'apbct-signup-wizard-checked-ico'}></span>
                <span>Powered by a global spam detection network protecting WordPress forms in real time.</span>
            </p>
        </div>
    );
}
