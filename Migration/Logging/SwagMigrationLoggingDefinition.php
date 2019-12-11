<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SwagMigrationAssistant\Migration\Logging;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use SwagMigrationAssistant\Migration\Run\SwagMigrationRunDefinition;

class SwagMigrationLoggingDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'swag_migration_logging';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            (new StringField('level', 'level', 64))->setFlags(new Required()),
            (new StringField('code', 'code'))->setFlags(new Required()),
            (new LongTextField('title', 'title'))->setFlags(new Required()),
            (new LongTextField('description', 'description'))->setFlags(new Required()),
            (new JsonField('parameters', 'parameters'))->setFlags(new Required()),
            (new StringField('title_snippet', 'titleSnippet'))->setFlags(new Required()),
            (new StringField('description_snippet', 'descriptionSnippet'))->setFlags(new Required()),
            new StringField('entity', 'entity', 128),
            new StringField('source_id', 'sourceId', 64),
            new FkField('run_id', 'runId', SwagMigrationRunDefinition::class),
            new CreatedAtField(),
            new UpdatedAtField(),
            new ManyToOneAssociationField('run', 'run_id', SwagMigrationRunDefinition::class),
        ]);
    }

    public function getCollectionClass(): string
    {
        return SwagMigrationLoggingCollection::class;
    }

    public function getEntityClass(): string
    {
        return SwagMigrationLoggingEntity::class;
    }
}
