<?php

declare(strict_types=1);

namespace MageOS\RMA\Model\ResolutionType;

use MageOS\RMA\Model\ResourceModel\ResolutionType as ResolutionTypeResource;
use MageOS\RMA\Model\ResourceModel\ResolutionType\CollectionFactory;
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
     * @param ResolutionTypeResource $resolutionTypeResource
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
        protected readonly ResolutionTypeResource $resolutionTypeResource,
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

        foreach ($items as $resolutionType) {
            $data = $resolutionType->getData();
            $data['store_labels'] = $this->resolutionTypeResource->getStoreLabels($resolutionType);
            $data['store_values'] = $this->resolutionTypeResource->getStoreValues($resolutionType);
            $this->loadedData[$resolutionType->getId()] = $data;
        }

        $data = $this->dataPersistor->get('rma_resolution_type');
        if (!empty($data)) {
            $resolutionType = $this->collection->getNewEmptyItem();
            $resolutionType->setData($data);
            $this->loadedData[(int) $resolutionType->getId()] = $resolutionType->getData();
            $this->dataPersistor->clear('rma_resolution_type');
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
