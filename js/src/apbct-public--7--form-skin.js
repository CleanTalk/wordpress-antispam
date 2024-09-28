/**
 * Form skin class
 *
 */
// eslint-disable-next-line no-unused-vars, require-jsdoc
class ApbctFormSkin {
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
        this.elements.push(document.querySelector('#apbct_form_decoration_header__content svg'));
        this.elements.push(document.querySelector('#apbct_form_decoration_header__text'));
        this.elements.push(document.querySelector('.apbct-trusted-text--label'));

        const flagWrap = document.querySelector('.apbct_holiday_decoration');
        if (flagWrap) {
            const flagLeft = window.getComputedStyle(flagWrap, '::before');
            const flagRight = window.getComputedStyle(flagWrap, '::after');
            if (flagLeft && flagRight) {
                this.elements.push(flagWrap);
            }
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
                if (element.classList.contains('apbct_holiday_decoration')) {
                    const clickX = event.offsetX;
                    const clickY = event.offsetY;
                    const flagLeftWidth = parseFloat(window.getComputedStyle(element, '::before').width) / 2;
                    const flagLeftHeight = parseFloat(window.getComputedStyle(element, '::before').height) / 2;
                    const flagRightWidth = parseFloat(window.getComputedStyle(element, '::after').width) / 2;
                    const flagRightHeight = parseFloat(window.getComputedStyle(element, '::after').height) / 2;

                    if (clickY > flagLeftHeight && clickX < flagLeftWidth ||
                    clickY > flagRightHeight && clickX > flagRightWidth
                    ) {
                        this.addClicks();
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
                if (element.classList.contains('apbct_holiday_decoration')) {
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
        if (document.ctFormSkinData) {
            if (document.ctFormSkinData.clicks) {
                document.ctFormSkinData.clicks++;
            } else {
                document.ctFormSkinData.clicks = 1;
            }
            return;
        }

        document.ctFormSkinData = {clicks: 1};
    }

    /**
     * Add selected
     */
    addSelected() {
        if (document.ctFormSkinData) {
            if (document.ctFormSkinData.selected) {
                document.ctFormSkinData.selected++;
            } else {
                document.ctFormSkinData.selected = 1;
            }
            return;
        }

        document.ctFormSkinData = {selected: 1};
    }

    /**
     * Track mouse movement
     */
    trackMouseMovement() {
        if (!document.ctFormSkinData) {
            document.ctFormSkinData = {};
        }
        if (!document.ctFormSkinData.mouseMovements) {
            document.ctFormSkinData.mouseMovements = [];
        }

        document.ctFormSkinData.mouseMovements.push({timestamp: Date.now()});

        if (document.ctFormSkinData.mouseMovements.length > 1) {
            const index = document.ctFormSkinData.mouseMovements.length - 1;
            const lastMovement = document.ctFormSkinData.mouseMovements[index];
            const firstMovement = document.ctFormSkinData.mouseMovements[0];
            const timeDiff = lastMovement.timestamp - firstMovement.timestamp;
            document.ctFormSkinData.hovering = timeDiff;
        }
    }
}
