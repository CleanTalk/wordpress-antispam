/**
 * Form skin class
 *
 */
// eslint-disable-next-line no-unused-vars, require-jsdoc
class ApbctFormDecorator {
    elements = [];

    /**
     * Constructor
     */
    constructor() {
        this.getElements();
        this.setListeners();
    }

    /**
     * Get elements
     */
    getElements() {
        const elements = document.querySelectorAll('*');
        const regexId = /^apbct-trusted-text--label/;
        const regexClass = /apbct_form_decoration--/;

        this.setDecorationBackground();

        // Collect elements with id or class that contains apbct-trusted-text--label or apbct_form_decoration--
        // id
        let matchingElements = Array.from(elements).filter((element) => {
            return regexId.test(element.id);
        });
        matchingElements.forEach((element) => {
            this.elements.push(element);
        });

        // class
        matchingElements = Array.from(elements).filter((element) => {
            return regexClass.test(element.className);
        });

        matchingElements.forEach((element) => {
            this.elements.push(element);
        });

        const flagWrap = document.querySelector('.apbct_form_decoration');
        if (flagWrap) {
            const flagLeft = window.getComputedStyle(flagWrap, '::before');
            const flagRight = window.getComputedStyle(flagWrap, '::after');
            if (flagLeft && flagRight) {
                this.elements.push(flagWrap);
            }
        }
    }

    /**
     * Set decoration background
     */
    setDecorationBackground() {
        let blockForms = document.querySelectorAll('#respond');

        if (document.querySelector('[class*="apbct_form_decoration"]')) {
            let classHeaderWrapper = document.querySelector('[class*="apbct_form_decoration"]').getAttribute('class');
            let endPosition = classHeaderWrapper.indexOf('_header__wrapper');
            let classTemplate = classHeaderWrapper.substring(0, endPosition);

            blockForms.forEach((blockForm) => {
                blockForm.className += ' ' + classTemplate;
            });
        }
    }

    /**
     * Set listeners
     */
    setListeners() {
        this.elements.forEach((element) => {
            if (!element) {
                return;
            }

            element.addEventListener('click', (event) => {
                if (element.className.indexOf('apbct_form_decoration') !== -1) {
                    if (element.className.indexOf('header__wrapper') !== -1) {
                        this.addClicks();
                        return;
                    }

                    const clickX = event.offsetX;
                    const clickY = event.offsetY;
                    const flagLeftWidth = parseFloat(window.getComputedStyle(element, '::before').width) / 2;
                    const flagLeftHeight = parseFloat(window.getComputedStyle(element, '::before').height) / 2;
                    const flagRightWidth = parseFloat(window.getComputedStyle(element, '::after').width) / 2;
                    const flagRightHeight = parseFloat(window.getComputedStyle(element, '::after').height) / 2;

                    if (element.className.indexOf('christmas') !== -1) {
                        if (
                            clickY < flagLeftHeight / 3 && clickX < flagLeftWidth ||
                            clickY < flagRightHeight / 3 && clickX > flagRightWidth
                        ) {
                            this.addClicks();
                            return;
                        }
                    }

                    if (
                        (element.className.indexOf('new-year') !== -1) ||
                        (element.className.indexOf('fourth-july') !== -1)
                    ) {
                        if (
                            clickY > flagLeftHeight && clickX < flagLeftWidth ||
                            clickY > flagRightHeight && clickX > flagRightWidth
                        ) {
                            this.addClicks();
                        }
                    }

                    return;
                }

                this.addClicks();
            });

            element.addEventListener('mouseup', (event) => {
                setTimeout(() => {
                    const selectedText = window.getSelection().toString();
                    if (selectedText) {
                        this.addSelected();
                    }
                }, 100);
            });

            element.addEventListener('mousemove', (event) => {
                if (element.className.indexOf('apbct_form_decoration') !== -1) {
                    const mouseX = event.offsetX;
                    const mouseY = event.offsetY;
                    const flagLeftWidth = parseFloat(window.getComputedStyle(element, '::before').width) / 2;
                    const flagLeftHeight = parseFloat(window.getComputedStyle(element, '::before').height) / 2;
                    const flagRightWidth = parseFloat(window.getComputedStyle(element, '::after').width) / 2;
                    const flagRightHeight = parseFloat(window.getComputedStyle(element, '::after').height) / 2;

                    if (mouseY > flagLeftHeight && mouseX < flagLeftWidth ||
                    mouseY > flagRightHeight && mouseX > flagRightWidth
                    ) {
                        this.trackMouseMovement();
                    }
                    return;
                }

                this.trackMouseMovement();
            });
        });
    }

    /**
     * Add clicks
     */
    addClicks() {
        if (document.ctFormDecorationMouseData) {
            if (document.ctFormDecorationMouseData.clicks) {
                document.ctFormDecorationMouseData.clicks++;
            } else {
                document.ctFormDecorationMouseData.clicks = 1;
            }
            return;
        }

        document.ctFormDecorationMouseData = {clicks: 1};
    }

    /**
     * Add selected
     */
    addSelected() {
        if (document.ctFormDecorationMouseData) {
            if (document.ctFormDecorationMouseData.selected) {
                document.ctFormDecorationMouseData.selected++;
            } else {
                document.ctFormDecorationMouseData.selected = 1;
            }
            return;
        }

        document.ctFormDecorationMouseData = {selected: 1};
    }

    /**
     * Track mouse movement
     */
    trackMouseMovement() {
        if (!document.ctFormDecorationMouseData) {
            document.ctFormDecorationMouseData = {};
        }
        if (!document.ctFormDecorationMouseData.mouseMovements) {
            document.ctFormDecorationMouseData.mouseMovements = [];
        }

        document.ctFormDecorationMouseData.mouseMovements.push({timestamp: Date.now()});

        if (document.ctFormDecorationMouseData.mouseMovements.length > 1) {
            const index = document.ctFormDecorationMouseData.mouseMovements.length - 1;
            const lastMovement = document.ctFormDecorationMouseData.mouseMovements[index];
            const firstMovement = document.ctFormDecorationMouseData.mouseMovements[0];
            const timeDiff = lastMovement.timestamp - firstMovement.timestamp;
            document.ctFormDecorationMouseData.hovering = timeDiff;
        }
    }
}
