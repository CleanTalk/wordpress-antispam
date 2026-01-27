<?php

use Cleantalk\ApbctWP\Promotions\GF2DBPromotion;
use PHPUnit\Framework\TestCase;

class GF2DBPromotionTest extends TestCase
{
    private $promotion;
    private $active_plugins;

    protected function setUp()
    {
        $this->promotion = new GF2DBPromotion();
        $this->promotion->init();
        $this->active_plugins = get_option('active_plugins', array());
    }

    protected function tearDown()
    {
        update_option('active_plugins', $this->active_plugins);
    }

    public function testSettingNameConstant()
    {
        $this->assertEquals('forms__gravity_promotion_gf2db', GF2DBPromotion::$setting_name);
    }

//    public function testCouldPerformActionsGravityActiveGf2dbInactive()
//    {
//
//        update_option('active_plugins', [
//            'gravityforms/gravityforms.php'
//        ]);
//
//        $result = $this->promotion->couldPerformActions();
//        $this->assertTrue($result);
//    }

    public function testCouldPerformActionsGravityInactive()
    {
        $result = $this->promotion->couldPerformActions();
        $this->assertFalse($result);
    }

    public function testCouldPerformActionsGf2dbActive()
    {
        update_option('active_plugins', [
            'gravityforms/gravityforms.php',
            'cleantalk-doboard-add-on-for-gravity-forms/cleantalk-doboard-add-on-for-gravity-forms.php'
        ]);

        $result = $this->promotion->couldPerformActions();
        $this->assertFalse($result);
    }

    public function testModifyAdminEmailContentAddsPromotionForAdminEmail()
    {
        $email = [
            'message' => '<html><body>Test message</body></html>',
            'headers' => []
        ];
        $notification = ['to' => '{admin_email}'];

        $result = $this->promotion->modifyAdminEmailContent($email, '', $notification, []);

        $this->assertStringContainsString('Organize and track all messages', $result['message']);
        $this->assertStringContainsString('cleantalk-doboard-add-on-for-gravity-forms', $result['message']);
        $this->assertStringContainsString('You can turn off this message', $result['message']);
    }

    public function testModifyAdminEmailContentSkipsNonAdminEmail()
    {
        $email = ['message' => 'test'];
        $notification = ['to' => 'user@example.com'];

        $result = $this->promotion->modifyAdminEmailContent($email, '', $notification, []);

        $this->assertEquals('test', $result['message']);
    }

    public function testModifyAdminEmailContentSkipsNonStringMessage()
    {
        $email = ['message' => []];
        $notification = ['to' => '{admin_email}'];

        $result = $this->promotion->modifyAdminEmailContent($email, '', $notification, []);

        $this->assertEquals([], $result['message']);
    }

    public function testGetDescriptionForSettings()
    {
        $description = $this->promotion->getDescriptionForSettings();
        $this->assertStringContainsString('Add helpful links to the bottom of all notifications', $description);
    }

    public function testGetTitleForSettings()
    {
        $title = $this->promotion->getTitleForSettings();
        $this->assertEquals('Attach helpful links to Gravity Forms admin notifications', $title);
    }

    public function testPrepareAdminEmailContentHTMLStructure()
    {
        $html = $this->promotion->prepareAdminEmailContentHTML();

        $this->assertStringStartsWith('<p>', $html);
        $this->assertStringContainsString('wordpress.org/plugins/cleantalk-doboard-add-on-for-gravity-forms', $html);
        $this->assertStringContainsString('options-general.php?page=cleantalk', $html);
        $this->assertStringEndsWith('</p>', $html);
    }
}
