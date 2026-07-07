import React, {useEffect, useState} from 'react';
import LogoSvgForWizard from '../LogoSvgForWizard';
import WizardConnectingAnimation from '../WizardConnectingAnimation.webp';
import {runSync} from '../../../hooks/useSync';

const {__} = wp.i18n;

export default function ConnectingInterface({handleShowSuccessInterface, setError}) {
    const [progress, setProgress] = useState(0);
    const [statusMessage, setStatusMessage] = useState('');

    useEffect(() => {
        const progressBarFill = document.querySelector('.apbct-signup-wizard-progressbar div');

        const handleProgress = (msg, percent) => {
            setStatusMessage(msg);
            setProgress(percent);
            if (progressBarFill) {
                progressBarFill.style.width = `${percent}%`;
            }
        };

        const handleComplete = () => {
            handleShowSuccessInterface();
        };

        const startSync = async () => {
            try {
                await runSync(handleProgress, handleComplete);
            } catch (syncError) {
                setError(
                    syncError.message || __('Synchronization failed. Please try again.', 'cleantalk-spam-protect'),
                );
            }
        };

        startSync();
    }, [handleShowSuccessInterface, setError]);

    return (
        <div className="apbct-signup-wizard">
            <div className="apbct-signup-wizard-card apbct-signup-wizard-card-connecting">
                <div className="apbct-signup-wizard-card-left">
                    <LogoSvgForWizard />
                    <div className={'apbct-signup-wizard-connecting-gif'}>
                        <img src={WizardConnectingAnimation} alt={'Connecting animation'} />
                    </div>
                </div>
                <div className={'apbct-signup-wizard-card-inner'}>
                    <p className={'apbct-signup-wizard-title'}>
                        {__('Connecting your site to CleanTalk cloud', 'cleantalk-spam-protect')}
                    </p>
                    <div className={'apbct-signup-wizard-progressbar-legend'}>
                        <span className={'apbct-signup-wizard-progressbar-legend-text'}>
                            {statusMessage}
                        </span>
                        <span className={'apbct-signup-wizard-progressbar-legend-percent'}>
                            {progress}%
                        </span>
                    </div>
                    <div className={'apbct-signup-wizard-progressbar'}>
                        <div></div>
                    </div>
                </div>
            </div>
        </div>
    );
}
