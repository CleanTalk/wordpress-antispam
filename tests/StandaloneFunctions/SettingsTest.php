<?php

include 'inc/cleantalk-settings.php';

use PHPUnit\Framework\TestCase;

class SettingsTest extends TestCase
{
    /**
     * @var string[]
     */
    private $errors;

    public function setUp()
    {
        $this->errors =  array (
            'sfw_update' => array (
                0 => array (
                    'error' => 'Error test: apbct_sfw_update__process_files',
                    'error_time' => 1634895504
                ),
                1 => array (
                    'error' => 'Error test: apbct_sfw_update__process_files',
                    'error_time' => 1634895506
                ),
                'sub_type' => array (
                    0 => array (
                        'error' => 'Error test: apbct_sfw_update__end_of_update',
                        'error_time' => 1634895516
                    ),
                ),
            ),
            'cron' => array (
                0 => array (
                    'error' => 'Error test: apbct_sfw_update__process_files',
                    'error_time' => 1634895506
                ),
                1 => array (
                    'error' => 'Error test: apbct_sfw_update__process_files',
                    'error_time' => 1634895509
                ),
            )
        );
    }

    public function test_apbct_settings__prepare_errors()
    {
        $prepared_errors = apbct_settings__prepare_errors($this->errors);
        $this->assertIsArray($prepared_errors);
    }

    public function test_apbct_settings__prepare_errors_wrong_parameter()
    {
        $prepared_errors = apbct_settings__prepare_errors('');
        $this->assertIsArray($prepared_errors);
    }

    public function test_apbct_settings__sanitize__exclusions__single_valid()
    {
        $test_exclusions_array = [
            'https://cleantalk.org',
            'https://www.cleantalk.org',
            'www.cleantalk.org',
            'cleantalk.org',

            'https://cleantalk.org/test',
            'https://www.cleantalk.org/test',
            'www.cleantalk.org/test',
            'cleantalk.org/test',

            'https://cleantalk.org/test/php.php',
            'https://www.cleantalk.org/test/php.php',
            'www.cleantalk.org/test/php.php',
            'cleantalk.org/test/php.php',

            'https://www.cleantalk.org/thereisamorethan128charshoweverthetestshouldwentfinethereisamorethan128charshoweverthetestshouldwentfinethereisamorethan128charshoweverthetestshouldwentfine'
        ];
        $result = true;
        $fail_on = '';
        foreach ($test_exclusions_array as $exclusion_string) {
            if (!apbct_settings__sanitize__exclusions($exclusion_string)){
                $result = false;
                $fail_on = $exclusion_string;
                break;
            }
        }
        $this->assertEquals(true, $result, 'Fails on: ' . $fail_on);
    }

    public function test_apbct_settings__sanitize__exclusions__single_invalid()
    {
        $test_exclusions_array = [
            'Thereisahustword',
            '<script>alert(\'(injection)\');</script>',
            '@823h8@627sh',
            ' ',
            'https:// cleantal k.org/tes t',
        ];
        $result = true;
        $fail_on = '';
        foreach ($test_exclusions_array as $exclusion_string) {
            if (apbct_settings__sanitize__exclusions($exclusion_string)){
                $result = false;
                $fail_on = $exclusion_string;
                break;
            }
        }
        $this->assertEquals(true, $result, 'Fails on: ' . $fail_on);
    }
}
