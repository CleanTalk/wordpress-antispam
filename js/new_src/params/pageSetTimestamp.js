export const PARAM_NAME = 'ct_ps_timestamp';

export function initPageSetTimestamp() {
    return Math.floor(new Date().getTime() / 1000);
}
