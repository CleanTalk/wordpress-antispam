<?php

namespace Cleantalk\ApbctWP\PluginSettingsPage;

use Cleantalk\ApbctWP\Escape;
use Cleantalk\ApbctWP\ServerRequirementsChecker\ServerRequirementsChecker;
use Cleantalk\ApbctWP\State;
use Cleantalk\ApbctWP\LinkConstructor;
use Cleantalk\Common\TT;

/**
 * Class SummaryAndSupportRenderer
 *
 * Renders the "Summary & Support" section of the CleanTalk plugin settings page.
 * This section displays statistics, server requirements, connection reports, and support options.
 *
 * @package Cleantalk\ApbctWP\PluginSettingsPage
 * @since 6.65
 */
class SummaryAndSupportRenderer
{
    /**
     * @var State Instance of the main CleanTalk state object containing plugin settings and statistics.
     */
    private $apbct;

    /**
     * Constructor.
     *
     * @param State $apbct The main CleanTalk state object.
     */
    public function __construct($apbct)
    {
        $this->apbct = $apbct;
    }

    /**
     * Renders the complete summary and support section of the settings page.
     *
     * This method constructs the main container with two columns: left side for statistics,
     * server requirements, and connection reports; right side for support buttons.
     *
     * @return string The rendered HTML content.
     */
    public function render()
    {
        $template = '
        <div id="apbct_summary_and_support" class="apbct_settings-field_wrapper" style="display: none;">
            <div id="apbct_summary_and_support-sides_wrap">
                <div id="apbct_summary_and_support-left_side">
                    <h3 class="apbct_summary_and_support-side_header">%s</h3>
                        <h4 class="apbct_summary_and_support-inner_header">%s</h4>
                        %s
                        <h4 class="apbct_summary_and_support-inner_header">%s</h4>
                        %s
                        <h4 class="apbct_summary_and_support-inner_header">%s</h4>
                        %s
                </div>
                <div id="apbct_summary_and_support-right_side">
                    <h3 class="apbct_summary_and_support-side_header">%s</h3>
                    %s
                </div>
            </div>
        </div>        
        ';
        $rendered = sprintf(
            $template,
            __('Summary'),
            __('Statistics'),
            $this->renderStatistics(), // Statistics will be rendered here
            __('Sever requirements check'),
            $this->renderServerRequirements(), // Server requirements will be rendered here
            __('Connections'),
            $this->renderConnectionReports(), // Connection reports will be rendered here
            __('Support'),
            $this->renderSupportActions()  // Support buttons will be rendered here
        );
        return $rendered;
    }

    /**
     * Renders the statistics section containing various plugin performance metrics.
     *
     * Includes last request time, average response time, last SFW block, SFW update status,
     * SFW logs, and plugin version information.
     *
     * @return string HTML content of the statistics section.
     */
    private function renderStatistics()
    {
        $render = '
            <div class="apbct_summary-list_of_items">
            %s
            %s
            %s
            %s
            %s
            %s
            </div>
        ';

        $render = sprintf(
            $render,
            $this->renderLastRequest(),
            $this->renderAverageTime(),
            $this->renderLastSfwBlock(),
            $this->renderSfwUpdate(),
            $this->renderSfwLogs(),
            $this->renderPluginVersion()
        );

        return $render;
    }

    /**
     * Renders the support buttons section with links to support resources.
     *
     * Includes buttons for opening support tickets, accessing help documentation,
     * and creating a support user account with result display area.
     *
     * @return string HTML content of the support buttons section.
     */
    private function renderSupportActions()
    {
        $template = '
        <div class="apbct_summary_and_support-support_buttons_wrapper">
            <a href="%s" target="_blank" class="cleantalk_link cleantalk_link-auto cleantalk_link_text_center">%s</a>
            <a href="%s" target="_blank" class="cleantalk_link cleantalk_link-auto cleantalk_link_text_center">%s</a>
            <button type="button" id="apbct_summary_and_support-create_user_button" class="cleantalk_link cleantalk_link-auto" onclick="apbctCreateSupportUser()">
                    <div>%s</div>
                    <img id="apbct_summary_and_support-create_user_button_preloader" class="apbct_preloader_button" src="%s">
            </button>
        </div>
        <div class="apbct_summary_and_support-user_creation_result">
                <p id="apbct_summary_and_support-user_creation_title"></p>
                <code>
                <p class="apbct_summary_and_support-user_creation_row">
                    <span>%s:</span><span class="apbct_summary_and_support-user_creation_value" id="apbct_summary_and_support-user_creation_username"></span>
                </p>
                <p class="apbct_summary_and_support-user_creation_row">
                    <span>%s:</span><span class="apbct_summary_and_support-user_creation_value" id="apbct_summary_and_support-user_creation_email"></span>
                </p>
                <p class="apbct_summary_and_support-user_creation_row">
                    <span>%s:</span><span class="apbct_summary_and_support-user_creation_value" id="apbct_summary_and_support-user_creation_password"></span>
                </p>
                </code>
                <p id="apbct_summary_and_support-user_creation_mail_sent"></p>
                <p id="apbct_summary_and_support-user_creation_cron_updated"></p>
        </div>
        ';
        $template = sprintf(
            $template,
            esc_html('https://wordpress.org/support/plugin/cleantalk-spam-protect'),
            __('Open Support Ticket'),
            esc_html('https://cleantalk.org/help/introduction#anti-spam'),
            __('CleanTalk Anti-Spam Help'),
            __('Create Support User'),
            TT::toString(Escape::escUrl(APBCT_URL_PATH . '/inc/images/preloader2.gif')),
            __('Username'),
            __('Email'),
            __('Password')
        );
        return $template;
    }

