import {initParams} from "./initParams";
import {setInputValue} from "./params/checkJs";

if (document.readyState !== 'loading') {
    initParams();
} else {
    document.addEventListener('DOMContentLoaded', initParams);
}

// Hack - it have to be defined for the ability to use from inline JS code in the page
window.apbct_js_keys__set_input_value = setInputValue;


