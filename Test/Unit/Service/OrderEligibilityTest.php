<?php

declare(strict_types=1);

namespace MageOS\RMA\Test\Unit\Service;

use IntlDateFormatter;
use MageOS\RMA\Helper\ModuleConfig;
use MageOS\RMA\Model\ResourceModel\Item\CollectionFactory as RmaItemCollectionFactory;
use MageOS\RMA\Service\OrderEligibility;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderItemInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order\Item as OrderItem;
use Magento\Sales\Model\ResourceModel\Order\Collection;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class OrderEligibilityTest extends TestCase
{
    protected ModuleConfig&MockObject $moduleConfig;
    protected RmaItemCollectionFactory&MockObject $rmaItemCollectionFactory;
    protected OrderCollectionFactory&MockObject $orderCollectionFactory;
    protected OrderRepositoryInterface&MockObject $orderRepository;
    protected TimezoneInterface&MockObject $timezone;
    protected StoreManagerInterface&MockObject $storeManager;
    protected ResourceConnection&MockObject $resourceConnection;

    protected function setUp(): void
    {
        $this->moduleConfig = $this->createMock(ModuleConfig::class);
        $this->rmaItemCollectionFactory = $this->createMock(RmaItemCollectionFactory::class);
        $this->orderCollectionFactory = $this->createMock(OrderCollectionFactory::class);
        $this->orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $this->timezone = $this->createMock(TimezoneInterface::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->resourceConnection = $this->createMock(ResourceConnection::class);
    }

    protected function createService(array $stubbedMethods = []): OrderEligibility
    {
        $args = [
            $this->moduleConfig,
            $this->rmaItemCollectionFactory,
            $this->orderCollectionFactory,
            $this->orderRepository,
            $this->timezone,
            $this->storeManager,
            $this->resourceConnection,
        ];

        if (empty($stubbedMethods)) {
            return new OrderEligibility(...$args);
        }

        return $this->getMockBuilder(OrderEligibility::class)
            ->setConstructorArgs($args)
            ->onlyMethods($stubbedMethods)
            ->getMock();
    }

    protected function createOrder(int $storeId = 1, string $status = 'complete', array $items = []): OrderInterface&MockObject
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getStoreId')->willReturn($storeId);
        $order->method('getStatus')->willReturn($status);
        $order->method('getEntityId')->willReturn(100);
        $order->method('getItems')->willReturn($items);

        return $order;
    }

    // -------------------------------------------------------------------------
    // isOrderEligible
    // -------------------------------------------------------------------------

    public function testIsOrderEligibleReturnsFalseWhenModuleDisabled(): void
    {
        $this->moduleConfig->method('isEnabled')->with(1)->willReturn(false);
        $service = $this->createService(['getEligibleItems']);

        $this->assertFalse($service->isOrderEligible($this->createOrder()));
    }

    public function testIsOrderEligibleReturnsFalseWhenStatusNotAllowed(): void
    {
        $this->moduleConfig->method('isEnabled')->willReturn(true);
        $this->moduleConfig->method('getAllowedOrderStatuses')->willReturn(['complete']);

        $order = $this->createOrder(status: 'pending');
        $service = $this->createService(['getEligibleItems']);

        $this->assertFalse($service->isOrderEligible($order));
    }

    public function testIsOrderEligibleReturnsTrueForUnshippedOrderWithReturnPeriod(): void
    {
        $this->moduleConfig->method('isEnabled')->willReturn(true);
        $this->moduleConfig->method('getAllowedOrderStatuses')->willReturn(['processing']);
        $this->moduleConfig->method('getReturnPeriod')->willReturn(30);

        $order = $this->createOrder(status: 'processing', items: [
            $this->createOrderItem(parentItemId: null, productType: 'simple', itemId: 10, qtyOrdered: 2),
        ]);

        /** @var OrderEligibility&MockObject $service */
        $service = $this->createService(['getAlreadyRequestedQty', 'getExpiredShipments']);
        $service->method('getAlreadyRequestedQty')->willReturn([]);
        $service->method('getExpiredShipments')->willReturn([]);

        $this->assertTrue($service->isOrderEligible($order));
    }

    public function testIsOrderEligibleReturnsFalseWhenNoEligibleItems(): void
    {
        $this->moduleConfig->method('isEnabled')->willReturn(true);
        $this->moduleConfig->method('getAllowedOrderStatuses')->willReturn(['complete']);

        $order = $this->createOrder(status: 'complete');

        /** @var OrderEligibility&MockObject $service */
        $service = $this->createService(['getEligibleItems']);
        $service->method('getEligibleItems')->with($order)->willReturn([]);

        $this->assertFalse($service->isOrderEligible($order));
    }

    public function testIsOrderEligibleReturnsFalseWhenAllItemsExpired(): void
    {
        $this->moduleConfig->method('isEnabled')->willReturn(true);
        $this->moduleConfig->method('getAllowedOrderStatuses')->willReturn(['complete']);

        $order = $this->createOrder(status: 'complete');

        /** @var OrderEligibility&MockObject $service */
        $service = $this->createService(['getEligibleItems']);
        $service->method('getEligibleItems')->with($order)->willReturn([
            ['order_item_id' => 1, 'qty_available' => 0, 'is_eligible' => false, 'disabled_reason' => 'Expired'],
            ['order_item_id' => 2, 'qty_available' => 0, 'is_eligible' => false, 'disabled_reason' => 'Expired'],
        ]);

        $this->assertFalse($service->isOrderEligible($order));
    }

    public function testIsOrderEligibleReturnsTrueWhenAtLeastOneItemIsEligible(): void
    {
        $this->moduleConfig->method('isEnabled')->willReturn(true);
        $this->moduleConfig->method('getAllowedOrderStatuses')->willReturn(['complete']);

        $order = $this->createOrder(status: 'complete');

        /** @var OrderEligibility&MockObject $service */
        $service = $this->createService(['getEligibleItems']);
        $service->method('getEligibleItems')->with($order)->willReturn([
            ['order_item_id' => 1, 'qty_available' => 0, 'is_eligible' => false, 'disabled_reason' => 'Expired'],
            ['order_item_id' => 2, 'qty_available' => 1, 'is_eligible' => true, 'disabled_reason' => ''],
        ]);

        $this->assertTrue($service->isOrderEligible($order));
    }

    public function testIsOrderEligibleReturnsTrueWhenAllConditionsMet(): void
    {
        $this->moduleConfig->method('isEnabled')->willReturn(true);
        $this->moduleConfig->method('getAllowedOrderStatuses')->willReturn(['complete']);

        $order = $this->createOrder(status: 'complete');

        /** @var OrderEligibility&MockObject $service */
        $service = $this->createService(['getEligibleItems']);
        $service->method('getEligibleItems')->with($order)->willReturn([
            [
                'order_item_id' => 1,
                'name' => 'Product',
                'sku' => 'SKU-1',
                'qty_ordered' => 2,
                'qty_already_requested' => 0,
                'qty_available' => 2,
                'is_eligible' => true,
                'disabled_reason' => '',
            ],
        ]);

        $this->assertTrue($service->isOrderEligible($order));
    }

    // -------------------------------------------------------------------------
    // getEligibleItems
    // -------------------------------------------------------------------------

    protected function createOrderItem(
        ?int $parentItemId,
        string $productType,
        int $itemId,
        int $qtyOrdered,
        string $name = 'Product',
        string $sku = 'SKU-001',
        int $qtyShipped = 0,
        int $qtyCanceled = 0,
        int $qtyRefunded = 0
    ): OrderItemInterface&MockObject {
        $item = $this->createMock(OrderItemInterface::class);
        $item->method('getParentItemId')->willReturn($parentItemId);
        $item->method('getProductType')->willReturn($productType);
        $item->method('getItemId')->willReturn($itemId);
        $item->method('getQtyOrdered')->willReturn($qtyOrdered);
        $item->method('getName')->willReturn($name);
        $item->method('getSku')->willReturn($sku);
        $item->method('getQtyShipped')->willReturn($qtyShipped);
        $item->method('getQtyCanceled')->willReturn($qtyCanceled);
        $item->method('getQtyRefunded')->willReturn($qtyRefunded);

        return $item;
    }

    protected function createBundleItem(
        int $itemId,
        float $qtyOrdered,
        array $children,
        float $qtyShipped = 0.0
    ): OrderItem&MockObject {
        $item = $this->createMock(OrderItem::class);
        $item->method('getParentItemId')->willReturn(null);
        $item->method('getProductType')->willReturn('bundle');
        $item->method('getItemId')->willReturn($itemId);
        $item->method('getQtyOrdered')->willReturn($qtyOrdered);
        $item->method('getName')->willReturn('Bundle');
        $item->method('getSku')->willReturn('BUNDLE-1');
        $item->method('getQtyShipped')->willReturn($qtyShipped);
        $item->method('isShipSeparately')->willReturn(true);
        $item->method('getChildrenItems')->willReturn($children);

        return $item;
    }

    public function testGetEligibleItemsSkipsChildItems(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getEntityId')->willReturn(1);
        $order->method('getItems')->willReturn([
            $this->createOrderItem(parentItemId: 5, productType: 'simple', itemId: 10, qtyOrdered: 1),
        ]);

        /** @var OrderEligibility&MockObject $service */
        $service = $this->createService(['getAlreadyRequestedQty']);
        $service->method('getAlreadyRequestedQty')->willReturn([]);

        $this->assertSame([], $service->getEligibleItems($order));
    }

    public function testGetEligibleItemsSkipsVirtualProducts(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getEntityId')->willReturn(1);
        $order->method('getItems')->willReturn([
            $this->createOrderItem(parentItemId: null, productType: 'virtual', itemId: 10, qtyOrdered: 1),
        ]);

        /** @var OrderEligibility&MockObject $service */
        $service = $this->createService(['getAlreadyRequestedQty']);
        $service->method('getAlreadyRequestedQty')->willReturn([]);

        $this->assertSame([], $service->getEligibleItems($order));
    }

    public function testGetEligibleItemsSkipsDownloadableProducts(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getEntityId')->willReturn(1);
        $order->method('getItems')->willReturn([
            $this->createOrderItem(parentItemId: null, productType: 'downloadable', itemId: 10, qtyOrdered: 1),
        ]);

        /** @var OrderEligibility&MockObject $service */
        $service = $this->createService(['getAlreadyRequestedQty']);
        $service->method('getAlreadyRequestedQty')->willReturn([]);

        $this->assertSame([], $service->getEligibleItems($order));
    }

    public function testGetEligibleItemsSkipsItemsWithNoAvailableQty(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getEntityId')->willReturn(1);
        $order->method('getItems')->willReturn([
            $this->createOrderItem(parentItemId: null, productType: 'simple', itemId: 10, qtyOrdered: 2),
        ]);

        /** @var OrderEligibility&MockObject $service */
        $service = $this->createService(['getAlreadyRequestedQty']);
        $service->method('getAlreadyRequestedQty')->willReturn([10 => 2]);

        $this->assertSame([], $service->getEligibleItems($order));
    }

    public function testGetEligibleItemsDeductsAlreadyRequestedQty(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getEntityId')->willReturn(1);
        $order->method('getItems')->willReturn([
            $this->createOrderItem(parentItemId: null, productType: 'simple', itemId: 10, qtyOrdered: 3, name: 'Shirt', sku: 'SHIRT-L'),
        ]);

        /** @var OrderEligibility&MockObject $service */
        $service = $this->createService(['getAlreadyRequestedQty']);
        $service->method('getAlreadyRequestedQty')->willReturn([10 => 1]);

        $result = $service->getEligibleItems($order);

        $this->assertCount(1, $result);
        $this->assertSame(1, $result[0]['qty_already_requested']);
        $this->assertSame(2, $result[0]['qty_available']);
        $this->assertTrue($result[0]['is_eligible']);
    }

    public function testGetEligibleItemsReturnsCorrectStructure(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getEntityId')->willReturn(1);
        $order->method('getItems')->willReturn([
            $this->createOrderItem(parentItemId: null, productType: 'simple', itemId: 10, qtyOrdered: 2, name: 'Blue Hat', sku: 'HAT-BL'),
        ]);

        /** @var OrderEligibility&MockObject $service */
        $service = $this->createService(['getAlreadyRequestedQty']);
        $service->method('getAlreadyRequestedQty')->willReturn([]);

        $result = $service->getEligibleItems($order);

        $this->assertCount(1, $result);
        $this->assertSame([
            'order_item_id' => 10,
            'name' => 'Blue Hat',
            'sku' => 'HAT-BL',
            'qty_ordered' => 2,
            'qty_already_requested' => 0,
            'qty_available' => 2,
            'is_eligible' => true,
            'disabled_reason' => '',
        ], $result[0]);
    }

    public function testGetEligibleItemsIncludesConfigurableProducts(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getEntityId')->willReturn(1);
        $order->method('getItems')->willReturn([
            $this->createOrderItem(parentItemId: null, productType: 'configurable', itemId: 20, qtyOrdered: 1, name: 'T-Shirt', sku: 'TSHIRT-M'),
        ]);

        /** @var OrderEligibility&MockObject $service */
        $service = $this->createService(['getAlreadyRequestedQty']);
        $service->method('getAlreadyRequestedQty')->willReturn([]);

        $result = $service->getEligibleItems($order);

        $this->assertCount(1, $result);
        $this->assertSame(20, $result[0]['order_item_id']);
    }

    public function testGetEligibleItemsFiltersMultipleItemTypes(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getEntityId')->willReturn(1);
        $order->method('getItems')->willReturn([
            $this->createOrderItem(parentItemId: null, productType: 'simple', itemId: 1, qtyOrdered: 2, name: 'Book', sku: 'BOOK-1'),
            $this->createOrderItem(parentItemId: null, productType: 'virtual', itemId: 2, qtyOrdered: 1),
            $this->createOrderItem(parentItemId: 1, productType: 'simple', itemId: 3, qtyOrdered: 1),
            $this->createOrderItem(parentItemId: null, productType: 'downloadable', itemId: 4, qtyOrdered: 1),
            $this->createOrderItem(parentItemId: null, productType: 'simple', itemId: 5, qtyOrdered: 1, name: 'Pen', sku: 'PEN-1'),
        ]);

        /** @var OrderEligibility&MockObject $service */
        $service = $this->createService(['getAlreadyRequestedQty']);
        $service->method('getAlreadyRequestedQty')->willReturn([]);

        $result = $service->getEligibleItems($order);

        $this->assertCount(2, $result);
        $this->assertSame(1, $result[0]['order_item_id']);
        $this->assertSame(5, $result[1]['order_item_id']);
    }

    public function testGetEligibleItemsSkipsShipmentQueryWhenReturnPeriodIsUnlimited(): void
    {
        $this->moduleConfig->method('getReturnPeriod')->with(1)->willReturn(0);
        $this->resourceConnection->expects($this->never())->method('getConnection');
        $this->timezone->expects($this->never())->method('formatDateTime');

        $order = $this->createOrder(items: [
            $this->createOrderItem(parentItemId: null, productType: 'simple', itemId: 10, qtyOrdered: 2),
        ]);

        /** @var OrderEligibility&MockObject $service */
        $service = $this->createService(['getAlreadyRequestedQty']);
        $service->method('getAlreadyRequestedQty')->willReturn([]);

        $result = $service->getEligibleItems($order);

        $this->assertCount(1, $result);
        $this->assertSame(2, $result[0]['qty_available']);
        $this->assertTrue($result[0]['is_eligible']);
    }

    public function testGetEligibleItemsUnshippedItemIsAlwaysEligibleWithReturnPeriod(): void
    {
        $this->moduleConfig->method('getReturnPeriod')->willReturn(30);
        $this->timezone->expects($this->never())->method('formatDateTime');

        $order = $this->createOrder(items: [
            $this->createOrderItem(parentItemId: null, productType: 'simple', itemId: 10, qtyOrdered: 2, name: 'Blue Hat', sku: 'HAT-BL'),
        ]);

        /** @var OrderEligibility&MockObject $service */
        $service = $this->createService(['getAlreadyRequestedQty', 'getExpiredShipments']);
        $service->method('getAlreadyRequestedQty')->willReturn([]);
        $service->expects($this->once())
            ->method('getExpiredShipments')
            ->with(100, $this->callback('is_string'))
            ->willReturn([]);

        $this->assertSame([
            [
                'order_item_id' => 10,
                'name' => 'Blue Hat',
                'sku' => 'HAT-BL',
                'qty_ordered' => 2,
                'qty_already_requested' => 0,
                'qty_available' => 2,
                'is_eligible' => true,
                'disabled_reason' => '',
            ],
        ], $service->getEligibleItems($order));
    }

    public function testGetEligibleItemsMarksFullyExpiredShipmentAsNotEligible(): void
    {
        $this->moduleConfig->method('getReturnPeriod')->willReturn(30);
        $this->timezone->method('getConfigTimezone')
            ->with(ScopeInterface::SCOPE_STORE, 1)
            ->willReturn('Europe/Rome');
        $this->timezone->expects($this->once())
            ->method('formatDateTime')
            ->with('2026-01-31 10:00:00', IntlDateFormatter::MEDIUM, IntlDateFormatter::NONE, null, 'Europe/Rome')
            ->willReturn('Jan 31, 2026');

        $order = $this->createOrder(items: [
            $this->createOrderItem(parentItemId: null, productType: 'simple', itemId: 10, qtyOrdered: 2, qtyShipped: 2),
        ]);

        /** @var OrderEligibility&MockObject $service */
        $service = $this->createService(['getAlreadyRequestedQty', 'getExpiredShipments']);
        $service->method('getAlreadyRequestedQty')->willReturn([]);
        $service->method('getExpiredShipments')->willReturn([
            10 => ['qty' => 2.0, 'shipped_at' => '2026-01-01 10:00:00'],
        ]);

        $result = $service->getEligibleItems($order);

        $this->assertCount(1, $result);
        $this->assertSame(0, $result[0]['qty_available']);
        $this->assertFalse($result[0]['is_eligible']);
        $this->assertStringContainsString('Jan 31, 2026', $result[0]['disabled_reason']);
    }

    public function testGetEligibleItemsPartialExpiredShipmentLeavesRemainingQty(): void
    {
        $this->moduleConfig->method('getReturnPeriod')->willReturn(30);
        $this->timezone->expects($this->never())->method('formatDateTime');

        $order = $this->createOrder(items: [
            $this->createOrderItem(parentItemId: null, productType: 'simple', itemId: 10, qtyOrdered: 3, qtyShipped: 2),
        ]);

        /** @var OrderEligibility&MockObject $service */
        $service = $this->createService(['getAlreadyRequestedQty', 'getExpiredShipments']);
        $service->method('getAlreadyRequestedQty')->willReturn([]);
        $service->method('getExpiredShipments')->willReturn([
            10 => ['qty' => 2.0, 'shipped_at' => '2026-01-01 10:00:00'],
        ]);

        $result = $service->getEligibleItems($order);

        $this->assertCount(1, $result);
        $this->assertSame(1, $result[0]['qty_available']);
        $this->assertTrue($result[0]['is_eligible']);
        $this->assertSame('', $result[0]['disabled_reason']);
    }

    public function testGetEligibleItemsCombinesAlreadyRequestedAndExpiredQty(): void
    {
        $this->moduleConfig->method('getReturnPeriod')->willReturn(30);

        $order = $this->createOrder(items: [
            $this->createOrderItem(parentItemId: null, productType: 'simple', itemId: 10, qtyOrdered: 5, qtyShipped: 2),
            $this->createOrderItem(parentItemId: null, productType: 'simple', itemId: 20, qtyOrdered: 4, qtyShipped: 2),
        ]);

        /** @var OrderEligibility&MockObject $service */
        $service = $this->createService(['getAlreadyRequestedQty', 'getExpiredShipments']);
        $service->method('getAlreadyRequestedQty')->willReturn([10 => 1, 20 => 3]);
        $service->method('getExpiredShipments')->willReturn([
            10 => ['qty' => 2.0, 'shipped_at' => '2026-01-01 10:00:00'],
            20 => ['qty' => 2.0, 'shipped_at' => '2026-01-01 10:00:00'],
        ]);

        $result = $service->getEligibleItems($order);

        $this->assertCount(2, $result);
        $this->assertSame(1, $result[0]['qty_already_requested']);
        $this->assertSame(3, $result[0]['qty_available']);
        $this->assertTrue($result[0]['is_eligible']);
        $this->assertSame(3, $result[1]['qty_already_requested']);
        $this->assertSame(1, $result[1]['qty_available']);
        $this->assertTrue($result[1]['is_eligible']);
    }

    public function testGetEligibleItemsConvertsBundleShipSeparatelyChildrenQtyToParent(): void
    {
        $this->moduleConfig->method('getReturnPeriod')->willReturn(30);
        $this->timezone->expects($this->never())->method('formatDateTime');

        $childA = $this->createOrderItem(parentItemId: 30, productType: 'simple', itemId: 31, qtyOrdered: 2, qtyShipped: 2);
        $childB = $this->createOrderItem(parentItemId: 30, productType: 'simple', itemId: 32, qtyOrdered: 4, qtyShipped: 2);
        $bundle = $this->createBundleItem(itemId: 30, qtyOrdered: 2.0, children: [$childA, $childB]);

        $order = $this->createOrder(items: [$bundle, $childA, $childB]);

        /** @var OrderEligibility&MockObject $service */
        $service = $this->createService(['getAlreadyRequestedQty', 'getExpiredShipments']);
        $service->method('getAlreadyRequestedQty')->willReturn([]);
        $service->method('getExpiredShipments')->willReturn([
            31 => ['qty' => 2.0, 'shipped_at' => '2026-01-01 10:00:00'],
            32 => ['qty' => 2.0, 'shipped_at' => '2026-01-02 10:00:00'],
        ]);

        $result = $service->getEligibleItems($order);

        $this->assertCount(1, $result);
        $this->assertSame(30, $result[0]['order_item_id']);
        $this->assertSame(1, $result[0]['qty_available']);
        $this->assertTrue($result[0]['is_eligible']);
    }

    public function testGetEligibleItemsMarksFullyExpiredBundleAsNotEligibleUsingLatestChildShipment(): void
    {
        $this->moduleConfig->method('getReturnPeriod')->willReturn(30);
        $this->timezone->method('getConfigTimezone')->willReturn('UTC');
        $this->timezone->expects($this->once())
            ->method('formatDateTime')
            ->with('2026-02-01 10:00:00', IntlDateFormatter::MEDIUM, IntlDateFormatter::NONE, null, 'UTC')
            ->willReturn('Feb 1, 2026');

        $childA = $this->createOrderItem(parentItemId: 30, productType: 'simple', itemId: 31, qtyOrdered: 2, qtyShipped: 2);
        $childB = $this->createOrderItem(parentItemId: 30, productType: 'simple', itemId: 32, qtyOrdered: 4, qtyShipped: 4);
        $bundle = $this->createBundleItem(itemId: 30, qtyOrdered: 2.0, children: [$childA, $childB]);

        $order = $this->createOrder(items: [$bundle, $childA, $childB]);

        /** @var OrderEligibility&MockObject $service */
        $service = $this->createService(['getAlreadyRequestedQty', 'getExpiredShipments']);
        $service->method('getAlreadyRequestedQty')->willReturn([]);
        $service->method('getExpiredShipments')->willReturn([
            31 => ['qty' => 2.0, 'shipped_at' => '2026-01-01 10:00:00'],
            32 => ['qty' => 4.0, 'shipped_at' => '2026-01-02 10:00:00'],
        ]);

        $result = $service->getEligibleItems($order);

        $this->assertCount(1, $result);
        $this->assertSame(0, $result[0]['qty_available']);
        $this->assertFalse($result[0]['is_eligible']);
        $this->assertStringContainsString('Feb 1, 2026', $result[0]['disabled_reason']);
    }

    public function testGetEligibleItemsBundleWithUnshippedChildStaysEligible(): void
    {
        $this->moduleConfig->method('getReturnPeriod')->willReturn(30);

        $childA = $this->createOrderItem(parentItemId: 30, productType: 'simple', itemId: 31, qtyOrdered: 2, qtyShipped: 2);
        $childB = $this->createOrderItem(parentItemId: 30, productType: 'simple', itemId: 32, qtyOrdered: 4);
        $bundle = $this->createBundleItem(itemId: 30, qtyOrdered: 2.0, children: [$childA, $childB]);

        $order = $this->createOrder(items: [$bundle, $childA, $childB]);

        /** @var OrderEligibility&MockObject $service */
        $service = $this->createService(['getAlreadyRequestedQty', 'getExpiredShipments']);
        $service->method('getAlreadyRequestedQty')->willReturn([]);
        $service->method('getExpiredShipments')->willReturn([
            31 => ['qty' => 2.0, 'shipped_at' => '2026-01-01 10:00:00'],
        ]);

        $result = $service->getEligibleItems($order);

        $this->assertCount(1, $result);
        $this->assertSame(2, $result[0]['qty_available']);
        $this->assertTrue($result[0]['is_eligible']);
    }

    public function testGetEligibleItemsExcludesCanceledQtyWithRecentShipment(): void
    {
        $this->moduleConfig->method('getReturnPeriod')->willReturn(30);
        $this->timezone->expects($this->never())->method('formatDateTime');

        $order = $this->createOrder(items: [
            $this->createOrderItem(
                parentItemId: null,
                productType: 'simple',
                itemId: 10,
                qtyOrdered: 2,
                qtyShipped: 1,
                qtyCanceled: 1
            ),
        ]);

        /** @var OrderEligibility&MockObject $service */
        $service = $this->createService(['getAlreadyRequestedQty', 'getExpiredShipments']);
        $service->method('getAlreadyRequestedQty')->willReturn([]);
        $service->method('getExpiredShipments')->willReturn([]);

        $result = $service->getEligibleItems($order);

        $this->assertCount(1, $result);
        $this->assertSame(2, $result[0]['qty_ordered']);
        $this->assertSame(1, $result[0]['qty_available']);
        $this->assertTrue($result[0]['is_eligible']);
    }

    public function testGetEligibleItemsMarksCanceledRemainderWithExpiredShipmentAsNotEligible(): void
    {
        $this->moduleConfig->method('getReturnPeriod')->willReturn(30);
        $this->timezone->method('getConfigTimezone')->willReturn('UTC');
        $this->timezone->expects($this->once())
            ->method('formatDateTime')
            ->with('2026-01-31 10:00:00', IntlDateFormatter::MEDIUM, IntlDateFormatter::NONE, null, 'UTC')
            ->willReturn('Jan 31, 2026');

        $order = $this->createOrder(items: [
            $this->createOrderItem(
                parentItemId: null,
                productType: 'simple',
                itemId: 10,
                qtyOrdered: 2,
                qtyShipped: 1,
                qtyCanceled: 1
            ),
        ]);

        /** @var OrderEligibility&MockObject $service */
        $service = $this->createService(['getAlreadyRequestedQty', 'getExpiredShipments']);
        $service->method('getAlreadyRequestedQty')->willReturn([]);
        $service->method('getExpiredShipments')->willReturn([
            10 => ['qty' => 1.0, 'shipped_at' => '2026-01-01 10:00:00'],
        ]);

        $result = $service->getEligibleItems($order);

        $this->assertCount(1, $result);
        $this->assertSame(0, $result[0]['qty_available']);
        $this->assertFalse($result[0]['is_eligible']);
        $this->assertStringContainsString('Jan 31, 2026', $result[0]['disabled_reason']);
    }

    public function testGetEligibleItemsExcludesQtyRefundedBeforeShipment(): void
    {
        $this->moduleConfig->method('getReturnPeriod')->willReturn(30);

        $order = $this->createOrder(items: [
            $this->createOrderItem(
                parentItemId: null,
                productType: 'simple',
                itemId: 10,
                qtyOrdered: 2,
                qtyRefunded: 1
            ),
        ]);

        /** @var OrderEligibility&MockObject $service */
        $service = $this->createService(['getAlreadyRequestedQty', 'getExpiredShipments']);
        $service->method('getAlreadyRequestedQty')->willReturn([]);
        $service->method('getExpiredShipments')->willReturn([]);

        $result = $service->getEligibleItems($order);

        $this->assertCount(1, $result);
        $this->assertSame(2, $result[0]['qty_ordered']);
        $this->assertSame(1, $result[0]['qty_available']);
        $this->assertTrue($result[0]['is_eligible']);
    }

    public function testGetEligibleItemsSkipsFullyCanceledItem(): void
    {
        $this->moduleConfig->method('getReturnPeriod')->willReturn(30);

        $order = $this->createOrder(items: [
            $this->createOrderItem(
                parentItemId: null,
                productType: 'simple',
                itemId: 10,
                qtyOrdered: 2,
                qtyCanceled: 2
            ),
        ]);

        /** @var OrderEligibility&MockObject $service */
        $service = $this->createService(['getAlreadyRequestedQty', 'getExpiredShipments']);
        $service->method('getAlreadyRequestedQty')->willReturn([]);
        $service->method('getExpiredShipments')->willReturn([]);

        $this->assertSame([], $service->getEligibleItems($order));
    }

    public function testGetEligibleItemsSkipsFullyRefundedUnshippedItem(): void
    {
        $this->moduleConfig->method('getReturnPeriod')->willReturn(30);
        $this->timezone->expects($this->never())->method('formatDateTime');

        $order = $this->createOrder(items: [
            $this->createOrderItem(
                parentItemId: null,
                productType: 'simple',
                itemId: 10,
                qtyOrdered: 2,
                qtyRefunded: 2
            ),
        ]);

        /** @var OrderEligibility&MockObject $service */
        $service = $this->createService(['getAlreadyRequestedQty', 'getExpiredShipments']);
        $service->method('getAlreadyRequestedQty')->willReturn([]);
        $service->method('getExpiredShipments')->willReturn([]);

        $this->assertSame([], $service->getEligibleItems($order));
    }

    public function testGetEligibleItemsDeductsCanceledQtyWhenReturnPeriodIsUnlimited(): void
    {
        $this->moduleConfig->method('getReturnPeriod')->willReturn(0);
        $this->resourceConnection->expects($this->never())->method('getConnection');

        $order = $this->createOrder(items: [
            $this->createOrderItem(
                parentItemId: null,
                productType: 'simple',
                itemId: 10,
                qtyOrdered: 5,
                qtyCanceled: 2
            ),
        ]);

        /** @var OrderEligibility&MockObject $service */
        $service = $this->createService(['getAlreadyRequestedQty']);
        $service->method('getAlreadyRequestedQty')->willReturn([10 => 1]);

        $result = $service->getEligibleItems($order);

        $this->assertCount(1, $result);
        $this->assertSame(5, $result[0]['qty_ordered']);
        $this->assertSame(2, $result[0]['qty_available']);
        $this->assertTrue($result[0]['is_eligible']);
    }

    public function testGetEligibleItemsSkipsFullyRefundedUnshippedItemWhenReturnPeriodIsUnlimited(): void
    {
        $this->moduleConfig->method('getReturnPeriod')->willReturn(0);
        $this->resourceConnection->expects($this->never())->method('getConnection');
        $this->timezone->expects($this->never())->method('formatDateTime');

        $order = $this->createOrder(items: [
            $this->createOrderItem(
                parentItemId: null,
                productType: 'simple',
                itemId: 10,
                qtyOrdered: 2,
                qtyRefunded: 2
            ),
        ]);

        /** @var OrderEligibility&MockObject $service */
        $service = $this->createService(['getAlreadyRequestedQty']);
        $service->method('getAlreadyRequestedQty')->willReturn([]);

        $this->assertSame([], $service->getEligibleItems($order));
    }

    public function testGetEligibleItemsExcludesQtyRefundedBeforeShipmentWhenReturnPeriodIsUnlimited(): void
    {
        $this->moduleConfig->method('getReturnPeriod')->willReturn(0);
        $this->resourceConnection->expects($this->never())->method('getConnection');

        $order = $this->createOrder(items: [
            $this->createOrderItem(
                parentItemId: null,
                productType: 'simple',
                itemId: 10,
                qtyOrdered: 2,
                qtyShipped: 1,
                qtyRefunded: 1
            ),
        ]);

        /** @var OrderEligibility&MockObject $service */
        $service = $this->createService(['getAlreadyRequestedQty']);
        $service->method('getAlreadyRequestedQty')->willReturn([]);

        $result = $service->getEligibleItems($order);

        $this->assertCount(1, $result);
        $this->assertSame(2, $result[0]['qty_ordered']);
        $this->assertSame(1, $result[0]['qty_available']);
        $this->assertTrue($result[0]['is_eligible']);
    }

    public function testGetEligibleItemsDerivesBundleShipSeparatelyShippedQtyFromChildren(): void
    {
        $this->moduleConfig->method('getReturnPeriod')->willReturn(30);
        $this->timezone->expects($this->never())->method('formatDateTime');

        $childA = $this->createOrderItem(parentItemId: 30, productType: 'simple', itemId: 31, qtyOrdered: 2, qtyShipped: 2);
        $childB = $this->createOrderItem(parentItemId: 30, productType: 'simple', itemId: 32, qtyOrdered: 4, qtyShipped: 4);
        $bundle = $this->createBundleItem(itemId: 30, qtyOrdered: 2.0, children: [$childA, $childB], qtyShipped: 0.0);

        $order = $this->createOrder(items: [$bundle, $childA, $childB]);

        /** @var OrderEligibility&MockObject $service */
        $service = $this->createService(['getAlreadyRequestedQty', 'getExpiredShipments']);
        $service->method('getAlreadyRequestedQty')->willReturn([]);
        $service->method('getExpiredShipments')->willReturn([
            31 => ['qty' => 2.0, 'shipped_at' => '2026-01-01 10:00:00'],
            32 => ['qty' => 2.0, 'shipped_at' => '2026-01-02 10:00:00'],
        ]);

        $result = $service->getEligibleItems($order);

        $this->assertCount(1, $result);
        $this->assertSame(30, $result[0]['order_item_id']);
        $this->assertSame(1, $result[0]['qty_available']);
        $this->assertTrue($result[0]['is_eligible']);
    }

    // -------------------------------------------------------------------------
    // getCustomerEligibleOrders
    // -------------------------------------------------------------------------

    protected function configureStores(int $currentStoreId, array $storeWebsites): void
    {
        $stores = [];
        foreach ($storeWebsites as $id => $websiteId) {
            $store = $this->createMock(StoreInterface::class);
            $store->method('getId')->willReturn($id);
            $store->method('getWebsiteId')->willReturn($websiteId);
            $stores[$id] = $store;
        }

        $this->storeManager->method('getStore')->with($currentStoreId)->willReturn($stores[$currentStoreId]);
        $this->storeManager->method('getStores')->willReturn($stores);
    }

    protected function createOrderCollection(
        array &$filters,
        Select&MockObject $mainSelect,
        ?AdapterInterface $connection = null
    ): Collection&MockObject {
        $collection = $this->createMock(Collection::class);
        $collection->method('addFieldToFilter')->willReturnCallback(
            function (string $field, mixed $condition = null) use ($collection, &$filters): Collection {
                $filters[$field] = $condition;

                return $collection;
            }
        );
        $collection->method('getTable')->willReturnArgument(0);
        $collection->method('getSelect')->willReturn($mainSelect);
        $collection->expects($this->once())->method('setOrder')->with('created_at', 'desc')->willReturnSelf();

        if ($connection === null) {
            $collection->expects($this->never())->method('getConnection');
        } else {
            $collection->method('getConnection')->willReturn($connection);
        }

        $this->orderCollectionFactory->expects($this->once())->method('create')->willReturn($collection);

        return $collection;
    }

    protected function createRecordingSelect(string $sql, array &$calls): Select&MockObject
    {
        $calls = ['from' => [], 'where' => []];
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnCallback(
            function (...$args) use ($select, &$calls): Select {
                $calls['from'][] = $args;

                return $select;
            }
        );
        $select->method('where')->willReturnCallback(
            function (...$args) use ($select, &$calls): Select {
                $calls['where'][] = [$args[0], $args[1] ?? null];

                return $select;
            }
        );
        $select->method('__toString')->willReturn($sql);

        return $select;
    }

    public function testGetCustomerEligibleOrdersAppliesBaseFiltersAndSortOrder(): void
    {
        $this->moduleConfig->method('getAllowedOrderStatuses')->with(1)->willReturn([]);
        $this->moduleConfig->method('getReturnPeriod')->with(1)->willReturn(0);
        $this->configureStores(1, [1 => 1, 2 => 2, 3 => 1]);

        $filters = [];
        $collection = $this->createOrderCollection($filters, $this->createMock(Select::class));

        $this->assertSame($collection, $this->createService()->getCustomerEligibleOrders(42, 1));
        $this->assertSame(42, $filters['customer_id']);
        $this->assertSame(['in'], array_keys($filters['store_id']));
        $this->assertSame([1, 3], array_values($filters['store_id']['in']));
    }

    public function testGetCustomerEligibleOrdersFiltersByAllowedStatuses(): void
    {
        $this->moduleConfig->method('getAllowedOrderStatuses')->willReturn(['complete', 'closed']);
        $this->moduleConfig->method('getReturnPeriod')->willReturn(0);
        $this->configureStores(1, [1 => 1]);

        $filters = [];
        $this->createOrderCollection($filters, $this->createMock(Select::class));

        $this->createService()->getCustomerEligibleOrders(42, 1);

        $this->assertSame(['in' => ['complete', 'closed']], $filters['status']);
    }

    public function testGetCustomerEligibleOrdersSkipsStatusFilterWhenNoStatusesConfigured(): void
    {
        $this->moduleConfig->method('getAllowedOrderStatuses')->willReturn([]);
        $this->moduleConfig->method('getReturnPeriod')->willReturn(0);
        $this->configureStores(1, [1 => 1]);

        $filters = [];
        $this->createOrderCollection($filters, $this->createMock(Select::class));

        $this->createService()->getCustomerEligibleOrders(42, 1);

        $this->assertArrayNotHasKey('status', $filters);
        $this->assertSame(['customer_id', 'store_id'], array_keys($filters));
    }

    public function testGetCustomerEligibleOrdersSkipsExistsConditionWhenReturnPeriodIsUnlimited(): void
    {
        $this->moduleConfig->method('getAllowedOrderStatuses')->willReturn(['complete']);
        $this->moduleConfig->method('getReturnPeriod')->willReturn(0);
        $this->configureStores(1, [1 => 1]);

        $mainSelect = $this->createMock(Select::class);
        $mainSelect->expects($this->never())->method('where');

        $filters = [];
        $this->createOrderCollection($filters, $mainSelect);

        /** @var OrderEligibility&MockObject $service */
        $service = $this->createService(['getCutoffDate']);
        $service->expects($this->never())->method('getCutoffDate');

        $service->getCustomerEligibleOrders(42, 1);
    }

    public function testGetCustomerEligibleOrdersAddsExistsConditionWhenReturnPeriodIsSet(): void
    {
        $this->moduleConfig->method('getAllowedOrderStatuses')->willReturn(['complete']);
        $this->moduleConfig->method('getReturnPeriod')->willReturn(30);
        $this->configureStores(1, [1 => 1]);

        $unshippedCalls = [];
        $shipmentCalls = [];
        $connection = $this->createMock(AdapterInterface::class);
        $connection->expects($this->exactly(2))
            ->method('select')
            ->willReturnOnConsecutiveCalls(
                $this->createRecordingSelect('SQL_UNSHIPPED', $unshippedCalls),
                $this->createRecordingSelect('SQL_SHIPMENTS', $shipmentCalls)
            );

        $mainSelect = $this->createMock(Select::class);
        $mainSelect->expects($this->once())
            ->method('where')
            ->with($this->callback(
                fn($condition): bool => is_string($condition)
                    && substr_count($condition, 'EXISTS (') === 2
                    && str_contains($condition, ' OR ')
                    && str_contains($condition, 'SQL_UNSHIPPED')
                    && str_contains($condition, 'SQL_SHIPMENTS')
            ))
            ->willReturnSelf();

        $filters = [];
        $this->createOrderCollection($filters, $mainSelect, $connection);

        /** @var OrderEligibility&MockObject $service */
        $service = $this->createService(['getCutoffDate']);
        $service->expects($this->once())->method('getCutoffDate')->with(30)->willReturn('2026-01-01 00:00:00');

        $service->getCustomerEligibleOrders(42, 1);
    }

    public function testGetCustomerEligibleOrdersSubqueriesFilterUnshippedItemsAndRecentShipments(): void
    {
        $this->moduleConfig->method('getAllowedOrderStatuses')->willReturn(['complete']);
        $this->moduleConfig->method('getReturnPeriod')->willReturn(30);
        $this->configureStores(1, [1 => 1]);

        $unshippedCalls = [];
        $shipmentCalls = [];
        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturnOnConsecutiveCalls(
            $this->createRecordingSelect('SQL_UNSHIPPED', $unshippedCalls),
            $this->createRecordingSelect('SQL_SHIPMENTS', $shipmentCalls)
        );

        $mainSelect = $this->createMock(Select::class);
        $mainSelect->method('where')->willReturnSelf();

        $filters = [];
        $this->createOrderCollection($filters, $mainSelect, $connection);

        /** @var OrderEligibility&MockObject $service */
        $service = $this->createService(['getCutoffDate']);
        $service->method('getCutoffDate')->with(30)->willReturn('2026-01-01 00:00:00');

        $service->getCustomerEligibleOrders(42, 1);

        $this->assertSame(['soi' => 'sales_order_item'], $unshippedCalls['from'][0][0]);
        $this->assertSame([
            ['soi.order_id = main_table.entity_id', null],
            ['soi.parent_item_id IS NULL', null],
            ['soi.product_type NOT IN (?)', ['virtual', 'downloadable']],
            ['soi.qty_ordered - soi.qty_canceled - soi.qty_refunded > soi.qty_shipped', null],
        ], $unshippedCalls['where']);

        $this->assertSame(['ss' => 'sales_shipment'], $shipmentCalls['from'][0][0]);
        $this->assertSame([
            ['ss.order_id = main_table.entity_id', null],
            ['ss.created_at >= ?', '2026-01-01 00:00:00'],
        ], $shipmentCalls['where']);
    }
}
