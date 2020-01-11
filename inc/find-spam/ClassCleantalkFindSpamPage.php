<?php


class ClassCleantalkFindSpamPage
{

    static private $apbct;

    static private $spam_checker;

    public function __construct( ClassCleantalkFindSpamChecker $apbct_spam_checker ) {

        self::$spam_checker = $apbct_spam_checker;

    }

    /**
     * @return void
     */
    public static function showFindSpamPage() {

        global $apbct;
        self::$apbct = $apbct;

        // If access key is unset in
        if( ! apbct_api_key__is_correct() ){
            if( 1 == self::$apbct->moderate_ip ){
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

        self::findSpamPage();

    }

    public static function generate_check_users_page() {

        self::set_screen_option();

        require_once(CLEANTALK_PLUGIN_DIR . 'inc/find-spam/ClassCleantalkFindSpamChecker.php');
        require_once(CLEANTALK_PLUGIN_DIR . 'inc/find-spam/ClassCleantalkFindSpamUsersChecker.php');
        require_once(CLEANTALK_PLUGIN_DIR . 'inc/find-spam/ClassApbctUsersListTable.php');

        new ClassCleantalkFindSpamPage( new ClassCleantalkFindSpamUsersChecker() );

    }

    public static function generate_check_spam_page() {

        self::set_screen_option();

        require_once(CLEANTALK_PLUGIN_DIR . 'inc/find-spam/ClassCleantalkFindSpamChecker.php');
        require_once(CLEANTALK_PLUGIN_DIR . 'inc/find-spam/ClassCleantalkFindSpamCommentsChecker.php');
        // @ToDo require_once(CLEANTALK_PLUGIN_DIR . 'inc/find-spam/ClassApbctCommentsListTable.php');

        new ClassCleantalkFindSpamPage( new ClassCleantalkFindSpamCommentsChecker() );

    }

    private static function set_screen_option() {

        $option = 'per_page';
        $args = array(
            'label'   => 'Показывать на странице',
            'default' => 10,
            'option'  => 'spam_per_page',
        );
        add_screen_option( $option, $args );

    }

    private static function findSpamPage() {

        ?>
        <div class="wrap">
        <h2><img src="<?php echo self::$apbct->logo__small__colored; ?>" /> <?php echo self::$apbct->plugin_name; ?></h2>
        <a style="color: gray; margin-left: 23px;" href="<?php echo self::$apbct->settings_link; ?>"><?php _e('Plugin Settings', 'cleantalk'); ?></a>
        <br />
        <h3><?php echo self::$spam_checker->get_page_title(); ?></h3>
        <div id="tabs">
            <ul>
                <li><a href="#tabs-1"><?php esc_html_e( 'Current scan results', 'cleantalk' ) ?></a></li>
                <li><a href="#tabs-2"><?php esc_html_e( 'Total spam found', 'cleantalk' ) ?></a></li>
                <li><a href="#tabs-3"><?php esc_html_e( 'Scan logs', 'cleantalk' ) ?></a></li>
            </ul>
            <div id="tabs-1">
                <?php self::$spam_checker->get_current_scan_page(); ?>
            </div>
            <div id="tabs-2">
                <?php self::$spam_checker->get_total_spam_page(); ?>
            </div>
            <div id="tabs-3">
                <?php self::$spam_checker->get_spam_logs_page(); ?>
            </div>
        </div>
        <?php

    }

}