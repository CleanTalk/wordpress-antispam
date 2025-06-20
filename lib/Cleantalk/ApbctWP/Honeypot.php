<?php

namespace Cleantalk\ApbctWP;

use Cleantalk\ApbctWP\Variables\AltSessions;
use Cleantalk\Common\TT;

class Honeypot
{
    /**
     * Returns HTML of a honeypot hidden field to the form. If $form_method is GET, adds a hidden submit button.
     * @param string $form_type
     * @param string $form_method
     * @return string
     */
    public static function generateHoneypotField($form_type, $form_method = 'post')
    {
        global $apbct;

        if ( ! $apbct->settings['data__honeypot_field'] || apbct_exclusions_check__url() || apbct_is_amp_request()) {
            return '';
        }

        // generate the honeypot trap input
        $honeypot =
                '<input
                    class="apbct_special_field %s"
                    name="%s"
                    aria-label="%s"
                    type="text" size="30" maxlength="200" autocomplete="off"
                    value=""
                />';

        $honeypot = sprintf(
            $honeypot,
            esc_attr('apbct_email_id__' . $form_type),
            esc_attr('apbct__email_id__' . $form_type),
            esc_attr('apbct__label_id__' . $form_type)
        );

        $submit_for_get_forms = '<input
                   id="%s" 
                   class="apbct_special_field %s"
                   name="%s"
                   aria-label="%s"
                   type="submit"
                   size="30"
                   maxlength="200"
                   value="%s"
               />';

        $submit_for_get_forms = sprintf(
            $submit_for_get_forms,
            esc_attr('apbct_submit_id__' . $form_type),
            esc_attr('apbct__email_id__' . $form_type),
            esc_attr('apbct__label_id__' . $form_type),
            esc_attr('apbct_submit_name__' . $form_type),
            TT::toString(mt_rand(0, 100000))
        );

        return $form_method === 'post' ? $honeypot : $honeypot . $submit_for_get_forms;
    }

    /**
     * Check honeypot
     * --------------------------------------------------------------
     * Description of the statuses:
     * 0 - means that honeypot field is dirty (was updated by bot)
     * 1 - means that honeypot field is clean (was not updated by bot)
     * null - means that honeypot field is not supported for current form
     * --------------------------------------------------------------
     * @return array
     */
    public static function check()
    {
        $result = [
            'status' => null,
            'value' => null,
            'source' => null
        ];

        $hp = self::getHoneypotFilledFields();

        // If honeypot field is not supported for current form
        if (is_null($hp)) {
            return $result;
        }

        // If honeypot field is dirty
        if (isset($hp['field_value'], $hp['field_source'])) {
            $result['value'] = $hp['field_value'];
            $result['source'] = $hp['field_source'];
            $result['status'] = 0;
            return $result;
        }

        // If honeypot field is clean
        $result['status'] = 1;

        return $result;
    }

    /**
     * Get filled honeypot fields
     * --------------------------------------------------------------
     * Description:
     * $hp_exists - flag if honeypot field exists, on form (need to prevent handling on not supported form)
     * $result - array with filled honeypot field value and source (only if honeypot field exists and dirty)
     * $honeypot_potential_values - array with possible honeypot field values
     * --------------------------------------------------------------
     * @return array|false|null
     */
    private static function getHoneypotFilledFields()
    {
        global $apbct;
        $hp_exists = false;
        $result = array();
        $honeypot_potential_values = array();

        if ( ! empty($_POST) ) {
            $honeypot_potential_values = array_filter($_POST, function ($key) use (&$hp_exists) {
                $result = strpos($key, 'apbct_email_id') !== false;
                if ($result) {
                    $hp_exists = true;
                }

                return $result;
            }, ARRAY_FILTER_USE_KEY);
        }

        // AltSessions way to collect search forms honeypot
        if ( $apbct->settings['forms__search_test'] ) {
            $alt_session_data = AltSessions::get("apbct_search_form__honeypot_value");
            if (!empty($alt_session_data)) {
                $honeypot_potential_values['apbct__email_id__search_form'] = $alt_session_data;
                $hp_exists = true;
            }
        }

        // if source is filled then pass them to params as additional fields
        if ( ! empty($honeypot_potential_values) ) {
            foreach ( $honeypot_potential_values as $source_name => $source_value ) {
                // detect only values that is not empty (i.e. malformed)
                if ( $source_value !== '' ) {
                    $result['field_value'] = $source_value;
                    $result['field_source'] = $source_name;
                    break;
                }
            }
        }

        if ( ! $hp_exists ) {
            return null;
        }

        return empty($result) ? false : $result;
    }
}
