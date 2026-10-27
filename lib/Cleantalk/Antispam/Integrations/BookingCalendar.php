<?php

namespace Cleantalk\Antispam\Integrations;

class BookingCalendar extends IntegrationBase
{
    public function getDataForChecking($argument)
    {
        // The plugin reads the booking params from $_REQUEST
        $calendar_request_params = [];
        if (isset($_REQUEST['calendar_request_params']) && is_array($_REQUEST['calendar_request_params'])) {
            $calendar_request_params = $_REQUEST['calendar_request_params'];
        } elseif (isset($_REQUEST['calendar_request_params']) && is_string($_REQUEST['calendar_request_params'])) {
            $calendar_request_params = json_decode(stripslashes($_REQUEST['calendar_request_params']), true);
        }

        // Check the booking request even without the form data
        $formdata = isset($calendar_request_params['formdata']) ? $calendar_request_params['formdata'] : '';

        $parsed_formdata = $this->parseBookingCalendarFormdata($formdata);

        $resource_id = isset($calendar_request_params['resource_id']) && is_scalar($calendar_request_params['resource_id'])
            ? (int)$calendar_request_params['resource_id']
            : 1;

        // Extract prepared data for ct_gfa_dto
        $nickname = $this->extractNickname($parsed_formdata);
        $email = $this->extractPrimaryEmail($parsed_formdata, $resource_id);
        $emails_array = $this->extractEmailsArray($parsed_formdata);
        $filtered_formdata = $this->filterFormdataForMessage($parsed_formdata);

        return ct_gfa_dto(
            apply_filters('apbct__filter_post', $filtered_formdata),
            $email,
            $nickname,
            $emails_array
        )->getArray();
    }

    /**
     * Extract nickname from firstname_val and secondname_val fields.
     * Searches for patterns: firstname_val1, firstname_val2, ... and secondname_val1, secondname_val2, ...
     *
     * @param array $parsed_formdata
     * @return string
     */
    private function extractNickname(array $parsed_formdata)
    {
        $firstname = $this->extractFieldByPattern($parsed_formdata, '/^firstname_val\d*$/i');
        $secondname = $this->extractFieldByPattern($parsed_formdata, '/^secondname_val\d*$/i');

        // If _val patterns not found, try without _val suffix
        if (empty($firstname)) {
            $firstname = $this->extractFieldByPattern($parsed_formdata, '/^firstname\d*$/i');
        }
        if (empty($secondname)) {
            $secondname = $this->extractFieldByPattern($parsed_formdata, '/^secondname\d*$/i');
        }

        $parts = array_filter([$firstname, $secondname]);
        return implode(' ', $parts);
    }

    /**
     * Extract primary email from email or email_val fields.
     * Priority: email field of the booking resource (email1) > first found email field
     *
     * @param array $parsed_formdata
     * @param int $resource_id
     * @return string
     */
    private function extractPrimaryEmail(array $parsed_formdata, $resource_id)
    {
        // The vendor saves the email field of the booking resource
        $field_name = 'email' . $resource_id;
        if (isset($parsed_formdata[$field_name]['value']) && $this->isValidEmail($parsed_formdata[$field_name]['value'])) {
            return $parsed_formdata[$field_name]['value'];
        }

        // Fallback: find first email by pattern
        return $this->extractFieldByPattern($parsed_formdata, '/^email_val\d*$/i', 'email')
            ?: $this->extractFieldByPattern($parsed_formdata, '/^email\d*$/i', 'email');
    }

    /**
     * Extract all emails from the form fields into array.
     *
     * @param array $parsed_formdata
     * @return array
     */
    private function extractEmailsArray(array $parsed_formdata)
    {
        $emails = [];

        foreach ($parsed_formdata as $field) {
            $value = isset($field['value']) ? $field['value'] : '';
            if ($this->isValidEmail($value)) {
                $emails[] = $value;
            }
        }

        return $emails;
    }

    /**
     * Extract first matching field value by regex pattern.
     *
     * @param array $parsed_formdata
     * @param string $pattern Regex pattern to match field names
     * @param string|null $expected_type Optional type filter (e.g., 'email', 'text')
     * @return string
     */
    private function extractFieldByPattern(array $parsed_formdata, $pattern, $expected_type = null)
    {
        foreach ($parsed_formdata as $key => $field) {
            if (preg_match($pattern, $key)) {
                $value = isset($field['value']) ? trim($field['value'], " \n\r\t\v\x00") : '';
                $type = isset($field['type']) ? $field['type'] : '';

                // If type filter specified, check it
                if ($expected_type !== null && $type !== $expected_type) {
                    continue;
                }

                if (!empty($value)) {
                    return $value;
                }
            }
        }
        return '';
    }

    /**
     * Simple email validation.
     *
     * @param string $email
     * @return bool
     */
    private function isValidEmail($email)
    {
        return is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Filter formdata to remove duplicates and already extracted fields.
     * Keeps only textarea fields for message, removes name fields and non-_val duplicates.
     *
     * @param array $parsed_formdata
     * @return array
     */
    private function filterFormdataForMessage(array $parsed_formdata)
    {
        $result = [];

        foreach ($parsed_formdata as $key => $field) {
            // Skip name fields - already extracted
            if (preg_match('/^(first|second|last|nick)?name/i', $key)) {
                continue;
            }

            // Skip non-_val duplicates if _val version exists (e.g., textarea1 when textarea_val1 exists)
            if (preg_match('/^(.+?)(\d+)$/', $key, $matches)) {
                $base = isset($matches[1]) ? $matches[1] : '';
                $num = isset($matches[2]) ? $matches[2] : '';
                // If this is NOT a _val field, check if _val version exists
                if (substr($base, -4) !== '_val') {
                    $val_key = $base . '_val' . $num;
                    if (isset($parsed_formdata[$val_key])) {
                        continue;
                    }
                }
            }

            $result[$key] = $field;
        }

        return $result;
    }

    /**
     * Parse Booking Calendar form data string into an associative array.
     * @param string $formdata
     * @return array
     */
    private function parseBookingCalendarFormdata($formdata)
    {
        $result = [];
        if (!is_string($formdata) || $formdata === '') {
            return $result;
        }
        $fields = explode('~', $formdata);
        foreach ($fields as $field) {
            $parts = explode('^', $field, 3);
            if (count($parts) === 3) {
                list($type, $name, $value) = $parts;
                $result[$name] = [
                    'type' => $type,
                    'value' => $value,
                ];
            }
        }
        return $result;
    }

    public function doBlock($message)
    {
        wp_send_json($message);
    }
}
