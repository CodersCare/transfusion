<?php

namespace T3thi\Transfusion\Tests\Functional;

use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use T3thi\Transfusion\Tests\Functional\SiteHandling\SiteBasedTestTrait;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Test Case for json-generation/export
 */
class DummyTest extends FunctionalTestCase
{
    use MockeryPHPUnitIntegration;
    use SiteBasedTestTrait;

    private const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8', 'iso' => 'en', 'hrefLang' => 'en-US', 'direction' => ''],
        'DK' => ['id' => 1, 'title' => 'Dansk', 'locale' => 'da_DK.UTF8', 'iso' => 'da', 'hrefLang' => 'da-DK', 'direction' => ''],
        'DE' => ['id' => 2, 'title' => 'Deutsch', 'locale' => 'de_DE.UTF8', 'iso' => 'de', 'hrefLang' => 'de-DE', 'direction' => ''],
        'IT' => ['id' => 3, 'title' => 'Italiano', 'locale' => 'it_IT.UTF8', 'iso' => 'it', 'hrefLang' => 'it-IT', 'direction' => ''],
        'ES' => ['id' => 4, 'title' => 'Español', 'locale' => 'es_ES.UTF8', 'iso' => 'es', 'hrefLang' => 'es-ES', 'direction' => ''],
        'PT' => ['id' => 5, 'title' => 'Português', 'locale' => 'pt_PT.UTF8', 'iso' => 'pt', 'hrefLang' => 'pt-PT', 'direction' => ''],
        'NL' => ['id' => 6, 'title' => 'Nederlands', 'locale' => 'nl_NL.UTF8', 'iso' => 'nl', 'hrefLang' => 'nl-NL', 'direction' => ''],
    ];
    protected array $coreExtensionsToLoad = [
        'scheduler',
        'impexp'
    ];

    /**
     * @var array Have needed extensions loaded
     */
    protected array $testExtensionsToLoad = [
        'typo3conf/ext/transfusion'
    ];

    protected array $configurationToUseInTestInstance = [
        'EXTENSIONS' => [
        ],
    ];

    /**
     * Sets up this test case.
     */
    protected function setUp(): void
    {
        parent::setUp();

        //$this->setUpBackendUserFromFixture(1);
        Bootstrap::initializeLanguageObject();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/tt_content.csv');

        $siteConfig = $this->buildSiteConfiguration(1, '/');
        $this->writeSiteConfiguration('testing', $siteConfig);

        date_default_timezone_set('Europe/Berlin');
    }

}
