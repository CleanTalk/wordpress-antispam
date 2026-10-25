import {catchXmlHttpRequest} from './interceptors/xhr';
import {catchFetchRequest} from './interceptors/fetch';
import {catchIframeFetchRequest} from './interceptors/iframeFetch';
import {catchJqueryAjaxRequest} from './interceptors/jqueryAjax';
import {catchWCRestRequest} from './interceptors/wcRest';

/**
 * Installs the interceptors synchronously, they must be ready before any request is sent.
 */
export function initInterceptors() {
    catchXmlHttpRequest();
    catchFetchRequest();
    catchIframeFetchRequest();
    catchJqueryAjaxRequest();
    catchWCRestRequest();
}
