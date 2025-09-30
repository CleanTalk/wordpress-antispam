<?php

namespace Cleantalk\ApbctWP;

use Cleantalk\ApbctWP\Variables\Server;
use Cleantalk\ApbctWP\Cron;

class SupportUser extends \Cleantalk\Common\SupportUser
{
    protected $cron_task_handler = 'apbct_cron_remove_support_user';

    /**
     * Updates timestamp of last user creation attempt
     */
    protected function setAjaxLastCall()
    {
        /**
         * @global State $apbct
         */
        global $apbct;
        $apbct->data[static::LAST_CALL_SIGN] = time();
        $apbct->saveData();
    }

    /**
     * Gets timestamp of last user creation attempt
     * @return int Unix timestamp of last call
     */
    protected function getAjaxLastCall()
    {
        global $apbct;

        return isset($apbct->data[static::LAST_CALL_SIGN]) ? (int)$apbct->data[static::LAST_CALL_SIGN] : 0;
    }

    /**
     * Cron job handler for deleting support users
     * Deletes all support users and removes the cron task
     */
    public function performCronDeleteUser()
    {
        parent::performCronDeleteUser();
        $cron = new Cron();
        $cron->removeTask(static::CRON_TASK_NAME);
    }

    /**
     * Initializes the cron task for deleting support users.
     * @return void
     */
    public function scheduleCronDeleteUser()
    {
        $cron = new Cron();
        if ( function_exists($this->cron_task_handler) ) {
            $cron->updateTask(
                parent::CRON_TASK_NAME,
                $this->cron_task_handler,
                parent::CRON_PERIOD_USER_DELETION,
                time() + parent::CRON_PERIOD_USER_DELETION
            );
        }
    }
}
