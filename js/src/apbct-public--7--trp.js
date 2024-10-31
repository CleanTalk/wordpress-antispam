document.addEventListener('DOMContentLoaded', function() {
    if ( ! ctPublic.theRealPerson ) {
        return;
    }

    const trpComments = document.querySelectorAll('.apbct-trp *[class*="comment-author"]');

    if ( trpComments.length === 0 ) {
        return;
    }

    trpComments.forEach(( element, index ) => {
        let trpLayout = document.createElement('div');
        trpLayout.setAttribute('class', 'apbct-real-user-badge');

        let trpImage = document.createElement('img');
        trpImage.setAttribute('src', ctPublic.theRealPerson.imgPersonUrl);
        trpImage.setAttribute('class', 'apbct-real-user-popup-img');

        let trpDescription = document.createElement('div');
        trpDescription.setAttribute('class', 'apbct-real-user-popup');

        let trpDescriptionHeading = document.createElement('p');
        trpDescriptionHeading.setAttribute('class', 'apbct-real-user-popup-header');
        trpDescriptionHeading.append(ctPublic.theRealPerson.phrases.trpHeading);

        let trpDescriptionContent = document.createElement('div');
        trpDescriptionContent.setAttribute('class', 'apbct-real-user-popup-content_row-');

        let trpDescriptionContentSpan1 = document.createElement('span');
        let trpImage2 = document.createElement('img');
        trpImage2.setAttribute('src', ctPublic.theRealPerson.imgPersonUrl);
        trpImage2.setAttribute('class', 'apbct-real-user-popup-img');
        trpDescriptionContentSpan1.append(trpImage2);

        let trpDescriptionContentSpan2 = document.createElement('span');
        trpDescriptionContentSpan2.append(ctPublic.theRealPerson.phrases.trpContent1 + ' ');
        trpDescriptionContentSpan2.append(ctPublic.theRealPerson.phrases.trpContent2 + ' ');

        let trpDescriptionContentSpan3 = document.createElement('span');
        let learnMoreLink = document.createElement('a');
        learnMoreLink.setAttribute('href', ctPublic.theRealPerson.trpContentLink);
        learnMoreLink.setAttribute('target', '_blank');
        learnMoreLink.text = ctPublic.theRealPerson.phrases.trpContentLearnMore
        trpDescriptionContentSpan3.append(learnMoreLink);

        trpDescriptionContent.append(trpDescriptionContentSpan1, trpDescriptionContentSpan2, trpDescriptionContentSpan3);
        trpDescription.append(trpDescriptionHeading, trpDescriptionContent);
        trpLayout.append(trpImage);
        element.append(trpLayout);
        element.append(trpDescription);
    });
});