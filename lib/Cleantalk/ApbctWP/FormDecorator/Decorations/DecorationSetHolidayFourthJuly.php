<?php

namespace Cleantalk\ApbctWP\FormDecorator\Decorations;

use Cleantalk\ApbctWP\FormDecorator\DecorationSet;

class DecorationSetHolidayFourthJuly extends DecorationSet
{
    public $text;

    public $color;

    protected $css_class_name = 'apbct_form_decoration--fourth-july';

    public function __construct()
    {
        $this->localized_name = __('Fourth July Celebration', 'cleantalk-spam-protect');
        parent::__construct();
    }

    protected function getHeaderSVG()
    {
        return '
        <svg viewBox="0 0 595 76" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M0 22H91V49V76H0L25 49L0 22Z" fill="' . $this->darkenHexColor($this->color, 10) . '"/>
        <path d="M595 22H504V49V76H595L570 49L595 22Z" fill="' . $this->darkenHexColor($this->color, 10) . '"/>
        <path d="M48 22H91V49V76L48 60V45V22Z" fill="' . $this->darkenHexColor($this->color, 32) . '"/>
        <path d="M547 22H504V49V76L547 60V49V22Z" fill="' . $this->darkenHexColor($this->color, 32) . '"/>
        <g filter="url(#filter0_d_3860_2299)">
        <path d="M48 6H547V33V60H48V33V6Z" fill="' . $this->color . '"/>
        </g>
        <defs>
        <filter id="filter0_d_3860_2299" x="38" y="0" width="519" height="74" filterUnits="userSpaceOnUse" color-interpolation-filters="sRGB">
        <feFlood flood-opacity="0" result="BackgroundImageFix"/>
        <feColorMatrix in="SourceAlpha" type="matrix" values="0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 127 0" result="hardAlpha"/>
        <feOffset dy="4"/>
        <feGaussianBlur stdDeviation="5"/>
        <feComposite in2="hardAlpha" operator="out"/>
        <feColorMatrix type="matrix" values="0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0 0.3 0"/>
        <feBlend mode="normal" in2="BackgroundImageFix" result="effect1_dropShadow_3860_2299"/>
        <feBlend mode="normal" in="SourceGraphic" in2="effect1_dropShadow_3860_2299" result="shape"/>
        </filter>
        </defs>
        </svg>
        ';
    }
}
