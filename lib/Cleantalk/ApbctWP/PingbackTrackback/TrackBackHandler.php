<?php

namespace Cleantalk\ApbctWP\PingbackTrackback;

/**
 * Blocks incoming Trackback requests.
 *
 * Trackbacks are a legacy blogging mechanism that allows external websites
 * to notify WordPress about links to published content. Because Trackbacks
 * are frequently abused for spam, this handler intercepts all incoming
 * Trackback requests and returns an error response before WordPress can
 * create a Trackback comment.
 */
class TrackBackHandler
{
    /**
     * Registers hooks required to block incoming Trackbacks.
     */
    public function __construct()
    {
        add_action(
            'pre_trackback_post',
            array($this, 'blockTrackback'),
            1,
            6
        );
    }

    /**
     * Stops Trackback processing and returns an error response.
     *
     * The response follows the standard Trackback protocol and prevents
     * WordPress from creating a Trackback comment.
     *
     * @return void
     */
    public function blockTrackback(...$_args)
    {
        if (function_exists('trackback_response')) {
            trackback_response(
                1,
                'Trackbacks are disabled'
            );
        }

        exit;
    }
}
