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

        let trpDescriptionContentSpan = document.createElement('span');
        trpDescriptionContentSpan.append(ctPublic.theRealPerson.phrases.trpContent1 + ' ');
        trpDescriptionContentSpan.append(ctPublic.theRealPerson.phrases.trpContent2);

        trpDescriptionContent.append(trpDescriptionContentSpan);
        trpDescription.append(trpDescriptionHeading, trpDescriptionContent);
        trpLayout.append(trpImage);
        element.append(trpLayout);
        element.append(trpDescription);
    });
});
