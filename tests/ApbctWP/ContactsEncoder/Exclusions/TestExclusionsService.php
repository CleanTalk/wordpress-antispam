<?php

namespace ApbctWP\ContactsEncoder\Exclusions;

use Cleantalk\ApbctWP\ContactsEncoder\Exclusions\ExclusionsService;
use Cleantalk\ApbctWP\Variables\Server;
use Cleantalk\Common\ContactsEncoder\Dto\Params;
use PHPUnit\Framework\TestCase;

class TestExclusionsService extends TestCase
{
    /**
     * @var ExclusionsService
     */
    private $exclusions_service;

    protected function setUp(): void
    {
        global $apbct;
        $apbct->api_key = 'testapikey';
        $apbct->key_is_ok = true;
        $apbct->settings['data__email_decoder'] = 1;

        $params = new Params();
        $params->api_key = $apbct->api_key;
        $this->exclusions_service = new ExclusionsService($params);

        // Reset any previously added filters
        remove_all_filters('apbct_skip_email_encoder_on_uri_chunk_list');

        // Reset Server variables cache using reflection
        $this->resetServerCache();
    }

    protected function tearDown(): void
    {
        // Clean up filters after each test
        remove_all_filters('apbct_skip_email_encoder_on_uri_chunk_list');

        // Reset Server variables cache
        $this->resetServerCache();
    }

    /**
     * Reset Server singleton instance cache using reflection
     */
    private function resetServerCache()
    {
        $reflection = new \ReflectionClass(Server::class);
        $property = $reflection->getProperty('instance');
        $property->setAccessible(true);
        $property->setValue(null, null);
    }

    /**
     * Test byUrlOnHooks returns false when no patterns provided via filter
     */
    public function testByUrlOnHooksReturnsFalseWhenNoPatternsProvided()
    {
        $_SERVER['REQUEST_URI'] = '/some-page/';

        $result = $this->invokePrivateMethod($this->exclusions_service, 'byUrlOnHooks');

        $this->assertFalse($result);
    }

    /**
     * Test byUrlOnHooks returns false when filter returns non-array
     */
    public function testByUrlOnHooksReturnsFalseWhenFilterReturnsNonArray()
    {
        $_SERVER['REQUEST_URI'] = '/some-page/';

        add_filter('apbct_skip_email_encoder_on_uri_chunk_list', function() {
            return 'not-an-array';
        });

        $result = $this->invokePrivateMethod($this->exclusions_service, 'byUrlOnHooks');

        $this->assertFalse($result);
    }

    /**
     * Test byUrlOnHooks returns true when simple substring matches
     */
    public function testByUrlOnHooksReturnsTrueForSimpleSubstringMatch()
    {
        $_SERVER['REQUEST_URI'] = '/hotel-rooms/details/';

        add_filter('apbct_skip_email_encoder_on_uri_chunk_list', function() {
            return array('details');
        });

        $result = $this->invokePrivateMethod($this->exclusions_service, 'byUrlOnHooks');

        $this->assertTrue($result);
    }

    /**
     * Test byUrlOnHooks returns false when simple substring does not match
     */
    public function testByUrlOnHooksReturnsFalseForSimpleSubstringNoMatch()
    {
        $_SERVER['REQUEST_URI'] = '/contact-us/';

        add_filter('apbct_skip_email_encoder_on_uri_chunk_list', function() {
            return array('details');
        });

        $result = $this->invokePrivateMethod($this->exclusions_service, 'byUrlOnHooks');

        $this->assertFalse($result);
    }

    /**
     * Test byUrlOnHooks returns true when __HOME__ keyword matches homepage
     */
    public function testByUrlOnHooksReturnsTrueForHomeKeywordOnHomepage()
    {
        $_SERVER['REQUEST_URI'] = '/';

        add_filter('apbct_skip_email_encoder_on_uri_chunk_list', function() {
            return array('__HOME__');
        });

        $result = $this->invokePrivateMethod($this->exclusions_service, 'byUrlOnHooks');

        $this->assertTrue($result);
    }

