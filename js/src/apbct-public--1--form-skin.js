/**
 * Form skin class
 *
 */
// eslint-disable-next-line no-unused-vars, require-jsdoc
class ApbctFormDecorator {
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

    checkElementInForms(event, addTarget) {
        let resultCheck;
        for (let i = 0; i < this.collectionForms.length; i++) {
            
            if (event.target.innerHTML.length > 0) {
                resultCheck = this.collectionForms[i].innerHTML.indexOf(event.target.innerHTML);
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
                    console.log(resultCheck);
                    console.log(event);
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
