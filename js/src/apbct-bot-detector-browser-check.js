/**
 * Class for bot detector work visualisation.
 *
 * Create a new CtBotDetectorWidget()  on any page to make it work.
 */
class CtBotDetectorWidget {
    titleOnCheck = 'botDetector in action!';
    // titleHuman = 'You\'re a real person!';
    titleActive = '';
    // eslint-disable-next-line max-len
    cleantalkLogo = 'https://s3.eu-central-1.amazonaws.com/cleantalk-ctask-atts/accounts/1/148997/57d089a85d4d208d/web_hi_res_512.png';
    // trpLogo = 'https://s3.eu-central-1.amazonaws.com/cleantalk-ctask-atts/accounts/1/153743/e7389e1e6ca21357/2.png';
    logoAltText = 'CleanTalk';
    privacyLink = 'https://cleantalk.org/privacy';
    termsLink = 'https://cleantalk.org/terms';
    // eslint-disable-next-line no-template-curly-in-string, max-len
    svgBad = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z" fill="#FF0000"/></svg>';
    // eslint-disable-next-line no-template-curly-in-string, max-len
    svgGood = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z" fill="#4CAF50"/></svg>';
    container = null;
    collapsedContent = null;
    fullContent = null;
    visitorIsBot = false;
    storageNameWidgetRendered = 'ct_widget_rendered';
    storageNameVisitorChecked = 'ct_widget_visitor_checked';

    /**
     * Class constructor
     */
    constructor() {
        this.addStyles();
        this.addEventListeners();
    }

    /**
     * Draw widget logic
     */
    initWidget() {
        this.titleActive = this.titleOnCheck;
        this.renderWidget();
        this.showWidget();
    }

    /**
     * Start listening of bot detector start.
     */
    addEventListeners() {
        // skip if user already checked
        if (this.storageGetVisitorChecked()) {
            return;
        }

        // setup storage flags
        this.storageSetWidgetRendered(0);
        this.storageSetVisitorChecked(0);

        // actions for frontend_data fire
        document.addEventListener('ctBotDetectorStart', () => {
            if (!this.storageGetWidgetRendered()) {
                this.initWidget();
                this.storageSetWidgetRendered(1);
            }
        });
        document.addEventListener('ctBotDetectorEnd', () => {
            if (this.storageGetWidgetRendered()) {
                this.collapseWidget();
                this.storageSetVisitorChecked(1);
            }
        });

        // actions for check_bot
        document.addEventListener('ctCheckBotStarted', () => {
            if (!this.storageGetWidgetRendered()) {
                this.initWidget();
                this.storageSetWidgetRendered(1);
            }
        });
        document.addEventListener('ctCheckBotFinished', () => {
            if (this.storageGetWidgetRendered()) {
                this.collapseWidget();
                this.storageSetVisitorChecked(1);
            }
        });

        // !!test action, remove before prod!!
        setTimeout(() => {
            if (!this.storageGetWidgetRendered()) {
                this.initWidget();
                this.storageSetWidgetRendered(1);
                this.collapseWidgetByTimeout();
            }
        }, 1000);
    }

    /**
     * =============== RENDERING ===============
     */

    /**
     * Render widget
     */
    renderWidget() {
        // Find or create container
        this.container = document.querySelector('.ct-browser-check-container') ||
            document.createElement('div');

        if (!this.container.classList.contains('ct-browser-check-container')) {
            this.container.classList.add('ct-browser-check-container');
            this.container.classList.add('ct-widget-hidden-element');
            document.body.appendChild(this.container);
        }

        // Clear existing content
        this.container.innerHTML = '';

        // Create and append collapsed widget
        this.collapsedContent = this.createCollapsedContent();
        // Add close button
        this.closeButton = this.createCloseButton();
        this.container.appendChild(this.closeButton);
        this.container.appendChild(this.collapsedContent);

        // Create and append full widget
        this.fullContent = this.createFullContent();
        this.container.appendChild(this.fullContent);
    }

    /**
     * Create spinner element for visual check process
     * @return {IXMLDOMElement} spinner
     */
    createSpinner() {
        const spinner = document.createElement('div');
        spinner.className = 'ct-browser-check-spinner loader';
        return spinner;
    }

