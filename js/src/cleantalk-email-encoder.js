(async function() {
    let apbctAmpState = await AMP.getState('apbctAmpState');

    apbctAmpState = JSON.parse(apbctAmpState);

    // const emailEncoderState = apbctAmpState.emailEncoderState;

    // Listen clicks on encoded emails
    let encodedEmailNodes = document.querySelectorAll('[data-original-string]');
    //ctPublic.encodedEmailNodes = encodedEmailNodes;
    if (encodedEmailNodes.length) {
        for (let i = 0; i < encodedEmailNodes.length; ++i) {
            console.log(encodedEmailNodes[i]);
            if (
                encodedEmailNodes[i].parentElement.href ||
                encodedEmailNodes[i].parentElement.parentElement.href
            ) {
                // Skip listening click on hyperlinks
                continue;
            }
            encodedEmailNodes[i].addEventListener('click', ctFillDecodedEmailHandler);
        }
    }

    function ctFillDecodedEmailHandler(event) {
        this.removeEventListener("click", ctFillDecodedEmailHandler);
        // @ToDo do email decoding here
    }
})();