<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SwagMigrationAssistant\Test\Migration\Services;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\InvoicePayment;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Services\TrackingEventClient;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use SwagMigrationAssistant\Migration\Connection\SwagMigrationConnectionEntity;
use SwagMigrationAssistant\Migration\Data\SwagMigrationDataDefinition;
use SwagMigrationAssistant\Migration\Data\SwagMigrationDataEntity;
use SwagMigrationAssistant\Migration\DataSelection\DataSelectionRegistry;
use SwagMigrationAssistant\Migration\DataSelection\DataSet\DataSetRegistry;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;
use SwagMigrationAssistant\Migration\Gateway\GatewayRegistry;
use SwagMigrationAssistant\Migration\Gateway\Reader\ReaderRegistry;
use SwagMigrationAssistant\Migration\Logging\LoggingService;
use SwagMigrationAssistant\Migration\Logging\SwagMigrationLoggingEntity;
use SwagMigrationAssistant\Migration\Mapping\MappingService;
use SwagMigrationAssistant\Migration\Mapping\SwagMigrationMappingDefinition;
use SwagMigrationAssistant\Migration\Mapping\SwagMigrationMappingEntity;
use SwagMigrationAssistant\Migration\Media\MediaFileService;
use SwagMigrationAssistant\Migration\MigrationContext;
use SwagMigrationAssistant\Migration\Run\RunService;
use SwagMigrationAssistant\Migration\Run\SwagMigrationRunEntity;
use SwagMigrationAssistant\Migration\Service\MigrationDataConverterInterface;
use SwagMigrationAssistant\Migration\Service\MigrationDataFetcherInterface;
use SwagMigrationAssistant\Migration\Service\MigrationDataWriter;
use SwagMigrationAssistant\Migration\Service\MigrationDataWriterInterface;
use SwagMigrationAssistant\Migration\Service\SwagMigrationAccessTokenService;
use SwagMigrationAssistant\Migration\Writer\CustomerWriter;
use SwagMigrationAssistant\Migration\Writer\ProductWriter;
use SwagMigrationAssistant\Migration\Writer\WriterRegistry;
use SwagMigrationAssistant\Profile\Shopware\DataSelection\DataSet\CategoryDataSet;
use SwagMigrationAssistant\Profile\Shopware\DataSelection\DataSet\CustomerDataSet;
use SwagMigrationAssistant\Profile\Shopware\DataSelection\DataSet\MediaDataSet;
use SwagMigrationAssistant\Profile\Shopware\DataSelection\DataSet\OrderDataSet;
use SwagMigrationAssistant\Profile\Shopware\DataSelection\DataSet\ProductDataSet;
use SwagMigrationAssistant\Profile\Shopware\DataSelection\DataSet\SalesChannelDataSet;
use SwagMigrationAssistant\Profile\Shopware\Gateway\Local\ShopwareLocalGateway;
use SwagMigrationAssistant\Profile\Shopware\Premapping\DeliveryTimeReader;
use SwagMigrationAssistant\Profile\Shopware\Premapping\OrderStateReader;
use SwagMigrationAssistant\Profile\Shopware\Premapping\PaymentMethodReader;
use SwagMigrationAssistant\Profile\Shopware\Premapping\SalutationReader;
use SwagMigrationAssistant\Profile\Shopware\Premapping\TransactionStateReader;
use SwagMigrationAssistant\Profile\Shopware55\Shopware55Profile;
use SwagMigrationAssistant\Test\MigrationServicesTrait;
use SwagMigrationAssistant\Test\Mock\DummyThemeService;
use SwagMigrationAssistant\Test\Mock\Migration\Logging\DummyLoggingService;
use SwagMigrationAssistant\Test\Mock\Migration\Media\DummyMediaFileService;
use SwagMigrationAssistant\Test\Mock\Migration\Service\DummyMigrationDataFetcher;

#[Package('services-settings')]
class MigrationDataWriterTest extends TestCase
{
    use IntegrationTestBehaviour;
    use MigrationServicesTrait;

    private EntityRepository $productRepo;

    private EntityRepository $categoryRepo;

    private EntityRepository $mediaRepo;

    private EntityRepository $customerRepo;

    private EntityRepository $orderRepo;

