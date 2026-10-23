import {storeParams} from './functions/storeParams';
import {PARAM_NAME as CHECK_JS_PARAM_NAME, initCheckJs} from './params/checkJs';
import {PARAM_NAME as PAGE_SET_TIMESTAMP_PARAM_NAME, initPageSetTimestamp} from './params/pageSetTimestamp';
import {PARAM_NAME as TIMEZONE_PARAM_NAME, initTimezone} from "./params/timezone";
import {PARAM_NAME as HEADLESS_PARAM_NAME, initHeadless} from "./params/headless";

export function initParams() {
    const initParams = [
        [CHECK_JS_PARAM_NAME, initCheckJs()],
        [PAGE_SET_TIMESTAMP_PARAM_NAME, initPageSetTimestamp()],
        [TIMEZONE_PARAM_NAME, initTimezone()],
        [HEADLESS_PARAM_NAME, initHeadless()],
    ];

    storeParams(initParams);
}
