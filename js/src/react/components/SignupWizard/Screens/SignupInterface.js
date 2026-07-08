import React, {useEffect, useState} from 'react';
import LogoSvgForWizard from '../LogoSvgForWizard';
import BeautifulDigits from './BeautifulDigits';
import {getKeyAuto} from '../../../modules/Http/wizardApi';

const {__} = wp.i18n;

export default function SignupInterface({
    handleExistingUserClick,
    handleShowConnectingInterface,
    setError,
    savedEmail,
    savedAgreedToTerms,
    onFormDataChange,
    settingsLink,
}) {
    const [email, setEmail] = useState(savedEmail);
    const [agreedToTerms, setAgreedToTerms] = useState(savedAgreedToTerms);
    const [buttonText, setButtonText] = useState(__('Connect to Account', 'cleantalk-spam-protect'));
    const [isSubmitting, setIsSubmitting] = useState(false);

    useEffect(() => {
        if (onFormDataChange) {
            onFormDataChange(email, agreedToTerms);
        }
    }, [email, agreedToTerms, onFormDataChange]);

    const fillTimezoneValue = () => {
        const d = new Date();
        return d.getTimezoneOffset() / 60 * (-1);
    };

    const handleSignUp = async (e) => {
        e.preventDefault();

        if (isSubmitting) {
            return;
        }

        const formData = new FormData(e.target);
        const submittedEmail = formData.get('email');
        const ctAdminTimezone = formData.get('ct_admin_timezone');

        setIsSubmitting(true);
        setButtonText(__('Please, wait...', 'cleantalk-spam-protect'));

        try {
            const result = await getKeyAuto(submittedEmail, ctAdminTimezone);

            if (result.success) {
                if (result.getTemplates && typeof cleantalkModal !== 'undefined') {
                    cleantalkModal.loaded = result.getTemplates;
                    cleantalkModal.open();
                    document.addEventListener('cleantalkModalClosed', function onModalClosed() {
                        document.removeEventListener('cleantalkModalClosed', onModalClosed);
                        document.location.reload();
                    });
                }
                handleShowConnectingInterface();
            } else {
                setError(result.msg);
            }
        } catch (submitError) {
            setError(submitError.message || __('Request failed. Please try again.', 'cleantalk-spam-protect'));
        } finally {
            setIsSubmitting(false);
            setButtonText(__('Connect to Account', 'cleantalk-spam-protect'));
        }
    };

    return (
        <div className="apbct-signup-wizard">
            <div className="apbct-signup-wizard-card">
                <div className={'apbct-signup-wizard-card-left'}>
                    <LogoSvgForWizard />
                    <div>
                        <p className={'apbct-signup-wizard-title'}>
                            {__('Protect your website in under 60 seconds', 'cleantalk-spam-protect')}
                        </p>
                        <p>
                            <span className={'apbct-signup-wizard-checked-ico'}></span>
                            <span>{__('Top-rated spam and bot protection for WordPress', 'cleantalk-spam-protect')}</span>
                        </p>
                        <p>
                            <span className={'apbct-signup-wizard-checked-ico'}></span>
                            <span>{__('Protects comments, registrations, and contact forms automatically', 'cleantalk-spam-protect')}</span>
                        </p>
                        <p>
                            <span className={'apbct-signup-wizard-checked-ico'}></span>
                            <span>{__('No CAPTCHAs, no puzzles, and no visitor friction', 'cleantalk-spam-protect')}</span>
                        </p>
                        <BeautifulDigits />
                        <div className={'apbct-signup-wizard-what-next'}>
                            <span className={'apbct-signup-wizard-what-next-title'}>
                                {__('What happens next?', 'cleantalk-spam-protect')}
                            </span>
                            <span className={'apbct-signup-wizard-what-next-hint'}>
                                {__('Once connected, the plugin synchronizes with the CleanTalk cloud, updates the spam database, and enables protection for your forms.', 'cleantalk-spam-protect')}
                            </span>
                        </div>
                    </div>
                </div>
                <div className={'apbct-signup-wizard-card-right'}>
                    <div>
                        <p className={'apbct-signup-wizard-title'}>
                            {__('Connect your website to CleanTalk cloud', 'cleantalk-spam-protect')}
                        </p>
                        <form onSubmit={handleSignUp}>
                            <div className='apbct-signup-wizard-form-group'>
                                <span className={'apbct-signup-wizard-input-title'}>
                                    {__('Email address', 'cleantalk-spam-protect')}
                                </span>
                                <input
                                    type={'email'}
                                    name={'email'}
                                    required={true}
                                    value={email}
                                    onChange={(e) => setEmail(e.target.value)}
                                />
                            </div>
                            <div className={'apbct-signup-wizard-form-group checkbox-block'}>
                                <input
                                    type={'checkbox'}
                                    name={'checkbox'}
                                    id={'apbct-signup-wizard-checkbox'}
                                    required={true}
                                    checked={agreedToTerms}
                                    onChange={(e) => setAgreedToTerms(e.target.checked)}
                                />
                                <label htmlFor={'apbct-signup-wizard-checkbox'}>
                                    <span>
                                        {__('I agree to the Privacy Policy and', 'cleantalk-spam-protect')}
                                        &nbsp;
                                        <a href={'https://cleantalk.org/publicoffer'} target={'_blank'} rel={'noreferrer'}>
                                            {__('License Agreement', 'cleantalk-spam-protect')}
                                        </a>
                                    </span>
                                </label>
                            </div>
                            <div className={'apbct-signup-wizard-form-group'}>
                                <button
                                    type={'submit'}
                                    name={'submit'}
                                    id={'apbct-signup-wizard-get-key-btn'}
                                    disabled={isSubmitting}
                                >
                                    {buttonText}
                                </button>
                            </div>
                            <input type={'hidden'} id={'apbct_admin_timezone'} name={'ct_admin_timezone'} value={fillTimezoneValue()} />
                        </form>
                        <div className={'apbct-signup-wizard-form-group'}>
                            <a className={'apbct-signup-wizard-skip-wizard-link'} onClick={handleExistingUserClick} href='#'>
                                {__('I already have an Access Key', 'cleantalk-spam-protect')}
                            </a>
                        </div>
                    </div>
                    <div>
                        <a className={'apbct-signup-wizard-skip-wizard-link'} href={settingsLink}>
                            {__('Skip setup wizard', 'cleantalk-spam-protect')}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    );
}
