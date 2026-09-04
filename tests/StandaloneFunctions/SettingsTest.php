<?php

include 'inc/cleantalk-settings.php';

use PHPUnit\Framework\TestCase;

class SettingsTest extends TestCase
{
    /**
     * @var string[]
     */
    private $errors;

    public function setUp(): void
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

    public function test_apbct_settings__set_fields()
    {
        $fields = apbct_settings__set_fields();
        $this->assertIsArray($fields);

        // Enumerate all top-level array keys
        $expected_keys = array(
            'main',
            'state',
            'different',
            'spoilers_links',
            'advanced_settings',
            'forms_protection',
            'wc',
            'comments_and_messages',
            'data_processing',
            'contact_data_encoding',
            'exclusions',
            'admin_bar',
            'sfw_features',
            'misc',
            'trusted_and_affiliate'
        );

        foreach ($expected_keys as $key) {
            $this->assertArrayHasKey($key, $fields, "Missing key: {$key}");
            $this->assertIsArray($fields[$key], "Key '{$key}' should be an array");
        }
    }

    public function test_apbct_settings__clear_errors_current_site()
    {
        global $apbct;

        $apbct->errorAdd('sfw_outdated', 'SpamFireWall database is outdated.');
        $this->assertTrue($apbct->errorExists('sfw_outdated'));

        apbct_settings__clear_errors(false);

        $this->assertFalse($apbct->errorExists('sfw_outdated'));
        $this->assertSame(array(), (array) $apbct->errors);
        $this->assertSame(array(), get_option($apbct->option_prefix . '_errors'));
    }

    public function test_apbct_settings__clear_errors_all_blogs_on_wpms()
    {
        global $apbct;

        if ( ! is_multisite() ) {
            $this->markTestSkipped('Requires WordPress Multisite');
        }

        $option_name = $apbct->option_prefix . '_errors';
        $stale_error = array(
            'sfw_outdated' => array(
                array(
                    'error'      => 'SpamFireWall database is outdated.',
                    'error_time' => time(),
                ),
            ),
        );

        $apbct->errorAdd('sfw_outdated', 'SpamFireWall database is outdated.');

        $blogs = get_sites(array('number' => 20));
        foreach ( $blogs as $blog ) {
            update_blog_option((int) $blog->blog_id, $option_name, $stale_error);
        }

        apbct_settings__clear_errors(true);

        $this->assertFalse($apbct->errorExists('sfw_outdated'));
        foreach ( $blogs as $blog ) {
            $this->assertSame(
                array(),
                get_blog_option((int) $blog->blog_id, $option_name),
                'Errors must be cleared on blog ' . $blog->blog_id
            );
        }
    }
}
