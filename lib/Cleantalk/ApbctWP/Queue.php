<?php

namespace Cleantalk\ApbctWP;

class Queue extends \Cleantalk\Common\Queue
{
    const QUEUE_NAME = 'cleantalk_sfw_update_queue';

    public function getQueue()
    {
        return get_option(self::QUEUE_NAME);
    }

    public static function clearQueue()
    {
        return delete_option(self::QUEUE_NAME);
    }

    public function saveQueue($queue)
    {
        return update_option(self::QUEUE_NAME, $queue);
    }
}
