<?php

declare(strict_types=1);

namespace MageOS\RMA\Controller\Adminhtml\Status;

use MageOS\RMA\Controller\Adminhtml\AbstractLookupMassDelete;
use MageOS\RMA\Model\RMA\StatusCodes;
use MageOS\RMA\Model\ResourceModel\Status\CollectionFactory;
use Magento\Backend\App\Action\Context;
use Magento\Ui\Component\MassAction\Filter;

class MassDelete extends AbstractLookupMassDelete
{
    const ADMIN_RESOURCE = 'MageOS_RMA::rma_status';

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
        parent::__construct($context, $filter, $collectionFactory, 'status');
    }

    /**
     * @param object $entity
     * @return bool
     */
    protected function isProtected(object $entity): bool
    {
        return StatusCodes::isProtected($entity->getCode());
    }

    /**
     * @param int $count
     * @return string
     */
    protected function getProtectedSkippedMessage(int $count): string
    {
        return (string)__('%1 status(es) are used by the system and cannot be deleted.', $count);
    }
}
