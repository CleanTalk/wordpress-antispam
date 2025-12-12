/**
 * @typedef {Object} ContactsEncoderConfig
 * @property {Object} serviceData
 * @property {string} serviceData.brandName
 * @property {Object} texts
 * @property {string} texts.waitForDecoding
 * @property {string} texts.decodingProcess
 * @property {string} texts.clickToSelect
 * @property {string} texts.originalContactsData
 * @property {string} texts.gotIt
 * @property {string} texts.blocked
 * @property {string} texts.canNotConnect
 * @property {string} texts.cannotDecode
 * @property {string} texts.contactsEncoder
 * @property {function(encodedStrings: String<JSON>): String<JSON>} decodeContactsRequest
 */
class ContactsEncoder {
    static CONFIG_DEFAULTS = {
        serviceData: {
            brandName: 'CleanTalk Contacts Encoder'
        },
        texts: {
            waitForDecoding: 'The magic is on the way!',
            decodingProcess: 'Decoding in progress...',
            clickToSelect: 'Click to select the whole data',
            originalContactsData: 'The complete one is',
            gotIt: 'Got it',
            blocked: 'Blocked',
            canNotConnect: 'Cannot connect',
            cannotDecode: 'Can not decode email. Unknown reason',
            contactsEncoder: 'CleanTalk Contacts Encoder',
        },
        /**
         * @param {string} encodedNodes JSON of encoded nodes
         * @return {string} JSON of encoded nodes {success: bool, data: [compiled response data]}
         */
        decodeContactsRequest: (encodedNodes) => {
            /**
             * @override Please, override this method by custom config object.
             */
            console.error('Not implemented');
        }
    };

    /**
     * Filled by `DOMContentLoaded`: NodeList of `document.querySelectorAll('[data-original-string]')`
     */
    encodedNodes = {};

    encodedEmailNodesIsMixed = false;

    /**
     * @param {ContactsEncoderConfig} config
     */
    constructor(config = {}) {
        this.config = { ...ContactsEncoder.CONFIG_DEFAULTS, ...config };

        this.validateRequiredMethods();
        this.bindMethods();
        this.init();
    }

    init() {
        document.addEventListener('DOMContentLoaded', () => {
            this.encodedNodes = document.querySelectorAll('[data-original-string]');
            this.encodedNodes.forEach(node => {
                if (node.parentNode &&
                    node.parentNode.tagName === 'A' &&
                    node.parentNode.getAttribute('href')?.includes('mailto:') &&
                    node.parentNode.hasAttribute('data-original-string'))
                {
                    console.log('This node skipped')
                    console.log(node)
                    return;
                }
                node.addEventListener('click', this.decodeContactsHandler);
            });
        });
    }

    validateRequiredMethods() {
        const requiredMethods = ['decodeContactsRequest'];
        for (const method of requiredMethods) {
            if (typeof this.config[method] !== 'function') {
                throw new Error(`Required method '${method}' must be implemented in incoming custom config`);
            }
        }
    }

    bindMethods() {
        this.decodeContactsHandler = this.decodeContactsHandler.bind(this);
    }

    showPopup() {
        document.body.classList.add('apbct-popup-fade');

        let popup = document.getElementById('apbct_popup');
        if (!popup) {
            popup = this.createPopup();
            document.body.appendChild(popup);
        } else {
            const popupText = document.getElementById('apbct_popup_text');
            if (popupText) {
                popupText.innerHTML = `
                <p id="apbct_email_ecoder__popup_text_node_first" class="apbct-email-encoder-elements_center">${this.config.texts.waitForDecoding}</p>
                <p id="apbct_email_ecoder__popup_text_node_second">${this.config.texts.decodingProcess}</p>
            `;
            }
        }
        popup.style.display = 'block';
    }

    createPopup() {
        const popup = document.createElement('div');
        popup.className = 'apbct-popup apbct-email-encoder-popup';
        popup.id = 'apbct_popup';

        popup.innerHTML = `
            <span class="apbct-email-encoder-elements_center">
                <p class="apbct-email-encoder--popup-header">${this.config.serviceData.brandName}</p>
            </span>
            <div id="apbct_popup_text" class="apbct-email-encoder-elements_center" style="color: black;">
                <p id="apbct_email_ecoder__popup_text_node_first" class="apbct-email-encoder-elements_center">${this.config.texts.waitForDecoding}</p>
                <p id="apbct_email_ecoder__popup_text_node_second">${this.config.texts.decodingProcess}</p>
            </div>
            ${this.createAnimation()}
        `;

        return popup;
    }

