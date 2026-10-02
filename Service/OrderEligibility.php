<?php

declare(strict_types=1);

namespace MageOS\RMA\Service;

use IntlDateFormatter;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Sql\Expression;
use Magento\Sales\Model\Order\Item as OrderItem;
use Magento\Sales\Model\ResourceModel\Order\Collection;
use Magento\Store\Model\ScopeInterface;
use MageOS\RMA\Helper\ModuleConfig;
use MageOS\RMA\Model\ResourceModel\Item\CollectionFactory as RmaItemCollectionFactory;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Exception\NoSuchEntityException;

class OrderEligibility
{
    const EXCLUDED_PRODUCT_TYPES = ['virtual', 'downloadable'];
    const TABLE_ORDER_ITEM = 'sales_order_item';
    const TABLE_SHIPMENT = 'sales_shipment';
    const TABLE_SHIPMENT_ITEM = 'sales_shipment_item';
    const KEY_QTY = 'qty';
    const KEY_QTY_SHIPPED = 'qty_shipped';
    const KEY_SHIPPED_AT = 'shipped_at';
    const DATE_FORMAT = 'Y-m-d H:i:s';

    /**
     * @param ModuleConfig $moduleConfig
     * @param RmaItemCollectionFactory $rmaItemCollectionFactory
     * @param OrderCollectionFactory $orderCollectionFactory
     * @param OrderRepositoryInterface $orderRepository
     * @param TimezoneInterface $timezone
     * @param StoreManagerInterface $storeManager
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        protected readonly ModuleConfig $moduleConfig,
        protected readonly RmaItemCollectionFactory $rmaItemCollectionFactory,
        protected readonly OrderCollectionFactory $orderCollectionFactory,
        protected readonly OrderRepositoryInterface $orderRepository,
        protected readonly TimezoneInterface $timezone,
        protected readonly StoreManagerInterface $storeManager,
        protected readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * @param OrderInterface $order
     * @return bool
     */
    public function isOrderEligible(OrderInterface $order): bool
    {
        $storeId = (int)$order->getStoreId();

        if (!$this->moduleConfig->isEnabled($storeId)) {
            return false;
        }

        $allowedStatuses = $this->moduleConfig->getAllowedOrderStatuses($storeId);
        if (!in_array($order->getStatus(), $allowedStatuses, true)) {
            return false;
        }

        return in_array(true, array_column($this->getEligibleItems($order), 'is_eligible'), true);
    }

    /**
     * @param OrderInterface $order
     * @return array
     */
    public function getEligibleItems(OrderInterface $order): array
    {
        $orderId = (int)$order->getEntityId();
        $storeId = (int)$order->getStoreId();
        $alreadyRequested = $this->getAlreadyRequestedQty($orderId);
        $returnPeriod = $this->moduleConfig->getReturnPeriod($storeId);
        $expiredShipments = $returnPeriod > 0
            ? $this->getExpiredShipments($orderId, $this->getCutoffDate($returnPeriod))
            : [];

        $items = [];
        foreach ($order->getItems() as $orderItem) {
            if ($orderItem->getParentItemId()) {
                continue;
            }

            if (in_array($orderItem->getProductType(), self::EXCLUDED_PRODUCT_TYPES, true)) {
                continue;
            }

            $orderItemId = (int)$orderItem->getItemId();
            $qtyOrdered = (int)$orderItem->getQtyOrdered();
            $qtyCanceled = (int)$orderItem->getQtyCanceled();
            $qtyAlreadyRequested = $alreadyRequested[$orderItemId] ?? 0;
            $qtyReturnable = $qtyOrdered - $qtyCanceled - $qtyAlreadyRequested;

            if ($qtyReturnable <= 0) {
                continue;
            }

            $shipment = $this->getItemExpiredShipment($orderItem, $expiredShipments);
            $qtyShipped = $shipment[self::KEY_QTY_SHIPPED];
            $qtyNotShipped = max(
                0,
                $qtyOrdered - $qtyCanceled - (int)$orderItem->getQtyRefunded() - $qtyShipped
            );
            $qtyWithinPeriod = $qtyNotShipped + max(0, $qtyShipped - $shipment[self::KEY_QTY]);
            $qtyAvailable = max(0, min($qtyReturnable, $qtyWithinPeriod));
            $disabledReason = '';

            if ($qtyAvailable === 0) {
                if ($shipment[self::KEY_SHIPPED_AT] === null) {
                    continue;
                }

                $disabledReason = (string)__(
                    'Return period expired on %1',
                    $this->formatExpiryDate($shipment[self::KEY_SHIPPED_AT], $returnPeriod, $storeId)
                );
            }

            $items[] = [
                'order_item_id' => $orderItemId,
                'name' => $orderItem->getName(),
                'sku' => $orderItem->getSku(),
                'qty_ordered' => $qtyOrdered,
                'qty_already_requested' => $qtyAlreadyRequested,
                'qty_available' => $qtyAvailable,
                'is_eligible' => $qtyAvailable > 0,
                'disabled_reason' => $disabledReason,
            ];
        }

        return $items;
    }

