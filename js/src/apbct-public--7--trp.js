document.addEventListener('DOMContentLoaded', function() {
    let ctTrpLocalize = undefined;
    let ctTrpIsAdminCommentsList = false;

    if ( typeof ctPublic !== 'undefined' || typeof ctTrpAdminLocalize !== 'undefined' ) {
        if ( typeof ctPublic !== 'undefined' && ctPublic.theRealPerson ) {
            ctTrpLocalize = ctPublic.theRealPerson;
        }
        if (
            typeof ctTrpLocalize === 'undefined' &&
            typeof ctTrpAdminLocalize !== 'undefined' &&
            ctTrpAdminLocalize.theRealPerson
        ) {
            ctTrpLocalize = ctTrpAdminLocalize.theRealPerson;
            ctTrpIsAdminCommentsList = true;
        }
    }

    if ( ! ctTrpLocalize ) {
        return;
    }

    // Selectors. Try to handle the WIDE range of themes.
    let themesCommentsSelector = '.apbct-trp *[class*="comment-author"]';
    let woocommerceReviewsSelector = '.apbct-trp *[class*="review__author"]';
    let adminCommentsListSelector = '.apbct-trp td[class*="column-author"] > strong';
    const trpComments = document.querySelectorAll(
        themesCommentsSelector + ',' +
        woocommerceReviewsSelector + ',' +
        adminCommentsListSelector);

    if ( trpComments.length === 0 ) {
        return;
    }

    trpComments.forEach(( element, index ) => {
        let trpLayout = document.createElement('div');
        trpLayout.setAttribute('class', 'apbct-real-user-badge');

        let trpImage = document.createElement('img');
        trpImage.setAttribute('src', ctTrpLocalize.imgPersonUrl);
        trpImage.setAttribute('class', 'apbct-real-user-popup-img');

        let trpDescription = document.createElement('div');
        trpDescription.setAttribute('class', 'apbct-real-user-popup');

        let trpDescriptionHeading = document.createElement('p');
        trpDescriptionHeading.setAttribute('class', 'apbct-real-user-popup-header');
        trpDescriptionHeading.append(ctTrpLocalize.phrases.trpHeading);

        let trpDescriptionContent = document.createElement('div');
        trpDescriptionContent.setAttribute('class', 'apbct-real-user-popup-content_row');

        let trpDescriptionContentSpan = document.createElement('span');
        trpDescriptionContentSpan.append(ctTrpLocalize.phrases.trpContent1 + ' ');
        trpDescriptionContentSpan.append(ctTrpLocalize.phrases.trpContent2);

        if ( ctTrpIsAdminCommentsList ) {
            let learnMoreLink = document.createElement('a');
            learnMoreLink.setAttribute('href', ctTrpLocalize.trpContentLink);
            learnMoreLink.setAttribute('target', '_blank');
            learnMoreLink.text = ctTrpLocalize.phrases.trpContentLearnMore;
            trpDescriptionContentSpan.append(' '); // Need one space
            trpDescriptionContentSpan.append(learnMoreLink);
        }

        trpDescriptionContent.append(trpDescriptionContentSpan);
        trpDescription.append(trpDescriptionHeading, trpDescriptionContent);
        trpLayout.append(trpImage);
        element.append(trpLayout);
        element.append(trpDescription);
    });

    const badges = document.querySelectorAll('.apbct-real-user-badge');

    badges.forEach(badge => {
        let hideTimeout = undefined;

        badge.addEventListener('click', function() {
            const popup = this.nextElementSibling;
            if (popup.classList.contains('apbct-real-user-popup')) {
                popup.classList.toggle('visible');
            }
        });

        badge.addEventListener('mouseenter', function() {
            const popup = this.nextElementSibling;
            if (popup.classList.contains('apbct-real-user-popup')) {
                popup.classList.add('visible');
            }
        });

        badge.addEventListener('mouseleave', function() {
            hideTimeout = setTimeout(() => {
                const popup = this.nextElementSibling;
                if (popup.classList.contains('apbct-real-user-popup')) {
                    popup.classList.remove('visible');
                }
            }, 1000);
        });

        const popup = badge.nextElementSibling;
        popup.addEventListener('mouseenter', function() {
            clearTimeout(hideTimeout);
            popup.classList.add('visible');
        });

        popup.addEventListener('mouseleave', function() {
            hideTimeout = setTimeout(() => {
                if (popup.classList.contains('apbct-real-user-popup')) {
                    popup.classList.remove('visible');
                }
            }, 1000);
        });

        // For mobile devices
        badge.addEventListener('touchend', function() {
            hideTimeout = setTimeout(() => {
                const popup = this.nextElementSibling;
                const selection = window.getSelection();
                // Check if no text is selected
                if (popup.classList.contains('apbct-real-user-popup') && selection.toString().length === 0) {
                    popup.classList.remove('visible');
                } else {
                    clearTimeout(hideTimeout);
                    document.addEventListener('selectionchange', function onSelectionChange() {
                        if (window.getSelection().toString().length === 0) {
                            // Restart the hide timeout when selection is cleared
                            hideTimeout = setTimeout(() => {
                                const popup = badge.nextElementSibling;
                                if (popup.classList.contains('apbct-real-user-popup')) {
                                    popup.classList.remove('visible');
                                }
                            }, 3000);
                            document.removeEventListener('selectionchange', onSelectionChange);
                        }
                    });
                }
            }, 3000);
        });
    });
});
