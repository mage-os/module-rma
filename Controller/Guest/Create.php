<?php

declare(strict_types=1);

namespace MageOS\RMA\Controller\Guest;

use MageOS\RMA\Service\GuestOrderService;
use MageOS\RMA\Service\OrderEligibility;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Framework\View\Result\PageFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Stdlib\Cookie\CookieSizeLimitReachedException;
use Magento\Framework\Stdlib\Cookie\FailureToSendException;
use Magento\Framework\Exception\LocalizedException;

class Create implements HttpGetActionInterface
{
    /**
     * @param PageFactory $resultPageFactory
     * @param RedirectFactory $resultRedirectFactory
     * @param GuestOrderService $guestOrderService
     * @param OrderEligibility $orderEligibility
     * @param RequestInterface $request
     * @param MessageManagerInterface $messageManager
     */
    public function __construct(
        protected readonly PageFactory $resultPageFactory,
        protected readonly RedirectFactory $resultRedirectFactory,
        protected readonly GuestOrderService $guestOrderService,
        protected readonly OrderEligibility $orderEligibility,
        protected readonly RequestInterface $request,
        protected readonly MessageManagerInterface $messageManager
    ) {
    }

    /**
     * @return ResultInterface
     * @throws InputException
     * @throws CookieSizeLimitReachedException
     * @throws FailureToSendException|LocalizedException
     */
    public function execute(): ResultInterface
    {
        $orderResult = $this->guestOrderService->loadValidOrder($this->request);

        if ($orderResult instanceof ResultInterface) {
            return $orderResult;
        }

        /** @var OrderInterface $order */
        $order = $orderResult;

        if (!$this->orderEligibility->isOrderEligible($order)) {
            $this->messageManager->addErrorMessage(__('This order is not eligible for a return.'));
            return $this->resultRedirectFactory->create()->setPath('sales/guest/form');
        }

        $this->request->setParam('current_order', $order);

        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->set(__('Withdraw from purchase'));

        return $resultPage;
    }
}