    /**
     * @param int $customerId
     * @param int $storeId
     * @return Collection
     * @throws NoSuchEntityException
     */
    public function getCustomerEligibleOrders(int $customerId, int $storeId): Collection
    {
        $allowedStatuses = $this->moduleConfig->getAllowedOrderStatuses($storeId);
        $returnPeriod = $this->moduleConfig->getReturnPeriod($storeId);

        $storeIds = $this->getStoreIdsForWebsite($storeId);

        $collection = $this->orderCollectionFactory->create();
        $collection->addFieldToFilter('customer_id', $customerId);
        $collection->addFieldToFilter('store_id', ['in' => $storeIds]);

        if (!empty($allowedStatuses)) {
            $collection->addFieldToFilter('status', ['in' => $allowedStatuses]);
        }

        if ($returnPeriod > 0) {
            $connection = $collection->getConnection();
            $unshippedItems = $connection->select()
                ->from(['soi' => $collection->getTable(self::TABLE_ORDER_ITEM)], [new Expression('1')])
                ->where('soi.order_id = main_table.entity_id')
                ->where('soi.parent_item_id IS NULL')
                ->where('soi.product_type NOT IN (?)', self::EXCLUDED_PRODUCT_TYPES)
                ->where('soi.qty_ordered - soi.qty_canceled - soi.qty_refunded > soi.qty_shipped');
            $recentShipments = $connection->select()
                ->from(['ss' => $collection->getTable(self::TABLE_SHIPMENT)], [new Expression('1')])
                ->where('ss.order_id = main_table.entity_id')
                ->where('ss.created_at >= ?', $this->getCutoffDate($returnPeriod));
            $collection->getSelect()->where(
                sprintf('EXISTS (%s) OR EXISTS (%s)', $unshippedItems, $recentShipments)
            );
        }
        $collection->setOrder('created_at', 'desc');

        return $collection;
    }

    /**
     * @param int $returnPeriod
     * @return string
     */
    protected function getCutoffDate(int $returnPeriod): string
    {
        return gmdate(self::DATE_FORMAT, strtotime("-{$returnPeriod} days"));
    }

    /**
     * @param int $orderId
     * @param string $cutoffDate
     * @return array
     */
    protected function getExpiredShipments(int $orderId, string $cutoffDate): array
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from(
                ['ssi' => $this->resourceConnection->getTableName(self::TABLE_SHIPMENT_ITEM)],
                [
                    'order_item_id',
                    self::KEY_QTY => new Expression('SUM(ssi.qty)'),
                    self::KEY_SHIPPED_AT => new Expression('MAX(ss.created_at)'),
                ]
            )
            ->join(
                ['ss' => $this->resourceConnection->getTableName(self::TABLE_SHIPMENT)],
                'ss.entity_id = ssi.parent_id',
                []
            )
            ->where('ss.order_id = ?', $orderId)
            ->where('ss.created_at < ?', $cutoffDate)
            ->group('ssi.order_item_id');

        $result = [];
        foreach ($connection->fetchAll($select) as $row) {
            $result[(int)$row['order_item_id']] = [
                self::KEY_QTY => (float)$row[self::KEY_QTY],
                self::KEY_SHIPPED_AT => $row[self::KEY_SHIPPED_AT],
            ];
        }

