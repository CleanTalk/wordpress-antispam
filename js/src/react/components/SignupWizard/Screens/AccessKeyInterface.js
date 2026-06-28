import React, {useState} from 'react';
import {saveAccessKey} from '../../../modules/Http/wizardApi';

const {__} = wp.i18n;

export default function AccessKeyInterface({handleBackToSignup, handleShowConnectingInterface, setError}) {
    const [buttonText, setButtonText] = useState(__('Connect to CleanTalk', 'cleantalk-spam-protect'));
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleSaveKey = async (e) => {
        e.preventDefault();

        if (isSubmitting) {
            return;
        }

        const formData = new FormData(e.target);
        const apiKey = formData.get('apbct_key');

        setIsSubmitting(true);
        setButtonText(__('Please, wait...', 'cleantalk-spam-protect'));

        try {
            const result = await saveAccessKey(apiKey);

            if (result.success) {
                handleShowConnectingInterface();
            } else {
                setError(result.msg);
            }
        } catch (submitError) {
            setError(submitError.message || __('Request failed. Please try again.', 'cleantalk-spam-protect'));
        } finally {
            setIsSubmitting(false);
            setButtonText(__('Connect to CleanTalk', 'cleantalk-spam-protect'));
        }
    };

    return (
        <div className="apbct-signup-wizard">
            <div className="apbct-signup-wizard-card existing-user">
                <a className={'apbct-signup-wizard-skip-wizard-link'} href={'#'} onClick={handleBackToSignup}>
                    <svg width="22" height="22" viewBox="0 0 22 22" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M17.4154 11H4.58203" stroke="#444444" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round"/>
                        <path d="M10.9987 17.4115L4.58203 10.9948L10.9987 4.57812" stroke="#444444" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round"/>
                    </svg>
                    {__('Go to previous step', 'cleantalk-spam-protect')}
                </a>
                <div className={'apbct-signup-wizard-card-inner'}>
                    <p className={'apbct-signup-wizard-title'}>
                        {__('Connect your site to CleanTalk cloud', 'cleantalk-spam-protect')}
                    </p>
                    <p>
                        <span>
                            {__('Copy the access key you have received via email or you can get it from ', 'cleantalk-spam-protect')}
                            <a href={'https://cleantalk.org/my/?cp_mode=antispam'} target={'_blank'} rel="noreferrer" style={{display: 'inline'}}>
                                {__('Cloud Dashboard', 'cleantalk-spam-protect')}
                            </a>
                            {'. '}
                            {__('If your account has multiple domains select the one you want to use.', 'cleantalk-spam-protect')}
                        </span>
                    </p>
                    <form onSubmit={handleSaveKey}>
                        <div className='apbct-signup-wizard-form-group'>
                            <input type={'text'} name={'apbct_key'} required={true} placeholder={__('Access key', 'cleantalk-spam-protect')}/>
                        </div>
                        <div className={'apbct-signup-wizard-form-group'}>
                            <button
                                type={'submit'}
                                name={'submit'}
                                id={'apbct-signup-wizard-save-key-btn'}
                                disabled={isSubmitting}
                            >
                                {buttonText}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    );
}