    createAnimation() {
        const animationElements = ['apbct_dog_one', 'apbct_dog_two', 'apbct_dog_three']
        const elements = animationElements.map(className =>
            `<span class="apbct_dog ${className}">@</span>`
        ).join('');

        return `<div class="apbct-ee-animation-wrapper">${elements}</div>`;
    }

    /**
     * Handler for decoding contacts
     * Removes the click event listener, shows a popup, executes the decode contacts request, and handles the result or error
     *
     * @param event
     * @return {Promise<*>}
     */
    decodeContactsHandler(event) {
        const target = event.currentTarget;
        target.removeEventListener('click', this.decodeContactsHandler);
        this.showPopup();

        let encodedEmailsCollection = {};
        for (let i = 0; i < this.encodedNodes.length; i++) {
            // disable click for mailto
            if (
                typeof this.encodedNodes[i].href !== 'undefined' &&
                this.encodedNodes[i].href.indexOf('mailto:') === 0
            ) {
                event.preventDefault();
                this.encodedEmailNodesIsMixed = true;
            }

            // Adding a tooltip
            let apbctTooltip = document.createElement('div');
            apbctTooltip.setAttribute('class', 'apbct-tooltip');
            this.encodedNodes[i].append(apbctTooltip);

            // collect encoded strings
            encodedEmailsCollection[i] = this.encodedNodes[i].dataset.originalString;
        }

        // JSONify encoded strings
        const originalStrings = JSON.stringify(encodedEmailsCollection);

        Promise.resolve(this.config.decodeContactsRequest(originalStrings))
            .then(result => {
                //@ToDo we can set cookie here to remember decoding success for this visitor
                this.handleDecodedData(result, this.encodedNodes, target);
            })
            .catch(error => {
                this.handleDecodeError(error);
            });
    }

    handleDecodeError(error) {
        this.resetEncodedNodes();
        this.showDecodeComment(error);
    }

    handleDecodedData(result, encodedEmailNodes, clickSource) {
        if (result.success && result.data[0].is_allowed === true) {
            // start process of visual decoding
            setTimeout(() => {
                // popup remove
                let popup = document.getElementById('apbct_popup');
                if (popup !== null) {
                    let email = '';
                    if (clickSource) {
                        let currentResultData;
                        result.data.forEach((row) => {
                            if (row.encoded_email === clickSource.dataset.originalString) {
                                currentResultData = row;
                            }
                        });

                        email = currentResultData.decoded_email.split(/[&?]/)[0];
                    } else {
                        email = result.data[0].decoded_email;
                    }
                    // handle first node
                    let firstNode = popup.querySelector('#apbct_email_ecoder__popup_text_node_first');
                    // get email selectable by click
                    let selectableEmail = document.createElement('b');
                    selectableEmail.setAttribute('class', 'apbct-email-encoder-select-whole-email');
                    selectableEmail.innerText = email;

                    selectableEmail.title = this.config.texts.clickToSelect;

                    // add email to the first node
                    if (firstNode) {
                        firstNode.innerHTML = this.config.texts.originalContactsData + '&nbsp;' + selectableEmail.outerHTML;
                        firstNode.setAttribute('style', 'flex-direction: row;');
                    }
                    // remove animation
                    let wrapper = popup.querySelector('.apbct-ee-animation-wrapper');
                    if (wrapper) {
                        wrapper.remove();
                    }
                    // remove second node
                    let secondNode = popup.querySelector('#apbct_email_ecoder__popup_text_node_second');
                    if (secondNode) {
                        secondNode.remove();
                    }
                    // add button
                    let buttonWrapper = document.createElement('span');
                    buttonWrapper.classList = 'apbct-email-encoder-elements_center top-margin-long';
                    if (!document.querySelector('.apbct-email-encoder-got-it-button')) {
                        let button = document.createElement('button');
                        button.innerText = this.config.texts.gotIt;
                        button.classList = 'apbct-email-encoder-got-it-button';
                        button.addEventListener('click', () => {
                            document.body.classList.remove('apbct-popup-fade');
                            popup.setAttribute('style', 'display:none');
                            this.fillDecodedNodes(encodedEmailNodes, result);
                            // click on mailto if so
                            if (this.encodedEmailNodesIsMixed && clickSource) {
                                clickSource.click();
                            }
                        });
                        buttonWrapper.append(button);
                        popup.append(buttonWrapper);
                    }
                }
            }, 3000);
        } else {
            if (clickSource) {
                if (result.success) {
                    this.resetEncodedNodes();
                    this.showDecodeComment(this.config.texts.blocked + ': ' + result.data[0].comment);
                } else {
                    this.resetEncodedNodes();
                    this.showDecodeComment(this.config.texts.canNotConnect + ': ' + result.apbct.comment);
                }
            } else {
                console.log('result', result);
            }
        }
    }

