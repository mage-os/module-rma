<?php

declare(strict_types=1);

namespace MageOS\RMA\Block\Customer\Rma;

use MageOS\RMA\Block\Trait\AttachmentConfigTrait;
use MageOS\RMA\Helper\ModuleConfig;
use MageOS\RMA\Model\Config\Source\ItemCondition as ItemConditionSource;
use MageOS\RMA\Model\Config\Source\Reason as ReasonSource;
use MageOS\RMA\Model\Config\Source\ResolutionType as ResolutionTypeSource;
use MageOS\RMA\Service\OrderEligibility;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Exception\NoSuchEntityException;

class Create extends Template
{
    use AttachmentConfigTrait;

    /**
     * @param Context $context
     * @param CustomerSession $customerSession
     * @param OrderEligibility $orderEligibility
     * @param ReasonSource $reasonSource
     * @param ResolutionTypeSource $resolutionTypeSource
     * @param ItemConditionSource $itemConditionSource
     * @param StoreManagerInterface $storeManager
     * @param Json $json
     * @param ModuleConfig $moduleConfig
     * @param array $data
     */
    public function __construct(
        Context $context,
        protected readonly CustomerSession $customerSession,
        protected readonly OrderEligibility $orderEligibility,
        protected readonly ReasonSource $reasonSource,
        protected readonly ResolutionTypeSource $resolutionTypeSource,
        protected readonly ItemConditionSource $itemConditionSource,
        protected readonly StoreManagerInterface $storeManager,
        protected readonly Json $json,
        protected readonly ModuleConfig $moduleConfig,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return array
     * @throws NoSuchEntityException
     */
    public function getEligibleOrders(): array
    {
        $customerId = (int)$this->customerSession->getCustomerId();
        $storeId = (int)$this->storeManager->getStore()->getId();

        if (!$customerId) {
            return [];
        }

        $orders = [];
        $collection = $this->orderEligibility->getCustomerEligibleOrders($customerId, $storeId);

        foreach ($collection as $order) {
            if ($this->orderEligibility->isOrderEligible($order)) {
                $orders[] = [
                    'value' => (int)$order->getEntityId(),
                    'label' => sprintf(
                        '#%s — %s',
                        $order->getIncrementId(),
                        $order->getCreatedAt()
                    ),
                ];
            }
        }

        return $orders;
    }

    /**
     * @return int|null
     */
    public function getPreselectedOrderId(): ?int
    {
        $orderId = $this->getRequest()->getParam('order_id');
        return $orderId ? (int)$orderId : null;
    }

    /**
     * @return array
     * @throws NoSuchEntityException
     */
    public function getReasons(): array
    {
        return $this->reasonSource->toOptionArray();
    }

    /**
     * @return array
     * @throws NoSuchEntityException
     */
    public function getResolutionTypes(): array
    {
        return $this->resolutionTypeSource->toOptionArray();
    }

    /**
     * @return array
     * @throws NoSuchEntityException
     */
    public function getConditions(): array
    {
        return $this->itemConditionSource->toOptionArray();
    }

    /**
     * @return string
     * @throws NoSuchEntityException
     */
    public function getConfigJson(): string
    {
        return $this->json->serialize([
            'itemsAjaxUrl' => $this->getItemsAjaxUrl(),
            'conditions' => $this->getConditions(),
            'preloadOrderId' => $this->getPreselectedOrderId(),
            'uploadUrl' => $this->getUploadUrl(),
            'allowedExtensions' => $this->getAllowedExtensions(),
            'maxFileSize' => $this->getMaxFileSize(),
            'maxFiles' => $this->getMaxFiles(),
        ]);
    }

    /**
     * @return string
     */
    public function getItemsAjaxUrl(): string
    {
        return $this->getUrl('rma/customer/orderItems');
    }

    /**
     * @return string
     */
    public function getPostUrl(): string
    {
        return $this->getUrl('rma/customer/save');
    }

    /**
     * @return string
     */
    public function getUploadUrl(): string
    {
        return $this->getUrl('rma/customer_attachment/upload');
    }

    /**
     * @return string
     */
    public function getBackUrl(): string
    {
        return $this->getUrl('rma/customer/history');
    }
}
