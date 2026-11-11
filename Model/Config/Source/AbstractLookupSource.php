<?php

declare(strict_types=1);

namespace MageOS\RMA\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Store\Model\StoreManagerInterface;

abstract class AbstractLookupSource implements OptionSourceInterface
{
    /**
     * @var array|null
     */
    protected ?array $options = null;

    /**
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        protected readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @return AbstractCollection
     */
    abstract protected function createCollection(): AbstractCollection;

    /**
     * @return string
     */
    abstract protected function getLabelTable(): string;

    /**
     * @return string
     */
    abstract protected function getLabelForeignKey(): string;

    /**
     * @return array
     * @throws NoSuchEntityException
     */
    public function toOptionArray(): array
    {
        if ($this->options === null) {
            $storeId = (int)$this->storeManager->getStore()->getId();
            $collection = $this->createCollection();
            $storeLabelMap = $this->getStoreLabelMap($storeId, $collection);
            $storeValueMap = $this->getStoreValueMap($storeId, $collection);

            // A store view can enable an entity that is disabled by default, so once the store view
            // carries values the "is_active" filter and the order are applied below, not in the query
            if (empty($storeValueMap)) {
                $collection->addFieldToFilter('is_active', 1);
            }
            $collection->setOrder('sort_order', 'ASC');

            $options = [];
            foreach ($collection as $entity) {
                $entityId = (int)$entity->getEntityId();
                $storeValues = $storeValueMap[$entityId] ?? [];

                if (!(int)($storeValues['is_active'] ?? $entity->getIsActive())) {
                    continue;
                }

                $label = $storeLabelMap[$entityId] ?? $entity->getLabel();
                $options[] = [
                    'value' => $entityId,
                    'label' => (string)__($label),
                    'sort_order' => (int)($storeValues['sort_order'] ?? $entity->getSortOrder()),
                ];
            }

            if (!empty($storeValueMap)) {
                // usort() is stable: entities with the same sort order keep the order of the collection
                usort($options, static fn (array $a, array $b): int => $a['sort_order'] <=> $b['sort_order']);
            }

            $this->options = array_map(
                static fn (array $option): array => ['value' => $option['value'], 'label' => $option['label']],
                $options
            );
        }

        return $this->options;
    }

    /**
     * Store view values of "is_active" and "sort_order"; an entity or a field without a value is absent
     *
     * @param int $storeId
     * @param AbstractCollection $collection
     * @return array [entity id => ['is_active' => int, 'sort_order' => int]]
     */
    protected function getStoreValueMap(int $storeId, AbstractCollection $collection): array
    {
        if ($storeId === 0) {
            return [];
        }

        $foreignKey = $this->getLabelForeignKey();
        $connection = $collection->getConnection();
        $select = $connection->select()
            ->from($collection->getTable($this->getLabelTable()), [$foreignKey, 'is_active', 'sort_order'])
            ->where('store_id = ?', $storeId)
            ->where('is_active IS NOT NULL OR sort_order IS NOT NULL');

        $map = [];
        foreach ($connection->fetchAll($select) as $row) {
            $map[(int)$row[$foreignKey]] = array_map(
                'intval',
                array_filter(
                    ['is_active' => $row['is_active'], 'sort_order' => $row['sort_order']],
                    static fn ($value): bool => $value !== null
                )
            );
        }

        return $map;
    }

    /**
     * @param int $storeId
     * @param AbstractCollection $collection
     * @return array
     */
    protected function getStoreLabelMap(int $storeId, AbstractCollection $collection): array
    {
        if ($storeId === 0) {
            return [];
        }

        $connection = $collection->getConnection();
        $select = $connection->select()
            ->from($collection->getTable($this->getLabelTable()), [$this->getLabelForeignKey(), 'label'])
            ->where('store_id = ?', $storeId);

        return $connection->fetchPairs($select);
    }
}
