<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\CurrencyDefinition;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use SwagMigrationAssistant\Migration\Connection\SwagMigrationConnectionEntity;
use SwagMigrationAssistant\Migration\DisplayWarning;
use SwagMigrationAssistant\Migration\EnvironmentInformation;
use SwagMigrationAssistant\Migration\Gateway\Reader\ReaderRegistry;
use SwagMigrationAssistant\Migration\MigrationContext;
use SwagMigrationAssistant\Migration\RequestStatusStruct;
use SwagMigrationAssistant\Profile\Shopware6\Gateway\Api\Reader\EnvironmentReader;
use SwagMigrationAssistant\Profile\Shopware6\Gateway\Api\Shopware6ApiGateway;
use SwagMigrationAssistant\Profile\Shopware6\Gateway\TableReaderInterface;
use SwagMigrationAssistant\Profile\Shopware6\Gateway\TotalReaderInterface;
use SwagMigrationAssistant\Profile\Shopware6\Shopware6MajorProfile;

class Shopware6ApiGatewayTest extends TestCase
{
    private Shopware6ApiGateway $shopware6ApiGateway;

    private MigrationContext $migrationContext;

    /**
     * @dataProvider provideEnvironments
     */
    public function testReadEnvironmentInformation(array $source, array $self, array $expectation): void
    {
        $this->createShopware6ApiGateway($self['shopwareVersion'], $source['shopwareVersion'], $source['updateAvailable'], $self['defaultCurrency'], $source['defaultCurrency'], $self['defaultLocale'], $source['defaultLocale']);
        $this->createMigrationContext($self['shopwareVersion']);

        $result = $this->shopware6ApiGateway->readEnvironmentInformation($this->migrationContext, Context::createDefaultContext());
        $expectedEnvironmentInfo = new EnvironmentInformation(
            'Shopware',
            $source['shopwareVersion'],
            'http://test.local',
            [],
            [],
            new RequestStatusStruct(),
            $expectation['migrationDisabled'],
            $expectation['displayWarnings'],
            $self['defaultCurrency'],
            $source['defaultCurrency'],
            $source['defaultLocale'],
            $self['defaultLocale'],
        );

        static::assertEquals($expectedEnvironmentInfo, $result);
    }

    public function provideEnvironments(): array
    {
        return [
            [ // old major to new major
                'source' => [
                    'shopwareVersion' => '6.4.0.0',
                    'defaultLocale' => 'en-GB',
                    'defaultCurrency' => 'EUR',
                    'updateAvailable' => false,
                ],
                'self' => [
                    'shopwareVersion' => '6.5.6.1',
                    'defaultLocale' => 'en-GB',
                    'defaultCurrency' => 'EUR',
                ],
                'expectation' => [
                    'migrationDisabled' => true,
                    'displayWarnings' => [
                        new DisplayWarning('swag-migration.index.shopwareMajorVersionText', [
                            'sourceSystem' => 'Shopware 6.4',
                            'targetSystem' => 'Shopware 6.5',
                        ]),
                    ],
                ],
            ],
            [ // new major to old major
                'source' => [
                    'shopwareVersion' => '6.6.0.0',
                    'defaultLocale' => 'en-GB',
                    'defaultCurrency' => 'EUR',
                    'updateAvailable' => false,
                ],
                'self' => [
                    'shopwareVersion' => '6.5.6.1',
                    'defaultLocale' => 'en-GB',
                    'defaultCurrency' => 'EUR',
                ],
                'expectation' => [
                    'migrationDisabled' => true,
                    'displayWarnings' => [
                        new DisplayWarning('swag-migration.index.shopwareMajorVersionText', [
                            'sourceSystem' => 'Shopware 6.6',
                            'targetSystem' => 'Shopware 6.5',
                        ]),
                    ],
                ],
            ],
            [ // same major but different minors
                'source' => [
                    'shopwareVersion' => '6.5.4.2',
                    'defaultLocale' => 'en-GB',
                    'defaultCurrency' => 'EUR',
                    'updateAvailable' => false,
                ],
                'self' => [
                    'shopwareVersion' => '6.5.6.1',
                    'defaultLocale' => 'en-GB',
                    'defaultCurrency' => 'EUR',
                ],
                'expectation' => [
                    'migrationDisabled' => false,
                    'displayWarnings' => [],
                ],
            ],
            [ // same major but plugin update available on source system
                'source' => [
                    'shopwareVersion' => '6.5.6.1',
                    'defaultLocale' => 'en-GB',
                    'defaultCurrency' => 'EUR',
                    'updateAvailable' => true,
                ],
                'self' => [
                    'shopwareVersion' => '6.5.6.1',
                    'defaultLocale' => 'en-GB',
                    'defaultCurrency' => 'EUR',
                ],
                'expectation' => [
                    'migrationDisabled' => false,
                    'displayWarnings' => [
                        new DisplayWarning('swag-migration.index.pluginVersionText', [
                            'sourceSystem' => 'Shopware 6.5',
                            'pluginName' => 'Migration Assistant',
                        ]),
                    ],
                ],
            ],
            [ // old major to new major with plugin update available in source system
                'source' => [
                    'shopwareVersion' => '6.4.0.0',
                    'defaultLocale' => 'en-GB',
                    'defaultCurrency' => 'EUR',
                    'updateAvailable' => true,
                ],
                'self' => [
                    'shopwareVersion' => '6.5.6.1',
                    'defaultLocale' => 'en-GB',
                    'defaultCurrency' => 'EUR',
                ],
                'expectation' => [
                    'migrationDisabled' => true,
                    'displayWarnings' => [
                        new DisplayWarning('swag-migration.index.shopwareMajorVersionText', [
                            'sourceSystem' => 'Shopware 6.4',
                            'targetSystem' => 'Shopware 6.5',
                        ]),
                        new DisplayWarning('swag-migration.index.pluginVersionText', [
                            'sourceSystem' => 'Shopware 6.4',
                            'pluginName' => 'Migration Assistant',
                        ]),
                    ],
                ],
            ],
            [ // same major into dev major
                'source' => [
                    'shopwareVersion' => '6.5.0.0',
                    'defaultLocale' => 'en-GB',
                    'defaultCurrency' => 'EUR',
                    'updateAvailable' => false,
                ],
                'self' => [
                    'shopwareVersion' => '6.5.9999999.9999999-dev',
                    'defaultLocale' => 'en-GB',
                    'defaultCurrency' => 'EUR',
                ],
                'expectation' => [
                    'migrationDisabled' => false,
                    'displayWarnings' => [],
                ],
            ],
            [ // old major into dev major
                'source' => [
                    'shopwareVersion' => '6.4.0.0',
                    'defaultLocale' => 'en-GB',
                    'defaultCurrency' => 'EUR',
                    'updateAvailable' => false,
                ],
                'self' => [
                    'shopwareVersion' => '6.5.9999999.9999999-dev',
                    'defaultLocale' => 'en-GB',
                    'defaultCurrency' => 'EUR',
                ],
                'expectation' => [
                    'migrationDisabled' => true,
                    'displayWarnings' => [
                        new DisplayWarning('swag-migration.index.shopwareMajorVersionText', [
                            'sourceSystem' => 'Shopware 6.4',
                            'targetSystem' => 'Shopware 6.5',
                        ]),
                    ],
                ],
            ],
            [ // dev major into older major
                'source' => [
                    'shopwareVersion' => '6.5.9999999.9999999-dev',
                    'defaultLocale' => 'en-GB',
                    'defaultCurrency' => 'EUR',
                    'updateAvailable' => false,
                ],
                'self' => [
                    'shopwareVersion' => '6.4.0.0',
                    'defaultLocale' => 'en-GB',
                    'defaultCurrency' => 'EUR',
                ],
                'expectation' => [
                    'migrationDisabled' => true,
                    'displayWarnings' => [
                        new DisplayWarning('swag-migration.index.shopwareMajorVersionText', [
                            'sourceSystem' => 'Shopware 6.5',
                            'targetSystem' => 'Shopware 6.4',
                        ]),
                    ],
                ],
            ],
        ];
    }

