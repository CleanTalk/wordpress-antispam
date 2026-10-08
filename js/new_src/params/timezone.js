export const PARAM_NAME = 'ct_timezone';

export function initTimezone() {
    const ctDate = new Date();
    return ctDate.getTimezoneOffset()/60*(-1);
}