    /**
     * Create collapsed widget content element
     * @return {IXMLDOMElement} wrapper
     */
    createCollapsedContent() {
        const wrapper = document.createElement('div');
        wrapper.id = 'ct_collapsed_widget_wrapper';
        wrapper.className = 'ct-widget-hidden-element';

        const imgContainer = document.createElement('div');
        imgContainer.style.width = '30px';
        imgContainer.style.height = '30px';

        const img = document.createElement('img');
        img.src = this.cleantalkLogo;
        imgContainer.appendChild(img);

        const altText = document.createElement('div');
        altText.textContent = this.logoAltText;

        wrapper.appendChild(imgContainer);
        wrapper.appendChild(altText);

        return wrapper;
    }

    /**
     * Create full content element
     * @return {IXMLDOMElement} wrapper
     */
    createFullContent() {
        const wrapper = document.createElement('div');
        wrapper.id = 'ct_full_widget_wrapper';
        wrapper.className = 'ct-widget-full-wrapper';

        // Add spinner
        wrapper.appendChild(this.createSpinner());

        // Add title
        const title = document.createElement('div');
        title.className = 'ct-browser-check-title';
        title.textContent = this.titleActive;
        wrapper.appendChild(title);

        // Add company info
        wrapper.appendChild(this.createCompanyInfo());

        return wrapper;
    }

    /**
     * Create close button
     * @return {IXMLDOMElement} wrapper
     */
    createCloseButton() {
        const button = document.createElement('button');
        button.className = 'ct-widget-close-button';
        button.innerHTML = `
        <svg width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M1 1L11 11M1 11L11 1" stroke="#777777" stroke-width="2" stroke-linecap="round"/>
        </svg>
    `;
        button.addEventListener('click', () => {
            this.hideWidget();
        });
        button.title = 'Close';
        return button;
    }

    /**
     * Create company info element
     * @return {IXMLDOMElement} wrapper
     */
    createCompanyInfo() {
        const container = document.createElement('div');
        container.className = 'ct-browser-check-company-info';

        // Create info wrapper
        const infoWrapper = document.createElement('div');
        infoWrapper.className = 'ct-browser-check-company-info-wrapper';

        // Create logo
        const logoContainer = document.createElement('div');
        logoContainer.className = 'ct-browser-check-company-info-logo';
        logoContainer.style.width = '20px';
        logoContainer.style.height = '20px';

        const logo = document.createElement('img');
        logo.src = this.cleantalkLogo;
        logo.alt = this.logoAltText;
        logoContainer.appendChild(logo);

        // Create title
        const title = document.createElement('div');
        title.className = 'ct-browser-check-company-info-title';
        title.textContent = this.logoAltText;

        // Add to wrapper
        infoWrapper.appendChild(logoContainer);
        infoWrapper.appendChild(title);

        // Create links
        const linksContainer = document.createElement('div');
        linksContainer.className = 'ct-browser-check-company-info-description';

        const privacyLink = document.createElement('a');
        privacyLink.href = this.privacyLink;
        privacyLink.target = '_blank';
        privacyLink.textContent = 'Privacy';

        const separator = document.createElement('span');
        separator.textContent = '·';

        const termsLink = document.createElement('a');
        termsLink.href = this.termsLink;
        termsLink.target = '_blank';
        termsLink.textContent = 'Terms';

        // Add links to container
        linksContainer.appendChild(privacyLink);
        linksContainer.appendChild(separator);
        linksContainer.appendChild(termsLink);

        // Add all to main container
        container.appendChild(infoWrapper);
        container.appendChild(linksContainer);

        return container;
    }

    /**
     * =============== ACTIONS ===============
     */

    /**
     * Make widget visible
     */
    showWidget() {
        this.container.classList.remove('ct-widget-hidden-element');
    }

    /**
     * Make widget invisible
     */
    hideWidget() {
        this.container.classList.add('ct-widget-hidden-element');
    }

    /**
     * Collapse widget by timeout
     * @param {int} timeout
     */
    collapseWidgetByTimeout(timeout = 3000) {
        setTimeout(() => {
            this.collapseWidget();
            this.storageSetVisitorChecked(1);
        }, timeout);
    }

    /**
     * Collapse widget by timeout
     */
    collapseWidget() {
        this.fullContent.classList = 'ct-widget-hidden-element';
        this.container.style.width = '80px';
        this.collapsedContent.classList = 'ct-widget-collapsed-wrapper';
    }

    /**
     * =============== STYLES ===============
     */

