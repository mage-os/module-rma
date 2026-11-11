<?php

declare(strict_types=1);

namespace MageOS\RMA\Test\Unit\Model;

use MageOS\RMA\Api\Data\RMAInterface;
use MageOS\RMA\Api\Data\RMASearchResultsInterface;
use MageOS\RMA\Model\RMA;
use MageOS\RMA\Api\Data\RMASearchResultsInterfaceFactory;
use MageOS\RMA\Model\RMA\StatusCodes;
use MageOS\RMA\Model\RMA\StatusResolver;
use MageOS\RMA\Model\RMAFactory;
use MageOS\RMA\Model\RMARepository;
use MageOS\RMA\Model\ResourceModel\RMA as ResourceModel;
use MageOS\RMA\Model\ResourceModel\RMA\CollectionFactory;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class RMARepositoryTest extends TestCase
{
    private ResourceModel&MockObject $resourceModel;
    private RMAFactory&MockObject $rmaFactory;
    private CollectionFactory&MockObject $collectionFactory;
    private RMASearchResultsInterfaceFactory&MockObject $searchResultsFactory;
    private CollectionProcessorInterface&MockObject $collectionProcessor;
    private EventManagerInterface&MockObject $eventManager;
    private StatusResolver&MockObject $statusResolver;
    private RMARepository $repository;

    protected function setUp(): void
    {
        $this->resourceModel      = $this->createMock(ResourceModel::class);
        $this->rmaFactory         = $this->createMock(RMAFactory::class);
        $this->collectionFactory  = $this->createMock(CollectionFactory::class);
        $this->searchResultsFactory = $this->createMock(RMASearchResultsInterfaceFactory::class);
        $this->collectionProcessor  = $this->createMock(CollectionProcessorInterface::class);
        $this->eventManager       = $this->createMock(EventManagerInterface::class);
        $this->statusResolver     = $this->createMock(StatusResolver::class);

        $this->repository = new RMARepository(
            $this->resourceModel,
            $this->rmaFactory,
            $this->collectionFactory,
            $this->searchResultsFactory,
            $this->collectionProcessor,
            $this->eventManager,
            $this->statusResolver
        );
    }

    /**
     * save() reads the stored status through get(), which loads a fresh RMA from the factory.
     */
    private function stubStoredRma(int $entityId, int $statusId): void
    {
        $storedRma = $this->createMock(RMA::class);
        $storedRma->method('getEntityId')->willReturn($entityId);
        $storedRma->method('getStatusId')->willReturn($statusId);

        $this->rmaFactory->method('create')->willReturn($storedRma);
    }

    // -------------------------------------------------------------------------
    // get
    // -------------------------------------------------------------------------

    public function testGetReturnsRmaWhenFound(): void
    {
        $rma = $this->createMock(RMA::class);
        $rma->method('getEntityId')->willReturn(1);

        $this->rmaFactory->method('create')->willReturn($rma);

        $result = $this->repository->get(1);

        $this->assertSame($rma, $result);
    }

    public function testGetThrowsNoSuchEntityExceptionWhenNotFound(): void
    {
        $this->expectException(NoSuchEntityException::class);

        $rma = $this->createMock(RMA::class);
        $rma->method('getEntityId')->willReturn(null);

        $this->rmaFactory->method('create')->willReturn($rma);

        $this->repository->get(999);
    }

    // -------------------------------------------------------------------------
    // save — exception wrapping
    // -------------------------------------------------------------------------

    public function testSaveThrowsCouldNotSaveExceptionOnResourceModelFailure(): void
    {
        $this->expectException(CouldNotSaveException::class);

        $rma = $this->createMock(RMA::class);
        $rma->method('getEntityId')->willReturn(null);

        $this->resourceModel->method('save')->willThrowException(new \Exception('DB error'));

        $this->repository->save($rma);
    }

    // -------------------------------------------------------------------------
    // delete — exception wrapping
    // -------------------------------------------------------------------------

    public function testDeleteThrowsCouldNotDeleteExceptionOnResourceModelFailure(): void
    {
        $this->expectException(CouldNotDeleteException::class);

        $rma = $this->createMock(RMA::class);
        $this->resourceModel->method('delete')->willThrowException(new \Exception('DB error'));

        $this->repository->delete($rma);
    }

    public function testDeleteReturnsTrueOnSuccess(): void
    {
        $rma = $this->createMock(RMA::class);

        $this->assertTrue($this->repository->delete($rma));
    }

    // -------------------------------------------------------------------------
    // dispatchEvents — new RMA
    // -------------------------------------------------------------------------

    public function testSaveDispatchesCreatedEventForNewRma(): void
    {
        $rma = $this->createMock(RMA::class);
        $rma->method('getEntityId')->willReturn(null);

        $this->eventManager->expects($this->once())
            ->method('dispatch')
            ->with('rma_created_after', ['rma' => $rma]);

        $this->repository->save($rma);
    }

    public function testSaveDoesNotDispatchStatusChangeEventForNewRma(): void
    {
        $rma = $this->createMock(RMA::class);
        $rma->method('getEntityId')->willReturn(null);

        $this->eventManager->expects($this->once())
            ->method('dispatch')
            ->with('rma_created_after', $this->anything());

        $this->repository->save($rma);
    }

    // -------------------------------------------------------------------------
    // dispatchEvents — existing RMA, status unchanged
    // -------------------------------------------------------------------------

    public function testSaveDispatchesNoEventsWhenStatusUnchanged(): void
    {
        $rma = $this->createMock(RMA::class);
        $rma->method('getEntityId')->willReturn(10);
        $rma->method('getStatusId')->willReturn(2);

        $this->stubStoredRma(10, 2);

        $this->eventManager->expects($this->never())->method('dispatch');

        $this->repository->save($rma);
    }

    // -------------------------------------------------------------------------
    // dispatchEvents — existing RMA, status changed
    // -------------------------------------------------------------------------

    public function testSaveDispatchesStatusChangeEventWhenStatusChanges(): void
    {
        $rma = $this->createMock(RMA::class);
        $rma->method('getEntityId')->willReturn(10);
        $rma->method('getStatusId')->willReturn(3);

        $this->stubStoredRma(10, 2);
        $this->statusResolver->method('getCodeById')->with(3)->willReturn('need_details');

        $this->eventManager->expects($this->once())
            ->method('dispatch')
            ->with('rma_status_change_after', [
                'rma'           => $rma,
                'old_status_id' => 2,
                'new_status_id' => 3,
            ]);

        $this->repository->save($rma);
    }

    // -------------------------------------------------------------------------
    // dispatchSemanticStatusEvent
    // -------------------------------------------------------------------------

    #[DataProvider('semanticStatusEventProvider')]
    public function testSaveDispatchesSemanticEventForKnownStatusCode(
        string $statusCode,
        string $expectedEvent
    ): void {
        $rma = $this->createMock(RMA::class);
        $rma->method('getEntityId')->willReturn(10);
        $rma->method('getStatusId')->willReturn(5);

        $this->stubStoredRma(10, 1);
        $this->statusResolver->method('getCodeById')->with(5)->willReturn($statusCode);

        $dispatched = [];
        $this->eventManager->method('dispatch')
            ->willReturnCallback(function (string $name, array $data) use (&$dispatched): void {
                $dispatched[] = $name;
            });

        $this->repository->save($rma);

        $this->assertContains('rma_status_change_after', $dispatched);
        $this->assertContains($expectedEvent, $dispatched);
    }

    public static function semanticStatusEventProvider(): array
    {
        return [
            'approved'             => [StatusCodes::APPROVED,             'rma_approved_after'],
            'rejected'             => [StatusCodes::REJECTED,             'rma_rejected_after'],
            'shipped_by_customer'  => [StatusCodes::SHIPPED_BY_CUSTOMER,  'rma_shipped_by_customer_after'],
            'received_by_admin'    => [StatusCodes::RECEIVED_BY_ADMIN,    'rma_received_after'],
            'canceled_by_customer' => [StatusCodes::CANCELED_BY_CUSTOMER, 'rma_canceled_after'],
            'resolved'             => [StatusCodes::RESOLVED,             'rma_resolved_after'],
        ];
    }

    public function testSaveDoesNotDispatchSemanticEventForCodeNotInMap(): void
    {
        $rma = $this->createMock(RMA::class);
        $rma->method('getEntityId')->willReturn(10);
        $rma->method('getStatusId')->willReturn(5);

        $this->stubStoredRma(10, 1);
        // need_details is not in STATUS_EVENT_MAP
        $this->statusResolver->method('getCodeById')->willReturn(StatusCodes::NEED_DETAILS);

        $dispatched = [];
        $this->eventManager->method('dispatch')
            ->willReturnCallback(function (string $name) use (&$dispatched): void {
                $dispatched[] = $name;
            });

        $this->repository->save($rma);

        $this->assertContains('rma_status_change_after', $dispatched);
        $this->assertCount(1, $dispatched);
    }

    public function testSaveDoesNotDispatchSemanticEventWhenStatusCodeIsNull(): void
    {
        $rma = $this->createMock(RMA::class);
        $rma->method('getEntityId')->willReturn(10);
        $rma->method('getStatusId')->willReturn(5);

        $this->stubStoredRma(10, 1);
        $this->statusResolver->method('getCodeById')->willReturn(null);

        $dispatched = [];
        $this->eventManager->method('dispatch')
            ->willReturnCallback(function (string $name) use (&$dispatched): void {
                $dispatched[] = $name;
            });

        $this->repository->save($rma);

        $this->assertContains('rma_status_change_after', $dispatched);
        $this->assertCount(1, $dispatched);
    }
}
