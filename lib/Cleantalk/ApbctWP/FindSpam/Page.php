<?php

namespace Cleantalk\ApbctWP\FindSpam;

use Cleantalk\ApbctWP\FindSpam\ListTable\Users;

class Page
{
    private $spam_checker;

    private $current_tab;

    private $wl_mode_data;

    private $use_main_site_wl_mode = false;

    public function __construct(Checker $apbct_spam_checker)
    {
        $this->spam_checker = $apbct_spam_checker;

        //if wpms and it is subsite
        if (defined('APBCT_WPMS') && !is_main_site() ) {
            $this->use_main_site_wl_mode = true;
            $current_blog = get_current_blog_id();
            //switch to main site and get its wl data
            switch_to_blog(get_main_site_id());
            //get stuff from main site
            $this->wl_mode_data['wl_brandname'] = get_option('cleantalk_data')['wl_brandname'];
            //return to subsite and get its wl data
            switch_to_blog($current_blog);
        }

        switch ( current_action() ) {
            case 'users_page_ct_check_users':
            case 'comments_page_ct_check_spam':
                $this->current_tab = 1;
                $this->generatePageHeader();
                $this->spam_checker->getCurrentScanPage();
                break;

            case 'users_page_ct_check_users_logs':
            case 'comments_page_ct_check_spam_logs':
                $this->current_tab = 3;
                $this->generatePageHeader();
                $this->spam_checker->getSpamLogsPage();
                break;

            case 'users_page_ct_check_users_bad':
                $this->current_tab = 2;
                $this->generatePageHeader();
                /** @psalm-suppress UndefinedMethod */
                $this->spam_checker->getBadUsersPage();
                break;
        }
    }

    /**
     *  Output header section of the FindSpam pages
     *
     * @return void (HTML layout output)
     */
    public static function showFindSpamPage()
    {
        switch ( current_action() ) {
            case 'users_page_ct_check_users':
            case 'users_page_ct_check_users_logs':
            case 'users_page_ct_check_users_bad':
                self::generateCheckUsersPage();
                break;

            case 'comments_page_ct_check_spam':
            case 'comments_page_ct_check_spam_logs':
                self::generateCheckSpamPage();
                break;
        }
    }

    private static function generateCheckUsersPage()
    {
        new self(new UsersChecker());

        self::closeTags();
    }

    private static function generateCheckSpamPage()
    {
        new self(new CommentsChecker());

        self::closeTags();
    }

    private function generatePageHeader()
    {
        // If access key is unset in
        if ( ! apbct_api_key__is_correct() ) {
            if ( 1 == $this->spam_checker->getApbct()->moderate_ip ) {
                echo '<h3>'
                    . sprintf(
                        __('Anti-Spam hosting tariff does not allow you to use this feature. To do so, you need to enter an Access Key in the %splugin settings%s.', 'cleantalk-spam-protect'),
                        '<a href="' . ( is_network_admin() ? 'settings.php?page=cleantalk' : 'options-general.php?page=cleantalk' ) . '">',
                        '</a>'
                    )
                    . '</h3>';
            }
            return;
        }

        $actual_logo_tag = '<img src=" ' . $this->spam_checker->getApbct()->logo__small__colored . '" alt="CleanTalk logo" />';
        $actual_plugin_name = $this->spam_checker->getApbct()->plugin_name;

        if ($this->spam_checker->getApbct()->data['wl_mode_enabled'] || $this->use_main_site_wl_mode) {
            $actual_logo_tag = '';
            if (isset($this->wl_mode_data['wl_brandname']) && $this->wl_mode_data['wl_brandname'] !== APBCT_NAME) {
                $actual_plugin_name = $this->wl_mode_data['wl_brandname'];
            } else {
                $actual_plugin_name = $this->spam_checker->getApbct()->data['wl_brandname'];
            }
        }

        ?>
        <div class="wrap">
        <h2>
            <?php
                echo $actual_logo_tag;
                echo $actual_plugin_name
            ?>
        </h2>
        <a style="color: gray; margin-left: 23px;" href="<?php echo $this->spam_checker->getApbct()->settings_link; ?>"><?php _e('Plugin Settings', 'cleantalk-spam-protect'); ?></a>
        <br />
        <h3><?php echo $this->spam_checker->getPageTitle(); ?></h3>
        <div id="ct_check_tabs">
            <ul>
                <li <?php echo (1 == $this->current_tab) ? 'class="active"' : ''; ?>><a href="<?php echo $this->spam_checker->getPageScriptName(); ?>?page=ct_check_<?php echo $this->spam_checker->getPageSlug(); ?>"><?php esc_html_e('Scan and new results', 'cleantalk-spam-protect') ?></a></li>
                <?php if ($this->spam_checker->getPageSlug() === 'users' && Users::getBadUsersCount() > 0) {
                    ?><li <?php echo (2 == $this->current_tab) ? 'class="active"' : ''; ?>><a href="<?php echo $this->spam_checker->getPageScriptName(); ?>?page=ct_check_<?php echo $this->spam_checker->getPageSlug(); ?>_bad"><?php esc_html_e('Non-checkable users', 'cleantalk-spam-protect') ?></a></li><?php
                }?>
                <li <?php echo (3 == $this->current_tab) ? 'class="active"' : ''; ?>><a href="<?php echo $this->spam_checker->getPageScriptName(); ?>?page=ct_check_<?php echo $this->spam_checker->getPageSlug(); ?>_logs"><?php esc_html_e('Scan logs', 'cleantalk-spam-protect') ?></a></li>
            </ul>
            <div id="ct_check_content">
        <?php
    }

    public static function setScreenOption()
    {
        $option = 'per_page';
        $args = array(
            'label'   => esc_html__('Show per page', 'cleantalk-spam-protect'),
            'default' => 10,
            'option'  => 'spam_per_page',
        );
        add_screen_option($option, $args);
    }

    private static function closeTags()
    {
        ?>
            </div>
        </div>
        <?php
    }
}
