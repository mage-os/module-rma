<?php

declare(strict_types=1);

namespace MageOS\RMA\Test\Unit\Service;

use MageOS\RMA\Api\Data\RMAInterface;
use MageOS\RMA\Api\Data\RMAInterfaceFactory;
use MageOS\RMA\Api\ItemRepositoryInterface;
use MageOS\RMA\Api\RMARepositoryInterface;
use MageOS\RMA\Helper\ModuleConfig;
use MageOS\RMA\Model\Item;
use MageOS\RMA\Model\ItemFactory;
use MageOS\RMA\Model\RMA\StatusCodes;
use MageOS\RMA\Model\ResourceModel\Status\CollectionFactory as StatusCollectionFactory;
use MageOS\RMA\Service\AttachmentService;
use MageOS\RMA\Service\OrderEligibility;
use MageOS\RMA\Service\RmaSubmitService;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class RmaSubmitServiceTest extends TestCase
{
    private RMARepositoryInterface&MockObject $rmaRepository;
    private RMAInterfaceFactory&MockObject $rmaFactory;
    private ItemFactory&MockObject $itemFactory;
    private ItemRepositoryInterface&MockObject $itemRepository;
    private StatusCollectionFactory&MockObject $statusCollectionFactory;
    private ModuleConfig&MockObject $moduleConfig;
    private AttachmentService&MockObject $attachmentService;
    private OrderEligibility&MockObject $orderEligibility;
    private ResourceConnection&MockObject $resourceConnection;
    private AdapterInterface&MockObject $connection;
    private EventManagerInterface&MockObject $eventManager;
    private RmaSubmitService $service;

    protected function setUp(): void
    {
        $this->rmaRepository = $this->createMock(RMARepositoryInterface::class);
        $this->rmaFactory = $this->createMock(RMAInterfaceFactory::class);
        $this->itemFactory = $this->createMock(ItemFactory::class);
        $this->itemRepository = $this->createMock(ItemRepositoryInterface::class);
        $this->statusCollectionFactory = $this->createMock(StatusCollectionFactory::class);
        $this->moduleConfig = $this->createMock(ModuleConfig::class);
        $this->attachmentService = $this->createMock(AttachmentService::class);
        $this->orderEligibility = $this->createMock(OrderEligibility::class);
        $this->resourceConnection = $this->createMock(ResourceConnection::class);
        $this->connection = $this->createMock(AdapterInterface::class);
        $this->eventManager = $this->createMock(EventManagerInterface::class);

        $this->resourceConnection->method('getConnection')->willReturn($this->connection);

        $this->service = new RmaSubmitService(
            $this->rmaRepository,
            $this->rmaFactory,
            $this->itemFactory,
            $this->itemRepository,
            $this->statusCollectionFactory,
            $this->moduleConfig,
            $this->attachmentService,
            $this->orderEligibility,
            $this->resourceConnection,
            $this->eventManager
        );
    }

    // -------------------------------------------------------------------------
    // getSelectedItems
    // -------------------------------------------------------------------------

    public function testGetSelectedItemsFiltersOutUnselectedItems(): void
    {
        $itemsData = [
            10 => ['selected' => '', 'qty_requested' => 1],
            20 => ['selected' => '1', 'qty_requested' => 2],
        ];

        $result = $this->service->getSelectedItems($itemsData);

        $this->assertArrayNotHasKey(10, $result);
        $this->assertArrayHasKey(20, $result);
    }

    public function testGetSelectedItemsFiltersOutZeroQty(): void
    {
        $itemsData = [
            10 => ['selected' => '1', 'qty_requested' => 0],
            20 => ['selected' => '1', 'qty_requested' => 2],
        ];

        $result = $this->service->getSelectedItems($itemsData);

        $this->assertArrayNotHasKey(10, $result);
        $this->assertArrayHasKey(20, $result);
    }

    public function testGetSelectedItemsFiltersOutNegativeQty(): void
    {
        $itemsData = [
            10 => ['selected' => '1', 'qty_requested' => -1],
        ];

        $result = $this->service->getSelectedItems($itemsData);

        $this->assertSame([], $result);
    }

    public function testGetSelectedItemsReturnsCorrectStructure(): void
    {
        $itemsData = [
            10 => ['selected' => '1', 'qty_requested' => 2, 'condition_id' => 3],
            20 => ['selected' => '1', 'qty_requested' => 1],
        ];

        $result = $this->service->getSelectedItems($itemsData);

        $this->assertSame(['qty_requested' => 2, 'condition_id' => 3], $result[10]);
        $this->assertSame(['qty_requested' => 1, 'condition_id' => null], $result[20]);
    }

    public function testGetSelectedItemsReturnsEmptyArrayWhenNoItemsSelected(): void
    {
        $result = $this->service->getSelectedItems([]);

        $this->assertSame([], $result);
    }

    // -------------------------------------------------------------------------
    // createRma
    // -------------------------------------------------------------------------

    public function testCreateRmaThrowsWhenReasonIdIsMissing(): void
    {
        $this->expectException(LocalizedException::class);

        $this->service->createRma(
            order: $this->createMock(OrderInterface::class),
            customerId: 1,
            customerEmail: 'test@example.com',
            customerName: 'Test User',
            reasonId: 0,
            resolutionTypeId: 1,
            selectedItems: []
        );
    }

    public function testCreateRmaThrowsWhenResolutionTypeIdIsMissing(): void
    {
        $this->expectException(LocalizedException::class);

        $this->service->createRma(
            order: $this->createMock(OrderInterface::class),
            customerId: 1,
            customerEmail: 'test@example.com',
            customerName: 'Test User',
            reasonId: 1,
            resolutionTypeId: 0,
            selectedItems: []
        );
    }

    public function testCreateRmaThrowsWhenStatusNotFound(): void
    {
        $this->expectException(LocalizedException::class);

        $order = $this->createMock(OrderInterface::class);
        $order->method('getStoreId')->willReturn(1);

        $this->moduleConfig->method('isAutoApproveEnabled')->willReturn(false);

        $statusCollection = $this->createMock(\MageOS\RMA\Model\ResourceModel\Status\Collection::class);
        $statusCollection->method('addFieldToFilter')->willReturnSelf();
        $statusCollection->method('setPageSize')->willReturnSelf();
        $statusCollection->method('getFirstItem')->willReturn(new \Magento\Framework\DataObject());

        $this->statusCollectionFactory->method('create')->willReturn($statusCollection);

        $this->service->createRma(
            order: $order,
            customerId: 1,
            customerEmail: 'test@example.com',
            customerName: 'Test User',
            reasonId: 1,
            resolutionTypeId: 1,
            selectedItems: []
        );
    }

    public function testCreateRmaSetsNewRequestStatusWhenAutoApproveDisabled(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getStoreId')->willReturn(1);
        $order->method('getEntityId')->willReturn(100);

        $this->moduleConfig->method('isAutoApproveEnabled')->with(1)->willReturn(false);

        $statusItem = $this->createMock(\MageOS\RMA\Api\Data\StatusInterface::class);
        $statusItem->method('getEntityId')->willReturn(1);

        $statusCollection = $this->createMock(\MageOS\RMA\Model\ResourceModel\Status\Collection::class);
        $statusCollection->method('setPageSize')->willReturnSelf();
        $statusCollection->method('getFirstItem')->willReturn($statusItem);

        $statusCollection->expects($this->once())
            ->method('addFieldToFilter')
            ->with(\MageOS\RMA\Api\Data\StatusInterface::CODE, StatusCodes::NEW_REQUEST)
            ->willReturnSelf();

        $this->statusCollectionFactory->method('create')->willReturn($statusCollection);

        $rma = $this->createMock(RMAInterface::class);
        $rma->method('getEntityId')->willReturn(10);
        $this->rmaFactory->method('create')->willReturn($rma);

        $this->orderEligibility->method('getEligibleItems')->willReturn([]);
        $this->attachmentService->method('saveFromJson');

        $this->service->createRma(
            order: $order,
            customerId: 1,
            customerEmail: 'test@example.com',
            customerName: 'Test User',
            reasonId: 1,
            resolutionTypeId: 1,
            selectedItems: []
        );
    }

    public function testCreateRmaSetsApprovedStatusWhenAutoApproveEnabled(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getStoreId')->willReturn(1);
        $order->method('getEntityId')->willReturn(100);

        $this->moduleConfig->method('isAutoApproveEnabled')->with(1)->willReturn(true);

        $statusItem = $this->createMock(\MageOS\RMA\Api\Data\StatusInterface::class);
        $statusItem->method('getEntityId')->willReturn(2);

        $statusCollection = $this->createMock(\MageOS\RMA\Model\ResourceModel\Status\Collection::class);
        $statusCollection->method('setPageSize')->willReturnSelf();
        $statusCollection->method('getFirstItem')->willReturn($statusItem);

        $statusCollection->expects($this->once())
            ->method('addFieldToFilter')
            ->with(\MageOS\RMA\Api\Data\StatusInterface::CODE, StatusCodes::APPROVED)
            ->willReturnSelf();

        $this->statusCollectionFactory->method('create')->willReturn($statusCollection);

        $rma = $this->createMock(RMAInterface::class);
        $rma->method('getEntityId')->willReturn(10);
        $this->rmaFactory->method('create')->willReturn($rma);

        $this->orderEligibility->method('getEligibleItems')->willReturn([]);
        $this->attachmentService->method('saveFromJson');

        $this->service->createRma(
            order: $order,
            customerId: 1,
            customerEmail: 'test@example.com',
            customerName: 'Test User',
            reasonId: 1,
            resolutionTypeId: 1,
            selectedItems: []
        );
    }

    public function testCreateRmaDispatchesCommitAfterEventOnceCommitted(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getStoreId')->willReturn(1);
        $order->method('getEntityId')->willReturn(100);

        $this->moduleConfig->method('isAutoApproveEnabled')->willReturn(false);

        $statusItem = $this->createMock(\MageOS\RMA\Api\Data\StatusInterface::class);
        $statusItem->method('getEntityId')->willReturn(1);

        $statusCollection = $this->createMock(\MageOS\RMA\Model\ResourceModel\Status\Collection::class);
        $statusCollection->method('addFieldToFilter')->willReturnSelf();
        $statusCollection->method('setPageSize')->willReturnSelf();
        $statusCollection->method('getFirstItem')->willReturn($statusItem);
        $this->statusCollectionFactory->method('create')->willReturn($statusCollection);

        $rma = $this->createMock(RMAInterface::class);
        $rma->method('getEntityId')->willReturn(10);
        $this->rmaFactory->method('create')->willReturn($rma);

        $this->orderEligibility->method('getEligibleItems')->willReturn([]);

        $this->connection->expects($this->once())->method('commit');
        $this->eventManager->expects($this->once())
            ->method('dispatch')
            ->with('rma_commit_after', ['rma' => $rma]);

        $this->service->createRma(
            order: $order,
            customerId: 1,
            customerEmail: 'test@example.com',
            customerName: 'Test User',
            reasonId: 1,
            resolutionTypeId: 1,
            selectedItems: []
        );
    }

    public function testCreateRmaRollsBackTransactionOnException(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getStoreId')->willReturn(1);
        $order->method('getEntityId')->willReturn(100);

        $this->moduleConfig->method('isAutoApproveEnabled')->willReturn(false);

        $statusItem = $this->createMock(\MageOS\RMA\Api\Data\StatusInterface::class);
        $statusItem->method('getEntityId')->willReturn(1);

        $statusCollection = $this->createMock(\MageOS\RMA\Model\ResourceModel\Status\Collection::class);
        $statusCollection->method('addFieldToFilter')->willReturnSelf();
        $statusCollection->method('setPageSize')->willReturnSelf();
        $statusCollection->method('getFirstItem')->willReturn($statusItem);
        $this->statusCollectionFactory->method('create')->willReturn($statusCollection);

        $rma = $this->createMock(RMAInterface::class);
        $rma->method('getEntityId')->willReturn(10);
        $this->rmaFactory->method('create')->willReturn($rma);

        $this->rmaRepository->method('save')->willThrowException(new \RuntimeException('DB error'));

        $this->connection->expects($this->once())->method('beginTransaction');
        $this->connection->expects($this->once())->method('rollBack');
        $this->connection->expects($this->never())->method('commit');
        $this->eventManager->expects($this->never())->method('dispatch');

        $this->expectException(\RuntimeException::class);

        $this->service->createRma(
            order: $order,
            customerId: 1,
            customerEmail: 'test@example.com',
            customerName: 'Test User',
            reasonId: 1,
            resolutionTypeId: 1,
            selectedItems: []
        );
    }

    // -------------------------------------------------------------------------
    // saveItems
    // -------------------------------------------------------------------------

    public function testSaveItemsThrowsWhenItemNotEligible(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessageMatches('/not eligible/');

        $order = $this->createMock(OrderInterface::class);
        $order->method('getIncrementId')->willReturn('000000001');
        $order->method('getEntityId')->willReturn(100);

        $this->orderEligibility->method('getEligibleItems')->with($order)->willReturn([
            ['order_item_id' => 5, 'qty_available' => 2],
        ]);

        $this->service->saveItems(
            rmaId: 1,
            selectedItems: [99 => ['qty_requested' => 1, 'condition_id' => null]],
            order: $order
        );
    }

    public function testSaveItemsThrowsWhenQtyExceedsAvailable(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessageMatches('/exceeds available/');

        $order = $this->createMock(OrderInterface::class);
        $order->method('getIncrementId')->willReturn('000000001');
        $order->method('getEntityId')->willReturn(100);

        $this->orderEligibility->method('getEligibleItems')->with($order)->willReturn([
            ['order_item_id' => 5, 'qty_available' => 1],
        ]);

        $this->service->saveItems(
            rmaId: 1,
            selectedItems: [5 => ['qty_requested' => 3, 'condition_id' => null]],
            order: $order
        );
    }

    public function testSaveItemsPersistsEachEligibleItem(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getEntityId')->willReturn(100);

        $this->orderEligibility->method('getEligibleItems')->willReturn([
            ['order_item_id' => 1, 'qty_available' => 2],
            ['order_item_id' => 2, 'qty_available' => 1],
        ]);

        $item = $this->createMock(Item::class);
        $this->itemFactory->method('create')->willReturn($item);
        $this->itemRepository->expects($this->exactly(2))->method('save');

        $this->service->saveItems(
            rmaId: 10,
            selectedItems: [
                1 => ['qty_requested' => 1, 'condition_id' => null],
                2 => ['qty_requested' => 1, 'condition_id' => 3],
            ],
            order: $order
        );
    }
}
