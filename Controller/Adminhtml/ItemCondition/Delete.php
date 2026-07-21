<?php

declare(strict_types=1);

namespace MageOS\RMA\Controller\Adminhtml\ItemCondition;

use MageOS\RMA\Api\ItemConditionRepositoryInterface;
use MageOS\RMA\Controller\Adminhtml\AbstractLookupDelete;
use Magento\Backend\App\Action\Context;

class Delete extends AbstractLookupDelete
{
    const ADMIN_RESOURCE = 'MageOS_RMA::rma_item_condition';

    /**
     * @param Context $context
     * @param ItemConditionRepositoryInterface $itemConditionRepository
     */
    public function __construct(
        Context $context,
        ItemConditionRepositoryInterface $itemConditionRepository
    ) {
        parent::__construct($context, $itemConditionRepository, 'item condition');
    }
}
