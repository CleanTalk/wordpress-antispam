<?php

namespace Cleantalk\Antispam\IntegrationsByHook\Tests;

use Cleantalk\Antispam\Integrations\NinjaForms;
use Cleantalk\ApbctWP\DTO\GetFieldsAnyDTO;
use Cleantalk\ApbctWP\State;
use Cleantalk\ApbctWP\Variables\Post;
use PHPUnit\Framework\TestCase;
use Mockery\MockInterface;

class TestNinjaForms extends TestCase
{
    /** @var NinjaForms */
    private $ninjaForms;

    /** @var MockInterface */
    private $mockGlobalApbct;

    protected function setUp(): void
    {
        parent::setUp();
        global $apbct;
        $apbct = new State('cleantalk', array('settings', 'data', 'errors', 'remote_calls', 'stats', 'fw_stats'));
        $this->ninjaForms = new NinjaForms();
        $this->post = array (
            'ct_bot_detector_event_token' => 'e38086feb53a3e02c9e65631bbe538575cfba5cacac48bb4925776db6a00386e',
            'apbct_visible_fields' => 'eyIwIjp7InZpc2libGVfZmllbGRzIjoibmYtZmllbGQtNS10ZXh0Ym94IGVtYWlsIG5mLWZpZWxkLTciLCJ2aXNpYmxlX2ZpZWxkc19jb3VudCI6MywiaW52aXNpYmxlX2ZpZWxkcyI6Im5mLWZpZWxkLWhwIGN0X2JvdF9kZXRlY3Rvcl9ldmVudF90b2tlbiIsImludmlzaWJsZV9maWVsZHNfY291bnQiOjJ9fQ==',
            'action' => 'nf_ajax_submit',
            'security' => 'd79c11d51e',
            'formData' => '{\\"id\\":\\"2\\",\\"fields\\":{\\"5\\":{\\"value\\":\\"asd\\",\\"id\\":5},\\"6\\":{\\"value\\":\\"s@cleantalk.org\\",\\"id\\":6},\\"7\\":{\\"value\\":\\"asd\\",\\"id\\":7},\\"8\\":{\\"value\\":\\"\\",\\"id\\":8}},\\"settings\\":{\\"objectType\\":\\"Form Setting\\",\\"editActive\\":\\"\\",\\"title\\":\\"Contact Me\\",\\"created_at\\":\\"2016-08-24 16:39:20\\",\\"form_title\\":\\"Contact Me\\",\\"default_label_pos\\":\\"above\\",\\"show_title\\":\\"1\\",\\"clear_complete\\":\\"1\\",\\"hide_complete\\":\\"1\\",\\"logged_in\\":\\"0\\",\\"key\\":\\"\\",\\"conditions\\":[],\\"wrapper_class\\":\\"\\",\\"element_class\\":\\"\\",\\"add_submit\\":\\"1\\",\\"not_logged_in_msg\\":\\"\\",\\"sub_limit_number\\":\\"\\",\\"sub_limit_msg\\":\\"\\",\\"calculations\\":[],\\"container_styles_background-color\\":\\"\\",\\"container_styles_border\\":\\"\\",\\"container_styles_border-style\\":\\"\\",\\"container_styles_border-color\\":\\"\\",\\"container_styles_color\\":\\"\\",\\"container_styles_height\\":\\"\\",\\"container_styles_width\\":\\"\\",\\"container_styles_font-size\\":\\"\\",\\"container_styles_margin\\":\\"\\",\\"container_styles_padding\\":\\"\\",\\"container_styles_display\\":\\"\\",\\"container_styles_float\\":\\"\\",\\"container_styles_show_advanced_css\\":\\"0\\",\\"container_styles_advanced\\":\\"\\",\\"title_styles_background-color\\":\\"\\",\\"title_styles_border\\":\\"\\",\\"title_styles_border-style\\":\\"\\",\\"title_styles_border-color\\":\\"\\",\\"title_styles_color\\":\\"\\",\\"title_styles_height\\":\\"\\",\\"title_styles_width\\":\\"\\",\\"title_styles_font-size\\":\\"\\",\\"title_styles_margin\\":\\"\\",\\"title_styles_padding\\":\\"\\",\\"title_styles_display\\":\\"\\",\\"title_styles_float\\":\\"\\",\\"title_styles_show_advanced_css\\":\\"0\\",\\"title_styles_advanced\\":\\"\\",\\"row_styles_background-color\\":\\"\\",\\"row_styles_border\\":\\"\\",\\"row_styles_border-style\\":\\"\\",\\"row_styles_border-color\\":\\"\\",\\"row_styles_color\\":\\"\\",\\"row_styles_height\\":\\"\\",\\"row_styles_width\\":\\"\\",\\"row_styles_font-size\\":\\"\\",\\"row_styles_margin\\":\\"\\",\\"row_styles_padding\\":\\"\\",\\"row_styles_display\\":\\"\\",\\"row_styles_show_advanced_css\\":\\"0\\",\\"row_styles_advanced\\":\\"\\",\\"row-odd_styles_background-color\\":\\"\\",\\"row-odd_styles_border\\":\\"\\",\\"row-odd_styles_border-style\\":\\"\\",\\"row-odd_styles_border-color\\":\\"\\",\\"row-odd_styles_color\\":\\"\\",\\"row-odd_styles_height\\":\\"\\",\\"row-odd_styles_width\\":\\"\\",\\"row-odd_styles_font-size\\":\\"\\",\\"row-odd_styles_margin\\":\\"\\",\\"row-odd_styles_padding\\":\\"\\",\\"row-odd_styles_display\\":\\"\\",\\"row-odd_styles_show_advanced_css\\":\\"0\\",\\"row-odd_styles_advanced\\":\\"\\",\\"success-msg_styles_background-color\\":\\"\\",\\"success-msg_styles_border\\":\\"\\",\\"success-msg_styles_border-style\\":\\"\\",\\"success-msg_styles_border-color\\":\\"\\",\\"success-msg_styles_color\\":\\"\\",\\"success-msg_styles_height\\":\\"\\",\\"success-msg_styles_width\\":\\"\\",\\"success-msg_styles_font-size\\":\\"\\",\\"success-msg_styles_margin\\":\\"\\",\\"success-msg_styles_padding\\":\\"\\",\\"success-msg_styles_display\\":\\"\\",\\"success-msg_styles_show_advanced_css\\":\\"0\\",\\"success-msg_styles_advanced\\":\\"\\",\\"error_msg_styles_background-color\\":\\"\\",\\"error_msg_styles_border\\":\\"\\",\\"error_msg_styles_border-style\\":\\"\\",\\"error_msg_styles_border-color\\":\\"\\",\\"error_msg_styles_color\\":\\"\\",\\"error_msg_styles_height\\":\\"\\",\\"error_msg_styles_width\\":\\"\\",\\"error_msg_styles_font-size\\":\\"\\",\\"error_msg_styles_margin\\":\\"\\",\\"error_msg_styles_padding\\":\\"\\",\\"error_msg_styles_display\\":\\"\\",\\"error_msg_styles_show_advanced_css\\":\\"0\\",\\"error_msg_styles_advanced\\":\\"\\",\\"allow_public_link\\":0,\\"embed_form\\":\\"\\",\\"form_title_heading_level\\":\\"3\\",\\"currency\\":\\"\\",\\"unique_field_error\\":\\"A form with this value has already been submitted.\\",\\"ninjaForms\\":\\"Ninja Forms\\",\\"changeEmailErrorMsg\\":\\"Please enter a valid email address!\\",\\"changeDateErrorMsg\\":\\"Please enter a valid date!\\",\\"confirmFieldErrorMsg\\":\\"These fields must match!\\",\\"fieldNumberNumMinError\\":\\"Number Min Error\\",\\"fieldNumberNumMaxError\\":\\"Number Max Error\\",\\"fieldNumberIncrementBy\\":\\"Please increment by \\",\\"fieldTextareaRTEInsertLink\\":\\"Insert Link\\",\\"fieldTextareaRTEInsertMedia\\":\\"Insert Media\\",\\"fieldTextareaRTESelectAFile\\":\\"Select a file\\",\\"formErrorsCorrectErrors\\":\\"Please correct errors before submitting this form.\\",\\"formHoneypot\\":\\"If you are a human seeing this field, please leave it empty.\\",\\"validateRequiredField\\":\\"This is a required field.\\",\\"honeypotHoneypotError\\":\\"Honeypot Error\\",\\"fileUploadOldCodeFileUploadInProgress\\":\\"File Upload in Progress.\\",\\"fileUploadOldCodeFileUpload\\":\\"FILE UPLOAD\\",\\"currencySymbol\\":false,\\"fieldsMarkedRequired\\":\\"Fields marked with an <span class=\\\\\\"ninja-forms-req-symbol\\\\\\">*</span> are required\\",\\"thousands_sep\\":\\",\\",\\"decimal_point\\":\\".\\",\\"siteLocale\\":\\"en_US\\",\\"dateFormat\\":\\"m/d/Y\\",\\"startOfWeek\\":\\"1\\",\\"of\\":\\"of\\",\\"previousMonth\\":\\"Previous Month\\",\\"nextMonth\\":\\"Next Month\\",\\"months\\":[\\"January\\",\\"February\\",\\"March\\",\\"April\\",\\"May\\",\\"June\\",\\"July\\",\\"August\\",\\"September\\",\\"October\\",\\"November\\",\\"December\\"],\\"monthsShort\\":[\\"Jan\\",\\"Feb\\",\\"Mar\\",\\"Apr\\",\\"May\\",\\"Jun\\",\\"Jul\\",\\"Aug\\",\\"Sep\\",\\"Oct\\",\\"Nov\\",\\"Dec\\"],\\"weekdays\\":[\\"Sunday\\",\\"Monday\\",\\"Tuesday\\",\\"Wednesday\\",\\"Thursday\\",\\"Friday\\",\\"Saturday\\"],\\"weekdaysShort\\":[\\"Sun\\",\\"Mon\\",\\"Tue\\",\\"Wed\\",\\"Thu\\",\\"Fri\\",\\"Sat\\"],\\"weekdaysMin\\":[\\"Su\\",\\"Mo\\",\\"Tu\\",\\"We\\",\\"Th\\",\\"Fr\\",\\"Sa\\"],\\"recaptchaConsentMissing\\":\\"reCaptcha validation couldn&#039;t load.\\",\\"recaptchaMissingCookie\\":\\"reCaptcha v3 validation couldn&#039;t load the cookie needed to submit the form.\\",\\"recaptchaConsentEvent\\":\\"Accept reCaptcha cookies before sending the form.\\",\\"currency_symbol\\":\\"\\",\\"beforeForm\\":\\"\\",\\"beforeFields\\":\\"\\",\\"afterFields\\":\\"\\",\\"afterForm\\":null},\\"extra\\":{}}',
        );
    }

