<?php

declare(strict_types=1);

namespace MageOS\RMA\Controller\Adminhtml\Status;

use MageOS\RMA\Api\StatusRepositoryInterface;
use MageOS\RMA\Controller\Adminhtml\AbstractLookupDelete;
use MageOS\RMA\Model\RMA\StatusCodes;
use Magento\Backend\App\Action\Context;

class Delete extends AbstractLookupDelete
{
    const ADMIN_RESOURCE = 'MageOS_RMA::rma_status';

    /**
     * @param Context $context
     * @param StatusRepositoryInterface $statusRepository
     */
    public function __construct(
        Context $context,
        StatusRepositoryInterface $statusRepository
    ) {
        parent::__construct($context, $statusRepository, 'status');
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
     * @param object $entity
     * @return string
     */
    protected function getProtectedMessage(object $entity): string
    {
        return (string)__('The status "%1" is used by the system and cannot be deleted.', $entity->getLabel());
    }
}
