<?php

declare(strict_types=1);

namespace MageOS\RMA\Controller\Guest;

use MageOS\RMA\Service\GuestOrderService;
use MageOS\RMA\Service\OrderEligibility;
use MageOS\RMA\Service\RmaSubmitService;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Message\ManagerInterface as MessageManagerInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\Cookie\CookieSizeLimitReachedException;
use Magento\Framework\Stdlib\Cookie\FailureToSendException;
use Exception;

class Save implements HttpPostActionInterface
{
    /**
     * @param RedirectFactory $resultRedirectFactory
     * @param RequestInterface $request
     * @param MessageManagerInterface $messageManager
     * @param GuestOrderService $guestOrderService
     * @param OrderEligibility $orderEligibility
     * @param RmaSubmitService $rmaSubmitService
     */
    public function __construct(
        protected readonly RedirectFactory $resultRedirectFactory,
        protected readonly RequestInterface $request,
        protected readonly MessageManagerInterface $messageManager,
        protected readonly GuestOrderService $guestOrderService,
        protected readonly OrderEligibility $orderEligibility,
        protected readonly RmaSubmitService $rmaSubmitService
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
        $resultRedirect = $this->resultRedirectFactory->create();

        $orderResult = $this->guestOrderService->loadValidOrder($this->request);

        if ($orderResult instanceof ResultInterface) {
            return $orderResult;
        }

        $order = $orderResult;

        if (!$this->orderEligibility->isOrderEligible($order)) {
            $this->messageManager->addErrorMessage(__('This order is not eligible for a return.'));
            return $resultRedirect->setPath('sales/guest/form');
        }

        $data = $this->request->getPostValue();

        $itemsData = $data['items'] ?? [];
        $selectedItems = $this->rmaSubmitService->getSelectedItems($itemsData);

        if (empty($selectedItems)) {
            $this->messageManager->addErrorMessage(__('Please select at least one item to return.'));
            return $resultRedirect->setPath('rma/guest/create');
        }

        try {
            $attachmentsJson = (string)($data['attachments'] ?? '');
            $customerId = (int)$order->getCustomerId() ?: null;
            $rma = $this->rmaSubmitService->createRma(
                $order,
                $customerId,
                (string)$order->getCustomerEmail(),
                (string)($order->getBillingAddress()?->getName() ?: __('Guest')),
                (int)($data['reason_id'] ?? 0),
                (int)($data['resolution_type_id'] ?? 0),
                $selectedItems,
                $attachmentsJson
            );

            $this->messageManager->addSuccessMessage(
                __('Your return request #%1 has been submitted successfully.', $rma->getIncrementId())
            );
            return $resultRedirect->setPath('sales/guest/view');
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (Exception $e) {
            $this->messageManager->addExceptionMessage($e, __('Something went wrong while submitting your return request.'));
        }

        return $resultRedirect->setPath('rma/guest/create');
    }
}
