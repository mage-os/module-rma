<?php

declare(strict_types=1);

namespace MageOS\RMA\Controller\Adminhtml\ItemCondition;

use MageOS\RMA\Controller\Adminhtml\AbstractLookupMassDelete;
use MageOS\RMA\Model\ResourceModel\ItemCondition\CollectionFactory;
use Magento\Backend\App\Action\Context;
use Magento\Ui\Component\MassAction\Filter;

class MassDelete extends AbstractLookupMassDelete
{
    const ADMIN_RESOURCE = 'MageOS_RMA::rma_item_condition';

    /**
     * @param Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        Context $context,
        Filter $filter,
        CollectionFactory $collectionFactory
    ) {
        parent::__construct($context, $filter, $collectionFactory, 'item condition');
    }
}
