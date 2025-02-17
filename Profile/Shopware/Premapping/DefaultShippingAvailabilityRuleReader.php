<?php declare(strict_types=1);

namespace SwagMigrationAssistant\Profile\Shopware\Premapping;

use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use SwagMigrationAssistant\Migration\MigrationContextInterface;
use SwagMigrationAssistant\Migration\Premapping\PremappingChoiceStruct;
use SwagMigrationAssistant\Migration\Premapping\PremappingEntityStruct;
use SwagMigrationAssistant\Migration\Premapping\PremappingStruct;
use SwagMigrationAssistant\Profile\Shopware\DataSelection\CustomerAndOrderDataSelection;
use SwagMigrationAssistant\Profile\Shopware\ShopwareProfileInterface;

class DefaultShippingAvailabilityRuleReader
{
    public const SOURCE_ID = 'default_shipping_availability_rule';
    private const MAPPING_NAME = 'shipping_availability_rule';

    private string $connectionPremappingValue = '';

    /**
     * @var string[]
     */
    private array $choiceUuids;

    public function __construct(private readonly EntityRepository $ruleRepo)
    {
    }

    public static function getMappingName(): string
    {
        return self::MAPPING_NAME;
    }

    public function supports(MigrationContextInterface $migrationContext, array $entityGroupNames): bool
    {
        return $migrationContext->getProfile() instanceof ShopwareProfileInterface
            && \in_array(CustomerAndOrderDataSelection::IDENTIFIER, $entityGroupNames, true);
    }

    public function getPremapping(Context $context, MigrationContextInterface $migrationContext): PremappingStruct
    {
        $choices = $this->getChoices($context);
        $this->fillConnectionPremappingValue($migrationContext);
        $mapping = $this->getMapping();

        return new PremappingStruct(self::getMappingName(), $mapping, $choices);
    }

    protected function fillConnectionPremappingValue(MigrationContextInterface $migrationContext): void
    {
        $connection = $migrationContext->getConnection();
        if ($connection === null) {
            return;
        }

        $mappingArray = $connection->getPremapping();

        if ($mappingArray === null) {
            return;
        }

        foreach ($mappingArray as $premapping) {
            if ($premapping['entity'] === self::MAPPING_NAME) {
                foreach ($premapping['mapping'] as $mapping) {
                    if (isset($this->choiceUuids[$mapping['destinationUuid']])) {
                        $this->connectionPremappingValue = $mapping['destinationUuid'];
                    }
                }
            }
        }
    }

    /**
     * @return PremappingEntityStruct[]
     */
    private function getMapping(): array
    {
        $entityData = [];
        $entityData[] = new PremappingEntityStruct(self::SOURCE_ID, 'Standard shipping availability rule', $this->connectionPremappingValue);

        return $entityData;
    }

    /**
     * @return PremappingChoiceStruct[]
     */
    private function getChoices(Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('name'));
        $rules = $this->ruleRepo->search($criteria, $context);

        $choices = [];
        /** @var RuleEntity $rule */
        foreach ($rules as $rule) {
            $id = $rule->getId();
            $name = $rule->getName();
            $choices[] = new PremappingChoiceStruct($id, $name);
            $this->choiceUuids[$id] = $id;
        }

        return $choices;
    }
}
