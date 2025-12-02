<?php

namespace Cleantalk\Common\ContactsEncoder\Exclusions;

use Cleantalk\Common\ContactsEncoder\Dto\Params;

class ExclusionsService
{
    /**
     * @var Params
     */
    protected $params;

    public function __construct(Params $params)
    {
        $this->params = $params;
    }

    /**
     * @return string|false
     * @psalm-suppress PossiblyUnusedReturnValue
     */
    public function doSkipBeforeAnything()
    {
        if ( $this->byAccessKeyFail() ) {
            return 'byAccessKeyFail';
        }

        return false;
    }

    /**
     * @param $content
     *
     * @return string|false
     * @psalm-suppress PossiblyUnusedReturnValue
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function doReturnContentBeforeModify($content)
    {
        if ( ! $this->params->do_encode_emails && ! $this->params->do_encode_phones ) {
            return 'globallyDisabledBothEncoding';
        }

        if ( $this->byLoggedIn() ) {
            return 'byLoggedIn';
        }

        //skip empty or invalid content
        if ( $this->byEmptyContent($content) ) {
            return 'byEmptyContent';
        }

        return false;
    }



    /**
     * @return bool
     */
    protected function byAccessKeyFail()
    {
        return empty($this->params->api_key);
    }

    /**
     * @param string $content
     *
     * @return bool
     */
    private function byEmptyContent($content)
    {
        //skip empty or invalid content
        return empty($content) || !is_string($content);
    }

    /**
     * @return bool
     */
    protected function byLoggedIn()
    {
        return $this->params->is_logged_in;
    }
}
