<?php

namespace ApbctWP;

use Cleantalk\ApbctWP\CleantalkRealPerson;

class TestCleantalkRealPerson extends \PHPUnit\Framework\TestCase
{
    public function testGetLocalizingData()
    {
        $localization_array = CleantalkRealPerson::getLocalizingData();

        $this->assertIsArray($localization_array);

        $this->assertArrayHasKey('theRealPerson', $localization_array);

        $this->assertArrayHasKey('phrases', $localization_array['theRealPerson']);
        $this->assertArrayHasKey('trpContentLink', $localization_array['theRealPerson']);
        $this->assertArrayHasKey('imgPersonUrl', $localization_array['theRealPerson']);
        $this->assertArrayHasKey('imgShieldUrl', $localization_array['theRealPerson']);

        $expected_phrase_keys = [
            'trpHeading',
            'trpContent1',
            'trpContent2',
            'trpContentLearnMore'
        ];

        foreach ($expected_phrase_keys as $key) {
            $this->assertArrayHasKey($key, $localization_array['theRealPerson']['phrases']);
        }
    }
}
