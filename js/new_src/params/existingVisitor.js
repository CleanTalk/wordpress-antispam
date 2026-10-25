import {apbctLocalStorage} from '../utils/apbctLocalStorage';

/**
 * Marks the visitor as returning. Must run after the features that check the "first hit" state.
 */
export function initExistingVisitor() {
    apbctLocalStorage.set('apbct_existing_visitor', 1);
}
