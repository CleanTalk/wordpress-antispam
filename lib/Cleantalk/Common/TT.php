<?php

namespace Cleantalk\Common;

/**
 * Provides a set of static methods for type transformations.
 */

class TT
{
    /**
     * Converts a given value to an integer.
     *
     * This method attempts to convert a given value to an integer. It first checks if the value is already an integer,
     * in which case it returns the value as is. If the value is not an integer but is a scalar type (e.g., string, float),
     * it casts the value to an integer. If neither condition is met, it defaults to returning 0.
     *
     * @param mixed $value The value to be converted to an integer.
     * @return int The converted integer value, or 0 if conversion is not possible.
     */
    public static function toInt($value)
    {
        if ( isset($value) && is_int($value) ) {
            return $value;
        }
        if ( isset($value) && is_scalar($value) ) {
            return (int)$value;
        }
        return 0;
    }

    /**
     * Converts a given value to a string.
     *
     * This method attempts to convert a given value to a string. It first checks if the value is already a string,
     * in which case it returns the value as is. If the value is not a string but is a scalar type (e.g., integer, float, boolean),
     * it casts the value to a string. If neither condition is met, it defaults to returning an empty string.
     *
     * @param mixed $value The value to be converted to a string.
     *
     * @return string The converted string value, or an empty string if conversion is not possible.
     *
     * @psalm-taint-specialize
     * todo Attention. The suppressing above is enabled to avoid taint analysis FP.
     * todo However we should sanitize any POST usage that aggregated in this method.
     */
    public static function toString($value)
    {
        if ( isset($value) && is_string($value) ) {
            return $value;
        }
        if ( isset($value) && is_scalar($value) ) {
            return (string)$value;
        }
        return '';
    }

    /**
     * Converts a given value to an array.
     *
     * This method attempts to convert a given value to an array. If the value is already an array,
     * it returns the value as is. If the value is not an array, it attempts to cast the value to an array.
     * This is useful for ensuring that the value can be worked with as an array, regardless of its original type.
     * If the value is null or cannot be converted, an empty array is returned.
     *
     * @param mixed $value The value to be converted to an array.
     * @return array The converted array, or an empty array if conversion is not possible.
     */
    public static function toArray($value)
    {
        if ( isset($value) && is_array($value) ) {
            return $value;
        }
        if ( isset($value) && !is_array($value) ) {
            //todo cast methods needs
            if ($value instanceof \ArrayObject) {
                return $value->getArrayCopy();
            }
            return (array)$value;
        }
        return array();
    }

    /**
     * Converts a given value to a boolean.
     *
     * This method attempts to convert a given value to a boolean. It first checks if the value is already a boolean,
     * in which case it returns the value as is. If the value is not a boolean, it attempts to cast the value to a boolean.
     * This casting is useful for ensuring that the value can be worked with as a boolean, regardless of its original type.
     * If the value is null or cannot be converted, false is returned.
     *
     * @param mixed $value The value to be converted to a boolean.
     * @return bool The converted boolean value, or false if conversion is not possible.
     */
    public static function toBool($value)
    {
        if ( isset($value) && is_bool($value) ) {
            return $value;
        }
        if ( isset($value) && !is_bool($value) ) {
            //todo cast methods needs
            return (bool)$value;
        }
        return false;
    }

    /**
     * Retrieves a value from an array by key and converts it to an integer.
     *
     * This method looks for a specified key in an array and returns its value converted to an integer.
     * If the key does not exist in the array, or the array itself is invalid, a default value is returned instead.
     * The conversion to integer is handled by the `toInt` method, ensuring consistent type casting across the class.
     *
     * @param mixed $array The array from which to retrieve the value.
     * @param int|string $key The key of the value to retrieve from the array.
     * @param int $default The default value to return if the key is not found or the array is invalid. Defaults to 0.
     * @return int The value retrieved from the array and converted to an integer, or the default value if not found.
     */
    public static function getArrayValueAsInt($array, $key, $default = 0)
    {
        if ( !is_array($array) || !isset($array[$key]) ) {
            return self::toInt($default);
        }

        return self::toInt($array[$key]);
    }

    /**
     * Retrieves a value from an array by key and converts it to a string.
     *
     * This method looks for a specified key in an array and returns its value converted to a string.
     * If the key does not exist in the array, or the array itself is invalid, a default value is returned instead.
     * The conversion to string is handled by the `toString` method, ensuring consistent type casting across the class.
     * @param mixed $array
     * @param int|string $key
     * @param string $default
     * @return string
     */
    public static function getArrayValueAsString($array, $key, $default = '')
    {
        if ( !is_array($array) || !isset($array[$key]) ) {
            return self::toString($default);
        }

        return self::toString($array[$key]);
    }

    /**
     * Retrieves a value from an array by key and converts it to an array.
     *
     * This method looks for a specified key in an array and returns its value converted to an array.
     * If the key does not exist in the array, or the array itself is invalid, a default value is returned instead.
     * The conversion to string is handled by the `toArray` method, ensuring consistent type casting across the class.
     * @param mixed $array
     * @param int|string $key
     * @param array $default
     * @return array
     */
    public static function getArrayValueAsArray($array, $key, $default = array())
    {
        if ( !is_array($array) || !isset($array[$key]) ) {
            return self::toArray($default);
        }

        return self::toArray($array[$key]);
    }

    /**
     * Retrieves a value from an array by key and converts it to boolean.
     * *
     * * This method looks for a specified key in an array and returns its value converted to boolean.
     * * If the key does not exist in the array, or the array itself is invalid, a default value is returned instead.
     * * The conversion to string is handled by the `toBool` method, ensuring consistent type casting across the class.
     * @param mixed $array
     * @param int|string $key
     * @param bool $default
     * @return bool
     */
    public static function getArrayValueAsBool($array, $key, $default = false)
    {
        if ( !is_array($array) || !isset($array[$key]) ) {
            return self::toBool($default);
        }

        return self::toBool($array[$key]);
    }
}
