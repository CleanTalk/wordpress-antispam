<?php

use Cleantalk\ApbctWP\State;

class TestFormSignsExclusion extends PHPUnit\Framework\TestCase
{
    private $test_form_data;

    public function setUp()
    {
        $this->test_form_data = array(
            'action' => 'test_action',
            'test_name_one' => 'value',
            'test_name_two' => 'value',
        );
    }

    public function test_sign_exclusion_default()
    {
        $result = apbct_exclusions_check__form_signs($this->test_form_data);
        $this->assertFalse($result);
    }

    public function test_sign_exclusion_single__action()
    {
        global $apbct;
        $apbct->settings['exclusions__form_signs'] = 'test_action';
        $apbct->saveSettings();
        $result = apbct_exclusions_check__form_signs($this->test_form_data);
        $this->assertTrue($result);
    }

    public function test_sign_exclusion_single__name()
    {
        global $apbct;
        $apbct->settings['exclusions__form_signs'] = 'test_name_one';
        $apbct->saveSettings();
        $result = apbct_exclusions_check__form_signs($this->test_form_data);
        $this->assertTrue($result);
    }

    public function test_sign_exclusion_multi__mixed()
    {
        global $apbct;
        $apbct->settings['exclusions__form_signs'] = 'test_name_one,test_action,test_name_two';
        $apbct->saveSettings();

        $result = apbct_exclusions_check__form_signs($this->test_form_data);
        $this->assertTrue($result);

        $this->test_form_data = array();
        $result = apbct_exclusions_check__form_signs($this->test_form_data);
        $this->assertFalse($result);

        $this->test_form_data = array(
            'name1' => 'value',
            'name2' => 'value'
        );
        $result = apbct_exclusions_check__form_signs($this->test_form_data);
        $this->assertFalse($result);

        $this->test_form_data = array(
            'test_name_on' => 'value',
            'action' => 'test_action'
        );
        $result = apbct_exclusions_check__form_signs($this->test_form_data);
        $this->assertTrue($result);
    }

    public function test_sign_exclusion_multi__mixed_regexp()
    {
        global $apbct;
        $apbct->settings['exclusions__form_signs'] = '.*test.*,.*_act.*';
        $apbct->saveSettings();

        $result = apbct_exclusions_check__form_signs($this->test_form_data);
        $this->assertTrue($result);

        $this->test_form_data = array();
        $result = apbct_exclusions_check__form_signs($this->test_form_data);
        $this->assertFalse($result);

        $this->test_form_data = array(
            'name1' => 'value',
            'name2' => 'value'
        );
        $result = apbct_exclusions_check__form_signs($this->test_form_data);
        $this->assertFalse($result);

        $this->test_form_data = array(
            'tes_t_name_on' => 'value',
            'action_' => 'test_action'
        );
        $result = apbct_exclusions_check__form_signs($this->test_form_data);
        $this->assertFalse($result);
    }
}
