<?php

declare(strict_types=1);

namespace MageOS\RMA\Controller\Adminhtml\Reason;

use MageOS\RMA\Api\ReasonRepositoryInterface;
use MageOS\RMA\Controller\Adminhtml\AbstractLookupSave;
use MageOS\RMA\Model\ReasonFactory;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\DataPersistorInterface;

class Save extends AbstractLookupSave
{
    const ADMIN_RESOURCE = 'MageOS_RMA::rma_reason';

    /**
     * @param Context $context
     * @param ReasonRepositoryInterface $reasonRepository
     * @param ReasonFactory $reasonFactory
     * @param DataPersistorInterface $dataPersistor
     */
    public function __construct(
        Context $context,
        ReasonRepositoryInterface $reasonRepository,
        ReasonFactory $reasonFactory,
        DataPersistorInterface $dataPersistor
    ) {
        parent::__construct($context, $reasonRepository, $reasonFactory, $dataPersistor, 'reason', 'rma_reason');
    }
}