    /**
     * Test byUrlOnHooks returns true when __HOME__ keyword matches empty URI
     */
    public function testByUrlOnHooksReturnsTrueForHomeKeywordOnEmptyUri()
    {
        $_SERVER['REQUEST_URI'] = '';

        add_filter('apbct_skip_email_encoder_on_uri_chunk_list', function() {
            return array('__HOME__');
        });

        $result = $this->invokePrivateMethod($this->exclusions_service, 'byUrlOnHooks');

        $this->assertTrue($result);
    }

    /**
     * Test byUrlOnHooks returns false when __HOME__ keyword does not match non-homepage
     */
    public function testByUrlOnHooksReturnsFalseForHomeKeywordOnNonHomepage()
    {
        $_SERVER['REQUEST_URI'] = '/some-page/';

        add_filter('apbct_skip_email_encoder_on_uri_chunk_list', function() {
            return array('__HOME__');
        });

        $result = $this->invokePrivateMethod($this->exclusions_service, 'byUrlOnHooks');

        $this->assertFalse($result);
    }

    /**
     * Test byUrlOnHooks returns true when regex pattern matches
     */
    public function testByUrlOnHooksReturnsTrueForRegexMatch()
    {
        $_SERVER['REQUEST_URI'] = '/';

        add_filter('apbct_skip_email_encoder_on_uri_chunk_list', function() {
            return array('^/$');
        });

        $result = $this->invokePrivateMethod($this->exclusions_service, 'byUrlOnHooks');

        $this->assertTrue($result);
    }

    /**
     * Test byUrlOnHooks returns true when regex pattern with path matches
     */
    public function testByUrlOnHooksReturnsTrueForRegexPathMatch()
    {
        $_SERVER['REQUEST_URI'] = '/contacts/';

        add_filter('apbct_skip_email_encoder_on_uri_chunk_list', function() {
            return array('^/contacts/?$');
        });

        $result = $this->invokePrivateMethod($this->exclusions_service, 'byUrlOnHooks');

        $this->assertTrue($result);
    }

    /**
     * Test byUrlOnHooks returns false when regex pattern does not match
     */
    public function testByUrlOnHooksReturnsFalseForRegexNoMatch()
    {
        $_SERVER['REQUEST_URI'] = '/about-us/';

        add_filter('apbct_skip_email_encoder_on_uri_chunk_list', function() {
            return array('^/contacts/?$');
        });

        $result = $this->invokePrivateMethod($this->exclusions_service, 'byUrlOnHooks');

        $this->assertFalse($result);
    }

    /**
     * Test byUrlOnHooks with multiple patterns - first match wins
     */
    public function testByUrlOnHooksWithMultiplePatternsFirstMatchWins()
    {
        $_SERVER['REQUEST_URI'] = '/hotel-rooms/details/';

        add_filter('apbct_skip_email_encoder_on_uri_chunk_list', function() {
            return array(
                '__HOME__',
                'hotel-rooms',
                'details',
            );
        });

        $result = $this->invokePrivateMethod($this->exclusions_service, 'byUrlOnHooks');

        $this->assertTrue($result);
    }

    /**
     * Test byUrlOnHooks skips non-string patterns
     */
    public function testByUrlOnHooksSkipsNonStringPatterns()
    {
        $_SERVER['REQUEST_URI'] = '/some-page/';

        add_filter('apbct_skip_email_encoder_on_uri_chunk_list', function() {
            return array(
                null,
                123,
                array('nested'),
                new \stdClass(),
            );
        });

        $result = $this->invokePrivateMethod($this->exclusions_service, 'byUrlOnHooks');

        $this->assertFalse($result);
    }

    /**
     * Test isUriMatchPattern with __HOME__ keyword on homepage
     */
    public function testIsUriMatchPatternHomeKeywordOnHomepage()
    {
        $result = $this->invokePrivateMethod($this->exclusions_service, 'isUriMatchPattern', ['__HOME__', '/']);
        $this->assertTrue($result);

        $result = $this->invokePrivateMethod($this->exclusions_service, 'isUriMatchPattern', ['__HOME__', '']);
        $this->assertTrue($result);
    }

