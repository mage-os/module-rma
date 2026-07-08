<?php

declare(strict_types=1);

namespace MageOS\RMA\Service;

use MageOS\RMA\Api\ItemConditionRepositoryInterface;
use MageOS\RMA\Api\ReasonRepositoryInterface;
use MageOS\RMA\Api\ResolutionTypeRepositoryInterface;
use MageOS\RMA\Api\StatusRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

class LabelResolver
{
    const string TYPE_STATUS = 'status';
    const string TYPE_REASON = 'reason';
    const string TYPE_RESOLUTION_TYPE = 'resolution_type';
    const string TYPE_ITEM_CONDITION = 'item_condition';

    /**
     * @param StatusRepositoryInterface $statusRepository
     * @param ReasonRepositoryInterface $reasonRepository
     * @param ResolutionTypeRepositoryInterface $resolutionTypeRepository
     * @param ItemConditionRepositoryInterface $itemConditionRepository
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        protected readonly StatusRepositoryInterface $statusRepository,
        protected readonly ReasonRepositoryInterface $reasonRepository,
        protected readonly ResolutionTypeRepositoryInterface $resolutionTypeRepository,
        protected readonly ItemConditionRepositoryInterface $itemConditionRepository,
        protected readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @param string $type
     * @param int $entityId
     * @param int|null $storeId
     * @return string
     */
    public function resolve(string $type, int $entityId, ?int $storeId = null): string
    {
        $storeId ??= $this->getCurrentStoreId();

        try {
            $entity = $this->loadEntity($type, $entityId);

            return (string)__($entity->getStoreLabel($storeId));
        } catch (NoSuchEntityException) {
            return '';
        }
    }

    /**
     * @param string $type
     * @param int $entityId
     * @param int|null $storeId
     * @return array|null
     */
    public function resolveAsArray(string $type, int $entityId, ?int $storeId = null): ?array
    {
        $storeId ??= $this->getCurrentStoreId();

        try {
            $entity = $this->loadEntity($type, $entityId);

            return [
                'id' => $entity->getEntityId(),
                'code' => $entity->getCode(),
                'label' => (string)__($entity->getStoreLabel($storeId)),
            ];
        } catch (NoSuchEntityException) {
            return null;
        }
    }

    /**
     * @param string $type
     * @param int $entityId
     * @return object
     * @throws NoSuchEntityException
     */
    protected function loadEntity(string $type, int $entityId): object
    {
        return match ($type) {
            self::TYPE_STATUS => $this->statusRepository->get($entityId),
            self::TYPE_REASON => $this->reasonRepository->get($entityId),
            self::TYPE_RESOLUTION_TYPE => $this->resolutionTypeRepository->get($entityId),
            self::TYPE_ITEM_CONDITION => $this->itemConditionRepository->get($entityId),
            default => throw new NoSuchEntityException(__('Unknown lookup type "%1".', $type)),
        };
    }

    /**
     * @return int
     */
    protected function getCurrentStoreId(): int
    {
        try {
            return (int)$this->storeManager->getStore()->getId();
        } catch (NoSuchEntityException) {
            return 0;
        }
    }
}
