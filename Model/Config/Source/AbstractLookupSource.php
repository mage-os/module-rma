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
            $collection->addFieldToFilter('is_active', 1);
            $collection->setOrder('sort_order', 'ASC');

            $storeLabelMap = $this->getStoreLabelMap($storeId, $collection);

            $this->options = [];
            foreach ($collection as $entity) {
                $entityId = (int)$entity->getEntityId();
                $label = $storeLabelMap[$entityId] ?? $entity->getLabel();
                $this->options[] = [
                    'value' => $entityId,
                    'label' => (string)__($label),
                ];
            }
        }

        return $this->options;
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
