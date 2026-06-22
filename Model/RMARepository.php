<?php

declare(strict_types=1);

namespace MageOS\RMA\Model;

use MageOS\RMA\Api\Data\RMAInterface;
use MageOS\RMA\Api\Data\RMASearchResultsInterface;
use MageOS\RMA\Api\Data\RMASearchResultsInterfaceFactory;
use MageOS\RMA\Api\RMARepositoryInterface;
use MageOS\RMA\Model\ResourceModel\RMA as ResourceModel;
use MageOS\RMA\Model\ResourceModel\RMA\CollectionFactory;
use MageOS\RMA\Model\RMA\StatusCodes;
use MageOS\RMA\Model\RMA\StatusResolver;
use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Exception\LocalizedException;
use Exception;

class RMARepository implements RMARepositoryInterface
{
    /**
     * @param ResourceModel $resourceModel
     * @param RMAFactory $rmaFactory
     * @param CollectionFactory $collectionFactory
     * @param RMASearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionProcessor
     * @param EventManagerInterface $eventManager
     * @param StatusResolver $statusResolver
     */
    public function __construct(
        protected readonly ResourceModel $resourceModel,
        protected readonly RMAFactory $rmaFactory,
        protected readonly CollectionFactory $collectionFactory,
        protected readonly RMASearchResultsInterfaceFactory $searchResultsFactory,
        protected readonly CollectionProcessorInterface $collectionProcessor,
        protected readonly EventManagerInterface $eventManager,
        protected readonly StatusResolver $statusResolver
    ) {
    }

    /**
     * @param int $entityId
     * @return RMAInterface
     * @throws NoSuchEntityException
     */
    public function get(int $entityId): RMAInterface
    {
        $rma = $this->rmaFactory->create();
        $this->resourceModel->load($rma, $entityId);

        if (!$rma->getEntityId()) {
            throw new NoSuchEntityException(__('The RMA with id "%1" does not exist.', $entityId));
        }

        return $rma;
    }

    /**
     * @param string $incrementId
     * @return RMAInterface
     * @throws NoSuchEntityException
     */
    public function getByIncrementId(string $incrementId): RMAInterface
    {
        $rma = $this->rmaFactory->create();
        $this->resourceModel->load($rma, $incrementId, RMAInterface::INCREMENT_ID);

        if (!$rma->getEntityId()) {
            throw new NoSuchEntityException(__('The RMA with increment id "%1" does not exist.', $incrementId));
        }

        return $rma;
    }

    /**
     * @param RMAInterface $rma
     * @return RMAInterface
     * @throws CouldNotSaveException
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function save(RMAInterface $rma): RMAInterface
    {
        $isNew = !$rma->getEntityId();
        $oldStatusId = null;

        if (!$isNew) {
            $oldStatusId = (int) $this->get((int) $rma->getEntityId())->getStatusId();
        }

        try {
            $this->resourceModel->save($rma);
            $rma->setData('is_new', $isNew);
        } catch (Exception $e) {
            throw new CouldNotSaveException(__('Could not save the RMA: %1', $e->getMessage()), $e);
        }

        $this->dispatchEvents($rma, $isNew, $oldStatusId);

        return $rma;
    }

    /**
     * @param RMAInterface $rma
     * @return bool
     * @throws CouldNotDeleteException
     */
    public function delete(RMAInterface $rma): bool
    {
        try {
            $this->resourceModel->delete($rma);
        } catch (Exception $e) {
            throw new CouldNotDeleteException(__('Could not delete the RMA: %1', $e->getMessage()), $e);
        }

        return true;
    }

    /**
     * @param int $entityId
     * @return bool
     * @throws CouldNotDeleteException
     * @throws NoSuchEntityException
     */
    public function deleteById(int $entityId): bool
    {
        return $this->delete($this->get($entityId));
    }

    /**
     * @param SearchCriteriaInterface $searchCriteria
     * @return RMASearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): RMASearchResultsInterface
    {
        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process($searchCriteria, $collection);

        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());

        return $searchResults;
    }

    /**
     * @param RMAInterface $rma
     * @param bool $isNew
     * @param int|null $oldStatusId
     * @return void
     */
    protected function dispatchEvents(RMAInterface $rma, bool $isNew, ?int $oldStatusId): void
    {
        if ($isNew) {
            $this->eventManager->dispatch('rma_created_after', ['rma' => $rma]);
            return;
        }

        $newStatusId = (int) $rma->getStatusId();

        if ($oldStatusId !== null && $oldStatusId !== $newStatusId) {
            $this->eventManager->dispatch('rma_status_change_after', [
                'rma' => $rma,
                'old_status_id' => $oldStatusId,
                'new_status_id' => $newStatusId,
            ]);

            $this->dispatchSemanticStatusEvent($rma, $newStatusId, $oldStatusId);
        }
    }

    /**
     * @param RMAInterface $rma
     * @param int $newStatusId
     * @param int $oldStatusId
     * @return void
     */
    protected function dispatchSemanticStatusEvent(RMAInterface $rma, int $newStatusId, int $oldStatusId): void
    {
        $statusCode = $this->statusResolver->getCodeById($newStatusId);

        if ($statusCode === null) {
            return;
        }

        $eventName = StatusCodes::STATUS_EVENT_MAP[$statusCode] ?? null;

        if ($eventName !== null) {
            $this->eventManager->dispatch($eventName, [
                'rma' => $rma,
                'old_status_id' => $oldStatusId,
                'new_status_id' => $newStatusId,
            ]);
        }
    }
}
