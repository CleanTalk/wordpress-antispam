<?php

namespace Cleantalk\Common;

/**
 * Class Arr
 * Fluent Interface
 * Allows to work with multi dimensional arrays
 *
 * @package Cleantalk
 *
 * @psalm-suppress UnusedProperty
 */
class Arr
{
    private $array;
    private $found = array();
    private $result = array();

    /**
     * Arr constructor.
     *
     * @param $array
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function __construct($array)
    {
        $this->array = is_array($array)
            ? $array
            : array();
    }

    /**
     * Recursive
     * Check if Array has keys given keys
     * Save found keys in $this->found
     *
     * @param array|string $keys
     * @param bool $regexp
     * @param array $array
     *
     * @return Arr
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function getKeys($keys = array(), $regexp = false, $array = array())
    {
        $array = $array ?: $this->array;
        $keys  = is_array($keys) ? $keys : explode(',', $keys);

        if (empty($array) || empty($keys)) {
            return $this;
        }

        $this->found = $keys === array('all')
            ? $this->array
            : $this->search(
                'key',
                $array,
                $keys,
                $regexp
            );

        return $this;
    }

    /**
     * Recursive
     * Check if Array has values given values
     * Save found keys in $this->found
     *
     * @param array|string $values
     * @param bool $regexp
     * @param array $array
     *
     * @return $this
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function getValues($values = array(), $regexp = false, $array = array())
    {
        $array = $array ?: $this->array;
        $keys  = is_array($values) ? $values : explode(',', $values);

        if (empty($array) || empty($values)) {
            return $this;
        }

        $this->found = $values === array('all')
            ? $this->array
            : $this->search(
                'value',
                $array,
                $keys,
                $regexp
            );

        return $this;
    }

    /**
     * @param array $searched
     * @param false $regexp
     * @param array $array
     *
     * @return $this
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function getArray($searched = array(), $regexp = false, $array = array())
    {
        $array = $array ?: $this->array;


        if (empty($array) || empty($searched)) {
            return $this;
        }

        $this->found = $searched === array('all')
            ? $this->array
            : $this->search(
                'array',
                $array,
                $searched,
                $regexp
            );

        $this->found = $this->found === $searched ? $this->found : array();

        return $this;
    }

    /**
     * Recursive
     * Check if array contains wanted data type
     *
     * @param string $type
     * @param array $array
     * @param array $found
     *
     * @return bool
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function is($type, $array = array(), $found = array())
    {
        $array = $array ?: $this->array;
        $found = $found ?: $this->found;

        foreach ($array as $key => $value) {
            if (array_key_exists($key, $found)) {
                if (is_array($found[$key])) {
                    if ( ! $this->is($type, $value, $found[$key])) {
                        return false;
                    }
                } else {
                    switch ($type) {
                        case 'regexp':
                            $value = preg_match('/\/.*\//', $value) === 1 ? $value : '/' . $value . '/';
                            if (@preg_match($value, '') === false) {
                                return false;
                            }
                            break;
                    }
                }
            }
        }

        return true;
    }

    /**
     * @param string $type
     * @param array $array
     * @param array $searched
     * @param bool $regexp
     * @param array $found
     *
     * @return array
     */
    private function search($type, $array = array(), $searched = array(), $regexp = false, $found = array())
    {
        foreach ($array as $key => $value) {
            // Recursion
            if (is_array($value)) {
                $result = $this->search($type, $value, $searched, $regexp, array());
                if ($result) {
                    $found[$key] = $result;
                }
                // Execution
            } else {
                foreach ($searched as $searched_key => $searched_val) {
                    switch ($type) {
                        case 'key':
                            if (
                                $key === $searched_val ||
                                ($regexp && preg_match('/' . $searched_val . '/', $key) === 1)
                            ) {
                                $found[$key] = true;
                            }
                            break;
                        case 'value':
                            if (
                                stripos($value, $searched_val) !== false ||
                                ($regexp && preg_match('/' . $searched_val . '/', $value) === 1)
                            ) {
                                $found[$key] = true;
                            }
                            break;
                        case 'array':
                            if (
                                stripos($key, $searched_key) !== false ||
                                ($regexp && preg_match('/' . $searched_key . '/', $key) === 1)
                            ) {
                                if (is_array($value)) {
                                    /** @psalm-suppress InvalidArgument */
                                    //@ToDo maybe $searched_key need to be replaced by $searched_val?
                                    $result = $this->search('array', $value, $searched_key, $regexp, array());
                                    if ($result) {
                                        $found[$key] = $result;
                                    }
                                } else {
                                    $found[$key] = $value;
                                }
                            }
                            break;
                    }
                }
            }
        }

        return $found;
    }

    /**
     * @param array $arr1
     * @param array $arr2
     *
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function compare($arr1, $arr2)
    {
        foreach ($arr1 as $_value) {
            if (($arr1 === $arr2) && is_array($arr1) && is_array($arr2)) {
                $this->compare($arr1, $arr2);
            }
        }
    }

    /**
     * Recursive
     * Delete elements from array with found keys ( $this->found )
     * If $searched param is differ from 'arr_special_param'
     *
     * @param mixed $searched
     * @param array $array
     * @param array $found
     *
     * @return array
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function delete($searched = 'arr_special_param', $array = array(), $found = array())
    {
        $array = $array ?: $this->array;
        $found = $found ?: $this->found;

        foreach ($array as $key => $value) {
            if (array_key_exists($key, $found)) {
                if (is_array($found[$key])) {
                    $array[$key] = $this->delete($searched, $value, $found[$key]);
                    if (empty($array[$key])) {
                        unset($array[$key]);
                    }
                } else {
                    if ($searched === 'arr_special_param' || $searched === $value) {
                        unset($array[$key]);
                    }
                }
            }
        }

        $this->result = $array;

        return $array;
    }

    /**
     * @return bool
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function result()
    {
        return (bool)$this->found;
    }
}
