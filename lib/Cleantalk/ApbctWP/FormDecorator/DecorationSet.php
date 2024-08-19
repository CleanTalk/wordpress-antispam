<?php

namespace Cleantalk\ApbctWP\FormDecorator;

class DecorationSet
{
    public $text;

    public $color;

    public function header()
    {
        $prefix = 'apbct_form_decoration_' . __FUNCTION__ . '__';
        $template = '
        <div id="%HEADER_WRAPPER_ID%" class="%HEADER_WRAPPER_CLASS%">
            <div id="%HEADER_CONTENT_ID%" class="%HEADER_CONTENT_CLASS%">
                %HEADER_SVG%<span id="%HEADER_TEXT_ID%" class="%HEADER_TEXT_CLASS%">%HEADER_TEXT%</span>
            </div>
        </div>
        ';

        $template = str_replace('%HEADER_WRAPPER_ID%', $prefix . 'wrapper', $template);
        $template = str_replace('%HEADER_WRAPPER_CLASS%', $prefix . 'wrapper', $template);
        $template = str_replace('%HEADER_CONTENT_ID%', $prefix . 'content', $template);
        $template = str_replace('%HEADER_CONTENT_CLASS%', $prefix . 'content', $template);
        //    display: flex;
        //    flex-wrap: nowrap;
        //    flex-direction: column-reverse;
        //    justify-content: center;
        //    align-items: center;
        $template = str_replace('%HEADER_SVG%', $this->getHeaderSVG(), $template);
        $template = str_replace('%HEADER_TEXT_ID%', $prefix . 'text', $template);
        $template = str_replace('%HEADER_TEXT_CLASS%', $prefix . 'text', $template);
        //    display: block;
        //    position: absolute;
        //    color: #FFF;
        $template = str_replace('%HEADER_TEXT%', $this->text, $template);
        return $template;
    }

    private function getHeaderSVG()
    {
        return '
        <svg width="auto" height="auto" viewBox="0 0 595 76" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M0 22H91V49V76H0L25 49L0 22Z" fill="'. $this->darkenHexColor($this->color, 10) .'"/>
        <path d="M595 22H504V49V76H595L570 49L595 22Z" fill="'. $this->darkenHexColor($this->color, 10) .'"/>
        <path d="M48 22H91V49V76L48 60V45V22Z" fill="'. $this->darkenHexColor($this->color, 32) .'"/>
        <path d="M547 22H504V49V76L547 60V49V22Z" fill="'. $this->darkenHexColor($this->color, 32) .'"/>
        <g filter="url(#filter0_d_3860_2299)">
        <path d="M48 6H547V33V60H48V33V6Z" fill="'. $this->color .'"/>
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

    /**
     * Darkens a given hex color by a specified percentage.
     *
     * This function takes a hex color code and a percentage by which to darken the color.
     * It then returns the darker hex color code.
     *
     * @param string $hex The original hex color code (e.g., '#336699').
     * @param float $percent The percentage by which to darken the color (e.g., 20 for 20%).
     * @return string The darkened hex color code.
     */
    private function darkenHexColor($hex, $percent)
    {
        // Remove the hash at the start if it's there
        $hex = ltrim($hex, '#');

        // Convert hex to RGB
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        // Calculate the darker color
        $r = max(0, min(255, $r - ($r * $percent / 100)));
        $g = max(0, min(255, $g - ($g * $percent / 100)));
        $b = max(0, min(255, $b - ($b * $percent / 100)));

        // Convert RGB back to hex
        return sprintf("#%02x%02x%02x", $r, $g, $b);
    }
}
