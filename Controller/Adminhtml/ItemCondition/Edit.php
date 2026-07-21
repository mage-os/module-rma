<?php

declare(strict_types=1);

namespace MageOS\RMA\Controller\Adminhtml\ItemCondition;

use MageOS\RMA\Api\ItemConditionRepositoryInterface;
use MageOS\RMA\Controller\Adminhtml\AbstractLookupEdit;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Edit extends AbstractLookupEdit
{
    const ADMIN_RESOURCE = 'MageOS_RMA::rma_item_condition';

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param ItemConditionRepositoryInterface $itemConditionRepository
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        ItemConditionRepositoryInterface $itemConditionRepository
    ) {
        parent::__construct($context, $resultPageFactory, $itemConditionRepository, 'item condition', 'MageOS_RMA::rma_item_condition', 'Item Conditions');
    }
}