    /**
     * Creates and adds styles to the document head
     * Uses CSSStyleSheet API when available for better performance
     */
    addStyles() {
        // Try to use the more modern CSSStyleSheet API first
        if ('adoptedStyleSheets' in document) {
            const sheet = new CSSStyleSheet();
            sheet.replaceSync(this.getStylesText());
            document.adoptedStyleSheets = [...document.adoptedStyleSheets, sheet];
        } else { // Fallback to traditional style element
            const style = document.createElement('style');
            style.textContent = this.getStylesText();
            document.head.appendChild(style);
        }
    }

    /**
     * Returns the complete CSS text in a more organized structure
     * @return {string}
     */
    getStylesText() {
        return `
        /* Loader styles */
        .ct-browser-check-loader { 
            width: 20px; 
            margin: 5px auto;
            aspect-ratio: 1; 
            border: 3px solid #F3F6F9; 
            border-radius: 50%; 
            position: relative; 
            transform: rotate(45deg); 
        } 
        
        .ct-browser-check-loader::before { 
            content: ""; 
            position: absolute; 
            inset: -3px; 
            border-radius: 50%; 
            border: 3px solid #026E88; 
            animation: l18 2s infinite linear; 
        } 
        
        /* Container styles */
        .ct-browser-check-container {
            font-size: 12px;
            font-weight: bold;
            color: #777777;
            position: fixed;
            top: calc(97% - 80px);
            right: -5px;
            width: 320px;
            height: 80px;
            z-index: 9999;
            background-color: #fff;
            border-radius: 5px 0 0 5px;
            border: 1px solid #bbbbbb;
            box-shadow: 0 0 5px 0 rgba(0, 0, 0, 0.1);
            transition: right 0.3s ease-in-out;
            padding: 1% 0px;
        }
        
        /* Company info styles */
        .ct-browser-check-company-info-wrapper {
            display: flex;
            flex-direction: row;
            flex-wrap: nowrap;
            justify-content: center;
            align-items: flex-end;
        }
        
        /* Widget layout styles */
        .ct-widget-collapsed-wrapper {
            display: flex; 
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%; 
        }
        
        /* Close button styles */
        .ct-widget-close-button {
            position: absolute;
            top: -6px;
            right: calc(100% - 8px);
            width: 15px;
            height: 15px;
            padding: 4px;
            background: white;
            box-shadow: 0 0 5px 0 rgba(0, 0, 0, 0.1);
            cursor: pointer;
            border: 1px solid #bbbbbb;
            border-radius: 15px;
            transition: opacity 0.2s ease;
        }
        
        .ct-widget-close-button:hover {
            scale: 1.1;
        }
        
        .ct-widget-close-button svg {
            display: block;
            width: 100%;
            height: 100%;
        }
        
        .ct-widget-full-wrapper {
            display: flex; 
            flex-direction: row;
            justify-content: space-evenly;
            align-items: center; 
        }
        
        .ct-widget-hidden-element {
            display: none;
        }
        
        .loader { 
                width: 40px; 
                aspect-ratio: 1; 
                border: 10px solid #F3F6F9; 
                border-radius: 50%; 
                position: relative; 
                transform: rotate(45deg); 
            } 
        .loader::before { 
            content: ""; 
            position: absolute; 
            inset: -10px; 
            border-radius: 50%; 
            border: 10px solid #026E88; 
            animation: l18 3s infinite linear; 
        } 
        
        /* Animation keyframes */
        @keyframes l18 { 
            0% { clip-path: polygon(50% 50%, 0 0, 0 0, 0 0, 0 0, 0 0) } 
            25% { clip-path: polygon(50% 50%, 0 0, 100% 0, 100% 0, 100% 0, 100% 0) } 
            50% { clip-path: polygon(50% 50%, 0 0, 100% 0, 100% 100%, 100% 100%, 100% 100%) } 
            75% { clip-path: polygon(50% 50%, 0 0, 100% 0, 100% 100%, 0 100%, 0 100%) } 
            100% { clip-path: polygon(50% 50%, 0 0, 100% 0, 100% 100%, 0 100%, 0 0) } 
        }
    `;
    }

    /**
     * =============== STORAGE ===============
     */

    /**
     * Set flag of widget rendered
     * @param {int} value 1|0
     */
    storageSetWidgetRendered(value) {
        sessionStorage.setItem(this.storageNameWidgetRendered, value.toString());
    }

    /**
     * Check if widget already rendered
     * @return {boolean}
     */
    storageGetWidgetRendered() {
        return sessionStorage.getItem(this.storageNameVisitorChecked) === '1';
    }

