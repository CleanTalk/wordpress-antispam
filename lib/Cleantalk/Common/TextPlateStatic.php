<?php

namespace Cleantalk\Common;

class TextPlateStatic
{
    use TextPlate;

    /**
     * @param string $_text
     * @param array $_plate
     * @param bool $trim
     * @return string
     */
    public static function render(string $_text, array $_plate, bool $trim = true): string
    {
        return static::textPlateRender($_text, $_plate, $trim);
    }
}
