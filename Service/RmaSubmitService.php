<?php

declare(strict_types=1);

namespace MageOS\RMA\Service;

use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use MageOS\RMA\Api\Data\RMAInterface;
use MageOS\RMA\Api\Data\RMAInterfaceFactory;
use MageOS\RMA\Api\Data\StatusInterface;
use MageOS\RMA\Api\ItemRepositoryInterface;
use MageOS\RMA\Api\RMARepositoryInterface;
use MageOS\RMA\Helper\ModuleConfig;
use MageOS\RMA\Model\ItemFactory;
use MageOS\RMA\Model\RMA\StatusCodes;
use MageOS\RMA\Model\ResourceModel\Status\CollectionFactory as StatusCollectionFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;
use Throwable;

class RmaSubmitService
{
    /**
     * @param RMARepositoryInterface $rmaRepository
     * @param RMAInterfaceFactory $rmaFactory
     * @param ItemFactory $itemFactory
     * @param ItemRepositoryInterface $itemRepository
     * @param StatusCollectionFactory $statusCollectionFactory
     * @param ModuleConfig $moduleConfig
     * @param AttachmentService $attachmentService
     * @param OrderEligibility $orderEligibility
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        protected readonly RMARepositoryInterface $rmaRepository,
        protected readonly RMAInterfaceFactory $rmaFactory,
        protected readonly ItemFactory $itemFactory,
        protected readonly ItemRepositoryInterface $itemRepository,
        protected readonly StatusCollectionFactory $statusCollectionFactory,
        protected readonly ModuleConfig $moduleConfig,
        protected readonly AttachmentService $attachmentService,
        protected readonly OrderEligibility $orderEligibility,
        protected readonly ResourceConnection $resourceConnection,
        protected readonly EventManagerInterface $eventManager,
    ) {
    }

    /**
     * @param array $itemsData
     * @return array
     */
    public function getSelectedItems(array $itemsData): array
    {
        $selected = [];

        foreach ($itemsData as $orderItemId => $itemData) {
            if (empty($itemData['selected'])) {
                continue;
            }

            $qtyRequested = (int)($itemData['qty_requested'] ?? 0);
            if ($qtyRequested <= 0) {
                continue;
            }

            $selected[(int)$orderItemId] = [
                'qty_requested' => $qtyRequested,
                'condition_id' => !empty($itemData['condition_id']) ? (int)$itemData['condition_id'] : null,
            ];
        }

        return $selected;
    }

    /**
     * @param OrderInterface $order
     * @param int|null $customerId
     * @param string $customerEmail
     * @param string $customerName
     * @param int $reasonId
     * @param int $resolutionTypeId
     * @param array $selectedItems
     * @param string $attachmentsJson
     * @return RMAInterface
     * @throws CouldNotSaveException
     * @throws LocalizedException
     * @throws Throwable
     */
    public function createRma(
        OrderInterface $order,
        ?int $customerId,
        string $customerEmail,
        string $customerName,
        int $reasonId,
        int $resolutionTypeId,
        array $selectedItems,
        string $attachmentsJson = ''
    ): RMAInterface {
        if (!$reasonId || !$resolutionTypeId) {
            throw new LocalizedException(__('Invalid request.'));
        }

        $storeId = (int)$order->getStoreId();

        $statusCode = $this->moduleConfig->isAutoApproveEnabled($storeId)
            ? StatusCodes::APPROVED
            : StatusCodes::NEW_REQUEST;
        $statusId = $this->getStatusIdByCode($statusCode);

        if (!$statusId) {
            throw new LocalizedException(__('Could not determine the initial RMA status.'));
        }

        $rma = $this->rmaFactory->create();
        $rma->setOrderId((int)$order->getEntityId());
        $rma->setCustomerId($customerId);
        $rma->setStoreId($storeId);
        $rma->setCustomerEmail($customerEmail);
        $rma->setCustomerName($customerName);
        $rma->setStatusId($statusId);
        $rma->setReasonId($reasonId);
        $rma->setResolutionTypeId($resolutionTypeId);

        $connection = $this->resourceConnection->getConnection();
        $connection->beginTransaction();

        try {
            $this->rmaRepository->save($rma);
            $rmaId = (int)$rma->getEntityId();
            $this->saveItems($rmaId, $selectedItems, $order);
            $this->attachmentService->saveFromJson($attachmentsJson, $rmaId);
            $connection->commit();
            $this->eventManager->dispatch('rma_commit_after', ['rma' => $rma]);
        } catch (Throwable $e) {
            $connection->rollBack();
            throw $e;
        }

        return $rma;
    }

    /**
     * @param int $rmaId
     * @param array $selectedItems
     * @param OrderInterface $order
     * @return void
     * @throws CouldNotSaveException
     * @throws LocalizedException
     */
    public function saveItems(int $rmaId, array $selectedItems, OrderInterface $order): void
    {
        $eligibleItems = [];
        foreach ($this->orderEligibility->getEligibleItems($order) as $eligibleItem) {
            $eligibleItems[$eligibleItem['order_item_id']] = $eligibleItem['qty_available'];
        }

        foreach ($selectedItems as $orderItemId => $itemData) {
            if (!isset($eligibleItems[$orderItemId])) {
                throw new LocalizedException(
                    __('Item ID %1 is not eligible for return on order #%2.', $orderItemId, $order->getIncrementId())
                );
            }

            if ($itemData['qty_requested'] > $eligibleItems[$orderItemId]) {
                throw new LocalizedException(
                    __('Requested qty for item %1 exceeds available qty (%2).', $orderItemId, $eligibleItems[$orderItemId])
                );
            }

            $item = $this->itemFactory->create();
            $item->setRmaId($rmaId);
            $item->setOrderItemId($orderItemId);
            $item->setQtyRequested($itemData['qty_requested']);
            $item->setConditionId($itemData['condition_id']);
            $this->itemRepository->save($item);
        }
    }

    /**
     * @param string $code
     * @return int|null
     */
    protected function getStatusIdByCode(string $code): ?int
    {
        $collection = $this->statusCollectionFactory->create();
        $collection->addFieldToFilter(StatusInterface::CODE, $code);
        $collection->setPageSize(1);

        $status = $collection->getFirstItem();
        return $status->getEntityId() ? (int)$status->getEntityId() : null;
    }
}
