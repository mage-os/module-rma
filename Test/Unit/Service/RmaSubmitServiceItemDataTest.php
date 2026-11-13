<?php

declare(strict_types=1);

namespace MageOS\RMA\Test\Unit\Service;

use MageOS\RMA\Api\Data\ItemInterface;
use MageOS\RMA\Api\Data\RMAInterfaceFactory;
use MageOS\RMA\Api\ItemRepositoryInterface;
use MageOS\RMA\Api\RMARepositoryInterface;
use MageOS\RMA\Helper\ModuleConfig;
use MageOS\RMA\Model\Item;
use MageOS\RMA\Model\ItemFactory;
use MageOS\RMA\Model\ResourceModel\Status\CollectionFactory as StatusCollectionFactory;
use MageOS\RMA\Service\AttachmentService;
use MageOS\RMA\Service\OrderEligibility;
use MageOS\RMA\Service\RmaSubmitService;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Per-item data posted next to the known fields: kept by getSelectedItems() and handed to the
 * "rma_item_create_before" / "rma_item_create_after" events by saveItems().
 */
class RmaSubmitServiceItemDataTest extends TestCase
{
    private ItemFactory&MockObject $itemFactory;
    private ItemRepositoryInterface&MockObject $itemRepository;
    private OrderEligibility&MockObject $orderEligibility;
    private EventManagerInterface&MockObject $eventManager;
    private RmaSubmitService $service;

    /**
     * @var array<int, array{0: string, 1: array}>
     */
    private array $calls = [];

    protected function setUp(): void
    {
        $this->itemFactory = $this->createMock(ItemFactory::class);
        $this->itemRepository = $this->createMock(ItemRepositoryInterface::class);
        $this->orderEligibility = $this->createMock(OrderEligibility::class);
        $this->eventManager = $this->createMock(EventManagerInterface::class);

        $this->eventManager->method('dispatch')->willReturnCallback(
            function (string $eventName, array $data = []): void {
                $this->calls[] = [$eventName, $data];
            }
        );
        $this->itemRepository->method('save')->willReturnCallback(
            function (ItemInterface $item): ItemInterface {
                $this->calls[] = ['save', ['item' => $item]];
                return $item;
            }
        );

        $this->service = new RmaSubmitService(
            $this->createMock(RMARepositoryInterface::class),
            $this->createMock(RMAInterfaceFactory::class),
            $this->itemFactory,
            $this->itemRepository,
            $this->createMock(StatusCollectionFactory::class),
            $this->createMock(ModuleConfig::class),
            $this->createMock(AttachmentService::class),
            $this->orderEligibility,
            $this->createMock(ResourceConnection::class),
            $this->eventManager
        );
    }

    public function testGetSelectedItemsKeepsExtraPostedFields(): void
    {
        $result = $this->service->getSelectedItems([
            10 => ['selected' => '1', 'qty_requested' => '2', 'condition_id' => '3', 'reason_id' => '7'],
            20 => ['selected' => '', 'qty_requested' => '1', 'reason_id' => '8'],
        ]);

        $this->assertSame(
            [10 => ['qty_requested' => 2, 'condition_id' => 3, 'reason_id' => '7']],
            $result
        );
    }

    public function testSaveItemsDispatchesCreateEventsAroundEachItem(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $this->orderEligibility->method('getEligibleItems')->willReturn([
            ['order_item_id' => 1, 'qty_available' => 2],
        ]);

        $item = $this->createMock(Item::class);
        $this->itemFactory->method('create')->willReturn($item);

        $itemData = ['qty_requested' => 1, 'condition_id' => null, 'reason_id' => '7'];
        $this->service->saveItems(rmaId: 10, selectedItems: [1 => $itemData], order: $order);

        $this->assertSame(
            ['rma_item_create_before', 'save', 'rma_item_create_after'],
            array_column($this->calls, 0)
        );

        $expected = ['item' => $item, 'item_data' => $itemData, 'order' => $order];
        $this->assertSame($expected, $this->calls[0][1]);
        $this->assertSame($expected, $this->calls[2][1]);
    }

    public function testSaveItemsDispatchesNothingForAnItemThatIsNotEligible(): void
    {
        $order = $this->createMock(OrderInterface::class);
        $order->method('getIncrementId')->willReturn('000000001');
        $this->orderEligibility->method('getEligibleItems')->willReturn([]);

        try {
            $this->service->saveItems(
                rmaId: 10,
                selectedItems: [1 => ['qty_requested' => 1, 'condition_id' => null]],
                order: $order
            );
            $this->fail('An item that is not eligible must not be saved.');
        } catch (LocalizedException) {
            $this->assertSame([], $this->calls);
        }
    }
}
