<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SwagMigrationAssistant\Test\Profile\Shopware55\Converter;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use SwagMigrationAssistant\Migration\Connection\SwagMigrationConnectionEntity;
use SwagMigrationAssistant\Migration\MigrationContext;
use SwagMigrationAssistant\Profile\Shopware\DataSelection\DataSet\CategoryAttributeDataSet;
use SwagMigrationAssistant\Profile\Shopware55\Converter\Shopware55CategoryAttributeConverter;
use SwagMigrationAssistant\Profile\Shopware55\Shopware55Profile;
use SwagMigrationAssistant\Test\Mock\Migration\Logging\DummyLoggingService;
use SwagMigrationAssistant\Test\Mock\Migration\Mapping\DummyMappingService;

#[Package('services-settings')]
class CategoryAttributeConverterTest extends TestCase
{
    private DummyLoggingService $loggingService;

    private Shopware55CategoryAttributeConverter $converter;

    private string $runId;

    private SwagMigrationConnectionEntity $connection;

    private MigrationContext $migrationContext;

    protected function setUp(): void
    {
        $mappingService = new DummyMappingService();
        $this->loggingService = new DummyLoggingService();
        $this->converter = new Shopware55CategoryAttributeConverter($mappingService, $this->loggingService);

        $this->runId = Uuid::randomHex();
        $this->connection = new SwagMigrationConnectionEntity();
        $this->connection->setId(Uuid::randomHex());
        $this->connection->setName('ConnectionName');
        $this->connection->setProfileName(Shopware55Profile::PROFILE_NAME);

        $this->migrationContext = new MigrationContext(
            new Shopware55Profile(),
            $this->connection,
            $this->runId,
            new CategoryAttributeDataSet(),
            0,
            250
        );
    }

    public function testSupports(): void
    {
        $supportsDefinition = $this->converter->supports($this->migrationContext);

        static::assertTrue($supportsDefinition);
    }

    public function testConvertDateAttribute(): void
    {
        $categoryData = require __DIR__ . '/../../../_fixtures/attribute_data.php';

        $context = Context::createDefaultContext();
        $convertResult = $this->converter->convert($categoryData[0], $context, $this->migrationContext);
        $this->converter->writeMapping($context);
        $converted = $convertResult->getConverted();

        static::assertNull($convertResult->getUnmapped());
        static::assertNotNull($convertResult->getMappingUuid());
        static::assertArrayHasKey('id', $converted);
        static::assertArrayHasKey('relations', $converted);
        static::assertSame('category', $converted['relations'][0]['entityName']);
        static::assertSame('migration_ConnectionName_category_categorydate1', $converted['customFields'][0]['name']);
        static::assertSame('datetime', $converted['customFields'][0]['type']);
        static::assertSame('date', $converted['customFields'][0]['config']['type']);
        static::assertSame('date', $converted['customFields'][0]['config']['dateType']);
        static::assertSame('date', $converted['customFields'][0]['config']['customFieldType']);
    }

    public function testConvertCheckboxAttribute(): void
    {
        $categoryData = require __DIR__ . '/../../../_fixtures/attribute_data.php';

        $context = Context::createDefaultContext();
        $convertResult = $this->converter->convert($categoryData[1], $context, $this->migrationContext);

        $converted = $convertResult->getConverted();

        static::assertNull($convertResult->getUnmapped());
        static::assertArrayHasKey('id', $converted);
        static::assertArrayHasKey('relations', $converted);
        static::assertSame('category', $converted['relations'][0]['entityName']);
        static::assertSame('migration_ConnectionName_category_checkbox1', $converted['customFields'][0]['name']);
        static::assertSame('checkbox', $converted['customFields'][0]['config']['type']);
        static::assertSame('checkbox', $converted['customFields'][0]['config']['customFieldType']);
    }

    public function testConvertDatetimeAttribute(): void
    {
        $categoryData = require __DIR__ . '/../../../_fixtures/attribute_data.php';

        $context = Context::createDefaultContext();
        $convertResult = $this->converter->convert($categoryData[2], $context, $this->migrationContext);

        $converted = $convertResult->getConverted();

        static::assertNull($convertResult->getUnmapped());
        static::assertArrayHasKey('id', $converted);
        static::assertArrayHasKey('relations', $converted);
        static::assertSame('category', $converted['relations'][0]['entityName']);
        static::assertSame('migration_ConnectionName_category_datetime1', $converted['customFields'][0]['name']);
        static::assertSame('date', $converted['customFields'][0]['config']['type']);
        static::assertSame('datetime', $converted['customFields'][0]['config']['dateType']);
        static::assertSame('date', $converted['customFields'][0]['config']['customFieldType']);
    }

    public function testConvertTextAttribute(): void
    {
        $categoryData = require __DIR__ . '/../../../_fixtures/attribute_data.php';

        $context = Context::createDefaultContext();
        $convertResult = $this->converter->convert($categoryData[3], $context, $this->migrationContext);

        $converted = $convertResult->getConverted();

        static::assertNull($convertResult->getUnmapped());
        static::assertArrayHasKey('id', $converted);
        static::assertArrayHasKey('relations', $converted);
        static::assertSame('category', $converted['relations'][0]['entityName']);
        static::assertSame('migration_ConnectionName_category_attr6', $converted['customFields'][0]['name']);
        static::assertSame('text', $converted['customFields'][0]['config']['type']);
        static::assertSame('text', $converted['customFields'][0]['config']['customFieldType']);
    }
}
