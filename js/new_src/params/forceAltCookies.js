/**
 * Detect forms that need to be handled via alternative cookies.
 * DO NOT ADD ANY NEW INTEGRATION IN THIS FLOW
 */
export function initForceAltCookies() {
    ctPublic.force_alt_cookies = document.querySelectorAll('iframe[name="jetpack_remote_comment"]').length > 0;

    setTimeout(function() {
        if (!ctPublic.force_alt_cookies) {
            ctPublic.force_alt_cookies =
                document.querySelectorAll('main[id^="bookingpress_booking_form"]').length > 0 ||
                document.querySelectorAll('.bpa-frontend-main-container').length > 0;
        }
    }, 1000);
}
