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
        $apbct = new State( 'cleantalk', array('settings', 'data', 'debug', 'errors', 'remote_calls', 'stats', 'fw_stats') );
        $apbct->settings['data__honeypot_field'] = 1;

        //define sample form test
        $sample_form = '<form role="search" method="get" id="searchform" class="searchform" action="http://www.test.dev/">
<div>
<label class="screen-reader-text" for="s">Search for:</label>
<input type="text" value="" name="s" id="s" />
<input type="submit" id="searchsubmit" value="Search" />
</div>
</form>';
        $this->assertNotFalse(simplexml_load_string(apbct_form_search__add_fields($sample_form)),
            'TEST DATA:['.$sample_form.']');
        //define empty string test
        $this->assertEmpty(apbct_form_search__add_fields(''),
            'TEST DATA:[EMPTY STRING]');
        //define values that will be returned as is
        $test_data_invalid[] = array('form'=>'<html></html>');
        $test_data_invalid[] = null;
        $test_data_invalid[] = false;
        $test_data_invalid[] = true;
        $test_data_invalid[] = new Exception();
        //and walk them
        foreach ($test_data_invalid as $test){
            $this->assertEquals(apbct_form_search__add_fields($test),$test);
        }

        //fails if signature not found in changed form
        $success = strpos(apbct_form_search__add_fields($sample_form), 'id="apbct_submit_id__search_form') &&
                   preg_match('/class=".*?apbct__email_id__search_form/', apbct_form_search__add_fields($sample_form)) &&
                   preg_match('/class=".*?apbct_special_field/',          apbct_form_search__add_fields($sample_form));
        $this->assertNotFalse($success);
        //form won`t be changed if search form checking is not set in settings
        $apbct->settings['forms__search_test'] = 0;
        $this->assertEquals(apbct_form_search__add_fields($sample_form),$sample_form);
    }
}