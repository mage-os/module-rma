<?php

declare(strict_types=1);

namespace MageOS\RMA\Controller\Adminhtml\Order;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use MageOS\RMA\Helper\ModuleConfig;
use MageOS\RMA\Service\OrderEligibility;

class Search extends Action implements HttpGetActionInterface
{
    use OrderOptionFormatter;

    const ADMIN_RESOURCE = 'MageOS_RMA::rma_manage';

    /**
     * @param Context $context
     * @param JsonFactory $resultJsonFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param ModuleConfig $moduleConfig
     * @param OrderEligibility $orderEligibility
     */
    public function __construct(
        Context $context,
        protected readonly JsonFactory $resultJsonFactory,
        protected readonly OrderRepositoryInterface $orderRepository,
        protected readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        protected readonly ModuleConfig $moduleConfig,
        protected readonly OrderEligibility $orderEligibility
    ) {
        parent::__construct($context);
    }

    /**
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $searchKey = $this->getRequest()->getParam('searchKey');
        $limit = (int)$this->getRequest()->getParam('limit', 20);

        $enabledStoreIds = $this->moduleConfig->getEnabledStoreIds();

        if (empty($enabledStoreIds)) {
            return $this->resultJsonFactory->create()->setData([
                'options' => [],
                'total' => 0,
            ]);
        }

        $this->searchCriteriaBuilder->addFilter('increment_id', '%' . $searchKey . '%', 'like');
        $this->searchCriteriaBuilder->addFilter('store_id', $enabledStoreIds, 'in');
        // Fetch more than needed since we post-filter for eligibility
        $this->searchCriteriaBuilder->setPageSize($limit * 3);

        $searchResult = $this->orderRepository->getList(
            $this->searchCriteriaBuilder->create()
        );

        $options = [];
        foreach ($searchResult->getItems() as $order) {
            if (!$this->orderEligibility->isOrderEligible($order)) {
                continue;
            }

            $options[] = $this->formatOrderOption($order);

            if (count($options) >= $limit) {
                break;
            }
        }

        return $this->resultJsonFactory->create()->setData([
            'options' => $options,
            'total' => count($options),
        ]);
    }
}
