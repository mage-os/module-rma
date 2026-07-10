<?php

declare(strict_types=1);

namespace MageOS\RMA\Controller\Adminhtml\Status;

use MageOS\RMA\Api\StatusRepositoryInterface;
use MageOS\RMA\Controller\Adminhtml\AbstractLookupSave;
use MageOS\RMA\Model\StatusFactory;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\DataPersistorInterface;

class Save extends AbstractLookupSave
{
    const ADMIN_RESOURCE = 'MageOS_RMA::rma_status';

    /**
     * @param Context $context
     * @param StatusRepositoryInterface $statusRepository
     * @param StatusFactory $statusFactory
     * @param DataPersistorInterface $dataPersistor
     */
    public function __construct(
        Context $context,
        StatusRepositoryInterface $statusRepository,
        StatusFactory $statusFactory,
        DataPersistorInterface $dataPersistor
    ) {
        parent::__construct($context, $statusRepository, $statusFactory, $dataPersistor, 'status', 'rma_status');
    }

    /**
     * @return string[]
     */
    protected function getImmutableFields(): array
    {
        return ['code'];
    }
}
