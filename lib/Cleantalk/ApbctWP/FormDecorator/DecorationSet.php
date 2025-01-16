<?php

namespace Cleantalk\ApbctWP\FormDecorator;

class DecorationSet
{
    public $text;

    /**
     * @var string
     * @psalm-suppress PossiblyUnusedProperty
     */
    public $color;

    /**
     * @var string
     */
    public $localized_name;

    /**
     * @var string
     */
    protected $css_class_name;

    public function __construct()
    {
    }

    /**
     * @return string
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function getCssClass()
    {
        return $this->css_class_name;
    }

    /**
     * @return void
     * @psalm-suppress PossiblyUnusedMethod
     */
    public function localizeCSS()
    {
        $css_file_path = $this->getCSSFilePath();
        if (false === $css_file_path) {
            //add error
            return;
        }
        add_action('wp_enqueue_scripts', function () {
            wp_enqueue_style(
                'ct_' . $this->css_class_name,
                $this->getCSSFilePath(),
                array(),
                APBCT_VERSION,
                'all'
            );
        });
    }

    private function getCSSFilePath()
    {
        $css_path = APBCT_URL_PATH . '/lib/Cleantalk/ApbctWP/FormDecorator/Decorations/' . $this->css_class_name . '.css';

        return @file_get_contents($css_path) ? $css_path : false;
    }

    public function header()
    {
        $prefix = $this->css_class_name . '_' . __FUNCTION__ . '__';
        $template = '
        <div id="%HEADER_WRAPPER_ID%" class="%HEADER_WRAPPER_CLASS%">
            <div id="%HEADER_CONTENT_ID%" class="%HEADER_CONTENT_CLASS%">
                %HEADER_SVG%<span id="%HEADER_TEXT_ID%" class="%HEADER_TEXT_CLASS%">%HEADER_TEXT%</span>
            </div>
            <span class="apbct_form_decoration--signature">APBCT</span>
        </div>
        ';

        $template = str_replace('%HEADER_WRAPPER_ID%', $prefix . 'wrapper', $template);
        $template = str_replace('%HEADER_WRAPPER_CLASS%', $prefix . 'wrapper', $template);
        $template = str_replace('%HEADER_CONTENT_ID%', $prefix . 'content', $template);
        $template = str_replace('%HEADER_CONTENT_CLASS%', $prefix . 'content', $template);
        $template = str_replace('%HEADER_SVG%', $this->getHeaderSVG(), $template);
        $template = str_replace('%HEADER_TEXT_ID%', $prefix . 'text', $template);
        $template = str_replace('%HEADER_TEXT_CLASS%', $prefix . 'text', $template);
        $template = str_replace('%HEADER_TEXT%', $this->text, $template);
        return $template;
    }

    protected function getHeaderSVG()
    {
        return '';
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
    protected function darkenHexColor($hex, $percent)
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
