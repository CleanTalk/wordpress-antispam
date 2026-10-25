import {startTrp} from './functions/trp';

// Separate entry for the admin comments list, built to js/public-3-trp.min.js
document.addEventListener('DOMContentLoaded', function() {
    if (typeof ctTrpAdminLocalize !== 'undefined' && ctTrpAdminLocalize.theRealPerson) {
        startTrp(ctTrpAdminLocalize.theRealPerson, true);
    }
});
