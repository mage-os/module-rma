<?php

declare(strict_types=1);

namespace MageOS\RMA\Controller\Adminhtml\ResolutionType;

use MageOS\RMA\Api\ResolutionTypeRepositoryInterface;
use MageOS\RMA\Controller\Adminhtml\AbstractLookupEdit;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Edit extends AbstractLookupEdit
{
    const ADMIN_RESOURCE = 'MageOS_RMA::rma_resolution_type';

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param ResolutionTypeRepositoryInterface $resolutionTypeRepository
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        ResolutionTypeRepositoryInterface $resolutionTypeRepository
    ) {
        parent::__construct($context, $resultPageFactory, $resolutionTypeRepository, 'resolution type', 'MageOS_RMA::rma_resolution_type', 'Resolution Types');
    }
}
