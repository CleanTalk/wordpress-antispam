<?php

use Cleantalk\ApbctWP\Constant;
use PHPUnit\Framework\TestCase;

class TestConstant extends TestCase
{
    public function testClassConstantIsAPointerToTheRegistryKey()
    {
        $this->assertSame(
            'APBCT_SERVICE__DISABLE_BLOCKING_TITLE',
            Constant::APBCT_SERVICE__DISABLE_BLOCKING_TITLE
        );
    }

    public function testDeprecatedAliasPointsToTheSameRegistryEntry()
    {
        $this->assertSame(
            Constant::APBCT_SERVICE__DISABLE_BLOCKING_TITLE,
            Constant::CLEANTALK_DISABLE_BLOCKING_TITLE
        );
        $this->assertSame(
            Constant::APBCT_SERVICE__SELF_OWNED_ACCESS_KEY,
            Constant::CLEANTALK_ACCESS_KEY
        );
    }

    public function testGetNamesListsCanonicalNameFirst()
    {
        $this->assertSame(
            array('APBCT_SERVICE__SKIP_ANTICRAWLER_ON_RSS_FEED', 'APBCT_ANTICRAWLER_EXLC_FEED'),
            Constant::getNames(Constant::APBCT_SERVICE__SKIP_ANTICRAWLER_ON_RSS_FEED)
        );
    }

    public function testGetNamesOnUnknownKeyReturnsEmptyArray()
    {
        $this->assertSame(array(), Constant::getNames('NO_SUCH_CONSTANT'));
    }

    public function testGetDefinitionsCoversEveryRegisteredConstant()
    {
        $definitions = Constant::getDefinitions();

        $this->assertNotEmpty($definitions);
        foreach ($definitions as $definition) {
            $this->assertSame(array('is_defined', 'value', 'description'), array_keys($definition));
            $this->assertNotEmpty($definition['description']);
        }
    }

    public function testNeverDefinedConstantIsFalseAndFallsBackToDefault()
    {
        $this->assertFalse(Constant::is(Constant::APBCT_SERVICE__WHITELABEL_ENABLED));
        $this->assertNull(Constant::getValue(Constant::APBCT_SERVICE__WHITELABEL_ENABLED));
        $this->assertSame(
            'fallback',
            Constant::getValue(Constant::APBCT_SERVICE__WHITELABEL_ENABLED, 'fallback')
        );
    }

    public function testResolutionAgainstLiveConstants()
    {
        define('APBCT_SERVICE__DISABLE_EMPTY_EMAIL_EXCEPTION', true);

        $this->assertTrue(Constant::is(Constant::APBCT_SERVICE__DISABLE_EMPTY_EMAIL_EXCEPTION));
        $this->assertTrue(Constant::getValue(Constant::APBCT_SERVICE__DISABLE_EMPTY_EMAIL_EXCEPTION));

        $active = Constant::getDefinitionsActive();
        $this->assertCount(1, $active);
        $this->assertSame('APBCT_SERVICE__DISABLE_EMPTY_EMAIL_EXCEPTION', $active[0]['is_defined']);
        $this->assertTrue($active[0]['value']);
        $this->assertNotEmpty($active[0]['description']);

        define('APBCT_ANTICRAWLER_EXLC_FEED', true);

        $this->assertTrue(Constant::is(Constant::APBCT_SERVICE__SKIP_ANTICRAWLER_ON_RSS_FEED));
        $this->assertTrue(Constant::is(Constant::APBCT_ANTICRAWLER_EXLC_FEED));

        $active = Constant::getDefinitionsActive();
        $this->assertCount(2, $active);
        $this->assertSame('APBCT_ANTICRAWLER_EXLC_FEED', $active[1]['is_defined']);

        define('APBCT_SET_AJAX_ROUTE_TYPE', 'admin_ajax');

        $this->assertTrue(Constant::is(Constant::APBCT_SERVICE__SET_AJAX_ROUTE_TYPE));
        $this->assertSame('admin_ajax', Constant::getValue(Constant::APBCT_SERVICE__SET_AJAX_ROUTE_TYPE));
        $this->assertTrue(Constant::is(Constant::APBCT_SERVICE__SET_AJAX_ROUTE_TYPE, 'admin_ajax'));
        $this->assertFalse(Constant::is(Constant::APBCT_SERVICE__SET_AJAX_ROUTE_TYPE, 'rest'));

        $active = Constant::getDefinitionsActive();
        $this->assertCount(3, $active);
        $this->assertSame('APBCT_SET_AJAX_ROUTE_TYPE', $active[2]['is_defined']);

        define('CLEANTALK_ACCESS_KEY', 'asdasdasd');

        $this->assertTrue(Constant::is(Constant::APBCT_SERVICE__SELF_OWNED_ACCESS_KEY));
        $this->assertSame('asdasdasd', Constant::getValue(Constant::APBCT_SERVICE__SELF_OWNED_ACCESS_KEY));

        $active = Constant::getDefinitionsActive();
        $this->assertCount(4, $active);
        $this->assertSame('CLEANTALK_ACCESS_KEY', $active[3]['is_defined']);

        // --- declared int, defined as bool: rejected by the type check -------------------------
        define('CLEANTALK_CHECK_COMMENTS_NUMBER', false);

        $this->assertFalse(Constant::is(Constant::APBCT_SERVICE__SKIP_ON_APPROVED_COMMENTS_NUMBER));
        // ... so the call site gets its default instead of a bogus value
        $this->assertSame(3, Constant::getValue(Constant::APBCT_SERVICE__SKIP_ON_APPROVED_COMMENTS_NUMBER, 3));

        // it is still reported as defined - the report shows what the site actually declared
        $active = Constant::getDefinitionsActive();
        $this->assertCount(5, $active);
        $this->assertSame('CLEANTALK_CHECK_COMMENTS_NUMBER', $active[4]['is_defined']);
        $this->assertFalse($active[4]['value']);
        $this->assertNotEmpty($active[4]['description']);
    }

    /**
     * gettype() answers 'boolean'/'integer' while the registry declares 'bool'/'int'. Without the
     * alias map no bool or int constant would ever validate - which is how the previous
     * implementation silently disabled CLEANTALK_CHECK_COMMENTS_NUMBER.
     *
     * @depends testResolutionAgainstLiveConstants
     */
    public function testDeclaredBoolTypeIsAcceptedDespiteGettypeNaming()
    {
        $this->assertSame('boolean', gettype(constant('APBCT_SERVICE__DISABLE_EMPTY_EMAIL_EXCEPTION')));
        $this->assertTrue(Constant::is(Constant::APBCT_SERVICE__DISABLE_EMPTY_EMAIL_EXCEPTION));
    }

    /**
     * The registry caches metadata, never values - a constant defined after the first lookup is
     * still picked up.
     *
     * @depends testResolutionAgainstLiveConstants
     */
    public function testRegistryCachesMetadataNotValues()
    {
        $this->assertSame(
            array(
                'APBCT_SERVICE__SKIP_ON_APPROVED_COMMENTS_NUMBER',
                'CLEANTALK_CHECK_COMMENTS_NUMBER',
            ),
            Constant::getNames(Constant::APBCT_SERVICE__SKIP_ON_APPROVED_COMMENTS_NUMBER)
        );
        // CLEANTALK_CHECK_COMMENTS_NUMBER was defined after the registry had already been built
        $this->assertNotEmpty(Constant::getDefinitionsActive());
    }
}