    private EntityRepository $currencyRepo;

    private MigrationDataFetcherInterface $migrationDataFetcher;

    private MigrationDataConverterInterface $migrationDataConverter;

    private MigrationDataWriterInterface $migrationDataWriter;

    private string $runUuid;

    private MigrationDataWriterInterface $dummyDataWriter;

    private DummyLoggingService $loggingService;

    private EntityRepository $loggingRepo;

    private EntityRepository $migrationDataRepo;

    private EntityRepository $migrationMappingRepo;

    private string $connectionId;

    private SwagMigrationConnectionEntity $connection;

    private EntityRepository $stateMachineStateRepository;

    private EntityRepository $stateMachineRepository;

    private EntityRepository $connectionRepo;

    private EntityRepository $runRepo;

    private EntityRepository $paymentRepo;

    private EntityRepository $salutationRepo;

    private Context $context;

    private MappingService $mappingService;

    private EntityWriter $entityWriter;

    private Connection $dbConnection;

    private EntityRepository $deliveryTimeRepo;

    private EntityRepository $localeRepo;

    private EntityRepository $languageRepo;

    private EntityRepository $salesChannelRepo;

    private EntityRepository $shippingRepo;

    private EntityRepository $countryRepo;

    private RunService $runService;

    private EntityRepository $themeSalesChannelRepo;

