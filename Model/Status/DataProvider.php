<?php

declare(strict_types=1);

namespace MageOS\RMA\Model\Status;

use MageOS\RMA\Model\RMA\StatusCodes;
use MageOS\RMA\Model\ResourceModel\Status as StatusResource;
use MageOS\RMA\Model\ResourceModel\Status\CollectionFactory;
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
     * @param StatusResource $statusResource
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
        protected readonly StatusResource $statusResource,
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

        foreach ($items as $status) {
            $data = $status->getData();
            $data['is_system'] = StatusCodes::isProtected($data['code'] ?? '');
            $data['store_labels'] = $this->statusResource->getStoreLabels($status);
            $data['store_values'] = $this->statusResource->getStoreValues($status);
            $this->loadedData[$status->getId()] = $data;
        }

        $data = $this->dataPersistor->get('rma_status');
        if (!empty($data)) {
            $status = $this->collection->getNewEmptyItem();
            $status->setData($data);
            $statusData = $status->getData();
            $statusData['is_system'] = StatusCodes::isProtected($statusData['code'] ?? '');
            $this->loadedData[(int) $status->getId()] = $statusData;
            $this->dataPersistor->clear('rma_status');
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
