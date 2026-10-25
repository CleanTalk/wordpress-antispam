import {ctSetCookieLegacy} from './functions/storeParams';

// Separate entry for the firewall die pages (SFW, AntiFlood, AntiCrawler): only cookie setting is needed there
window.ctSetCookie = ctSetCookieLegacy;
