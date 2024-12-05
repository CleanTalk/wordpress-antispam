<?php

namespace Cleantalk\ApbctWP\FormDecorator;

use Cleantalk\ApbctWP\FormDecorator\Decorations\DecorationSetHolidayDefault;
use Cleantalk\ApbctWP\FormDecorator\Decorations\DecorationSetHolidayFourthJuly;
use Cleantalk\ApbctWP\FormDecorator\Decorations\DecorationSetHolidayNewYear;
use Cleantalk\ApbctWP\FormDecorator\Decorations\DecorationSetHolidayChristmas;
use Cleantalk\Templates\Singleton;

class DecorationRegistry
{
    use Singleton;

    /**
     * @var DecorationSet[]
     */
    private $decorations = array();

    public function __construct()
    {
        $this->registerDecorations();
    }

    public function registerDecorations()
    {
        $this->decorations['holiday_fourth_july'] = new DecorationSetHolidayFourthJuly();
        $this->decorations['holiday_new_year'] = new DecorationSetHolidayNewYear();
        $this->decorations['holiday_christmas'] = new DecorationSetHolidayChristmas();
        $this->decorations['holiday_default'] = new DecorationSetHolidayDefault();
    }

    public function getDecoration($name)
    {
        return isset($this->decorations[$name]) ? $this->decorations[$name] : false;
    }

    /**
     * @return string[]
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function getDecorationLocalizedNames()
    {
        return array_map(function ($set) {
            return $set->localized_name;
        }, array_values($this->decorations));
    }

    public function getRegisteredNameByLocalizedName($localized_name)
    {
        foreach ($this->decorations as $_key => $decoration_set) {
            if ($decoration_set->localized_name === $localized_name) {
                return $_key;
            }
        }
        return false;
    }
}
