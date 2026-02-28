<?php

namespace Cleantalk\Common;

/**
 * Fill the template with placeholders.
 * Basic brace signs is {{}} but can be overloaded in the caller class.
 * Use static::textPlateRender($_text, $_plate), where $_text is a template, $_plate is array of replacments.
 * <code>
 *      YourClass() {
 *          use TextPlate
 *          ...
 *          public function yourMethod() {
 *              $_text = 'Hello {{name}}!';
 *              $_plate = ['name' => 'World'];
 *              $result = self::textPlateRender($_text, $_plate);
 *          }
 *      }
 * </code>
 */
trait TextPlate
{
    protected static $text_plate_left_brace = '{{';
    protected static $text_plate_right_brace = '}}';
    /**
     *
     * @param string $_text A template.
     * @param array $_plate Replacements array.
     * @param bool $trim Do trim?
     * @return string
     */
    public static function textPlateRender(string $_text, array $_plate, bool $trim = true): string
    {
        try {
            $search = [];
            $_text = $trim ? trim($_text) : $_text;
            foreach ($_plate as $key => $value) {
                $key = self::validatePlateKey($key, $_text);
                $value = self::validatePlateValue($value, $key);
                $search[self::getBraced($key)] = $value;
            }
            // fill
            $result = strtr($_text, $search);
            // validate
            $result = self::validateResult($result);
        } catch (\Exception $e) {
            $result = self::getErrorString($e->getMessage());
        }
        return $result;
    }

    /**
     * @param string $item
     * @return string
     */
    private static function getBraced(string $item): string
    {
        return static::$text_plate_left_brace . $item . static::$text_plate_right_brace;
    }

    /**
     * @param string $result
     * @return string
     * @throws \Exception
     */
    private static function validateResult(string $result): string
    {
        $left_brace = preg_quote(static::$text_plate_left_brace, '#');
        $right_brace = preg_quote(static::$text_plate_right_brace, '#');
        $regexp = '#' . $left_brace . '\w+' . $right_brace . '#';
        $missed = '';
        preg_match_all($regexp, $result, $matches);
        if (isset($matches[0])) {
            $missed = implode(', ', $matches[0]);
        }
        if (!empty($missed)) {
            throw new \Exception('UNFILLED PLACEHOLDER EXIST:' . $missed);
        }
        return $result;
    }

    /**
     * @param string $key
     * @param string $_text
     * @return string
     * @throws \Exception
     */
    private static function validatePlateKey(string $key, string $_text): string
    {
        if (strpos($_text, self::getBraced($key)) === false) {
            throw new \Exception('NO PLACE FOR GIVEN PLACEHOLDER: ' . self::getBraced($key));
        }
        return $key;
    }

    /**
     * @param $value
     * @param $key
     * @return string
     * @throws \Exception
     */
    private static function validatePlateValue($value, $key): string
    {
        if (!is_string($value)) {
            throw new \Exception('INVALID PLACEHOLDER VALUE FOR GIVEN PLACEHOLDER: ' . $key);
        }
        return $value;
    }

    /**
     * @param string $error_string
     * @return string
     */
    private static function getErrorString(string $error_string): string
    {
        return 'TEXTPLATE_ERROR: ' . self::sanitizeError($error_string) . ', CALLED: ' . static::class;
    }

    /**
     * @param string $value
     * @return string
     */
    private static function sanitizeError(string $value): string
    {
        if (function_exists('esc_js')) {
            $value = esc_js($value);
        }
        return $value;
    }
}