    /**
     * Renders information about the last spam check request.
     *
     * @return string HTML content showing last request server and time.
     */
    private function renderLastRequest()
    {
        $server = isset($this->apbct->stats['last_request']['server']) && $this->apbct->stats['last_request']['server']
            ? Escape::escUrl($this->apbct->stats['last_request']['server'])
            : __('unknown', 'cleantalk-spam-protect');

        $time = isset($this->apbct->stats['last_request']['time']) && $this->apbct->stats['last_request']['time']
            ? date('M d Y H:i:s', $this->apbct->stats['last_request']['time'])
            : __('unknown', 'cleantalk-spam-protect');

        $html = sprintf(
            __('Last spam check request to %s server was at %s.', 'cleantalk-spam-protect'),
            is_string($server) ? $server : __('unknown', 'cleantalk-spam-protect'),
            $time ? $time : __('unknown', 'cleantalk-spam-protect')
        );

        return $this->wrapListItem($html);
    }

    /**
     * Renders the average request time for the past 7 days.
     *
     * @return string HTML content showing average request time.
     */
    private function renderAverageTime()
    {
        $earliest_date = null;
        if (!empty($this->apbct->stats['requests'])) {
            $request_keys = array_keys($this->apbct->stats['requests']);
            if (!empty($request_keys)) {
                $earliest_date = min($request_keys);
            }
        }

        $average_time = null;
        if ($earliest_date !== null && isset($this->apbct->stats['requests'][$earliest_date]['average_time'])) {
            $average_time = $this->apbct->stats['requests'][$earliest_date]['average_time'];
        }

        $formatted_time = $average_time !== null
            ? round($average_time, 3)
            : __('unknown', 'cleantalk-spam-protect');

        $html = sprintf(
            __('Average request time for past 7 days: %s seconds.', 'cleantalk-spam-protect'),
            $formatted_time
        );

        return $this->wrapListItem($html);
    }

    /**
     * Renders information about the last SpamFireWall block.
     *
     * @return string HTML content showing last SFW block IP and time.
     */
    private function renderLastSfwBlock()
    {
        $last_sfw_block_ip = isset($this->apbct->stats['last_sfw_block']['ip']) && $this->apbct->stats['last_sfw_block']['ip']
            ? $this->apbct->stats['last_sfw_block']['ip']
            : __('unknown', 'cleantalk-spam-protect');

        $last_sfw_block_time = isset($this->apbct->stats['last_sfw_block']['time']) && $this->apbct->stats['last_sfw_block']['time']
            ? date('M d Y H:i:s', $this->apbct->stats['last_sfw_block']['time'])
            : __('unknown', 'cleantalk-spam-protect');

        $html = sprintf(
            __('Last time SpamFireWall was triggered for %s IP at %s', 'cleantalk-spam-protect'),
            $last_sfw_block_ip ? $last_sfw_block_ip : __('unknown', 'cleantalk-spam-protect'),
            $last_sfw_block_time ? $last_sfw_block_time : __('unknown', 'cleantalk-spam-protect')
        );

        return $this->wrapListItem($html);
    }

    /**
     * Renders SpamFireWall update information.
     *
     * @return string HTML content showing SFW update time, entry count, and update progress if applicable.
     */
    private function renderSfwUpdate()
    {
        $last_update_time = isset($this->apbct->stats['sfw']['last_update_time']) && $this->apbct->stats['sfw']['last_update_time']
            ? date('M d Y H:i:s', $this->apbct->stats['sfw']['last_update_time'])
            : __('unknown', 'cleantalk-spam-protect');

        $html = sprintf(
            __('SpamFireWall was updated %s. Now contains %s entries.', 'cleantalk-spam-protect'),
            $last_update_time ? $last_update_time : __('unknown', 'cleantalk-spam-protect'),
            isset($this->apbct->stats['sfw']['entries']) ? (int)$this->apbct->stats['sfw']['entries'] : __('unknown', 'cleantalk-spam-protect')
        );

        if ($this->apbct->fw_stats['firewall_updating_id']) {
            $html .= ' ' . __('Under updating now:', 'cleantalk-spam-protect') . ' ' . (int)$this->apbct->fw_stats['firewall_update_percent'] . '%';
        }

        return $this->wrapListItem($html);
    }

