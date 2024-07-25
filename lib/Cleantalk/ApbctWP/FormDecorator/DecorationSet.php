<?php

namespace Cleantalk\ApbctWP\FormDecorator;

class DecorationSet {

    public function __construct()
    {
    }

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
        $template = str_replace('%HEADER_TEXT%', $this->getHeaderText(), $template);
        return $template;
    }

    private function getHeaderSVG()
    {
        return '
        <svg width="auto" height="auto" viewBox="0 0 595 76" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M0 22H91V49V76H0L25 49L0 22Z" fill="#CF2B2B"/>
        <path d="M595 22H504V49V76H595L570 49L595 22Z" fill="#CF2B2B"/>
        <path d="M48 22H91V49V76L48 60V45V22Z" fill="#9B1F1E"/>
        <path d="M547 22H504V49V76L547 60V49V22Z" fill="#9B1F1E"/>
        <g filter="url(#filter0_d_3860_2299)">
        <path d="M48 6H547V33V60H48V33V6Z" fill="#E62F2E"/>
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

    private function getHeaderText()
    {
        return __('HAPPY 4TH JULY', 'cleantalk-spam-protect');
    }

}
