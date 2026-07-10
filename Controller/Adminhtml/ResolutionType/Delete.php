<?php

declare(strict_types=1);

namespace MageOS\RMA\Controller\Adminhtml\ResolutionType;

use MageOS\RMA\Api\ResolutionTypeRepositoryInterface;
use MageOS\RMA\Controller\Adminhtml\AbstractLookupDelete;
use Magento\Backend\App\Action\Context;

class Delete extends AbstractLookupDelete
{
    const ADMIN_RESOURCE = 'MageOS_RMA::rma_resolution_type';

    /**
     * @param Context $context
     * @param ResolutionTypeRepositoryInterface $resolutionTypeRepository
     */
    public function __construct(
        Context $context,
        ResolutionTypeRepositoryInterface $resolutionTypeRepository
    ) {
        parent::__construct($context, $resolutionTypeRepository, 'resolution type');
    }
}
