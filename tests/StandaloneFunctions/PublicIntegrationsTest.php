<?php

use PHPUnit\Framework\TestCase;
use Cleantalk\ApbctWP\State;
class PublicIntegrationsTest extends TestCase
{
    /**
     * @var string[]
     */

    public function test_apbct_form_search__add_fields()
    {
        global $apbct;
        $apbct = new State( 'cleantalk', array('settings', 'data', 'errors', 'remote_calls', 'stats', 'fw_stats') );
        $apbct->settings['data__honeypot_field'] = 1;
        $wp_search_form = new \Cleantalk\Antispam\IntegrationsByClass\WPSearchForm();

        //define sample form test
        $sample_form = '<form role="search" method="get" id="searchform" class="searchform" action="http://www.test.dev/">
<div>
<label class="screen-reader-text" for="s">Search for:</label>
<input type="text" value="" name="s" id="s" />
<input type="submit" id="searchsubmit" value="Search" />
</div>
</form>';
        $this->assertNotFalse(
            simplexml_load_string($wp_search_form->apbctFormSearchAddFields($sample_form)),
            'TEST DATA:[' . $sample_form . '] \n Returned [' . $wp_search_form->apbctFormSearchAddFields($sample_form) . ']'
        );
        //define empty string test
        $this->assertEmpty($wp_search_form->apbctFormSearchAddFields(''),
            'TEST DATA:[EMPTY STRING]');
        //define values that will be returned as is
        $test_data_invalid[] = array('form'=>'<html></html>');
        $test_data_invalid[] = null;
        $test_data_invalid[] = false;
        $test_data_invalid[] = true;
        $test_data_invalid[] = new Exception();
        //and walk them
        foreach ($test_data_invalid as $test){
            $this->assertEquals($wp_search_form->apbctFormSearchAddFields($test),$test);
        }

        //fails if signature not found in changed form
        $success = (preg_match('/class=".*?apbct__email_id__search_form/', $wp_search_form->apbctFormSearchAddFields($sample_form)) ||
                   preg_match('/id=".*?apbct_event_id/', $wp_search_form->apbctFormSearchAddFields($sample_form))
                    )&&
                   preg_match('/class=".*?apbct_special_field/', $wp_search_form->apbctFormSearchAddFields($sample_form));
        $this->assertNotFalse($success);

        //form won`t be changed if search form checking is not set in settings
        $apbct->settings['forms__search_test'] = 0;
        $this->assertEquals($wp_search_form->apbctFormSearchAddFields($sample_form),$sample_form);
    }
}
