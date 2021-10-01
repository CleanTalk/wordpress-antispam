<?php

namespace Cleantalk\ApbctWP\State;

use Cleantalk\Common\State\Options;

class Errors extends Options
{
    /**
     * @inheritDoc
     */
    protected function setDefaults()
    {
        return array();
    }

    /**
     * Prepares an adds an error to the plugin's data
     *
     * @param string $type Error type/subtype
     * @param string|array $error Error
     * @param string $major_type Error major type
     * @param bool $set_time Do we need to set time of this error
     *
     * @returns null
     */
    public function errorAdd($type, $error, $major_type = null, $set_time = true)
    {
        $error = is_array($error)
            ? $error['error']
            : $error;

        // Exceptions
        if (($type == 'send_logs' && $error == 'NO_LOGS_TO_SEND') ||
            ($type == 'send_firewall_logs' && $error == 'NO_LOGS_TO_SEND') ||
            $error == 'LOG_FILE_NOT_EXISTS'
        ) {
            return;
        }

        $error = array(
            'error'      => $error,
            'error_time' => $set_time ? current_time('timestamp') : null,
        );

        if ( ! empty($major_type)) {
            $this->errors[$major_type][$type] = $error;
        } else {
            $this->errors[$type] = $error;
        }

        $this->saveErrors();
    }

    /**
     * Deletes an error from the plugin's data
     *
     * @param array|string $type Error type to delete
     * @param bool $save_flag Do we need to save data after error was deleted
     * @param string $major_type Error major type to delete
     *
     * @returns null
     */
    public function errorDelete($type, $save_flag = false, $major_type = null)
    {
        /** @noinspection DuplicatedCode */
        if (is_string($type)) {
            $type = explode(' ', $type);
        }

        foreach ($type as $val) {
            if ($major_type) {
                if (isset($this->errors[$major_type][$val])) {
                    unset($this->errors[$major_type][$val]);
                }
            } else {
                if (isset($this->errors[$val])) {
                    unset($this->errors[$val]);
                }
            }
        }

        // Save if flag is set and there are changes
        if ($save_flag) {
            $this->saveErrors();
        }
    }

    /**
     * Deletes all errors from the plugin's data
     *
     * @param bool $save_flag Do we need to save data after all errors was deleted
     *
     * @returns null
     */
    public function errorDeleteAll($save_flag = false)
    {
        $this->errors = array();
        if ($save_flag) {
            $this->saveErrors();
        }
    }

    /**
     * Set or deletes an error depends on the first bool parameter
     *
     * @param $add_error
     * @param $error
     * @param $type
     * @param null $major_type
     * @param bool $set_time
     * @param bool $save_flag
     */
    public function errorToggle($add_error, $type, $error, $major_type = null, $set_time = true, $save_flag = true)
    {
        if ( $add_error && ! $this->errorExists($type) ) {
            $this->errorAdd($type, $error, $major_type, $set_time);
        } elseif ( $this->errorExists($type) ) {
            $this->errorDelete($type, $save_flag, $major_type);
        }
    }

    public function errorExists($error_type)
    {
        return array_key_exists($error_type, (array)$this->errors);
    }

    /**
     * Checking if errors are in the setting, and they are not empty.
     *
     * @return bool
     */
    public function isHaveErrors()
    {
        if ( count((array)$this->errors) ) {
            foreach ( (array)$this->errors as $error ) {
                if ( is_array($error) ) {
                    return (bool)count($error);
                }
            }

            return true;
        }

        return false;
    }

}
