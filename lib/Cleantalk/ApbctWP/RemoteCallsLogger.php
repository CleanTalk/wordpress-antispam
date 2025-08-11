<?php

namespace Cleantalk\ApbctWP;

class RemoteCallsLogger
{
    /**
     * Data for logging
     */
    private $logging_data;

    /**
     * Message
     */
    private $message;

    /**
     * Constructor: fill logging_data
     *
     * @param array | string | null $logging_data
     */
    public function __construct($logging_data)
    {
        $this->logging_data = $logging_data;
        $this->message = $this->createMessage();
    }

    /**
     * Write log
     *
     * @return void
     */
    public function writeLog()
    {
        error_log(
            $this->message
        );
    }

    /**
     * Create message
     *
     * @return string
     */
    private function createMessage()
    {
        $substring = '';

        // logging_data - string
        if (is_string($this->logging_data)) {
            return $this->logging_data;
        }

        // logging_data - array
        foreach ($this->logging_data as $key => $data) {
            // if data is array
            if (is_array($data)) {
                $substring2 = '';

                foreach ($data as $param => $value) {
                    $substring2 .= "$param: $value; ";
                }

                $substring .= "[$key]: $substring2; ";
            } else {
                $substring .= "$key: $data; ";
            }
        }

        return $substring;
    }
}
