<?php

declare(strict_types=1);

namespace MageOS\RMA\Controller\Adminhtml\Reason;

use MageOS\RMA\Api\ReasonRepositoryInterface;
use MageOS\RMA\Controller\Adminhtml\AbstractLookupEdit;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Edit extends AbstractLookupEdit
{
    const ADMIN_RESOURCE = 'MageOS_RMA::rma_reason';

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param ReasonRepositoryInterface $reasonRepository
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        ReasonRepositoryInterface $reasonRepository
    ) {
        parent::__construct($context, $resultPageFactory, $reasonRepository, 'reason', 'MageOS_RMA::rma_reason', 'Reasons');
    }
}
