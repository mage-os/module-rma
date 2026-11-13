<?php

declare(strict_types=1);

namespace MageOS\RMA\Model\ItemCondition;

use MageOS\RMA\Model\ResourceModel\ItemCondition as ItemConditionResource;
use MageOS\RMA\Model\ResourceModel\ItemCondition\CollectionFactory;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Ui\DataProvider\Modifier\PoolInterface;
use Magento\Ui\DataProvider\ModifierPoolDataProvider;

class DataProvider extends ModifierPoolDataProvider
{
    /**
     * @var array|null
     */
    protected ?array $loadedData = null;

    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param CollectionFactory $collectionFactory
     * @param DataPersistorInterface $dataPersistor
     * @param RequestInterface $request
     * @param ItemConditionResource $itemConditionResource
     * @param array $meta
     * @param array $data
     * @param PoolInterface|null $pool
     */
    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        CollectionFactory $collectionFactory,
        protected readonly DataPersistorInterface $dataPersistor,
        protected readonly RequestInterface $request,
        protected readonly ItemConditionResource $itemConditionResource,
        array $meta = [],
        array $data = [],
        ?PoolInterface $pool = null
    ) {
        $this->collection = $collectionFactory->create();
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data, $pool);
    }

    /**
     * @return array|null
     */
    public function getData(): ?array
    {
        if ($this->loadedData !== null) {
            return $this->loadedData;
        }

        $items = $this->collection->getItems();

        foreach ($items as $itemCondition) {
            $data = $itemCondition->getData();
            $data['store_labels'] = $this->itemConditionResource->getStoreLabels($itemCondition);
            $data['store_values'] = $this->itemConditionResource->getStoreValues($itemCondition);
            $this->loadedData[$itemCondition->getId()] = $data;
        }

        $data = $this->dataPersistor->get('rma_item_condition');
        if (!empty($data)) {
            $itemCondition = $this->collection->getNewEmptyItem();
            $itemCondition->setData($data);
            $this->loadedData[(int) $itemCondition->getId()] = $itemCondition->getData();
            $this->dataPersistor->clear('rma_item_condition');
        }

        return $this->loadedData;
    }

    /**
     * @return array
     */
    public function getMeta(): array
    {
        $meta = parent::getMeta();

        $meta['general']['children']['code']['arguments']['data']['config']['disabled'] =
            (bool)$this->request->getParam('entity_id');

        return $meta;
    }
}
