class ctBrowserCheck {
    titleCheck = 'botDetector in action!';
    titleHuman = "You're a real person!";
    title = '';
    logo = 'https://s3.eu-central-1.amazonaws.com/cleantalk-ctask-atts/accounts/1/148997/57d089a85d4d208d/web_hi_res_512.png';
    trpLogo = 'https://s3.eu-central-1.amazonaws.com/cleantalk-ctask-atts/accounts/1/153743/e7389e1e6ca21357/2.png';
    logoAlt = 'CleanTalk';
    privacyLink = 'https://cleantalk.org/privacy';
    termsLink = 'https://cleantalk.org/terms';
    // eslint-disable-next-line no-template-curly-in-string, max-len
    svgBad = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z" fill="#FF0000"/></svg>';
    // eslint-disable-next-line no-template-curly-in-string, max-len
    svgGood = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41L9 16.17z" fill="#4CAF50"/></svg>';
    resultStore = 'ct-browser-check-result';
    container = null;
    result = 'human';
    isLoader = true;

    constructor() {
        this.addStyles();
        this.addEventListener();
    }

    addEventListener() {
        document.addEventListener('ctBotDetectorStart', () => {
            // if (localStorage.getItem(this.resultStore)) {
            //     this.showSavedResult();
            //     return;
            // }

            this.showLoader();
        });
    }

    showSavedResult() {
    }

    showLoader() {
        this.title = this.titleCheck;
        this.render();
        this.showContainer();
    }

    render() {
        if (document.querySelector('.ct-browser-check-container')) {
            this.container = document.querySelector('.ct-browser-check-container');
        } else {
            this.container = document.createElement('div');
            this.container.className = 'ct-browser-check-container';
            document.body.appendChild(this.container);
        }

        this.container.innerHTML += `<div class="ct-browser-check-wrapper" style="display: flex; flex-direction: column; align-items: center; justify-content: space-between;">`;
        // this.container.innerHTML += `<div class="ct-browser-check-title">${this.title}</div> `;
        this.container.innerHTML += `<div class="ct-browser-check-title">test</div> `;
        this.container.innerHTML += this.companyInfoHtml();
        this.container.innerHTML += `</div>`;
    }

    companyInfoHtml() {
        return `
            <div class="ct-browser-check-company-info">
                <div class="ct-browser-check-company-info-wrapper">
                    <div class="ct-browser-check-company-info-logo" style="width: 20px; height: 20px;">
                        <img src="${this.logo}" alt="${this.logoAlt}">
                    </div>
                    <div class="ct-browser-check-company-info-title">${this.logoAlt}</div>
                </div>
                <div class="ct-browser-check-company-info-description">
                    <a href="${this.privacyLink}" target="_blank">Privacy</a>
                    <span>&middot;</span>
                    <a href="${this.termsLink}" target="_blank">Terms</a>
                </div>
            </div>
        `;
    }

    showContainer() {
        this.container.style.display = 'block';
    }

    hideContainer() {
        this.container.style.display = 'none';
    }

    /**
     * Create and add styles
     */
    addStyles() {
        const style = document.createElement('style');

        style.textContent = `
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
            @keyframes l18 { 
                0% {clip-path:polygon(50% 50%,0 0,0 0,0 0 ,0 0 ,0 0 )} 
                25% {clip-path:polygon(50% 50%,0 0,100% 0,100% 0 ,100% 0 ,100% 0 )} 
                50% {clip-path:polygon(50% 50%,0 0,100% 0,100% 100%,100% 100%,100% 100%)} 
                75% {clip-path:polygon(50% 50%,0 0,100% 0,100% 100%,0 100%,0 100%)} 
                100% {clip-path:polygon(50% 50%,0 0,100% 0,100% 100%,0 100%,0 0 )} 
            }

            .ct-browser-check-container {
                display: none;
                font-size: 12px;
                font-weight: bold;
                color: #777777;
                text-align: center;
                position: fixed;
                top: 20%;
                right: -5px;
                width: 365px;
                height: 70px;
                z-index: 9999;
                background-color: #fff;
                border-radius: 5px 0 0 5px;
                border: 1px solid #bbbbbb;
                box-shadow: 0 0 5px 0 rgba(0, 0, 0, 0.1);
                transition: right 0.3s ease-in-out;
            }
        `;

        document.head.appendChild(style);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    console.log('DOMContentLoaded');
    const apbctBrowserCheck = new ctBrowserCheck();
    console.log(apbctBrowserCheck);
    apbctBrowserCheck.render();
});
// document.addEventListener('DOMContentLoaded', function() {
//     loaderContainer.className = 'ct-browser-check-container';
//     let startTime = null;
//     let timeoutToShowResult = 3000;

//     document.addEventListener('ctBotDetectorStart', function() {
//         if (localStorage.getItem('ct-browser-check-result')) {
//             showSavedResult();
//             return;
//         }

//         loaderContainer.style.display = 'block';
//         startTime = new Date();
//     });

//     document.addEventListener('ctBotDetectorEnd', function() {
//         console.log('ctBotDetectorEnd');
//     });

//     document.addEventListener('ctBotDetectorResult', function() {
//         if (localStorage.getItem('ct-browser-check-result')) {
//             return;
//         }

//         if (startTime && new Date() - startTime < timeoutToShowResult) {
//             setTimeout(() => {
//                 showResult();
//             }, timeoutToShowResult - (new Date() - startTime));
//             return;
//         }

//         setTimeout(() => {
//             showResult();
//         }, timeoutToShowResult);
//     });

//     function showSavedResult() {
//         const result = localStorage.getItem('ct-browser-check-result');
//         if (result === 'human') {
//             document.querySelector('.ct-browser-check-title').textContent = 'The Real Person';
//         }
//     }

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


//     document.addEventListener('ctBotDetectorError', function() {
//         console.log('ctBotDetectorError');
//     });


//     // add div with title
//     const title = document.createElement('div');
//     title.className = 'ct-browser-check-title';
//     title.style.cssText = `
//         border-bottom: 1px solid #bbbbbb;
//     `;
//     title.textContent = 'botDetector';
//     loaderContainer.appendChild(title);

//     // Create loader element
//     const loader = document.createElement('div');
//     loader.className = 'ct-browser-check-loader';

//     // Add loader to container and container to body
//     loaderContainer.appendChild(loader);
//     document.body.appendChild(loaderContainer);

//     // add div with description
//     const description = document.createElement('div');
//     description.className = 'ct-browser-check-description';
//     description.style.cssText = `
//         border-top: 1px solid #bbbbbb;
//     `;
//     description.textContent = 'Browser check';
//     loaderContainer.appendChild(description);
// });

// /**
//  * Hide browser check
//  * eslint-disable-next-line no-unused-vars
//  */
// function ctBrowserCheckHide() {
//     document.querySelector('.ct-browser-check-title').textContent = 'TRP';
//     loaderContainer.style.right = '-70px';
//     loaderContainer.style.alignItems = 'left';
//     document.querySelector('.ct-browser-check-title').style.alignItems = 'left';
//     document.querySelector('.ct-browser-check-title').style.width = '40px';
//     document.querySelector('.ct-browser-check-human').style.marginLeft = '-30px';
// }
