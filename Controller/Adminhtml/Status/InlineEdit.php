<?php

declare(strict_types=1);

namespace MageOS\RMA\Controller\Adminhtml\Status;

use MageOS\RMA\Api\StatusRepositoryInterface;
use MageOS\RMA\Controller\Adminhtml\AbstractLookupInlineEdit;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;

class InlineEdit extends AbstractLookupInlineEdit
{
    const ADMIN_RESOURCE = 'MageOS_RMA::rma_status';

    /**
     * @param Context $context
     * @param StatusRepositoryInterface $statusRepository
     * @param JsonFactory $jsonFactory
     */
    public function __construct(
        Context $context,
        StatusRepositoryInterface $statusRepository,
        JsonFactory $jsonFactory
    ) {
        parent::__construct($context, $statusRepository, $jsonFactory, 'Status');
    }

    /**
     * @return string[]
     */
    protected function getImmutableFields(): array
    {
        return ['code'];
    }
}