    protected function tearDown(): void
    {
        global $apbct;
        unset($apbct);
        $_POST = [];
    }

    public function testGetDataForChecking_SkipsOnDisplayAfterFormAction()
    {
        add_action('ninja_forms_display_after_form', function() {
            // Act & Assert
            $result = $this->ninjaForms->getDataForChecking(null);
            $this->assertNull($result);
        }, 10, 1);

        do_action('ninja_forms_display_after_form');
    }

    public function testGetDataForChecking_SkipsOnCleantalkExecuted()
    {
        // Arrange
        $_POST = [];
        $_GET = [];

        // Act & Assert
        $result = $this->ninjaForms->getDataForChecking(null);
        $this->assertNull($result);
    }

    public function testGetDataForChecking_SkipsLoggedInUser()
    {
        // Arrange
        $_POST = [];
        $GLOBALS['cleantalk_executed'] = false;
        global $apbct;
        $apbct->settings['data__protect_logged_in'] = 0;

        // Act & Assert
        $result = $this->ninjaForms->getDataForChecking(null);
        $this->assertNull($result);
    }

    public function testGetDataForChecking_SkipsNinjaProServiceRequests()
    {


        // Arrange
        Post::getInstance()->variables = [];
        $_POST = $this->post;
        $_POST['nonce_ts'] = '123';
        $GLOBALS['cleantalk_executed'] = false;

        // Act & Assert
        $result = $this->ninjaForms->getDataForChecking(null);
        $this->assertNull($result);

        Post::getInstance()->variables = [];
        $_POST = $this->post;
        $_POST['nn'] = '123';
        $GLOBALS['cleantalk_executed'] = false;

        // Act & Assert
        $result = $this->ninjaForms->getDataForChecking(null);
        $this->assertNotNull($result);
    }

