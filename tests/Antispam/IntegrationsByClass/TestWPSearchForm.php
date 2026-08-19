<?php

namespace Antispam\IntegrationsByClass;

use Cleantalk\Antispam\IntegrationsByClass\WPSearchForm;
use Cleantalk\ApbctWP\UpdatePlugin\DbTablesCreator;
use Cleantalk\ApbctWP\Variables\AltSessions;
use Cleantalk\ApbctWP\Variables\Server;
use PHPUnit\Framework\TestCase;

class TestWPSearchForm extends TestCase
{
    /**
     * @var array
     */
    private $serverBackup = array();

    public function setUp(): void
    {
        global $wpdb;
        parent::setUp();

        $this->serverBackup = $_SERVER;
        Server::getInstance()->variables = [];

        $_SERVER['HTTP_USER_AGENT'] = 'phpunit-user-agent';
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en-US,en;q=0.9';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['REQUEST_URI'] = '/search-source-page/';
        $_SERVER['HTTP_REFERER'] = '';

        $creator = new DbTablesCreator();
        $creator->createTable($wpdb->prefix . 'cleantalk_sessions');

        AltSessions::wipe();
    }

    public function tearDown(): void
    {
        AltSessions::wipe();

        $_SERVER = $this->serverBackup;

        parent::tearDown();
    }

    public function testSetSearchFormDrawnStoresCurrentRequestPath()
    {
        $_SERVER['REQUEST_URI'] = '/search-source-page/?foo=bar';

        WPSearchForm::setSearchFormDrawn();

        $stored = AltSessions::get('search_form_ready');
        $stored = is_string($stored) ? json_decode($stored, true) : $stored;
        $this->assertIsArray($stored);
        $this->assertArrayHasKey('/search-source-page/', $stored);
        $this->assertSame(1, $stored['/search-source-page/']);
    }

    public function testIsSearchFormDrawnReturnsStoredPathIfRefererMatches()
    {
        $_SERVER['REQUEST_URI'] = '/search-source-page/';
        WPSearchForm::setSearchFormDrawn();

        $_SERVER['HTTP_REFERER'] = 'https://example.test/search-source-page/?s=query';

        $this->assertSame(
            '/search-source-page/',
            WPSearchForm::isSearchFormDrawn()
        );
    }

    public function testIsSearchFormDrawnReturnsFalseIfRefererDoesNotMatch()
    {
        $_SERVER['REQUEST_URI'] = '/search-source-page/';
        WPSearchForm::setSearchFormDrawn();

        $_SERVER['HTTP_REFERER'] = 'https://example.test/another-page/?s=query';

        $this->assertFalse(WPSearchForm::isSearchFormDrawn());
    }

    public function testIsSearchFormDrawnReturnsFalseIfNoStoredSearchFormExists()
    {
        $_SERVER['HTTP_REFERER'] = 'https://example.test/search-source-page/?s=query';

        $this->assertFalse(WPSearchForm::isSearchFormDrawn());
    }

    public function testSetSearchFormSentRemovesStoredPath()
    {
        $_SERVER['REQUEST_URI'] = '/search-source-page/';
        WPSearchForm::setSearchFormDrawn();

        WPSearchForm::setSearchFormSent('/search-source-page/');

        $this->assertSame(0, AltSessions::get('search_form_ready'));
    }

    public function testSetSearchFormSentKeepsOtherStoredPaths()
    {
        AltSessions::set(
            'search_form_ready',
            array(
                '/first-page/' => 1,
                '/second-page/' => 1,
            )
        );

        WPSearchForm::setSearchFormSent('/first-page/');

        $stored = AltSessions::get('search_form_ready');
        $stored = is_string($stored) ? json_decode($stored, true) : $stored;

        $this->assertIsArray($stored);
        $this->assertArrayNotHasKey('/first-page/', $stored);
        $this->assertArrayHasKey('/second-page/', $stored);
        $this->assertSame(1, $stored['/second-page/']);
    }
}
