<?php

declare(strict_types=1);

namespace MageOS\RMA\Controller\Adminhtml\ItemCondition;

use MageOS\RMA\Api\ItemConditionRepositoryInterface;
use MageOS\RMA\Controller\Adminhtml\AbstractLookupSave;
use MageOS\RMA\Model\ItemConditionFactory;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\DataPersistorInterface;

class Save extends AbstractLookupSave
{
    const ADMIN_RESOURCE = 'MageOS_RMA::rma_item_condition';

    /**
     * @param Context $context
     * @param ItemConditionRepositoryInterface $itemConditionRepository
     * @param ItemConditionFactory $itemConditionFactory
     * @param DataPersistorInterface $dataPersistor
     */
    public function __construct(
        Context $context,
        ItemConditionRepositoryInterface $itemConditionRepository,
        ItemConditionFactory $itemConditionFactory,
        DataPersistorInterface $dataPersistor
    ) {
        parent::__construct(
            $context,
            $itemConditionRepository,
            $itemConditionFactory,
            $dataPersistor,
            'item condition',
            'rma_item_condition'
        );
    }
}
