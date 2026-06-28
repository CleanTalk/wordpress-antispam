import React, {useCallback, useState} from 'react';
import './styles.css';

import AccessKeyInterface from './Screens/AccessKeyInterface';
import SuccessInterface from './Screens/SuccessInterface';
import SignupInterface from './Screens/SignupInterface';
import ConnectingInterface from './Screens/ConnectingInterface';
import ErrorInterface from './Screens/ErrorInterface';

export default function SignupWizard({dataRoot}) {
    const [error, setError] = useState(null);
    const [showAccessKeyInterface, setShowAccessKeyInterface] = useState(false);
    const [showSuccessInterface, setShowSuccessInterface] = useState(false);
    const [showConnectingInterface, setShowConnectingInterface] = useState(false);

    const [savedFormData, setSavedFormData] = useState({
        email: dataRoot.adminEmail ? dataRoot.adminEmail : '',
        agreedToTerms: true,
    });

    const handleBackToSignup = (e) => {
        e.preventDefault();
        setError(null);
        setShowAccessKeyInterface(false);
        setShowSuccessInterface(false);
        setShowConnectingInterface(false);
    };

    const handleExistingUserClick = (e) => {
        e.preventDefault();
        setShowAccessKeyInterface(true);
        setShowSuccessInterface(false);
        setShowConnectingInterface(false);
    };

    const handleShowSuccessInterface = () => {
        setShowSuccessInterface(true);
        setShowAccessKeyInterface(false);
        setShowConnectingInterface(false);
    };

    const handleShowConnectingInterface = () => {
        setShowConnectingInterface(true);
        setShowAccessKeyInterface(false);
        setShowSuccessInterface(false);
    };

    const handleFormDataChange = useCallback((email, agreedToTerms) => {
        setSavedFormData({email, agreedToTerms});
    }, []);

    if (error) {
        return <ErrorInterface
            error={error}
            handleBackToSignup={handleBackToSignup}
        />;
    }

    if (showAccessKeyInterface) {
        return <AccessKeyInterface
            handleBackToSignup={handleBackToSignup}
            handleShowConnectingInterface={handleShowConnectingInterface}
            setError={setError}
        />;
    }

    if (showSuccessInterface) {
        return <SuccessInterface settingsLink={dataRoot.settingsLink} />;
    }

    if (showConnectingInterface) {
        return <ConnectingInterface
            handleShowSuccessInterface={handleShowSuccessInterface}
        />;
    }

    return <SignupInterface
        handleExistingUserClick={handleExistingUserClick}
        handleShowConnectingInterface={handleShowConnectingInterface}
        setError={setError}
        savedEmail={savedFormData.email}
        savedAgreedToTerms={savedFormData.agreedToTerms}
        onFormDataChange={handleFormDataChange}
        settingsLink={dataRoot.settingsLink}
    />;
}
