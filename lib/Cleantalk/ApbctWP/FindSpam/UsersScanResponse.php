<?php

// Singleton
namespace Cleantalk\ApbctWP\FindSpam;

use Cleantalk\Common\TT;
use Cleantalk\Templates\Singleton;

class UsersScanResponse
{
    use Singleton;

    private $end;
    private $checked;
    private $spam;
    private $bad;
    private $error;
    private $error_message;

    private function init()
    {
        $this->end = 0;
        $this->checked = 0;
        $this->spam = 0;
        $this->bad = 0;
        $this->error = 0;
        $this->error_message = '';
    }

    /**
     * @return array
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function toArray()
    {
        return array(
            'end' => $this->end,
            'checked' => $this->checked,
            'spam' => $this->spam,
            'bad' => $this->bad,
            'error' => $this->error,
            'error_message' => $this->error_message,
        );
    }

    /**
     * @return string
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function toJson()
    {
        return TT::toString(json_encode($this->toArray()));
    }

    /**
     * @return string
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function getErrorMessage()
    {
        return $this->error_message;
    }

    /**
     * @param string $error_message
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function setErrorMessage($error_message)
    {
        $this->error_message = $error_message;
    }

    /**
     * @return string
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function getEnd()
    {
        return $this->end;
    }

    /**
     * @param int 1|0 $end
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function setEnd($end)
    {
        $this->end = $end;
    }

    /**
     * @return integer
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function getChecked()
    {
        return $this->checked;
    }

    /**
     * @param integer $checked
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function setChecked($checked)
    {
        $this->checked = $checked;
    }

    /**
     * @param integer $checked
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function updateChecked($checked)
    {
        $this->checked += $checked;
    }

    /**
     * @return string|integer
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function getSpam()
    {
        return $this->spam;
    }

    /**
     * @param integer $spam
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function setSpam($spam)
    {
        $this->spam = $spam;
    }

    /**
     * @param integer $spam
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function updateSpam($spam)
    {
        $this->spam += $spam;
    }

    /**
     * @return integer
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function getBad()
    {
        return $this->bad;
    }

    /**
     * @param integer $bad
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function setBad($bad)
    {
        $this->bad = $bad;
    }

    /**
     * @return string
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * @param int 1|0 $error
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function setError($error)
    {
        $this->error = $error;
    }
}
