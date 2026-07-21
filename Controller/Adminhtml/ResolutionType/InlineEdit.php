<?php

declare(strict_types=1);

namespace MageOS\RMA\Controller\Adminhtml\ResolutionType;

use MageOS\RMA\Api\ResolutionTypeRepositoryInterface;
use MageOS\RMA\Controller\Adminhtml\AbstractLookupInlineEdit;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;

class InlineEdit extends AbstractLookupInlineEdit
{
    const ADMIN_RESOURCE = 'MageOS_RMA::rma_resolution_type';

    /**
     * @param Context $context
     * @param ResolutionTypeRepositoryInterface $resolutionTypeRepository
     * @param JsonFactory $jsonFactory
     */
    public function __construct(
        Context $context,
        ResolutionTypeRepositoryInterface $resolutionTypeRepository,
        JsonFactory $jsonFactory
    ) {
        parent::__construct($context, $resolutionTypeRepository, $jsonFactory, 'Resolution Type');
    }
}
