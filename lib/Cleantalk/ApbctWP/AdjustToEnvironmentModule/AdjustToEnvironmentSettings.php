<?php

namespace Cleantalk\ApbctWP\AdjustToEnvironmentModule;

class AdjustToEnvironmentSettings
{
    public static function render()
    {
        $view = '';
        $view_buttons = '';

        $view .= '<div class="apbct_settings-field_wrapper" id="apbct-action-adjust-env">';
        $view__adjusted = __('We changed some settings of third party plugins to make compatibility. ', 'cleantalk-spam-protect');
        $view__adjusted .= __('You are free to reverse it, but it may lead to incorrect operation of the software.', 'cleantalk-spam-protect');
        $view__adjust_suggestion = __('We suggest you to change some settings of third party plugins to make compatibility. ', 'cleantalk-spam-protect');

        $info = AdjustToEnvironmentHandler::getInfoWhatWeChanged();

        foreach ( AdjustToEnvironmentHandler::SET_OF_ADJUST as $_env_name => $class ) {
            $key_of_adjust = array_search($class, AdjustToEnvironmentHandler::SET_OF_ADJUST);

            if (!class_exists($class) || false === $key_of_adjust) {
                continue;
            }

            if (isset($info[$class]['changed'])) {
                $adjust_obj = new $class($info);

                if ($adjust_obj->isEnvComplyToAdjustRequires()) {
                    if ( $adjust_obj->hasEnvBeenAdjustedByModule() ) {
                            $view .= $view__adjusted;
                            $view_buttons .= self::renderReverseBtn($key_of_adjust);
                    } else {
                        $view .= $view__adjust_suggestion;
                        $view_buttons .= self::renderChangeBtn($key_of_adjust);
                    }
                }
            }
        }

        $view .= $view_buttons;
        $view .= '</div>';

        if ($view_buttons === '') {
            $view = '';
        }

        return $view;
    }

    private static function renderChangeBtn($key)
    {
        $btn = self::btnLayout();

        $title = '';
        $description = '';

        switch ($key) {
            case 'w3tc':
                $title = 'W3 Total Cache';
                $description = __('Next button will disable "Cache URIs with query string variables" option.', 'cleantalk-spam-protect');
                break;
        }

        $replaces = [
            '{TITLE}' => sprintf(__("Make adjust for %s", 'cleantalk-spam-protect'), $title),
            '{DESCRIPTION}' => $description,
            '{KEY}' => 'change-' . $key,
            '{ADJUST}' => $key,
        ];

        foreach ( $replaces as $place_holder => $replace ) {
            $replace = is_null($replace) ? '' : $replace;
            $btn = str_replace($place_holder, $replace, $btn);
        }

        return $btn;
    }

    private static function renderReverseBtn($key)
    {
        $btn = self::btnLayout();

        $title = '';
        $description = '';

        switch ($key) {
            case 'w3tc':
                $title = 'W3 Total Cache';
                $description = __('Next button will enable "Cache URIs with query string variables" option back.', 'cleantalk-spam-protect');
                break;
        }

        $replaces = [
            '{TITLE}' => __("Reverse adjust for $title", 'cleantalk-spam-protect'),
            '{DESCRIPTION}' => $description,
            '{KEY}' => 'reverse-' . $key,
            '{ADJUST}' => $key,
        ];

        foreach ( $replaces as $place_holder => $replace ) {
            $replace = is_null($replace) ? '' : $replace;
            $btn = str_replace($place_holder, $replace, $btn);
        }

        return $btn;
    }

    private static function btnLayout()
    {
        $btn = '<div class="apbct_settings_description"> {DESCRIPTION} </div>'
            . '<button type="button" id="apbct-action-adjust-{KEY}" data-adjust="{ADJUST}"'
            . 'class="button button-secondary" style="margin: 5px 0 0 10px;">'
            . '{TITLE}'
            . '</button>';

        return $btn;
    }
}