    private EntityRepository $themeRepo;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->initRepos();
        $this->initConnectionAndRun();
        $this->initServices();
        $this->initMapping();
    }

    public function initServices(): void
    {
        $this->loggingService = new DummyLoggingService();
        $this->createMappingService();
        $this->migrationDataWriter = $this->getContainer()->get(MigrationDataWriter::class);
        $this->migrationDataFetcher = $this->getMigrationDataFetcher(
            $this->entityWriter,
            $this->mappingService,
            $this->getContainer()->get(MediaFileService::class),
            $this->loggingRepo,
            $this->getContainer()->get(SwagMigrationDataDefinition::class),
            $this->getContainer()->get(DataSetRegistry::class),
            $this->getContainer()->get('currency.repository'),
            $this->getContainer()->get('language.repository'),
            $this->getContainer()->get(ReaderRegistry::class)
        );
        $this->migrationDataConverter = $this->getMigrationDataConverter(
            $this->entityWriter,
            $this->mappingService,
            $this->getContainer()->get(MediaFileService::class),
            $this->loggingRepo,
            $this->getContainer()->get(SwagMigrationDataDefinition::class),
            $this->paymentRepo,
            $this->shippingRepo,
            $this->countryRepo,
            $this->salesChannelRepo
        );

        $mappingRepo = $this->getContainer()->get('swag_migration_mapping.repository');

        $this->dummyDataWriter = new MigrationDataWriter(
            $this->entityWriter,
            $this->migrationDataRepo,
            new WriterRegistry(
                [
                    new ProductWriter($this->entityWriter, $this->getContainer()->get(ProductDefinition::class)),
                    new CustomerWriter($this->entityWriter, $this->getContainer()->get(CustomerDefinition::class)),
                ]
            ),
            new DummyMediaFileService(),
            $this->loggingService,
            $this->getContainer()->get(SwagMigrationDataDefinition::class),
            $mappingRepo
        );

        $this->runService = new RunService(
            $this->runRepo,
            $this->connectionRepo,
            new DummyMigrationDataFetcher(new GatewayRegistry([]), $this->loggingService),
            new SwagMigrationAccessTokenService($this->runRepo),
            new DataSelectionRegistry([]),
            $this->migrationDataRepo,
            $this->mediaRepo,
            $this->salesChannelRepo,
            $this->themeRepo,
            new EntityIndexerRegistry([], $this->getContainer()->get('messenger.bus.shopware'), $this->getContainer()->get('event_dispatcher')),
            new DummyThemeService($this->themeSalesChannelRepo),
            $this->mappingService,
            $this->getContainer()->get('cache.object'),
            new SwagMigrationDataDefinition(),
            $this->dbConnection,
            new LoggingService($this->loggingRepo),
            $this->getContainer()->get(TrackingEventClient::class),
            $this->getContainer()->get('messenger.bus.shopware')
        );
    }

    public function requiredProperties(): array
    {
        return [
            ['email', true],
            ['email', false],
            ['firstName', true],
            ['firstName', false],
            ['lastName', true],
            ['lastName', false],
        ];
    }

    public function testHandleWriteException(): void
    {
        $migrationContext = new MigrationContext(
            new Shopware55Profile(),
            $this->connection,
            $this->runUuid,
            new ProductDataSet(),
            0,
            250
        );

        $updateWrittenData = [];
        $this->invokeMethod($this->migrationDataWriter, 'handleWriteException', [
            new WriteException(),
            [
                'randomId' => [
                    'name' => 'myProduct',
                ],
            ],
            'product',
            &$updateWrittenData,
            $migrationContext,
            $this->context,
        ]);

        $loggingService = (new \ReflectionClass(\get_class($this->migrationDataWriter)))->getProperty('loggingService');
        $loggingService->setAccessible(true);
        $loggingService = $loggingService->getValue($this->migrationDataWriter);
        $loggingService->saveLogging($this->context);

        /** @var SwagMigrationLoggingEntity $log */
        $log = $this->loggingRepo->search(new Criteria(), $this->context)->first();
        static::assertSame('SWAG_MIGRATION_RUN_EXCEPTION', $log->getCode());
    }

    /**
     * @dataProvider requiredProperties
     */
    public function testWriteInvalidData(string $missingProperty, bool $mappingExists): void
    {
        $context = Context::createDefaultContext();
        $migrationContext = new MigrationContext(
            new Shopware55Profile(),
            $this->connection,
            $this->runUuid,
            new CustomerDataSet(),
            0,
            250
        );

        $data = $this->migrationDataFetcher->fetchData($migrationContext, $context);
        $this->migrationDataConverter->convert($data, $migrationContext, $context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('entity', 'customer'));

        /** @var SwagMigrationDataEntity|null $data */
        $data = $this->migrationDataRepo->search($criteria, $context)->first();
        static::assertNotNull($data);
        $customer = $data->jsonSerialize();
        $customer['id'] = $data->getId();
        unset($customer['run'], $customer['converted'][$missingProperty], $customer['autoIncrement']);

        $this->migrationDataRepo->update([$customer], $context);
        if (!$mappingExists) {
            $this->migrationMappingRepo->delete([['id' => $data->getMappingUuid()]], $context);
        }

        $customerTotalBefore = $this->customerRepo->search(new Criteria(), $context)->getTotal();
        $context->scope(Context::USER_SCOPE, function (Context $context) use ($migrationContext): void {
            $this->dummyDataWriter->writeData($migrationContext, $context);
        });
        $customerTotalAfter = $this->dbConnection->executeQuery('select count(*) from customer')->fetchOne();

        static::assertSame(2, $customerTotalAfter - $customerTotalBefore);
        static::assertCount(1, $this->loggingService->getLoggingArray());
        $this->loggingService->resetLogging();

        $failureConvertCriteria = new Criteria([$data->getId()]);
        $failureConvertCriteria->addFilter(new EqualsFilter('writeFailure', true));
        $result = $this->migrationDataRepo->searchIds($failureConvertCriteria, $context)->firstId();
        static::assertNotNull($result);

        $checksumResetCriteria = new Criteria([$data->getMappingUuid() ?? '']);
        /** @var SwagMigrationMappingEntity|null $result */
        $result = $this->migrationMappingRepo->search($checksumResetCriteria, $context)->first();
        if ($mappingExists) {
            static::assertNotNull($result);
            static::assertNull($result->getChecksum());
        } else {
            static::assertNull($result);
        }
    }

    public function testWriteSalesChannelData(): void
    {
        $context = Context::createDefaultContext();
        $migrationContext = new MigrationContext(
            new Shopware55Profile(),
            $this->connection,
            $this->runUuid,
            new SalesChannelDataSet(),
            0,
            250
        );

        $data = $this->migrationDataFetcher->fetchData($migrationContext, $context);
        $this->migrationDataConverter->convert($data, $migrationContext, $context);
        $criteria = new Criteria();
        $salesChannelTotalBefore = $this->salesChannelRepo->search($criteria, $context)->getTotal();

        $context->scope(Context::USER_SCOPE, function (Context $context) use ($migrationContext): void {
            $this->migrationDataWriter->writeData($migrationContext, $context);
        });
        $salesChannelTotalAfter = $this->dbConnection->executeQuery('select count(*) from sales_channel')->fetchOne();

        $this->runService->finishMigration($this->runUuid, $context);

        static::assertSame(2, $salesChannelTotalAfter - $salesChannelTotalBefore);
    }

    public function testAssignThemeToSalesChannel(): void
    {
        $context = Context::createDefaultContext();
        $migrationContext = new MigrationContext(
            new Shopware55Profile(),
            $this->connection,
            $this->runUuid,
            new SalesChannelDataSet(),
            0,
            250
        );

        $data = $this->migrationDataFetcher->fetchData($migrationContext, $context);
        $this->migrationDataConverter->convert($data, $migrationContext, $context);
        $context->scope(Context::USER_SCOPE, function (Context $context) use ($migrationContext): void {
            $this->migrationDataWriter->writeData($migrationContext, $context);
        });
        $this->runService->finishMigration($this->runUuid, $context);

        $beforeThemeSalesChannel = $this->dbConnection->executeQuery('select count(*) from theme_sales_channel')->fetchOne();
        $this->runService->assignThemeToSalesChannel($this->runUuid, $context);
        $afterThemeSalesChannel = $this->dbConnection->executeQuery('select count(*) from theme_sales_channel')->fetchOne();

        static::assertSame(2, $afterThemeSalesChannel - $beforeThemeSalesChannel);
    }

    public function testWriteCustomerData(): void
    {
        $context = Context::createDefaultContext();
        $migrationContext = new MigrationContext(
            new Shopware55Profile(),
            $this->connection,
            $this->runUuid,
            new CustomerDataSet(),
            0,
            250
        );

        $data = $this->migrationDataFetcher->fetchData($migrationContext, $context);
        $this->migrationDataConverter->convert($data, $migrationContext, $context);
        $criteria = new Criteria();
        $customerTotalBefore = $this->customerRepo->search($criteria, $context)->getTotal();

        $context->scope(Context::USER_SCOPE, function (Context $context) use ($migrationContext): void {
            $this->migrationDataWriter->writeData($migrationContext, $context);
        });
        $customerTotalAfter = $this->dbConnection->executeQuery('select count(*) from customer')->fetchOne();

        static::assertSame(3, $customerTotalAfter - $customerTotalBefore);
    }

    public function testWriteOrderData(): void
    {
        $context = Context::createDefaultContext();
        // Add users, who have ordered
        $userMigrationContext = new MigrationContext(
            new Shopware55Profile(),
            $this->connection,
            $this->runUuid,
            new CustomerDataSet(),
            0,
            250
        );
        $data = $this->migrationDataFetcher->fetchData($userMigrationContext, $context);
        $this->migrationDataConverter->convert($data, $userMigrationContext, $context);
        $this->clearCacheData();

        $context->scope(Context::USER_SCOPE, function (Context $context) use ($userMigrationContext): void {
            $this->migrationDataWriter->writeData($userMigrationContext, $context);
            $this->clearCacheData();
        });

        // Add orders
        $migrationContext = new MigrationContext(
            new Shopware55Profile(),
            $this->connection,
            $this->runUuid,
            new OrderDataSet(),
            0,
            250
        );

        $criteria = new Criteria();

        // Get data before writing
        $data = $this->migrationDataFetcher->fetchData($migrationContext, $context);
        $this->migrationDataConverter->convert($data, $migrationContext, $context);
        $this->clearCacheData();

        $orderTotalBefore = $this->orderRepo->search($criteria, $context)->getTotal();
        // Get data after writing
        $context->scope(Context::USER_SCOPE, function (Context $context) use ($migrationContext): void {
            $this->migrationDataWriter->writeData($migrationContext, $context);
            $this->clearCacheData();
        });
        $orderTotalAfter = $this->orderRepo->search($criteria, $context)->getTotal();

        static::assertSame(2, $orderTotalAfter - $orderTotalBefore);
    }

    public function testWriteMediaData(): void
    {
        $context = Context::createDefaultContext();
        $migrationContext = new MigrationContext(
            new Shopware55Profile(),
            $this->connection,
            $this->runUuid,
            new MediaDataSet(),
            0,
            250
        );

        $data = $this->migrationDataFetcher->fetchData($migrationContext, $context);
        $this->migrationDataConverter->convert($data, $migrationContext, $context);
        $criteria = new Criteria();
        $totalBefore = $this->mediaRepo->search($criteria, $context)->getTotal();

        $context->scope(Context::USER_SCOPE, function (Context $context) use ($migrationContext): void {
            $this->migrationDataWriter->writeData($migrationContext, $context);
        });
        $totalAfter = $this->dbConnection->executeQuery('select count(*) from media')->fetchOne();

        static::assertSame(23, $totalAfter - $totalBefore);
    }

    public function testWriteCategoryData(): void
    {
        $context = Context::createDefaultContext();
        $migrationContext = new MigrationContext(
            new Shopware55Profile(),
            $this->connection,
            $this->runUuid,
            new CategoryDataSet(),
            0,
            250
        );

        $data = $this->migrationDataFetcher->fetchData($migrationContext, $context);
        $this->migrationDataConverter->convert($data, $migrationContext, $context);
        $criteria = new Criteria();
        $totalBefore = $this->categoryRepo->search($criteria, $context)->getTotal();

        $context->scope(Context::USER_SCOPE, function (Context $context) use ($migrationContext): void {
            $this->migrationDataWriter->writeData($migrationContext, $context);
        });
        $totalAfter = $this->dbConnection->executeQuery('select count(*) from category')->fetchOne();

        static::assertSame(9, $totalAfter - $totalBefore);
    }

    public function testWriteProductData(): void
    {
        $context = Context::createDefaultContext();
        $migrationContext = new MigrationContext(
            new Shopware55Profile(),
            $this->connection,
            $this->runUuid,
            new ProductDataSet(),
            0,
            250
        );

        $this->clearCacheData();
        $data = $this->migrationDataFetcher->fetchData($migrationContext, $context);
        $this->migrationDataConverter->convert($data, $migrationContext, $context);

        $criteria = new Criteria();
        $productTotalBefore = $this->productRepo->search($criteria, $context)->getTotal();

        $context->scope(Context::USER_SCOPE, function (Context $context) use ($migrationContext): void {
            $this->migrationDataWriter->writeData($migrationContext, $context);
        });
        $productTotalAfter = (int) $this->dbConnection->executeQuery('select count(*) from product')->fetchOne();

        static::assertSame(42, $productTotalAfter - $productTotalBefore);
    }

    public function testWriteDataWithUnknownWriter(): void
    {
        $context = Context::createDefaultContext();
        $migrationContext = new MigrationContext(
            new Shopware55Profile(),
            $this->connection,
            $this->runUuid,
            new MediaDataSet(),
            0,
            250
        );
        $data = $this->migrationDataFetcher->fetchData($migrationContext, $context);
        $this->migrationDataConverter->convert($data, $migrationContext, $context);
        $this->dummyDataWriter->writeData($migrationContext, $context);

        $logs = $this->loggingService->getLoggingArray();

        static::assertSame('SWAG_MIGRATION_RUN_EXCEPTION', $logs[0]['code']);
        static::assertSame('SWAG_MIGRATION__WRITER_NOT_FOUND', $logs[0]['parameters']['exceptionCode']);
        static::assertCount(1, $logs);
    }

    private function createMappingService(): void
    {
        $this->mappingService = new MappingService(
            $this->migrationMappingRepo,
            $this->localeRepo,
            $this->languageRepo,
            $this->countryRepo,
            $this->currencyRepo,
            $this->getContainer()->get('tax.repository'),
            $this->getContainer()->get('number_range.repository'),
            $this->getContainer()->get('rule.repository'),
            $this->getContainer()->get('media_thumbnail_size.repository'),
            $this->getContainer()->get('media_default_folder.repository'),
            $this->categoryRepo,
            $this->getContainer()->get('cms_page.repository'),
            $this->deliveryTimeRepo,
            $this->getContainer()->get('document_type.repository'),
            $this->entityWriter,
            $this->getContainer()->get(SwagMigrationMappingDefinition::class)
        );
    }

    private function invokeMethod(object $object, string $methodName, array $parameters = []): ?object
    {
        $method = (new \ReflectionClass($object::class))->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $parameters);
    }

    private function initRepos(): void
    {
        $this->dbConnection = $this->getContainer()->get(Connection::class);
        $this->entityWriter = $this->getContainer()->get(EntityWriter::class);
        $this->runRepo = $this->getContainer()->get('swag_migration_run.repository');
        $this->paymentRepo = $this->getContainer()->get('payment_method.repository');
        $this->mediaRepo = $this->getContainer()->get('media.repository');
        $this->productRepo = $this->getContainer()->get('product.repository');
        $this->categoryRepo = $this->getContainer()->get('category.repository');
        $this->orderRepo = $this->getContainer()->get('order.repository');
        $this->customerRepo = $this->getContainer()->get('customer.repository');
        $this->connectionRepo = $this->getContainer()->get('swag_migration_connection.repository');
        $this->migrationDataRepo = $this->getContainer()->get('swag_migration_data.repository');
        $this->migrationMappingRepo = $this->getContainer()->get('swag_migration_mapping.repository');
        $this->loggingRepo = $this->getContainer()->get('swag_migration_logging.repository');
        $this->stateMachineRepository = $this->getContainer()->get('state_machine.repository');
        $this->stateMachineStateRepository = $this->getContainer()->get('state_machine_state.repository');
        $this->currencyRepo = $this->getContainer()->get('currency.repository');
        $this->salutationRepo = $this->getContainer()->get('salutation.repository');
        $this->deliveryTimeRepo = $this->getContainer()->get('delivery_time.repository');
        $this->localeRepo = $this->getContainer()->get('locale.repository');
        $this->languageRepo = $this->getContainer()->get('language.repository');
        $this->salesChannelRepo = $this->getContainer()->get('sales_channel.repository');
        $this->themeRepo = $this->getContainer()->get('theme.repository');
        $this->shippingRepo = $this->getContainer()->get('shipping_method.repository');
        $this->countryRepo = $this->getContainer()->get('country.repository');
        $this->themeSalesChannelRepo = $this->getContainer()->get('theme_sales_channel.repository');
    }

    private function initConnectionAndRun(): void
    {
        $this->connectionId = Uuid::randomHex();
        $this->runUuid = Uuid::randomHex();

        $this->context->scope(MigrationContext::SOURCE_CONTEXT, function (Context $context): void {
            $this->connectionRepo->create(
                [
                    [
                        'id' => $this->connectionId,
                        'name' => 'myConnection',
                        'credentialFields' => [
                            'endpoint' => 'testEndpoint',
                            'apiUser' => 'testUser',
                            'apiKey' => 'testKey',
                        ],
                        'profileName' => Shopware55Profile::PROFILE_NAME,
                        'gatewayName' => ShopwareLocalGateway::GATEWAY_NAME,
                    ],
                ],
                $context
            );
        });
        $connection = $this->connectionRepo->search(new Criteria([$this->connectionId]), $this->context)->first();

        static::assertInstanceOf(SwagMigrationConnectionEntity::class, $connection);

        $this->connection = $connection;

        $this->runRepo->create(
            [
                [
                    'id' => $this->runUuid,
                    'status' => SwagMigrationRunEntity::STATUS_RUNNING,
                    'connectionId' => $this->connectionId,
                ],
            ],
            $this->context
        );
    }

    private function initMapping(): void
    {
        $orderStateUuid = $this->getOrderStateUuid(
            $this->stateMachineRepository,
            $this->stateMachineStateRepository,
            0,
            $this->context
        );
        $this->mappingService->createMapping($this->connectionId, OrderStateReader::getMappingName(), '0', Uuid::randomHex(), [], $orderStateUuid);

        $transactionStateUuid = $this->getTransactionStateUuid(
            $this->stateMachineRepository,
            $this->stateMachineStateRepository,
            17,
            $this->context
        );
        $this->mappingService->getOrCreateMapping($this->connectionId, TransactionStateReader::getMappingName(), '17', $this->context, Uuid::randomHex(), [], $transactionStateUuid);

        $paymentUuid = $this->getPaymentUuid(
            $this->paymentRepo,
            InvoicePayment::class,
            $this->context
        );

        $this->mappingService->getOrCreateMapping($this->connectionId, PaymentMethodReader::getMappingName(), '3', $this->context, Uuid::randomHex(), [], $paymentUuid);
        $this->mappingService->getOrCreateMapping($this->connectionId, PaymentMethodReader::getMappingName(), '4', $this->context, Uuid::randomHex(), [], $paymentUuid);
        $this->mappingService->getOrCreateMapping($this->connectionId, PaymentMethodReader::getMappingName(), '5', $this->context, Uuid::randomHex(), [], $paymentUuid);

        $salutationUuid = $this->getSalutationUuid(
            $this->salutationRepo,
            'mr',
            $this->context
        );

        $this->mappingService->getOrCreateMapping($this->connectionId, SalutationReader::getMappingName(), 'mr', $this->context, Uuid::randomHex(), [], $salutationUuid);
        $this->mappingService->getOrCreateMapping($this->connectionId, SalutationReader::getMappingName(), 'ms', $this->context, Uuid::randomHex(), [], $salutationUuid);

        $deliveryTimeUuid = $this->getFirstDeliveryTimeUuid($this->deliveryTimeRepo, $this->context);
        $this->mappingService->getOrCreateMapping($this->connectionId, DeliveryTimeReader::getMappingName(), DeliveryTimeReader::SOURCE_ID, $this->context, null, [], $deliveryTimeUuid);

        $currencyUuid = $this->getCurrencyUuid(
            $this->currencyRepo,
            'EUR',
            $this->context
        );
        $this->mappingService->getOrCreateMapping($this->connectionId, DefaultEntities::CURRENCY, 'JPY', $this->context, Uuid::randomHex(), [], $currencyUuid);

        $currencyUuid = $this->getCurrencyUuid(
            $this->currencyRepo,
            'EUR',
            $this->context
        );
        $this->mappingService->getOrCreateMapping($this->connectionId, DefaultEntities::CURRENCY, 'JPY', $this->context, Uuid::randomHex(), [], $currencyUuid);
        $this->mappingService->getOrCreateMapping($this->connectionId, DefaultEntities::CURRENCY, 'EUR', $this->context, Uuid::randomHex(), [], $currencyUuid);

        $languageUuid = $this->getLanguageUuid(
            $this->localeRepo,
            $this->languageRepo,
            'de-DE',
            $this->context
        );
        $this->mappingService->getOrCreateMapping($this->connectionId, DefaultEntities::LANGUAGE, 'en-US', $this->context, Uuid::randomHex(), [], $languageUuid);
        $this->mappingService->getOrCreateMapping($this->connectionId, DefaultEntities::LANGUAGE, 'en-GB', $this->context, Uuid::randomHex(), [], Defaults::LANGUAGE_SYSTEM);
        $this->mappingService->getOrCreateMapping($this->connectionId, DefaultEntities::LANGUAGE, 'nl-NL', $this->context, Uuid::randomHex(), [], $languageUuid);
        $this->mappingService->getOrCreateMapping($this->connectionId, DefaultEntities::LANGUAGE, 'bn-IN', $this->context, Uuid::randomHex(), [], $languageUuid);

        $this->mappingService->getOrCreateMapping($this->connectionId, DefaultEntities::CUSTOMER_GROUP, '1', $this->context, Uuid::randomHex(), [], 'cfbd5018d38d41d8adca10d94fc8bdd6');
        $this->mappingService->getOrCreateMapping($this->connectionId, DefaultEntities::CUSTOMER_GROUP, '2', $this->context, Uuid::randomHex(), [], 'cfbd5018d38d41d8adca10d94fc8bdd6');

        $categoryUuid = $this->getCategoryUuid($this->categoryRepo, $this->context);
        $this->mappingService->getOrCreateMapping($this->connectionId, DefaultEntities::CATEGORY, '3', $this->context, Uuid::randomHex(), [], $categoryUuid);
        $this->mappingService->getOrCreateMapping($this->connectionId, DefaultEntities::CATEGORY, '39', $this->context, Uuid::randomHex(), [], $categoryUuid);

        $this->mappingService->writeMapping($this->context);
        $this->clearCacheData();
    }
}
