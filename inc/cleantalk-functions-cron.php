<?php

/**
 * Send connection reports cron wrapper.
 * If setting misc__send_connection_reports is disabled there will no reports sen on cron.
 */
function ct_cron_send_connection_report_email()
{
    global $apbct;
    if (isset($apbct->settings['misc__send_connection_reports']) && $apbct->settings['misc__send_connection_reports'] == 1) {
        $apbct->getConnectionReports()->sendUnsentReports(true);
    }
}

/**
 * Send js errors reports cron wrapper.
 * If setting misc__send_connection_reports is disabled there will no reports sen on cron.
 */
function ct_cron_send_js_error_report_email()
{
    global $apbct;
    if (isset($apbct->settings['misc__send_connection_reports']) && $apbct->settings['misc__send_connection_reports'] == 1) {
        $apbct->getJsErrorsReport()->sendEmail(true);
    }
}

/**
 * Cron job handler
 * Clear old alt-cookies/no-cookies from the database
 *
 * @return void
 */
function apbct_cron_clear_old_session_data()
{
    global $apbct;

    if ( $apbct->data['cookies_type'] === 'alternative' ) {
        \Cleantalk\ApbctWP\Variables\AltSessions::cleanFromOld();
    }
}
