<?php

namespace Cleantalk\ApbctWP;

use Cleantalk\Common\TT;
use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\ApbctWP\Variables\AltSessions;

class Honeypot
{
    /**
     * Returns HTML of a honeypot hidden field to the form. If $form_method is GET, adds a hidden submit button.
     * @param $form_type
     * @param string $form_method
     * @return string
     */
    public static function generateHoneypotField($form_type, $form_method = 'post')
    {
        global $apbct;

        if ( ! $apbct->settings['data__honeypot_field'] || apbct_exclusions_check__url() || apbct_is_amp_request()) {
            return '';
        }

        $apbct_event_id = mt_rand(0, 100000);

        // field label (preventing validators warning)
        $label = '<label ' .  'class="apbct_special_field" ' .  'id="apbct_label_id' . $apbct_event_id . '" ' .
            'for="apbct__email_id__' . $form_type . '_' . $apbct_event_id . '"' .  '>' . $apbct_event_id . '</label>';

        // generate the honeypot trap input
        $honeypot = $label . '<input
            id="apbct__email_id__' . $form_type . '_' . $apbct_event_id . '"
            class="apbct_special_field apbct__email_id__' . $form_type . '"
            name="apbct__email_id__' . $form_type . '_' . $apbct_event_id . '"
            type="text" size="30" maxlength="200" autocomplete="off"
            value="' . $apbct_event_id . '" apbct_event_id="' . $apbct_event_id . '"
            />';

        // if POST, add a hidden input to transfer apbct_event_id to the form data
        if ($form_method === 'post') {
            return $honeypot . '<input id="apbct_event_id_' . $form_type . '_' . $apbct_event_id . '"
                class="apbct_special_field" name="apbct_event_id" type="hidden" value="' . $apbct_event_id . '" />';
        }

        // if GET, place a submit button if method is get to prevent keyboard send misfunction
        return $honeypot . '<input id="apbct_submit_id__' . $form_type . '_' . $apbct_event_id . '" 
            class="apbct_special_field apbct__email_id__' . $form_type . '" name="apbct_submit_id__' . $form_type . '_' . $apbct_event_id . '"  
            type="submit" size="30" maxlength="200" value="' . $apbct_event_id . '" />';
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
     * $hp_id - honeypot id, value from apbct_event_id
     * $hp_exists - flag if honeypot field is exists, on form (need to prevent handling on not supported form)
     * $result - array with filled honeypot field value and source (only if honeypot field is exists and dirty)
     * $honeypot_potential_values - array with possible honeypot field values
     * --------------------------------------------------------------
     * @return array|false|null
     */
    private static function getHoneypotFilledFields()
    {
        global $apbct;

        $hp_id = false;
        $hp_exists = false;
        $result = array();
        $honeypot_potential_values = array();

        if ( ! empty($_POST) ) {
            $hp_id = TT::toString(Post::get('apbct_event_id')); // get honeypot id

            // collect probable sources
            $honeypot_potential_values = array(
                'wc_apbct_email_id' =>                  Post::get('wc_apbct_email_id_' . $hp_id),
                'apbct__email_id__wp_register' =>       Post::get('apbct__email_id__wp_register_' . $hp_id),
                'apbct__email_id__wp_contact_form_7' => Post::get('apbct__email_id__wp_contact_form_7_' . $hp_id),
                'apbct__email_id__wp_wpforms' =>        Post::get('apbct__email_id__wp_wpforms_' . $hp_id),
                'apbct__email_id__gravity_form' =>      Post::get('apbct__email_id__gravity_form_' . $hp_id),
                'apbct__email_id__elementor_form' =>    Post::get('apbct__email_id__elementor_form_' . $hp_id)
            );
        }

        // AltSessions way to collect search forms honeypot
        if ( $apbct->settings['forms__search_test'] ) {
            $alt_search_event_id = AltSessions::get("apbct_search_form__honeypot_id");
            $alt_search_value = AltSessions::get("apbct_search_form__honeypot_value");
            if ( $alt_search_event_id && $alt_search_value ) {
                $honeypot_potential_values['apbct__email_id__search_form'] = $alt_search_value;
            }
        }

        // if source is filled then pass them to params as additional fields
        if ( ! empty($honeypot_potential_values) ) {
            foreach ( $honeypot_potential_values as $source_name => $source_value ) {
                if (!$source_value) {
                    continue;
                }

                $hp_exists = true;

                // handle search form separately
                if ($source_name === 'apbct__email_id__search_form') {
                    $hp_id = isset($alt_search_event_id) ? $alt_search_event_id : $hp_id;
                }

                // detect only values that is not equal to hp_id
                if ( $source_value !== $hp_id ) {
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
