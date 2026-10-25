import {storeParams} from './functions/storeParams';
import {PARAM_NAME as CHECK_JS_PARAM_NAME, initCheckJs} from './params/checkJs';
import {PARAM_NAME as PAGE_SET_TIMESTAMP_PARAM_NAME, initPageSetTimestamp} from './params/pageSetTimestamp';
import {PARAM_NAME as TIMEZONE_PARAM_NAME, initTimezone} from "./params/timezone";
import {PARAM_NAME as HEADLESS_PARAM_NAME, initHeadless} from "./params/headless";
import {PARAM_NAME as FKP_TIMESTAMP_PARAM_NAME, initFkpTimestamp} from './params/fkpTimestamp';

export function initParams() {
    const obligatoryParams = [
        [CHECK_JS_PARAM_NAME, initCheckJs()],
        [PAGE_SET_TIMESTAMP_PARAM_NAME, initPageSetTimestamp()],
        [TIMEZONE_PARAM_NAME, initTimezone()],
        [HEADLESS_PARAM_NAME, initHeadless()],
        [FKP_TIMESTAMP_PARAM_NAME, initFkpTimestamp()],
    ];

    storeParams(obligatoryParams);
}
