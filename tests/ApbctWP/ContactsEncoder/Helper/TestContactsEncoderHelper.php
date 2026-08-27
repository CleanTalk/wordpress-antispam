<?php

namespace ApbctWP\ContactsEncoder\Helper;

use Cleantalk\Common\ContactsEncoder\Helper\ContactsEncoderHelper;
use PHPUnit\Framework\TestCase;

class TestContactsEncoderHelper extends TestCase
{
    /**
     * @var ContactsEncoderHelper
     */
    private $helper;

    protected function setUp(): void
    {
        $this->helper = new ContactsEncoderHelper();
        remove_all_filters('apbct_email_encoder_attribute_exclusions_signs');
        remove_all_filters('apbct_skip_email_encoder_on_attribute_list');
    }

    protected function tearDown(): void
    {
        remove_all_filters('apbct_email_encoder_attribute_exclusions_signs');
        remove_all_filters('apbct_skip_email_encoder_on_attribute_list');
    }

    public function testHasAttributeExclusionsForInputDataMask()
    {
        $mask = '(999) 999-9999';
        $content = '<input type="tel" class="large" data-mask="' . $mask . '" />';

        $this->assertTrue($this->helper->hasAttributeExclusions($mask, $content));
    }

    public function testHasAttributeExclusionsForInputPlaceholder()
    {
        $email = 'info@example.com';
        $content = '<input type="email" placeholder="' . $email . '" />';

        $this->assertTrue($this->helper->hasAttributeExclusions($email, $content));
    }

    public function testHasAttributeExclusionsReturnsFalseForPlainPhone()
    {
        $phone = '(800) 555-1234';
        $content = 'Call us at ' . $phone;

        $this->assertFalse($this->helper->hasAttributeExclusions($phone, $content));
    }

    public function testHasAttributeExclusionsHonorsAttributeListFilter()
    {
        add_filter('apbct_skip_email_encoder_on_attribute_list', function ($attribute_list) {
            $attribute_list[] = 'data-phone-format';
            return $attribute_list;
        });

        $mask = '(999) 321-1233';
        $content = '<span data-phone-format="' . $mask . '"></span>';

        $this->assertTrue($this->helper->hasAttributeExclusions($mask, $content));
    }

    public function testHasAttributeExclusionsReturnsFalseForEmptyMatch()
    {
        $this->assertFalse($this->helper->hasAttributeExclusions('', '<input data-mask="(999) 999-9999" />'));
    }
}
