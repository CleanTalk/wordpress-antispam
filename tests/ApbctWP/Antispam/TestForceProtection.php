<?php

use Cleantalk\ApbctWP\Antispam\ForceProtection;
use Cleantalk\ApbctWP\Localize\CtPublicLocalize;

/**
 * Tests for ForceProtection wrappers token.
 */
class TestForceProtection extends \ApbctTestCase
{
    protected function setUp(): void
    {
        global $apbct;

        $apbct->settings['forms__force_protection'] = 1;
    }

    public function testWrapperHasTokenOfCurrentResponse()
    {
        $fp = ForceProtection::getInstance();
        $content = '<form action="https://external.example/submit" method="post"><input name="email"></form>';

        $result = $fp->modifyContent($content);

        $token = $fp->getWrapperToken();
        $this->assertSame(1, preg_match('/^[0-9a-f]{32}$/', $token));
        $this->assertStringContainsString(
            '<div class="ct-encoded-form-wrapper" data-ct-wrapper-token="' . $token . '">',
            $result
        );
        $this->assertSame($token, $fp->getWrapperToken());
    }

    public function testPlantedWrapperIsNotChanged()
    {
        $planted = '<div class="ct-encoded-form-wrapper" data-ct-wrapper-token="guess">'
            . '<div class="ct-encoded-form" data-encoded-form="PGltZz4="></div></div>';

        $result = ForceProtection::getInstance()->modifyContent($planted);

        $this->assertSame($planted, $result);
    }

    /**
     * Invalid key: cleantalk.php does not start force protection, localization must not start it either.
     */
    public function testNotInitializedForceProtectionIsNotStartedByLocalize()
    {
        $this->resetInstance();

        $localize_data = CtPublicLocalize::getData();

        $this->assertArrayNotHasKey('force_protection_wrapper_token', $localize_data);
        $this->assertFalse(ForceProtection::isInitialized());
    }

    public function testTokenIsLocalizedForJs()
    {
        ForceProtection::getInstance();

        $localize_data = CtPublicLocalize::getData();

        $this->assertArrayHasKey('force_protection_wrapper_token', $localize_data);
        $this->assertSame(
            ForceProtection::getInstance()->getWrapperToken(),
            $localize_data['force_protection_wrapper_token']
        );
    }

    private function resetInstance()
    {
        $property = new \ReflectionProperty(ForceProtection::class, 'instance');
        $property->setAccessible(true);
        $instances = (array)$property->getValue();
        unset($instances[ForceProtection::class]);
        $property->setValue(null, $instances);
    }
}
