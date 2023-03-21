<?php declare(strict_types=1);
/*
 * (c) shopware AG <info@shopware.com>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SwagMigrationAssistant\DataProvider\Provider\Data;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use SwagMigrationAssistant\Migration\DataSelection\DefaultEntities;

class CountryStateProvider extends AbstractProvider
{
    public function __construct(private readonly EntityRepository $countryStateRepo)
    {
    }

    public function getIdentifier(): string
    {
        return DefaultEntities::COUNTRY_STATE;
    }

    public function getProvidedData(int $limit, int $offset, Context $context): array
    {
        $criteria = new Criteria();
        $criteria->setLimit($limit);
        $criteria->setOffset($offset);
        $criteria->addAssociation('translations');
        $criteria->addAssociation('country');
        $criteria->addSorting(new FieldSorting('id'));
        $result = $this->countryStateRepo->search($criteria, $context);

        return $this->cleanupSearchResult($result, ['countryStateId']);
    }

    public function getProvidedTotal(Context $context): int
    {
        return $this->readTotalFromRepo($this->countryStateRepo, $context);
    }
}
