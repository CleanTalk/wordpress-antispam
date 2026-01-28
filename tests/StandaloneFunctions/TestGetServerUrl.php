<?php

namespace StandaloneFunctions;
use Cleantalk\ApbctWP\State;
use PHPUnit;

class TestGetServerUrl extends PHPUnit\Framework\TestCase
{
    private $correct_urls;
    private $incorrect_urls;

    public function setUp(): void
    {
        $this->correct_urls = array(
            'moderate.cleantalk.org',
            'api.cleantalk.org',
            'api.cleantalk.org/?',
            'api.cleantalk.org?',
            'moderate.cleantalk.org/api2.0',
            'api4.cleantalk.org/api2.0',
        );

        $this->incorrect_urls = array(
            'google.com',
            'cleantalk.org',
            'moderate.cleantalk.org.scum.com',
        );
    }

    public function testCorrectUrls()
    {
        delete_option('cleantalk_server');
        foreach ($this->correct_urls as $url) {
            update_option(
                'cleantalk_server',
                array(
                    'ct_work_url'       => $url,
                    'ct_server_ttl'     => 100,
                    'ct_server_changed' => time(),
                )
            );
            $server_details = ct_get_server();
            $this->assertIsArray($server_details);
            $this->assertArrayHasKey('ct_work_url', $server_details);
            $this->assertNotNull($server_details['ct_work_url']);
            $this->assertTrue(in_array($server_details['ct_work_url'], $this->correct_urls));
        }
        delete_option('cleantalk_server');
    }

    public function testIncorrectUrls()
    {
        delete_option('cleantalk_server');
        foreach ($this->incorrect_urls as $url) {
            update_option(
                'cleantalk_server',
                array(
                    'ct_work_url'       => $url,
                    'ct_server_ttl'     => 100,
                    'ct_server_changed' => time(),
                )
            );
            $server_details = ct_get_server();
            $this->assertIsArray($server_details);
            $this->assertArrayHasKey('ct_work_url', $server_details);
            $this->assertIsNotString($server_details['ct_work_url']);
        }
        delete_option('cleantalk_server');
    }

}