    /**
     * Test isUriMatchPattern with __HOME__ keyword on non-homepage
     */
    public function testIsUriMatchPatternHomeKeywordOnNonHomepage()
    {
        $result = $this->invokePrivateMethod($this->exclusions_service, 'isUriMatchPattern', ['__HOME__', '/page/']);
        $this->assertFalse($result);
    }

    /**
     * Test isUriMatchPattern with simple substring
     */
    public function testIsUriMatchPatternSimpleSubstring()
    {
        $result = $this->invokePrivateMethod($this->exclusions_service, 'isUriMatchPattern', ['details', '/hotel/details/']);
        $this->assertTrue($result);

        $result = $this->invokePrivateMethod($this->exclusions_service, 'isUriMatchPattern', ['details', '/about/']);
        $this->assertFalse($result);
    }

    /**
     * Test isUriMatchPattern with query string
     */
    public function testIsUriMatchPatternWithQueryString()
    {
        $result = $this->invokePrivateMethod($this->exclusions_service, 'isUriMatchPattern', ['page_id=19', '/?page_id=19']);
        $this->assertTrue($result);

        $result = $this->invokePrivateMethod($this->exclusions_service, 'isUriMatchPattern', ['page_id=19', '/?page_id=20']);
        $this->assertFalse($result);
    }

    /**
     * Test isUriMatchPattern with regex pattern
     */
    public function testIsUriMatchPatternRegex()
    {
        // Exact homepage match
        $result = $this->invokePrivateMethod($this->exclusions_service, 'isUriMatchPattern', ['^/$', '/']);
        $this->assertTrue($result);

        $result = $this->invokePrivateMethod($this->exclusions_service, 'isUriMatchPattern', ['^/$', '/page/']);
        $this->assertFalse($result);
    }

    /**
     * Test isUriMatchPattern with regex optional trailing slash
     */
    public function testIsUriMatchPatternRegexOptionalTrailingSlash()
    {
        $pattern = '^/contacts/?$';

        $result = $this->invokePrivateMethod($this->exclusions_service, 'isUriMatchPattern', [$pattern, '/contacts']);
        $this->assertTrue($result);

        $result = $this->invokePrivateMethod($this->exclusions_service, 'isUriMatchPattern', [$pattern, '/contacts/']);
        $this->assertTrue($result);

        $result = $this->invokePrivateMethod($this->exclusions_service, 'isUriMatchPattern', [$pattern, '/contacts/subpage']);
        $this->assertFalse($result);
    }

    /**
     * Test isUriMatchPattern with regex containing slashes
     */
    public function testIsUriMatchPatternRegexWithSlashes()
    {
        $result = $this->invokePrivateMethod($this->exclusions_service, 'isUriMatchPattern', ['^/api/v1/.*', '/api/v1/users']);
        $this->assertTrue($result);
    }

    /**
     * Test isUriMatchPattern handles invalid regex gracefully
     */
    public function testIsUriMatchPatternHandlesInvalidRegexGracefully()
    {
        // Invalid regex pattern - unmatched parenthesis
        $result = $this->invokePrivateMethod($this->exclusions_service, 'isUriMatchPattern', ['^/test(', '/test']);
        $this->assertFalse($result);
    }

    /**
     * Test doReturnContentBeforeModify returns byUrlOnHooks when pattern matches
     */
    public function testDoReturnContentBeforeModifyReturnsByUrlOnHooksWhenMatched()
    {
        $_SERVER['REQUEST_URI'] = '/';

        add_filter('apbct_skip_email_encoder_on_uri_chunk_list', function() {
            return array('__HOME__');
        });

        $result = $this->exclusions_service->doReturnContentBeforeModify('<html>test@email.com</html>');

        $this->assertEquals('byUrlOnHooks', $result);
    }

