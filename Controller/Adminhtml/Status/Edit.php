<?php

declare(strict_types=1);

namespace MageOS\RMA\Controller\Adminhtml\Status;

use MageOS\RMA\Api\StatusRepositoryInterface;
use MageOS\RMA\Controller\Adminhtml\AbstractLookupEdit;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Edit extends AbstractLookupEdit
{
    const ADMIN_RESOURCE = 'MageOS_RMA::rma_status';

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param StatusRepositoryInterface $statusRepository
     */
    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        StatusRepositoryInterface $statusRepository
    ) {
        parent::__construct($context, $resultPageFactory, $statusRepository, 'status', 'MageOS_RMA::rma_status', 'Statuses');
    }
}
