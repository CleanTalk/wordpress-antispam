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
        $this->errors = array(
            'sfw_update' => array(
                0 => array(
                    'error' => 'Error test: apbct_sfw_update__process_files',
                    'error_time' => 1634895504
                ),
                1 => array(
                    'error' => 'Error test: apbct_sfw_update__process_files',
                    'error_time' => 1634895506
                ),
                'sub_type' => array(
                    0 => array(
                        'error' => 'Error test: apbct_sfw_update__end_of_update',
                        'error_time' => 1634895516
                    ),
                ),
            ),
            'cron' => array(
                0 => array(
                    'error' => 'Error test: apbct_sfw_update__process_files',
                    'error_time' => 1634895506
                ),
                1 => array(
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

    public function test_apbct_settings__sanitize__exclusions__valid()
    {
        $test_exclusions_string = "https://cleantalk.org'
            'https://www.cleantalk.org'
            'www.cleantalk.org'
            'cleantalk.org'
            'https://cleantalk.org/test'
            'https://www.cleantalk.org/test'
            'www.cleantalk.org/test'
            'cleantalk.org/test'
            'https://cleantalk.org/test/php.php'
            'https://www.cleantalk.org/test/php.php'
            'www.cleantalk.org/test/php.php'
            'cleantalk.org/test/php.php'
            'https://www.cleantalk.org/thereisamorethan128charshoweverthetestshouldwentfinethereisamorethan128charshoweverthetestshouldwentfinethereisamorethan128charshoweverthetestshouldwentfine'";

        $test_data[] = 'expression';
        $test_data[] = 'expression,';
        $test_data[] = $test_exclusions_string;
        $message = 'Fails on:';
        foreach ($test_data as $key) {
            $result = is_string(apbct_settings__sanitize__exclusions($key));
            if (!$result) {
                $message .= $key . ' TYPE:' . gettype($key);
                break;
            }
        }
        $this->assertTrue($result, $message);
    }

    public function test_apbct_settings__sanitize__exclusions__invalid()
    {
        $test_data[] = array();
        $test_data[] = $this;
        $message = 'Fails on:';
        foreach ($test_data as $key) {
            $result = (bool)apbct_settings__sanitize__exclusions($key);
            if ($result) {
                $message .= $key . ' TYPE:' . gettype($key);
                break;
            }
        }

        $this->assertFalse($result, $message);
    }

    public function test_apbct_settings__sanitize__exclusions__cutting()
    {
        $test_data = '1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21';
        $this->assertEquals('1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20',
            apbct_settings__sanitize__exclusions($test_data));

        $test_data = 'https://www.cleantalk.org/thereisamorethan128charshoweverthetestshouldwentfinethereisamorethan128charshoweverthetestshouldwentfinethereisamorethan128charshoweverthetestshouldwentfine,https://www.cleantalk.org/thereisamorethan128charshoweverthetestshouldwentfinethereisamorethan128charshoweverthetestshouldwentfinethereisamorethan128charshoweverthetestshouldwentfine';
        $this->assertEquals('https://www.cleantalk.org/thereisamorethan128charshoweverthetestshouldwentfinethereisamorethan128charshoweverthetestshouldwentfi',
            apbct_settings__sanitize__exclusions($test_data));

        $test_data = 'https://www.cleantalk.org/thereisamorethan128charshoweverthetestshouldwentfinethereisamorethan128charshoweverthetestshouldwentfinethereisamorethan128charshoweverthetestshouldwentfine,https://www.clessantalk.org/thereisamorethan128charshoweverthetestshouldwentfinethereisamorethan128charshoweverthetestshouldwentfinethereisamorethan128charshoweverthetestshouldwentfine';
        $this->assertEquals('https://www.cleantalk.org/thereisamorethan128charshoweverthetestshouldwentfinethereisamorethan128charshoweverthetestshouldwentfi,https://www.clessantalk.org/thereisamorethan128charshoweverthetestshouldwentfinethereisamorethan128charshoweverthetestshouldwent',
            apbct_settings__sanitize__exclusions($test_data));
    }

    public function test_apbct_settings__sanitize__exclusions__wrong_params()
    {
        $this->assertIsString(apbct_settings__sanitize__exclusions(''));
        $this->assertIsString(apbct_settings__sanitize__exclusions('', 0));
    }
}