    /**
     * Reset click event for encoded email
     */
    resetEncodedNodes() {
        this.encodedNodes.forEach(element => {
            element.addEventListener('click', this.decodeContactsHandler);
        });
        this.encodedEmailNodesIsMixed = false;
    }

    /**
     * Show Decode Comment
     * @param {string} comment
     */
    showDecodeComment(comment) {
        if ( ! comment ) {
            comment = this.config.texts.cannotDecode;
        }

        let popup = document.getElementById('apbct_popup');
        let popupText = document.getElementById('apbct_popup_text');
        if (popup !== null) {
            document.body.classList.remove('apbct-popup-fade');
            popupText.innerText = this.config.texts.contactsEncoder + ': ' + comment;
            setTimeout(() => {
                popup.setAttribute('style', 'display:none');
            }, 3000);
        }
    }

    /**
     * Run filling for every node with decoding result.
     * @param {mixed} encodedNodes
     * @param {mixed} decodingResult
     */
    fillDecodedNodes(encodedNodes, decodingResult) {
        if (encodedNodes.length > 0) {
            for (let i = 0; i < encodedNodes.length; i++) {
                // chek what is what
                let currentResultData;
                decodingResult.data.forEach((row) => {
                    if (row.encoded_email === encodedNodes[i].dataset.originalString) {
                        currentResultData = row;
                    }
                });
                // quit case on cloud block
                if (currentResultData.is_allowed === false) {
                    return;
                }
                // handler for mailto
                if (
                    typeof encodedNodes[i].href !== 'undefined' &&
                    (
                        encodedNodes[i].href.indexOf('mailto:') === 0 ||
                        encodedNodes[i].href.indexOf('tel:') === 0
                    )
                ) {
                    let linkTypePrefix;
                    if (encodedNodes[i].href.indexOf('mailto:') === 0) {
                        linkTypePrefix = 'mailto:';
                    } else if (encodedNodes[i].href.indexOf('tel:') === 0) {
                        linkTypePrefix = 'tel:';
                    } else {
                        continue;
                    }
                    let encodedEmail = encodedNodes[i].href.replace(linkTypePrefix, '');
                    let baseElementContent = encodedNodes[i].innerHTML;
                    encodedNodes[i].innerHTML = baseElementContent.replace(
                        encodedEmail,
                        currentResultData.decoded_email,
                    );
                    encodedNodes[i].href = linkTypePrefix + currentResultData.decoded_email;

                    encodedNodes[i].querySelectorAll('span.apbct-email-encoder').forEach((el) => {
                        let encodedEmailTextInsideMailto = '';
                        decodingResult.data.forEach((row) => {
                            if (row.encoded_email === el.dataset.originalString) {
                                encodedEmailTextInsideMailto = row.decoded_email;
                            }
                        });
                        el.innerHTML = encodedEmailTextInsideMailto;
                    });
                } else {
                    encodedNodes[i].classList.add('no-blur');
                    // fill the nodes
                    setTimeout(() => {
                        this.processDecodedDataResult(currentResultData, encodedNodes[i]);
                    }, 2000);
                }
                // remove listeners
                encodedNodes[i].removeEventListener('click', this.decodeContactsHandler);
            }
        } else {
            let currentResultData = decodingResult.data[0];
            encodedNodes.classList.add('no-blur');
            // fill the nodes
            setTimeout(() => {
                this.processDecodedDataResult(currentResultData, encodedNodes);
            }, 2000);
            encodedNodes.removeEventListener('click', this.decodeContactsHandler);
        }
    }

    /**
     * @param {mixed} response
     * @param {mixed} targetElement
     */
    processDecodedDataResult(response, targetElement) {
        targetElement.setAttribute('title', '');
        targetElement.removeAttribute('style');
        this.fillDecodedEmail(targetElement, response.decoded_email);
    }

    /**
     * @param {mixed} target
     * @param {string} email
     */
    fillDecodedEmail(target, email) {
        target.innerHTML = target.innerHTML.replace(/.+?(<div class=["']apbct-tooltip["'].+?<\/div>)/, email + '$1');
    }
}
