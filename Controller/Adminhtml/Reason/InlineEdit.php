<?php

declare(strict_types=1);

namespace MageOS\RMA\Controller\Adminhtml\Reason;

use MageOS\RMA\Api\ReasonRepositoryInterface;
use MageOS\RMA\Controller\Adminhtml\AbstractLookupInlineEdit;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;

class InlineEdit extends AbstractLookupInlineEdit
{
    const ADMIN_RESOURCE = 'MageOS_RMA::rma_reason';

    /**
     * @param Context $context
     * @param ReasonRepositoryInterface $reasonRepository
     * @param JsonFactory $jsonFactory
     */
    public function __construct(
        Context $context,
        ReasonRepositoryInterface $reasonRepository,
        JsonFactory $jsonFactory
    ) {
        parent::__construct($context, $reasonRepository, $jsonFactory, 'Reason');
    }
}
