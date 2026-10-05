<?php

namespace Cleantalk\ApbctWP;

use Cleantalk\Common\AbstractUpdateChangelogNotice;

/**
 * Shows the changelog of the pending Security by CleanTalk update
 * on the plugins list page. Uses native WordPress admin markup only.
 */
class UpdateChangelogNotice extends AbstractUpdateChangelogNotice
{
    /**
     * @inheritDoc
     */
    protected function getPluginFile()
    {
        return APBCT_PLUGIN_BASE_NAME;
    }

    /**
     * @inheritDoc
     */
    protected function getSlug()
    {
        return 'cleantalk-spam-protect';
    }

    /**
     * @inheritDoc
     */
    protected function getGithubRepo()
    {
        return 'CleanTalk/wordpress-antispam';
    }

    /**
     * @inheritDoc
     */
    protected function escapeChangelogHtml($html)
    {
        return Escape::escKsesPreset($html, 'apbct_update_changelog_notice');
    }

    /**
     * @inheritDoc
     *
     * The markup is placed outside the message paragraph, so the native notice
     * icon is not duplicated.
     */
    protected function getNoticeHtml($version, $changelog_html)
    {
        return sprintf(
            '<details open>'
            . '<summary><strong>%s</strong></summary>'
            . '%s'
            . '</details>',
            esc_html(
                __('A few useful tweaks in the new version', 'cleantalk-spam-protect')
            ),
            $changelog_html
        );
    }
}