        return $result;
    }

    /**
     * @param OrderItemInterface $orderItem
     * @param array $expiredShipments
     * @return array
     */
    protected function getItemExpiredShipment(OrderItemInterface $orderItem, array $expiredShipments): array
    {
        $result = [self::KEY_QTY_SHIPPED => 0, self::KEY_QTY => 0, self::KEY_SHIPPED_AT => null];

        $children = $orderItem instanceof OrderItem && $orderItem->isShipSeparately()
            ? $orderItem->getChildrenItems()
            : [];

        if (!$children) {
            $result[self::KEY_QTY_SHIPPED] = (int)$orderItem->getQtyShipped();
            $shipment = $expiredShipments[(int)$orderItem->getItemId()] ?? null;
            if ($shipment) {
                $result[self::KEY_QTY] = (int)$shipment[self::KEY_QTY];
                $result[self::KEY_SHIPPED_AT] = $shipment[self::KEY_SHIPPED_AT];
            }

            return $result;
        }

        $parentQtyOrdered = (float)$orderItem->getQtyOrdered();
        $parentShippedQty = null;
        $parentExpiredQty = null;
        foreach ($children as $child) {
            $ratio = (float)$child->getQtyOrdered() / $parentQtyOrdered;
            if ($ratio <= 0) {
                continue;
            }

            $shipment = $expiredShipments[(int)$child->getItemId()] ?? null;
            $childShippedQty = (int)floor((float)$child->getQtyShipped() / $ratio);
            $childExpiredQty = (int)floor(($shipment[self::KEY_QTY] ?? 0) / $ratio);
            $parentShippedQty = min($parentShippedQty ?? $childShippedQty, $childShippedQty);
            $parentExpiredQty = min($parentExpiredQty ?? $childExpiredQty, $childExpiredQty);

            if ($shipment && ($result[self::KEY_SHIPPED_AT] === null
                || $shipment[self::KEY_SHIPPED_AT] > $result[self::KEY_SHIPPED_AT])
            ) {
                $result[self::KEY_SHIPPED_AT] = $shipment[self::KEY_SHIPPED_AT];
            }
        }
        $result[self::KEY_QTY_SHIPPED] = $parentShippedQty ?? 0;
        $result[self::KEY_QTY] = $parentExpiredQty ?? 0;

        return $result;
    }

    /**
     * @param string $shippedAt
     * @param int $returnPeriod
     * @param int $storeId
     * @return string
     */
    protected function formatExpiryDate(string $shippedAt, int $returnPeriod, int $storeId): string
    {
        return $this->timezone->formatDateTime(
            gmdate(self::DATE_FORMAT, strtotime("{$shippedAt} UTC +{$returnPeriod} days")),
            IntlDateFormatter::MEDIUM,
            IntlDateFormatter::NONE,
            null,
            $this->timezone->getConfigTimezone(ScopeInterface::SCOPE_STORE, $storeId)
        );
    }

    /**
     * @param int $storeId
     * @return int[]
     * @throws NoSuchEntityException
     */
    protected function getStoreIdsForWebsite(int $storeId): array
    {
        $store = $this->storeManager->getStore($storeId);
        $websiteId = (int)$store->getWebsiteId();
        $storeIds = array_map(
            fn($s) => (int)$s->getId(),
            array_filter(
                $this->storeManager->getStores(),
                fn($s) => (int)$s->getWebsiteId() === $websiteId
            )
        );

        return $storeIds ?: [$storeId];
    }

    /**
     * @param int $orderId
     * @return array
     */
    public function getAlreadyRequestedQty(int $orderId): array
    {
        $collection = $this->rmaItemCollectionFactory->create();

        $collection->getSelect()->join(
            ['rma' => $collection->getTable('rma_entity')],
            'main_table.rma_id = rma.entity_id',
            []
        )->where('rma.order_id = ?', $orderId);

        $result = [];
        foreach ($collection as $item) {
            $orderItemId = (int)$item->getData('order_item_id');
            $qty = (int)$item->getData('qty_requested');
            $result[$orderItemId] = ($result[$orderItemId] ?? 0) + $qty;
        }

        return $result;
    }
}