    public function testGetGFAOld_ReturnsDtoWithPostData()
    {
        // Arrange
        $_POST['test_field'] = 'test_value';
        $_GET['ninja_forms_ajax_submit'] = '1';

        // Mock apply_filters
        $input_array = apply_filters('apbct__filter_post', $_POST);

        // Act
        $dto = $this->ninjaForms->getGFAOld();

        // Assert
        $this->assertInstanceOf(GetFieldsAnyDTO::class, $dto);
    }

    public function testDoBlock_SetsGlobalResponseAndAddsActions()
    {
        // Arrange
        global $apbct;
        $apbct = (object)['response' => ''];
        $message = 'Spam detected';

        // Mock add_action calls (this requires more complex mocking for globals)
        $this->ninjaForms->doBlock($message);

        // Assert
        $this->assertEquals('Spam detected', $apbct->response);
    }

    public function testHookPreventSubmission_ReturnsFalse()
    {
        // Act & Assert
        $result = NinjaForms::hookPreventSubmission(null, 123);
        $this->assertFalse($result);
    }

    public function testSenderEmailIsSet()
    {
        // Arrange & Act
        Post::getInstance()->variables = [];
        $_POST = $this->post;

        $this->ninjaForms->getDataForChecking(null);

        // Assert
        $reflection = new \ReflectionClass($this->ninjaForms);
        $property = $reflection->getProperty('sender_email');
        $property->setAccessible(true);
        $this->assertEquals('s@cleantalk.org', $property->getValue($this->ninjaForms));
    }

    public function testXmlFieldsAreRemoved()
    {

        Post::getInstance()->variables = [];
        // Arrange
        $_POST['formData'] = json_encode(['fields' => [
            ['id' => 1, 'value' => '<xml>malicious</xml>'],
        ]]);


        // Act
        $result = $this->ninjaForms->getDataForChecking(null);

        // Assert
        $this->assertArrayNotHasKey('nf-field-1-', $result['message']);
    }

    public function testNoFormID()
    {

        Post::getInstance()->variables = [];
        // Arrange
        $_POST['formData'] = json_encode(['fields' => [
            ['id' => 1, 'value' => '<xml>malicious</xml>'],
        ]]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No form id provided');
        // Act
        $result = $this->ninjaForms->getGFANew();

        // Assert
        $this->assertNotNull('nf-field-1-', $result['message']);
    }

    public function testNoFormData()
    {

        Post::getInstance()->variables = [];
        // Arrange
        $_POST['formData'] = json_encode(['id' => 1]);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No form data is provided');
        // Act
        $result = $this->ninjaForms->getGFANew();

        // Assert
        $this->assertNotNull('nf-field-1-', $result['message']);
    }
}
