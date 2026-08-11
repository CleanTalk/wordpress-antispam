<?php

namespace Cleantalk\ApbctWP\AdminBannersModule;

abstract class AdminBannerAbstract
{
    /**
     * @var string
     */
    protected $prefix = 'cleantalk_';

    /**
     * Hiding time in days
     */
    const HIDING_TIME = 14;

    /**
     * @var string
     */
    protected $banner_id;

    /**
     * Show the banner if conditions are met
     *
     * @return void
     */
    public function show()
    {
        if ($this->needToShow()) {
            $this->display();
        }
    }

    /**
     * Check if banner needs to be shown
     *
     * @return bool
     */
    abstract protected function needToShow();

    /**
     * Output the banner HTML
     *
     * @return void
     */
    abstract protected function display();

    /**
     * Check if the banner was dismissed by the user
     *
     * @return bool
     */
    protected function isDismissed()
    {
        $uid = get_current_user_id();
        $dismissed_date = get_option($this->banner_id . '_' . $uid . '_dismissed');

        if ($dismissed_date !== false && \Cleantalk\Common\Helper::dateValidate($dismissed_date)) {
            $current_date = date_create();
            $notice_date = date_create($dismissed_date);

            $diff = date_diff($current_date, $notice_date);

            if ($diff->days <= static::HIDING_TIME) {
                return true;
            }
        }

        return false;
    }
}
