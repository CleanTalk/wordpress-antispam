/**
 * Get event token
 * @return {string|boolean}
 */
export function getEventToken() {
    let eventToken = localStorage.getItem('bot_detector_event_token');

    try {
        eventToken = JSON.parse(eventToken);
    } catch {
        eventToken = false;
    }

    if (eventToken !== null && eventToken !== false &&
        eventToken.hasOwnProperty('value') && eventToken.value !== ''
    ) {
        return eventToken.value;
    }

    return false;
}
