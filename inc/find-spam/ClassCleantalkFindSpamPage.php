<?php


class ClassCleantalkFindSpamPage
{

    private $spam_checker;

    private $current_tab;

    public function __construct( ClassCleantalkFindSpamChecker $apbct_spam_checker ) {

        $this->spam_checker = $apbct_spam_checker;

        switch ( current_action() ) {

            case 'users_page_ct_check_users' :
            case 'comments_page_ct_check_spam' :
                $this->current_tab = 1;
                $this->generatePageHeader();
                $this->spam_checker->getCurrentScanPage();
                break;

            case 'users_page_ct_check_users_total' :
            case 'comments_page_ct_check_spam_total' :
                $this->current_tab = 2;
                $this->generatePageHeader();
                $this->spam_checker->getTotalSpamPage();
                break;

            case 'users_page_ct_check_users_logs' :
            case 'comments_page_ct_check_spam_logs' :
                $this->current_tab = 3;
                $this->generatePageHeader();
                $this->spam_checker->getSpamLogsPage();
                break;

        }

    }

    /**
     *  Output header section of the FindSpam pages
     *
     * @return void (HTML layout output)
     */
    public static function showFindSpamPage() {
        switch ( current_action() ) {

            case 'users_page_ct_check_users' :
            case 'users_page_ct_check_users_total' :
            case 'users_page_ct_check_users_logs' :
                self::generateCheckUsersPage();
                break;

            case 'comments_page_ct_check_spam' :
            case 'comments_page_ct_check_spam_total' :
            case 'comments_page_ct_check_spam_logs' :
                self::generateCheckSpamPage();
                break;

        }

    }

    private static function generateCheckUsersPage() {

        new self( new ClassCleantalkFindSpamUsersChecker() );

        self::closeTags();

    }

    private static function generateCheckSpamPage() {

        new self( new ClassCleantalkFindSpamCommentsChecker() );

        self::closeTags();

    }

    private function generatePageHeader() {

        // If access key is unset in
        if( ! apbct_api_key__is_correct() ){
            if( 1 == $this->spam_checker->getApbct()->moderate_ip ){
                echo '<h3>'
                    .sprintf(
                        __('Antispam hosting tariff does not allow you to use this feature. To do so, you need to enter an Access Key in the %splugin settings%s.', 'cleantalk'),
                        '<a href="' . ( is_network_admin() ? 'settings.php?page=cleantalk' : 'options-general.php?page=cleantalk' ).'">',
                        '</a>'
                    )
                    .'</h3>';
            }
            return;
        }

        ?>
        <div class="wrap">
        <h2><img src="<?php echo $this->spam_checker->getApbct()->logo__small__colored; ?>" alt="CleanTalk logo" /> <?php echo $this->spam_checker->getApbct()->plugin_name; ?></h2>
        <a style="color: gray; margin-left: 23px;" href="<?php echo $this->spam_checker->getApbct()->settings_link; ?>"><?php _e('Plugin Settings', 'cleantalk'); ?></a>
        <br />
        <h3><?php echo $this->spam_checker->getPageTitle(); ?></h3>
        <div id="ct_check_tabs">
            <ul>
                <li <?php echo (1 == $this->current_tab) ? 'class="active"' : ''; ?>><a href="<?php echo $this->spam_checker->getPageScriptName(); ?>?page=ct_check_<?php echo $this->spam_checker->getPageSlug(); ?>"><?php esc_html_e( 'Scan and new results', 'cleantalk' ) ?></a></li>
                <li <?php echo (2 == $this->current_tab) ? 'class="active"' : ''; ?>><a href="<?php echo $this->spam_checker->getPageScriptName(); ?>?page=ct_check_<?php echo $this->spam_checker->getPageSlug(); ?>_total"><?php esc_html_e( 'Previous scan results', 'cleantalk' ) ?></a></li>
                <li <?php echo (3 == $this->current_tab) ? 'class="active"' : ''; ?>><a href="<?php echo $this->spam_checker->getPageScriptName(); ?>?page=ct_check_<?php echo $this->spam_checker->getPageSlug(); ?>_logs"><?php esc_html_e( 'Scan logs', 'cleantalk' ) ?></a></li>
            </ul>
            <div id="ct_check_content">
        <?php

    }

    public static function setScreenOption() {

        $option = 'per_page';
        $args = array(
            'label'   => esc_html__( 'Show per page', 'cleantalk' ),
            'default' => 10,
            'option'  => 'spam_per_page',
        );
        add_screen_option( $option, $args );

    }

    private static function closeTags() {

        ?>
            </div>
        </div>
        <?php

    }

}