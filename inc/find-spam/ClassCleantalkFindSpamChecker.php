<?php


abstract class ClassCleantalkFindSpamChecker
{

    protected $page_title = '';

    protected $apbct;

    protected $page_slug;

    public function __construct() {

        global $apbct;
        $this->apbct = $apbct;

        // jQueryUI
        wp_enqueue_script( 'jqueryui', plugins_url('/cleantalk-spam-protect/js/jquery-ui.min.js'), array('jquery'), '1.12.1' );
        wp_enqueue_style( 'jqueryui_css', plugins_url('/cleantalk-spam-protect/css/jquery-ui.min.css'), array(), '1.21.1', 'all' );
        wp_enqueue_style( 'jqueryui_theme_css', plugins_url('/cleantalk-spam-protect/css/jquery-ui.theme.min.css'), array(), '1.21.1', 'all' );

        // Common CSS
        wp_enqueue_style( 'cleantalk_admin_css_settings_page', plugins_url('/cleantalk-spam-protect/css/cleantalk-spam-check.min.css'), array( 'jqueryui_css' ), APBCT_VERSION, 'all' );

    }

    public function get_page_title() {

        return $this->page_title;

    }

    abstract function get_current_scan_page();

    abstract function get_total_spam_page();

    abstract function get_spam_logs_page();

    abstract function last_check_date();

    protected function get_current_scan_panel() {
        ?>
        <!-- Check options -->
        <div class="ct_to_hide" id="ct_check_params_wrapper">
            <button class="button ct_check_params_elem" id="ct_check_spam_button" <?php echo !$this->apbct->data['moderate'] ? 'disabled="disabled"' : ''; ?>><?php _e("Start check", 'cleantalk'); ?></button>
            <?php if(!empty($_COOKIE['ct_paused_'.$this->page_slug.'_check'])) { ?><button class="button ct_check_params_elem" id="ct_proceed_check_button"><?php _e("Continue check", 'cleantalk'); ?></button><?php } ?>
            <p class="ct_check_params_desc"><?php _e("The plugin will check all $this->page_slug against blacklists database and show you senders that have spam activity on other websites.", 'cleantalk'); ?></p>
            <br />
            <div class="ct_check_params_elem ct_check_params_elem_sub">
                <input id="ct_accurate_check" type="checkbox" value="1" /><label for="ct_accurate_check"><strong><?php _e("Accurate check", 'cleantalk'); ?></strong></label>
            </div>
            <p class="ct_check_params_desc"><?php _e("Allows to use $this->page_slug's dates to perform more accurate check. Could seriously slow down the check.", 'cleantalk'); ?></p>
            <br />
            <div class="ct_check_params_elem ct_check_params_elem_sub">
                <input id="ct_allow_date_range" type="checkbox" value="1" /><label for="ct_allow_date_range"><strong><?php _e("Specify date range", 'cleantalk'); ?></strong></label>
            </div>
            <div class="ct_check_params_desc">
                <input class="ct_date" type="text" id="ct_date_range_from" value="<?php echo $this->last_check_date(); ?>" disabled readonly />
                <input class="ct_date" type="text" id="ct_date_range_till" value="<?php echo date( "M j Y"); ?>" disabled readonly />
            </div>
            <div class="ct_check_params_desc">
                <p><?php esc_html_e( "Begin/end dates of creation $this->page_slug to check. If no date is specified, the plugin uses the last $this->page_slug check date.", 'cleantalk'); ?></p>
            </div>
            <br>
            <?php apbct_admin__badge__get_premium(); ?>
        </div>

        <!-- Cooling notice -->
        <h3 id="ct_cooling_notice"></h3>

        <!-- Preloader and working message -->
        <div id="ct_preloader">
            <img src="<?php echo APBCT_URL_PATH; ?>/inc/images/preloader.gif" alt="Cleantalk preloader" />
        </div>
        <div id="ct_working_message">
            <?php _e("Please wait for a while. CleanTalk is checking all $this->page_slug via blacklist database at cleantalk.org. You will have option to delete found spam $this->page_slug after plugin finish.", 'cleantalk'); ?>
        </div>

        <!-- Pause button -->
        <button class="button" id="ct_pause">Pause check</button>
        <?php
    }

}