    /**
     * Test doReturnContentBeforeModify returns byContentSigns when content matches exclusion signs
     * Note: et_pb_contact_form is excluded from this test because it has special logic (!is_admin())
     */
    public function testDoReturnContentBeforeModifyReturnsByContentSignsWhenMatched()
    {
        $_SERVER['REQUEST_URI'] = '/some-page/';

        // Test with ninja forms exclusion signs (single element array)
        $content = '<div class="ninja-forms-noscript-message">Please enable JavaScript</div>';
        $result = $this->exclusions_service->doReturnContentBeforeModify($content);

        $this->assertEquals('byContentSigns', $result);
    }

    /**
     * Test doReturnContentBeforeModify returns byContentSigns for ninja forms
     */
    public function testDoReturnContentBeforeModifyReturnsByContentSignsForNinjaForms()
    {
        $_SERVER['REQUEST_URI'] = '/contact/';

        $content = '<div class="ninja-forms-noscript-message">Please enable JavaScript</div>';
        $result = $this->exclusions_service->doReturnContentBeforeModify($content);

        $this->assertEquals('byContentSigns', $result);
    }

    /**
     * Test doReturnContentBeforeModify returns byContentSigns for leaflet maps
     */
    public function testDoReturnContentBeforeModifyReturnsByContentSignsForLeafletMaps()
    {
        $_SERVER['REQUEST_URI'] = '/locations/';

        $content = '<div class="leaflet leaflet-map map-wrap">Map content</div>';
        $result = $this->exclusions_service->doReturnContentBeforeModify($content);

        $this->assertEquals('byContentSigns', $result);
    }

    /**
     * Test doReturnContentBeforeModify returns byContentSigns for WooCommerce order confirmation
     */
    public function testDoReturnContentBeforeModifyReturnsByContentSignsForWooCommerce()
    {
        $_SERVER['REQUEST_URI'] = '/checkout/order-received/';

        $content = '<form class="wc-block-order-confirmation-create-account-form">Form content</form>';
        $result = $this->exclusions_service->doReturnContentBeforeModify($content);

        $this->assertEquals('byContentSigns', $result);
    }

    /**
     * Test byContentSigns returns false for empty content
     */
    public function testByContentSignsReturnsFalseForEmptyContent()
    {
        $result = $this->invokePrivateMethod($this->exclusions_service, 'byContentSigns', ['']);
        $this->assertFalse($result);

        $result = $this->invokePrivateMethod($this->exclusions_service, 'byContentSigns', [null]);
        $this->assertFalse($result);
    }

    /**
     * Test byContentSigns returns false for non-string content
     */
    public function testByContentSignsReturnsFalseForNonStringContent()
    {
        $result = $this->invokePrivateMethod($this->exclusions_service, 'byContentSigns', [123]);
        $this->assertFalse($result);

        $result = $this->invokePrivateMethod($this->exclusions_service, 'byContentSigns', [array('test')]);
        $this->assertFalse($result);
    }

    /**
     * Test byContentSigns returns false when only partial signs match
     */
    public function testByContentSignsReturnsFalseForPartialSignMatch()
    {
        // Only one sign from array('_unique_id', 'et_pb_contact_form') - should return false
        $content = '<div class="some-form"><input name="_unique_id" value="test" /></div>';
        $result = $this->invokePrivateMethod($this->exclusions_service, 'byContentSigns', [$content]);

        $this->assertFalse($result);
    }

    /**
     * Test byContentSigns with elementor swiper gallery exclusion
     */
    public function testByContentSignsForElementorSwiperGallery()
    {
        $content = '<div class="elementor-swiper elementor-testimonial swiper-pagination">Gallery content</div>';
        $result = $this->invokePrivateMethod($this->exclusions_service, 'byContentSigns', [$content]);

        $this->assertTrue($result);
    }

    /**
     * Test byContentSigns with ics_calendar exclusion
     */
    public function testByContentSignsForIcsCalendar()
    {
        $content = '<div class="ics_calendar">Calendar content</div>';
        $result = $this->invokePrivateMethod($this->exclusions_service, 'byContentSigns', [$content]);

        $this->assertTrue($result);
    }

