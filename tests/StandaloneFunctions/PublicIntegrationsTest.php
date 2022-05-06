<?php

//include 'inc/cleantalk-public-integrations.php';

use PHPUnit\Framework\TestCase;
use Cleantalk\ApbctWP\State;

class PublicIntegrationsTest extends TestCase
{
    /**
     * @var string[]
     */

    public function test_apbct_form_search__add_fields()
    {

        $sample_form = '<form role="search" method="get" id="searchform" class="searchform" action="http://www.test.dev/">
<div>
<label class="screen-reader-text" for="s">Search for:</label>
<input type="text" value="" name="s" id="s" />
<input type="submit" id="searchsubmit" value="Search" />
</div>
</form>';

        $test_data_valid[] = $sample_form;
        $test_data_valid[] = '';

        foreach ($test_data_valid as $test){
            $this->assertNotFalse(simplexml_load_string(apbct_form_search__add_fields($test)));
        }

        $test_data_invalid[] = array('form'=>'<html></html>');
        $test_data_invalid[] = null;
        $test_data_invalid[] = false;
        $test_data_invalid[] = true;
        $test_data_invalid[] = new Exception();

        foreach ($test_data_invalid as $test){
            $this->assertFalse(simplexml_load_string(apbct_form_search__add_fields($test)));
        }

        $signature = '<input 
        id="apbct__email_id__search_form" 
        class="apbct__email_id__search_form" 
        autocomplete="off" 
        name="apbct__email_id__search_form"  
        type="text" 
        value="" 
        size="30" 
        maxlength="200" 
    />';
        $success = strpos(apbct_form_search__add_fields($sample_form),$signature);
        $this->assertNotFalse($success);
    }
}