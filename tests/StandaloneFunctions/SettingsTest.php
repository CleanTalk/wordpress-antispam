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
}
