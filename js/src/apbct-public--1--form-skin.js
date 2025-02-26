/**
 * Class collecting user activity data
 *
 */
// eslint-disable-next-line no-unused-vars, require-jsdoc
class ApbctCollectingUserActivity {
    elementBody = document.querySelector('body');
    collectionForms = document.forms;
    /**
     * Constructor
     */
    constructor() {
        this.setListeners();
    }

    /**
     * Set listeners
     */
    setListeners() {
        this.elementBody.addEventListener('click', (event) => {
            this.checkElementInForms(event, 'addClicks');
        });

        this.elementBody.addEventListener('mouseup', (event) => {
            setTimeout(() => {
                const selectedText = window.getSelection().toString();
                if (selectedText) {
                    this.addSelected();
                }
            }, 100);
        });

        this.elementBody.addEventListener('mousemove', (event) => {
            this.checkElementInForms(event, 'trackMouseMovement');
        });
    }

    /**
     * Checking if there is an element in the form
     * @param {object} event
     * @param {string} addTarget
     */
    checkElementInForms(event, addTarget) {
        let resultCheck;
        for (let i = 0; i < this.collectionForms.length; i++) {
            if (
                event.target.outerHTML.length > 0 &&
                this.collectionForms[i].innerHTML.length > 0
            ) {
                resultCheck = this.collectionForms[i].innerHTML.indexOf(event.target.outerHTML);
            } else {
                resultCheck = -1;
            }
        }

        switch (addTarget) {
        case 'addClicks':
            if (resultCheck < 0) {
                this.addClicks();
            }
            break;
        case 'trackMouseMovement':
            if (resultCheck > -1) {
                this.trackMouseMovement();
            }
            break;
        default:
            break;
        }
    }

    /**
     * Add clicks
     */
    addClicks() {
        if (document.ctCollectingUserActivityData) {
            if (document.ctCollectingUserActivityData.clicks) {
                document.ctCollectingUserActivityData.clicks++;
            } else {
                document.ctCollectingUserActivityData.clicks = 1;
            }
            return;
        }

        document.ctCollectingUserActivityData = {clicks: 1};
    }

    /**
     * Add selected
     */
    addSelected() {
        if (document.ctCollectingUserActivityData) {
            if (document.ctCollectingUserActivityData.selected) {
                document.ctCollectingUserActivityData.selected++;
            } else {
                document.ctCollectingUserActivityData.selected = 1;
            }
            return;
        }

        document.ctCollectingUserActivityData = {selected: 1};
    }

    /**
     * Track mouse movement
     */
    trackMouseMovement() {
        if (!document.ctCollectingUserActivityData) {
            document.ctCollectingUserActivityData = {};
        }
        if (!document.ctCollectingUserActivityData.mouseMovementsInsideForm) {
            document.ctCollectingUserActivityData.mouseMovementsInsideForm = false;
        }

        document.ctCollectingUserActivityData.mouseMovementsInsideForm = true;
    }
}
