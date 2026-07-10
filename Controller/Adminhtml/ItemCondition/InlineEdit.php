<?php

declare(strict_types=1);

namespace MageOS\RMA\Controller\Adminhtml\ItemCondition;

use MageOS\RMA\Api\ItemConditionRepositoryInterface;
use MageOS\RMA\Controller\Adminhtml\AbstractLookupInlineEdit;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;

class InlineEdit extends AbstractLookupInlineEdit
{
    const ADMIN_RESOURCE = 'MageOS_RMA::rma_item_condition';

    /**
     * @param Context $context
     * @param ItemConditionRepositoryInterface $itemConditionRepository
     * @param JsonFactory $jsonFactory
     */
    public function __construct(
        Context $context,
        ItemConditionRepositoryInterface $itemConditionRepository,
        JsonFactory $jsonFactory
    ) {
        parent::__construct($context, $itemConditionRepository, $jsonFactory, 'Item Condition');
    }
}
