<?php

declare(strict_types=1);

namespace MageOS\RMA\Controller\Adminhtml\ResolutionType;

use MageOS\RMA\Api\ResolutionTypeRepositoryInterface;
use MageOS\RMA\Controller\Adminhtml\AbstractLookupSave;
use MageOS\RMA\Model\ResolutionTypeFactory;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\DataPersistorInterface;

class Save extends AbstractLookupSave
{
    const ADMIN_RESOURCE = 'MageOS_RMA::rma_resolution_type';

    /**
     * @param Context $context
     * @param ResolutionTypeRepositoryInterface $resolutionTypeRepository
     * @param ResolutionTypeFactory $resolutionTypeFactory
     * @param DataPersistorInterface $dataPersistor
     */
    public function __construct(
        Context $context,
        ResolutionTypeRepositoryInterface $resolutionTypeRepository,
        ResolutionTypeFactory $resolutionTypeFactory,
        DataPersistorInterface $dataPersistor
    ) {
        parent::__construct(
            $context,
            $resolutionTypeRepository,
            $resolutionTypeFactory,
            $dataPersistor,
            'resolution type',
            'rma_resolution_type'
        );
    }
}
