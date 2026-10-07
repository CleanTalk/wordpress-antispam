<?php

namespace ApbctWP\Variables;

use Cleantalk\ApbctWP\Variables\AltSessions;
use Cleantalk\ApbctWP\Variables\Server;
use PHPUnit\Framework\TestCase;

class TestAltSessionsGetID extends TestCase
{
    /**
     * @var array
     */
    private $serverBackup = array();

    protected function setUp(): void
    {
        parent::setUp();

        $this->serverBackup = $_SERVER;
        Server::getInstance()->variables = array();

        $_SERVER['HTTP_USER_AGENT'] = 'phpunit-user-agent';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        Server::getInstance()->variables = array();

        parent::tearDown();
    }

    public function testGetIDIgnoresAcceptLanguage()
    {
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en-US';
        $short = AltSessions::getID();

        Server::getInstance()->variables = array();
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en-US,en;q=0.9';
        $full = AltSessions::getID();

        Server::getInstance()->variables = array();
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'de-DE,de;q=0.9';
        $other = AltSessions::getID();

        Server::getInstance()->variables = array();
        unset($_SERVER['HTTP_ACCEPT_LANGUAGE']);
        $empty = AltSessions::getID();

        $this->assertSame($short, $full);
        $this->assertSame($short, $other);
        $this->assertSame($short, $empty);
        $this->assertSame(16, strlen($short));
    }

    public function testGetIDDiffersWhenUserAgentDiffers()
    {
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en-US,en;q=0.9';
        $first = AltSessions::getID();

        Server::getInstance()->variables = array();
        $_SERVER['HTTP_USER_AGENT'] = 'phpunit-user-agent-other';
        $second = AltSessions::getID();

        $this->assertNotSame($first, $second);
    }
}