    /**
     * Set flag of visitor checked
     * @param {int} value 1|0
     */
    storageSetVisitorChecked(value) {
        sessionStorage.setItem(this.storageNameVisitorChecked, value.toString());
    }

    /**
     * Check if visitor already checked
     * @return {boolean}
     */
    storageGetVisitorChecked() {
        return sessionStorage.getItem(this.storageNameVisitorChecked) === '1';
    }
}

document.addEventListener('DOMContentLoaded', function() {
    console.log('DOMContentLoaded');
    const apbctBrowserCheck = new CtBotDetectorWidget();
    console.log(apbctBrowserCheck);
});
//
// document.addEventListener('DOMContentLoaded', function() {
//     loaderContainer.className = 'ct-browser-check-container';
//     let startTime = null;
//     let timeoutToShowResult = 3000;
//
//     document.addEventListener('ctBotDetectorStart', function() {
//         if (localStorage.getItem('ct-browser-check-result')) {
//             showSavedResult();
//             return;
//         }
//
//         loaderContainer.style.display = 'block';
//         startTime = new Date();
//     });
//
//     document.addEventListener('ctBotDetectorEnd', function() {
//         console.log('ctBotDetectorEnd');
//     });
//
//     document.addEventListener('ctBotDetectorResult', function() {
//         if (localStorage.getItem('ct-browser-check-result')) {
//             return;
//         }
//
//         if (startTime && new Date() - startTime < timeoutToShowResult) {
//             setTimeout(() => {
//                 showResult();
//             }, timeoutToShowResult - (new Date() - startTime));
//             return;
//         }
//
//         setTimeout(() => {
//             showResult();
//         }, timeoutToShowResult);
//     });
//
//     function showSavedResult() {
//         const result = localStorage.getItem('ct-browser-check-result');
//         if (result === 'human') {
//             document.querySelector('.ct-browser-check-title').textContent = 'The Real Person';
//         }
//     }
//
//     /**
//      * Show result
//      */
//     function showResult() {
//         // let results = ['bot', 'human'];
//         let results = ['human'];
//         let result = results[Math.floor(Math.random() * results.length)];
//         // store result in local storage
//         localStorage.setItem('ct-browser-check-result', result);
//         if (document.querySelector('.ct-browser-check-loader')) {
//             document.querySelector('.ct-browser-check-loader').remove();
//         }
//         if (document.querySelector('.ct-browser-check-description')) {
//             document.querySelector('.ct-browser-check-description').remove();
//         }
//         document.querySelector('.ct-browser-check-container').style.height = '50px';
//
//         // prepare data
//         let title = 'botDetector';
//         let svg = '';
//         let arrowAction = '';
//         switch (result) {
//         case 'bot':
//             console.log('bot');
//             // eslint-disable-next-line no-template-curly-in-string, max-len
//             svg = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z" fill="#FF0000"/></svg>';
//             break;
//         case 'human':
//             console.log('human');
//             title = 'The Real Person';
//             // eslint-disable-next-line no-template-curly-in-string, max-len
//             svg = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z" fill="#4CAF50"/></svg>';
//             arrowAction = ' style="cursor: pointer;" onclick="ctBrowserCheckHide();" ';
//             break;
//         }
//         document.querySelector('.ct-browser-check-title').textContent = title;
//         const dev = document.createElement('div');
//         dev.className = 'ct-browser-check-human';
//         dev.innerHTML = `
//             ${svg}
//             <span class="ct-browser-check-human-arrow"${arrowAction}>&gt;</span>
//         `;
//         loaderContainer.appendChild(dev);
//     }
//
//
//     document.addEventListener('ctBotDetectorError', function() {
//         console.log('ctBotDetectorError');
//     });
//
//
//     // add div with title
//     const title = document.createElement('div');
//     title.className = 'ct-browser-check-title';
//     title.style.cssText = `
//         border-bottom: 1px solid #bbbbbb;
//     `;
//     title.textContent = 'botDetector';
//     loaderContainer.appendChild(title);
//
//     // Create loader element
//     const loader = document.createElement('div');
//     loader.className = 'ct-browser-check-loader';
//
//     // Add loader to container and container to body
//     loaderContainer.appendChild(loader);
//     document.body.appendChild(loaderContainer);
//
//     // add div with description
//     const description = document.createElement('div');
//     description.className = 'ct-browser-check-description';
//     description.style.cssText = `
//         border-top: 1px solid #bbbbbb;
//     `;
//     description.textContent = 'Browser check';
//     loaderContainer.appendChild(description);
// });
