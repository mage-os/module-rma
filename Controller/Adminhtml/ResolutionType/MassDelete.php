<?php

declare(strict_types=1);

namespace MageOS\RMA\Controller\Adminhtml\ResolutionType;

use MageOS\RMA\Controller\Adminhtml\AbstractLookupMassDelete;
use MageOS\RMA\Model\ResourceModel\ResolutionType\CollectionFactory;
use Magento\Backend\App\Action\Context;
use Magento\Ui\Component\MassAction\Filter;

class MassDelete extends AbstractLookupMassDelete
{
    const ADMIN_RESOURCE = 'MageOS_RMA::rma_resolution_type';

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
        parent::__construct($context, $filter, $collectionFactory, 'resolution type');
    }
}
