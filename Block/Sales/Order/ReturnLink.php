<?php

declare(strict_types=1);

namespace MageOS\RMA\Block\Sales\Order;

use MageOS\RMA\Service\OrderEligibility;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Html\Link;
use Magento\Framework\View\Element\Template\Context;
use Magento\Customer\Model\Context as CustomerContext;
use Magento\Sales\Api\Data\OrderInterface;

class ReturnLink extends Link
{
    /**
     * @param Context $context
     * @param OrderEligibility $orderEligibility
     * @param Registry $registry
     * @param HttpContext $httpContext
     * @param array $data
     */
    public function __construct(
        Context $context,
        protected readonly OrderEligibility $orderEligibility,
        protected readonly Registry $registry,
        protected readonly HttpContext $httpContext,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return OrderInterface|null
     */
    protected function getCurrentOrder(): ?OrderInterface
    {
        return $this->registry->registry('current_order');
    }

    /**
     * @return string
     */
    public function getHref(): string
    {
        $order = $this->getCurrentOrder();
        if (!$order) {
            return '';
        }

        $isLoggedIn = $this->httpContext->getValue(CustomerContext::CONTEXT_AUTH);

        if ($isLoggedIn) {
            return $this->getUrl('rma/customer/create', ['order_id' => $order->getEntityId()]);
        }

        return $this->getUrl('rma/guest/create');
    }

    /**
     * @return string
     */
    protected function _toHtml(): string
    {
        $order = $this->getCurrentOrder();

        if (!$order || !$this->orderEligibility->isOrderEligible($order)) {
            return '';
        }


        return sprintf(
            '<a href="%s" class="%s" title="%s">%s</a>',
            $this->escapeUrl($this->getHref()),
            $this->getData('classes'),
            $this->escapeHtmlAttr(__('Cancel the order or return the items')),
            $this->escapeHtml($this->getData('label') ?: __('Withdraw from purchase'))
        );
    }
}