    /**
     * Test byContentSigns with Stylish Cost Calculator exclusion
     */
    public function testByContentSignsForStylishCostCalculator()
    {
        $content = '<div class="scc-form-field-item">Calculator field</div>';
        $result = $this->invokePrivateMethod($this->exclusions_service, 'byContentSigns', [$content]);

        $this->assertTrue($result);
    }

    /**
     * Test byContentSigns with enfold theme contact form exclusion
     */
    public function testByContentSignsForEnfoldTheme()
    {
        $content = '<form class="av_contact"><input name="email" /><input name="from_email" /></form>';
        $result = $this->invokePrivateMethod($this->exclusions_service, 'byContentSigns', [$content]);

        $this->assertTrue($result);
    }

    /**
     * Test byContentSigns with Divi builder contact form exclusion
     */
    public function testByContentSignsForDiviBuilder()
    {
        $content = '<div data-_builder_version="4.0" data-custom_contact_email="test@example.com">Form</div>';
        $result = $this->invokePrivateMethod($this->exclusions_service, 'byContentSigns', [$content]);

        $this->assertTrue($result);
    }

    /**
     * Test doSkipBeforeModifyingHooksAdded returns byPluginSetting when encoder is disabled
     */
    public function testDoSkipBeforeModifyingHooksAddedReturnsByPluginSetting()
    {
        global $apbct;
        $apbct->settings['data__email_decoder'] = 0;

        $result = $this->exclusions_service->doSkipBeforeModifyingHooksAdded();

        $this->assertEquals('byPluginSetting', $result);

        // Restore setting
        $apbct->settings['data__email_decoder'] = 1;
    }

    /**
     * Test doSkipBeforeModifyingHooksAdded returns false when encoder is enabled and no exclusions match
     */
    public function testDoSkipBeforeModifyingHooksAddedReturnsFalse()
    {
        global $apbct;
        $apbct->settings['data__email_decoder'] = 1;

        $_SERVER['REQUEST_METHOD'] = 'GET';
        unset($_POST['um_request']);
        unset($_COOKIE['apbct_email_encoder_passed']);

        $result = $this->exclusions_service->doSkipBeforeModifyingHooksAdded();

        $this->assertFalse($result);
    }

    /**
     * Test byAccessKeyFail returns true when key is not ok
     */
    public function testByAccessKeyFailReturnsTrueWhenKeyNotOk()
    {
        global $apbct;
        $original_key_is_ok = $apbct->key_is_ok;
        $apbct->key_is_ok = false;

        $result = $this->invokePrivateMethod($this->exclusions_service, 'byAccessKeyFail');

        $this->assertTrue($result);

        // Restore
        $apbct->key_is_ok = $original_key_is_ok;
    }

    /**
     * Test byAccessKeyFail returns false when key is ok
     */
    public function testByAccessKeyFailReturnsFalseWhenKeyOk()
    {
        global $apbct;
        $apbct->key_is_ok = true;

        $result = $this->invokePrivateMethod($this->exclusions_service, 'byAccessKeyFail');

        $this->assertFalse($result);
    }

    /**
     * Test byPluginSetting returns true when encoder is disabled
     */
    public function testByPluginSettingReturnsTrueWhenDisabled()
    {
        global $apbct;
        $apbct->settings['data__email_decoder'] = 0;

        $result = $this->invokePrivateMethod($this->exclusions_service, 'byPluginSetting', [$apbct]);

        $this->assertTrue($result);

        // Restore
        $apbct->settings['data__email_decoder'] = 1;
    }

    /**
     * Test byPluginSetting returns false when encoder is enabled
     */
    public function testByPluginSettingReturnsFalseWhenEnabled()
    {
        global $apbct;
        $apbct->settings['data__email_decoder'] = 1;

        $result = $this->invokePrivateMethod($this->exclusions_service, 'byPluginSetting', [$apbct]);

        $this->assertFalse($result);
    }

    /**
     * Helper method to invoke private methods for testing
     *
     * @param object $object
     * @param string $methodName
     * @param array $parameters
     * @return mixed
     */
    private function invokePrivateMethod($object, $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }
}
