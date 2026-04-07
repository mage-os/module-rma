<?php

declare(strict_types=1);

namespace MageOS\RMA\Controller\Adminhtml\Rma;

use Magento\Framework\App\Action\HttpPostActionInterface;
use MageOS\RMA\Api\Data\RMAInterface;
use MageOS\RMA\Api\Data\RMAInterfaceFactory;
use MageOS\RMA\Api\RMARepositoryInterface;
use MageOS\RMA\Controller\Adminhtml\Rma as BaseController;
use MageOS\RMA\Service\RmaSubmitService;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Exception;

class Save extends BaseController implements HttpPostActionInterface
{
    /**
     * @param Context $context
     * @param RMARepositoryInterface $rmaRepository
     * @param RMAInterfaceFactory $rmaFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param DataPersistorInterface $dataPersistor
     * @param RmaSubmitService $rmaSubmitService
     */
    public function __construct(
        Context $context,
        protected readonly RMARepositoryInterface $rmaRepository,
        protected readonly RMAInterfaceFactory $rmaFactory,
        protected readonly OrderRepositoryInterface $orderRepository,
        protected readonly DataPersistorInterface $dataPersistor,
        protected readonly RmaSubmitService $rmaSubmitService
    ) {
        parent::__construct($context);
    }

    /**
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $data = $this->getRequest()->getPostValue();

        if (!$data) {
            return $resultRedirect->setPath('*/*/');
        }

        $id = (int)($data['entity_id'] ?? 0);

        return $id
            ? $this->processExistingRma($resultRedirect, $data, $id)
            : $this->processNewRma($resultRedirect, $data);
    }

    /**
     * @param Redirect $resultRedirect
     * @param array $data
     * @param int $id
     * @return ResultInterface
     */
    protected function processExistingRma(Redirect $resultRedirect, array $data, int $id): ResultInterface
    {
        try {
            $model = $this->rmaRepository->get($id);
        } catch (LocalizedException) {
            $this->messageManager->addErrorMessage(__('This RMA request no longer exists.'));

            return $resultRedirect->setPath('*/*/');
        }

        if (isset($data['status_id'])) {
            $model->setStatusId((int)$data['status_id']);
        }

        return $this->saveAndRedirect($resultRedirect, $model, $data, $id);
    }

    /**
     * @param Redirect $resultRedirect
     * @param array $data
     * @return ResultInterface
     */
    protected function processNewRma(Redirect $resultRedirect, array $data): ResultInterface
    {
        $orderId = (int)($data['order_id'] ?? 0);

        if (!$orderId) {
            $this->messageManager->addErrorMessage(__('Please select an order.'));
            $this->dataPersistor->set('rma_entity', $data);

            return $resultRedirect->setPath('*/*/new');
        }

        $statusId = (int)($data['status_id'] ?? 0);
        $reasonId = (int)($data['reason_id'] ?? 0);
        $resolutionTypeId = (int)($data['resolution_type_id'] ?? 0);

        if (!$statusId || !$reasonId || !$resolutionTypeId) {
            $this->messageManager->addErrorMessage(__('Invalid request.'));
            $this->dataPersistor->set('rma_entity', $data);

            return $resultRedirect->setPath('*/*/new');
        }

        $selectedItems = $this->rmaSubmitService->getSelectedItems($data['items'] ?? []);

        if (empty($selectedItems)) {
            $this->messageManager->addErrorMessage(__('Please select at least one item to return.'));
            $this->dataPersistor->set('rma_entity', $data);

            return $resultRedirect->setPath('*/*/new');
        }

        try {
            $order = $this->orderRepository->get($orderId);
        } catch (LocalizedException) {
            $this->messageManager->addErrorMessage(__('The selected order does not exist.'));
            $this->dataPersistor->set('rma_entity', $data);

            return $resultRedirect->setPath('*/*/new');
        }

        $model = $this->buildNewRma($data, $order);

        return $this->saveAndRedirect($resultRedirect, $model, $data, 0, $selectedItems, $order);
    }

    /**
     * @param array $data
     * @param OrderInterface $order
     * @return RMAInterface
     */
    protected function buildNewRma(array $data, OrderInterface $order): RMAInterface
    {
        $model = $this->rmaFactory->create();
        $model->setOrderId((int)$order->getEntityId());
        $model->setCustomerId($order->getCustomerId() ? (int)$order->getCustomerId() : null);
        $model->setStoreId((int)$order->getStoreId());
        $model->setCustomerEmail((string)$order->getCustomerEmail());
        $model->setCustomerName((string)($order->getCustomerName() ?: __('Guest')));
        $model->setStatusId((int)($data['status_id'] ?? 0));
        $model->setReasonId((int)($data['reason_id'] ?? 0));
        $model->setResolutionTypeId((int)($data['resolution_type_id'] ?? 0));

        return $model;
    }

    /**
     * @param Redirect $resultRedirect
     * @param RMAInterface $model
     * @param array $data
     * @param int $id
     * @param array $selectedItems
     * @param OrderInterface|null $order
     * @return ResultInterface
     */
    protected function saveAndRedirect(
        Redirect $resultRedirect,
        RMAInterface $model,
        array $data,
        int $id,
        array $selectedItems = [],
        ?OrderInterface $order = null
    ): ResultInterface {
        try {
            $this->rmaRepository->save($model);

            if (!empty($selectedItems) && $order !== null) {
                $this->rmaSubmitService->saveItems((int)$model->getEntityId(), $selectedItems, $order);
            }

            $this->messageManager->addSuccessMessage(__('You saved the RMA request.'));
            $this->dataPersistor->clear('rma_entity');

            if ($this->getRequest()->getParam('back') === 'continue') {
                return $resultRedirect->setPath('*/*/edit', ['entity_id' => $model->getEntityId()]);
            }

            return $resultRedirect->setPath('*/*/');
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (Exception $e) {
            $this->messageManager->addExceptionMessage($e, __('Something went wrong while saving the RMA request.'));
        }

        $this->dataPersistor->set('rma_entity', $data);

        return $resultRedirect->setPath($id ? '*/*/edit' : '*/*/new', $id ? ['entity_id' => $id] : []);
    }
}