    /**
     * Renders SpamFireWall log information.
     *
     * @return string HTML content showing last SFW event send time and amount.
     */
    private function renderSfwLogs()
    {
        $last_send_time = $this->apbct->stats['sfw']['last_send_time']
            ? date('M d Y H:i:s', $this->apbct->stats['sfw']['last_send_time'])
            : __('unknown', 'cleantalk-spam-protect');

        $html = sprintf(
            __('SpamFireWall sent %s events at %s.', 'cleantalk-spam-protect'),
            $this->apbct->stats['sfw']['last_send_amount'] ? (int)$this->apbct->stats['sfw']['last_send_amount'] : __('unknown', 'cleantalk-spam-protect'),
            $last_send_time ? $last_send_time : __('unknown', 'cleantalk-spam-protect')
        );

        return $this->wrapListItem($html);
    }


    /**
     * Renders the current plugin version.
     *
     * @return string HTML content showing the plugin version.
     */
    private function renderPluginVersion()
    {
        return $this->wrapListItem(
            __('Plugin version', 'cleantalk-spam-protect') . ' ' . APBCT_VERSION
        );
    }

    /**
     * Renders connection reports showing failed connections to CleanTalk servers.
     *
     * Displays a table of negative reports and provides option to send unsent reports
     * if the feature is enabled in settings.
     *
     * @return string HTML content of connection reports section.
     */
    private function renderConnectionReports()
    {
        $connection_reports = $this->apbct->getConnectionReports();
        $render = '';

        if (!$connection_reports->hasNegativeReports()) {
            $render .= __('There are no failed connections to server.', 'cleantalk-spam-protect');
        } else {
            $reports_html = $connection_reports->prepareNegativeReportsHtmlForSettingsPage();
            $render .= Escape::escKses(
                $reports_html,
                array(
                    'tr' => array('style' => true),
                    'td' => array(),
                    'th' => array('colspan' => true),
                    'b' => array(),
                    'span' => array('id' => true),
                    'div' => array('id' => true),
                    'table' => array('id' => true),
                )
            );

            if (!$connection_reports->hasUnsentReports()) {
                $render .= __('All the reports already have been sent.', 'cleantalk-spam-protect');
            } else {
                $sending_disabled = !$this->apbct->settings['misc__send_connection_reports'];
                $disabled = $sending_disabled ? ' disabled="disabled"' : '';
                $sending_report_div = '<div id="apbct_sending_report_div">';
                if ($sending_disabled) {
                    $sending_report_div .= '<span id="apbct_sending_report_disabled_notice">'
                                     . __('Please, enable "Send connection reports" setting to be able to send reports', 'cleantalk-spam-protect')
                                    . '</span>';
                }
                $sending_report_div .= '<button name="submit" style="margin:0;" class="cleantalk_link cleantalk_link-manual" value="ct_send_connection_report"' . $disabled . '>'
                                 . __('Send new report', 'cleantalk-spam-protect')
                                 . '</button>';
                $sending_report_div .= '</div>';
                $render .= $sending_report_div;
            }
        }
        return $render;
    }


    /**
     * Renders server requirements check results.
     *
     * Checks various server requirements and displays warnings for any unmet requirements
     * with links to documentation for resolving issues.
     *
     * @return string HTML content of server requirements section.
     */
    private function renderServerRequirements()
    {
        $checker = new ServerRequirementsChecker();
        $warnings = $checker->checkRequirements() ?: [];
        $requirements_data = $checker->requirements;
        $requirement_items = $checker->requirement_items;

        $render = '<div class="apbct_summary-list_of_items">';

        foreach ($requirement_items as $key => $item) {
            $value = $requirements_data[$key];
            if ($key === 'curl_support' || $key === 'allow_url_fopen') {
                $value = $value ? __('enabled', 'cleantalk-spam-protect') : __('disabled', 'cleantalk-spam-protect');
            }
            $label = sprintf(__($item['label'], 'cleantalk-spam-protect'), $value);

            $warn_text = '';
            foreach ($warnings as $warn) {
                if (stripos($warn, $item['pattern']) !== false) {
                    $warn_text = ' <span>(' . esc_html($warn) . ')</span>';
                    break;
                }
            }
            $coloring_template = 'class="%s"';
            $icon_right_style = sprintf(
                $coloring_template,
                (
                    !empty($warn_text)
                        ? ' apbct-red apbct-icon-attention-alt'
                        : ' apbct-green apbct-icon-ok'
                )
            );
            $render .= $this->wrapListItem('<span>' . $label . $warn_text . '</span><span ' . $icon_right_style . '></span>');
        }

        if (!empty($warnings)) {
            $link = LinkConstructor::buildCleanTalkLink('notice_server_requirements', 'help/system-requirements-for-anti-spam-and-security ');
            $render .= sprintf(
                '<a href="%s">%s</a>',
                $link,
                __('Instructions for solving the compatibility issue', 'cleantalk-spam-protect')
            );
        }
        $render .= '</div>';
        return $render;
    }

    /**
     * Wraps content in a list item with appropriate styling.
     *
     * @param string $html The content to wrap.
     * @return string The wrapped HTML content.
     */
    private function wrapListItem($html)
    {
        $wrap = '<span class="apbct_summary_list_item apbct-icon-right-dir">%s</span>';
        $wrap = sprintf(
            $wrap,
            $html
        );

        return $wrap;
    }
}
