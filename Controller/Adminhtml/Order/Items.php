<?php

declare(strict_types=1);

namespace MageOS\RMA\Controller\Adminhtml\Order;

use MageOS\RMA\Model\Config\Source\ItemCondition as ItemConditionSource;
use MageOS\RMA\Service\OrderEligibility;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;

class Items extends Action implements HttpGetActionInterface
{
    const ADMIN_RESOURCE = 'MageOS_RMA::rma_manage';

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderEligibility $orderEligibility
     * @param ItemConditionSource $itemConditionSource
     */
    public function __construct(
        Context $context,
        protected readonly JsonFactory $resultJsonFactory,
        protected readonly OrderRepositoryInterface $orderRepository,
        protected readonly OrderEligibility $orderEligibility,
        protected readonly ItemConditionSource $itemConditionSource
    ) {
        parent::__construct($context);
    }

    /**
     * @return ResultInterface
     * @throws NoSuchEntityException
     */
    public function execute(): ResultInterface
    {
        $orderId = (int)$this->getRequest()->getParam('order_id');

        if (!$orderId) {
            return $this->resultJsonFactory->create()->setData([
                'items' => [],
                'conditions' => [],
                'error' => (string)__('No order ID provided.'),
            ]);
        }

        try {
            $order = $this->orderRepository->get($orderId);
        } catch (NoSuchEntityException $e) {
            return $this->resultJsonFactory->create()->setData([
                'items' => [],
                'conditions' => [],
                'error' => (string)__('Order not found.'),
            ]);
        }

        $items = $this->orderEligibility->getEligibleItems($order);

        return $this->resultJsonFactory->create()->setData([
            'items' => $items,
            'conditions' => $this->itemConditionSource->toOptionArray(),
        ]);
    }
}
