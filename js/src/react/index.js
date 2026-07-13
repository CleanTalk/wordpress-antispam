import React from 'react';
import ReactDOM from 'react-dom/client';
import './styles.css';
import SignupWizard from './components/SignupWizard/SignupWizard';

function toggleSignupWizardLayout(showWizard) {
    const rootElement = document.getElementById('apbct-page--react');
    const settingsWrap = document.getElementById('apbct-settings-page-wrap');

    if (rootElement) {
        rootElement.style.display = showWizard ? '' : 'none';
    }

    if (settingsWrap) {
        settingsWrap.style.display = showWizard ? 'none' : '';
    }
}

addEventListener('DOMContentLoaded', () => {
    const rootElement = document.getElementById('apbct-page--react');

    if (!rootElement || !rootElement.dataset.pageData || !rootElement.dataset.tabsData) {
        return;
    }

    try {
        const dataPage = JSON.parse(rootElement.dataset.pageData);
        const dataTabs = JSON.parse(rootElement.dataset.tabsData);
        const urlParams = new URLSearchParams(window.location.search);
        const isSignupWizard = urlParams.get('signup_wizard') === '1' && dataTabs.needsSignupWizard;

        if (!isSignupWizard) {
            return;
        }

        toggleSignupWizardLayout(true);
        ReactDOM.createRoot(rootElement).render(<SignupWizard dataRoot={dataPage} />);
    } catch (error) {
        console.error('Failed to initialize APBCT React settings:', error);
    }
});
