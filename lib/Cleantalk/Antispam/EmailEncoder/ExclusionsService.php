<?php

namespace Cleantalk\Antispam\EmailEncoder;

use Cleantalk\ApbctWP\State;
use Cleantalk\ApbctWP\Variables\Cookie;
use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\ApbctWP\Variables\Server;
use Cleantalk\Common\TT;

class ExclusionsService
{
    /**
     * @return string|false
     * @psalm-suppress PossiblyUnusedReturnValue
     */
    public function doSkipBeforeAnything()
    {
        return false;
    }

    /**
     * @return string|false
     * @psalm-suppress PossiblyUnusedReturnValue
     */
    public function doSkipBeforeModifyingHooksAdded()
    {
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
        return false;
    }
}