    protected function createShopware6ApiGateway(string $selfShopwareVersion, string $sourceShopwareVersion, bool $sourceUpdateAvailable, string $selfDefaultCurrency, string $sourceDefaultCurrency, string $selfDefaultLocale, string $sourceDefaultLocale): void
    {
        $readerRegistry = new ReaderRegistry([]);
        $environmentReader = static::createStub(EnvironmentReader::class);
        $environmentReader->method('read')->willReturn([
            'environmentInformation' => [
                'defaultShopLanguage' => $sourceDefaultLocale,
                'defaultCurrency' => $sourceDefaultCurrency,
                'shopwareVersion' => $sourceShopwareVersion,
                'versionText' => $sourceShopwareVersion,
                'revision' => 'c6221a390c0891e4c637b8c75927644ad87bd260',
                'additionalData' => [],
                'updateAvailable' => $sourceUpdateAvailable,
            ],
            'requestStatus' => new RequestStatusStruct(),
        ]);
        $currencyEntity = new CurrencyEntity();
        $currencyEntity->setId(Defaults::CURRENCY);
        $currencyEntity->setIsoCode($selfDefaultCurrency);
        $currencyRepo = new StaticEntityRepository(
            [
                new EntitySearchResult(
                    CurrencyDefinition::ENTITY_NAME,
                    1,
                    new EntityCollection([$currencyEntity]),
                    null,
                    new Criteria(),
                    Context::createDefaultContext(),
                ),
            ],
            new CurrencyDefinition(),
        );

        $languageEntity = new LanguageEntity();
        $languageEntity->setId(Defaults::LANGUAGE_SYSTEM);
        $localeEntity = new LocaleEntity();
        $localeEntity->setId(Uuid::randomHex());
        $localeEntity->setCode($selfDefaultLocale);
        $languageEntity->setLocale($localeEntity);
        $languageRepo = new StaticEntityRepository(
            [
                new EntitySearchResult(
                    LanguageDefinition::ENTITY_NAME,
                    1,
                    new EntityCollection([$languageEntity]),
                    null,
                    new Criteria(),
                    Context::createDefaultContext(),
                ),
            ],
            new LanguageDefinition(),
        );

        $totalReader = static::createStub(TotalReaderInterface::class);
        $totalReader->method('readTotals')->willReturn([]);

        $tableReader = static::createStub(TableReaderInterface::class);
        $tableReader->method('read')->willReturn([]);

        $this->shopware6ApiGateway = new \SwagMigrationAssistant\Profile\Shopware6\Gateway\Api\Shopware6ApiGateway(
            $readerRegistry,
            $environmentReader,
            $currencyRepo,
            $languageRepo,
            $totalReader,
            $tableReader,
            $selfShopwareVersion,
        );
    }

    protected function createMigrationContext(string $selfShopwareVersion): void
    {
        $profile = new Shopware6MajorProfile($selfShopwareVersion);
        $connection = new SwagMigrationConnectionEntity();
        $connection->setId(Uuid::randomHex());
        $connection->setCredentialFields([
            'endpoint' => 'http://test.local',
            'apiUser' => 'dummyUser',
            'apiPassword' => 'dummyPassword',
            'bearer_token' => 'dummyToken',
        ]);

        $this->migrationContext = new MigrationContext(
            $profile,
            $connection,
            Uuid::randomHex(),
            null,
            0,
            100
        );
    }
}
