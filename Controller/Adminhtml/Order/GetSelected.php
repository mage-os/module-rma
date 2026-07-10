<?php

declare(strict_types=1);

namespace MageOS\RMA\Controller\Adminhtml\Order;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;

class GetSelected extends Action implements HttpGetActionInterface
{
    use OrderOptionFormatter;

    const ADMIN_RESOURCE = 'MageOS_RMA::rma_manage';

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        Context $context,
        protected readonly JsonFactory $resultJsonFactory,
        protected readonly OrderRepositoryInterface $orderRepository
    ) {
        parent::__construct($context);
    }

    /**
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $orderIds = $this->getRequest()->getParam('ids');
        $options = [];

        if (!is_array($orderIds)) {
            return $this->resultJsonFactory->create()->setData($options);
        }

        foreach ($orderIds as $id) {
            try {
                $order = $this->orderRepository->get((int)$id);
                $options[] = $this->formatOrderOption($order);
            } catch (NoSuchEntityException $e) {
                continue;
            }
        }

        return $this->resultJsonFactory->create()->setData($options);
    }
}
