<?php

namespace Cleantalk\ApbctWP\FindSpam;

use Cleantalk\ApbctWP\Variables\Cookie;
use Cleantalk\Common\TT;
use Cleantalk\ApbctWP\ApbctEnqueue;

abstract class Checker
{
    protected $page_title = '';

    protected $apbct;

    protected $page_script_name;

    protected $page_slug;

    protected $list_table;

    public function __construct()
    {
        global $apbct;
        $this->apbct = $apbct;

        // jQueryUI
        wp_enqueue_script('jquery-ui-datepicker');

        ApbctEnqueue::getInstance()->custom(
            'jqueryui_css',
            APBCT_CSS_ASSETS_PATH . '/jquery-ui.min.css',
            array(),
            '1.21.1',
            null,
            'all'
        );

        // Common CSS
        ApbctEnqueue::getInstance()->css('cleantalk-spam-check.css');
    }

    /**
     * @return string
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function getPageTitle()
    {
        return $this->page_title;
    }

    /**
     * @return mixed
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function getPageScriptName()
    {
        return $this->page_script_name;
    }

    /**
     * @return mixed
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function getPageSlug()
    {
        return $this->page_slug;
    }

    /**
     * @return mixed
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function getApbct()
    {
        return $this->apbct;
    }

    abstract public function getCurrentScanPage();

    abstract public function getSpamLogsPage();

    protected function getCurrentScanPanel($spam_checker)
    {
        global $apbct;

        $dates_allowed  = '';
        $dates_disabled = 'disabled';
        if ( Cookie::get('ct_' . $this->page_slug . '_dates_allowed') ) {
            $dates_allowed  = 'checked';
            $dates_disabled = '';
        }
        $dates_from = $dates_till = '';
        $cookie_dates_from = TT::toString(Cookie::get('ct_' . $this->page_slug . '_dates_from'));
        $cookie_dates_till = TT::toString(Cookie::get('ct_' . $this->page_slug . '_dates_till'));
        if (
            preg_match('/^[a-zA-Z]{3}\s{1}\d{1,2}\s{1}\d{4}$/', $cookie_dates_from) &&
            preg_match('/^[a-zA-Z]{3}\s{1}\d{1,2}\s{1}\d{4}$/', $cookie_dates_till)
        ) {
            $dates_from = $cookie_dates_from;
            $dates_till = $cookie_dates_till;
        }
        ?>

        <!-- Count -->
        <h3 id="ct_checking_count"><?php
            echo $spam_checker::getCountText(); ?></h3>

        <!-- Main info -->
        <h3 id="ct_checking_status"><?php
            echo $spam_checker::ctAjaxInfo(true); ?></h3>

        <!-- Check options -->
        <div class="ct_to_hide" id="ct_check_params_wrapper">
            <button class="button ct_check_params_elem" id="ct_check_spam_button" <?php
            echo ! $this->apbct->data['moderate'] ? 'disabled="disabled"' : ''; ?>><?php
                _e("Start check", 'cleantalk-spam-protect'); ?></button>
            <?php
            if ( ! empty($_COOKIE['ct_paused_' . $this->page_slug . '_check']) ) { ?>
                <button class="button ct_check_params_elem" id="ct_proceed_check_button"><?php
                _e("Continue check", 'cleantalk-spam-protect'); ?></button><?php
            } ?>
            <p class="ct_check_params_desc">
                <?php _e(
                    "The plugin will check all $this->page_slug against blacklists database and show you senders that have spam activity on other websites.",
                    'cleantalk-spam-protect'
                ); ?>
            </p>
            <p class="ct_check_params_desc">
                <?php _e('Process of verification is designed to check IP addresses and emails against our blacklist database. Please note that this check only verifies whether the IP address or email is listed in our blacklist at the time of the request. If the IP address or email was not on the blacklist at the time of the query, the system will not flag the user or comment as suspicious. Therefore, this is not an anti-spam feature but rather a check against a static database of blacklisted entries.', 'cleantalk-spam-protect'); ?>
            </p>
            <br/>
            <div class="ct_check_params_elem ct_check_params_elem_sub">
                <input id="ct_accurate_check" type="checkbox" value="1"/><label for="ct_accurate_check"><strong><?php
                        _e("Accurate check", 'cleantalk-spam-protect'); ?></strong></label>
            </div>
            <p class="ct_check_params_desc">
                <?php _e(
                    "Allows to use $this->page_slug's dates to perform more accurate check. Could seriously slow down the check.",
                    'cleantalk-spam-protect'
                ); ?>
            </p>
            <br/>
            <div class="ct_check_params_elem ct_check_params_elem_sub">
                <input id="ct_allow_date_range" type="checkbox" value="1" <?php
                echo $dates_allowed; ?> /><label for="ct_allow_date_range"><strong><?php
                        _e("Specify date range", 'cleantalk-spam-protect'); ?></strong></label>
            </div>
            <div class="ct_check_params_desc">
                <label for="ct_date_range_from"></label>
                <input
                    class="ct_date"
                    type="text"
                    id="ct_date_range_from"
                    value="<?php echo esc_html($dates_from); ?>"
                    <?php echo esc_html($dates_disabled); ?>
                    readonly
                />
                <label for="ct_date_range_till"></label>
                <input
                    class="ct_date"
                    type="text"
                    id="ct_date_range_till"
                    value="<?php echo esc_html($dates_till); ?>"
                    <?php echo esc_html($dates_disabled); ?>
                    readonly
                />
            </div>
            <div class="ct_check_params_desc">
                <p><?php
                    esc_html_e(
                        "Begin/end dates of creation $this->page_slug to check. If no date is specified, the plugin will check all entries.",
                        'cleantalk-spam-protect'
                    ); ?></p>
            </div>
            <br>
            <?php
            echo apbct_admin__badge__get_premium('checkers');
            ?>
        </div>

        <!-- Cooling notice -->
        <h3 id="ct_cooling_notice"></h3>

        <!-- Preloader and working message -->
        <div id="ct_preloader">
            <img src="<?php
            echo APBCT_URL_PATH . '/inc/images/preloader.gif'; ?>" alt="Cleantalk preloader"/>
        </div>
        <div id="ct_working_message">
            <?php
            _e(
                "Please wait for a while. " . $apbct->data['wl_brandname_short'] . " is checking all $this->page_slug via blacklist database at cleantalk.org. You will have option to delete found spam $this->page_slug after plugin finish.",
                'cleantalk-spam-protect'
            ); ?>
        </div>

        <!-- Pause button -->
        <button class="button" id="ct_pause">Pause check</button>
        <?php
    }

    protected function getFooter()
    {
        ?>
        <div id="ct_checktable_footer">
            <p>
                <strong>Purpose of use:</strong><br/>
                Public VPN - not spam<br/>
                TOR - potential spam<br/>
                Hosting - potential spam<br/>
                <br/>
                <strong>Attacks count:</strong><br/>
                IP - >=3 spam<br/>
                E-mail - >=5 spam</br>
                <br/>
                <strong>&#42; Total count of comments - </strong>the number of active comments with the status 'comment',
                'approve', 'disapprove', without statuses 'spam', 'trash', and only on existing pages.<br/>
                <strong>&#42;&#42; Number of checked comments - </strong>all comments with all statuses except 'spam',
                'trash' and 'order_note', such as review, trackback, ping, and comments. The comments on deleted pages have also been taken. Therefore, this
                number may be more than the total number of comments.<br/>
            </p>
        </div>
        <?php
    }

    public static function writeSpamLog($scan_type, $scan_date, $cnt_checked, $cnt_spam, $cnt_bad)
    {
        global $wpdb;
        $wpdb->insert(
            APBCT_SPAMSCAN_LOGS,
            array(
                'scan_type'     => $scan_type,
                'start_time'    => $scan_date,
                //@ToDo this is the END date. Need to place both: start and end of scanning
                'count_to_scan' => $cnt_checked,
                'found_spam'    => $cnt_spam,
                'found_bad'     => $cnt_bad
            ),
            array('%s', '%s', '%d', '%d', '%d')
        );
    }
}
