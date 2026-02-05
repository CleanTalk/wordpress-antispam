<?php

namespace Cleantalk\Antispam\Integrations;

use Cleantalk\ApbctWP\Variables\Post;
use Cleantalk\ApbctWP\Variables\Get;
use PHPUnit\Framework\TestCase;

class TestGiveWP extends TestCase
{
    private $integration;

    protected function setUp(): void
    {
        parent::setUp();
        $this->integration = new GiveWP();
    }

    protected function tearDown(): void
    {
        // Clean up global state
        $_POST = [];
        $_GET = [];
        Post::getInstance()->variables = [];
        Get::getInstance()->variables = [];
        parent::tearDown();
    }

    /**
     * Test REST API donation with valid data
     */
    public function testGetDataForCheckingRestApiWithValidData()
    {
        $_GET['givewp-route'] = 'validate';
        $_POST['firstName'] = 'John';
        $_POST['lastName'] = 'Doe';
        $_POST['email'] = 'john.doe@example.com';
        $_POST['amount'] = '100';

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
        $this->assertEquals('john.doe@example.com', $result['email']);
        $this->assertEquals('John Doe', $result['nickname']);
    }

    /**
     * Test REST API with only firstName
     */
    public function testGetDataForCheckingRestApiWithOnlyFirstName()
    {
        $_GET['givewp-route'] = 'validate';
        $_POST['firstName'] = 'Jane';
        $_POST['lastName'] = '';
        $_POST['email'] = 'jane@example.com';

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
        $this->assertEquals('Jane ', $result['nickname']);
    }

    /**
     * Test REST API with only lastName
     */
    public function testGetDataForCheckingRestApiWithOnlyLastName()
    {
        $_GET['givewp-route'] = 'validate';
        $_POST['firstName'] = '';
        $_POST['lastName'] = 'Smith';
        $_POST['email'] = 'smith@example.com';

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
        $this->assertEquals('Smith', $result['nickname']);
    }

    /**
     * Test classic donation form with give_process_donation action
     */
    public function testGetDataForCheckingClassicDonationForm()
    {
        $_POST['action'] = 'give_process_donation';
        $_POST['give_email'] = 'donor@example.com';
        $_POST['give_first'] = 'Test';
        $_POST['give_last'] = 'User';

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('email', $result);
    }

    /**
     * Test AJAX donation with give_action=donation
     */
    public function testGetDataForCheckingAjaxDonation()
    {
        $_POST['give_action'] = 'donation';
        $_POST['give_ajax'] = '1';
        $_POST['give_email'] = 'ajax.donor@example.com';

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('email', $result);
    }

    /**
     * Test AJAX purchase with give_action=purchase
     */
    public function testGetDataForCheckingAjaxPurchase()
    {
        $_POST['give_action'] = 'purchase';
        $_POST['give_ajax'] = '1';
        $_POST['give_email'] = 'purchase@example.com';

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
    }

    /**
     * Test multipage form without email (first page)
     */
    public function testGetDataForCheckingMultipageFormWithoutEmail()
    {
        $_POST['action'] = 'give_process_donation';
        $_POST['give_amount'] = '50';
        // No email field on first page

        $result = $this->integration->getDataForChecking(null);

        $this->assertNull($result);
    }

    /**
     * Test with empty POST data
     */
    public function testGetDataForCheckingWithEmptyPost()
    {
        $_POST = [];

        $result = $this->integration->getDataForChecking(null);

        $this->assertNull($result);
    }

    /**
     * Test with wrong action
     */
    public function testGetDataForCheckingWithWrongAction()
    {
        $_POST['action'] = 'some_other_action';
        $_POST['email'] = 'test@example.com';

        $result = $this->integration->getDataForChecking(null);

        $this->assertNull($result);
    }

    /**
     * Test REST API without email
     */
    public function testGetDataForCheckingRestApiWithoutEmail()
    {
        $_GET['givewp-route'] = 'validate';
        $_POST['firstName'] = 'Test';
        $_POST['lastName'] = 'User';
        // No email

        $result = $this->integration->getDataForChecking(null);

        $this->assertNull($result);
    }

    /**
     * Test with apbct__filter_post filter applied
     */
    public function testGetDataForCheckingWithFilter()
    {
        if (!function_exists('apply_filters')) {
            $this->markTestSkipped('WordPress apply_filters function not available');
        }

        $_GET['givewp-route'] = 'validate';
        $_POST['email'] = 'filtered@example.com';
        $_POST['firstName'] = 'Filtered';

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
    }

    /**
     * Test give_ajax without proper give_action
     */
    public function testGetDataForCheckingAjaxWithoutProperAction()
    {
        $_POST['give_ajax'] = '1';
        $_POST['give_action'] = 'invalid_action';
        $_POST['email'] = 'test@example.com';

        $result = $this->integration->getDataForChecking(null);

        $this->assertNull($result);
    }

    /**
     * Test with special characters in names
     */
    public function testGetDataForCheckingWithSpecialCharacters()
    {
        $_GET['givewp-route'] = 'validate';
        $_POST['firstName'] = "O'Brien";
        $_POST['lastName'] = 'Müller';
        $_POST['email'] = 'special@example.com';

        $result = $this->integration->getDataForChecking(null);

        $this->assertIsArray($result);
        $this->assertStringContainsString("O'Brien", $result['nickname']);
        $this->assertStringContainsString('Müller', $result['nickname']);
    }

    /**
     * Test REST API with empty strings
     */
    public function testGetDataForCheckingRestApiWithEmptyStrings()
    {
        $_GET['givewp-route'] = 'validate';
        $_POST['firstName'] = '';
        $_POST['lastName'] = '';
        $_POST['email'] = '';

        $result = $this->integration->getDataForChecking(null);

        $this->assertNull($result);
    }
}
