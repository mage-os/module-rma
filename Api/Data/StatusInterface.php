<?php

declare(strict_types=1);

namespace MageOS\RMA\Api\Data;

/**
 * @api
 */
interface StatusInterface
{
    const string ENTITY_ID = 'entity_id';
    const string CODE = 'code';
    const string LABEL = 'label';
    const string IS_ACTIVE = 'is_active';
    const string SORT_ORDER = 'sort_order';

    /**
     * @return int|null
     */
    public function getEntityId(): ?int;

    /**
     * @param int $entityId
     * @return $this
     */
    public function setEntityId(int $entityId): self;

    /**
     * @return string
     */
    public function getCode(): string;

    /**
     * @param string $code
     * @return $this
     */
    public function setCode(string $code): self;

    /**
     * @return string
     */
    public function getLabel(): string;

    /**
     * @param string $label
     * @return $this
     */
    public function setLabel(string $label): self;

    /**
     * @return int
     */
    public function getIsActive(): int;

    /**
     * @param int $isActive
     * @return $this
     */
    public function setIsActive(int $isActive): self;

    /**
     * @return int
     */
    public function getSortOrder(): int;

    /**
     * @param int $sortOrder
     * @return $this
     */
    public function setSortOrder(int $sortOrder): self;

    /**
     * @return string[]
     */
    public function getStoreLabels(): array;

    /**
     * @param string[] $storeLabels
     * @return $this
     */
    public function setStoreLabels(array $storeLabels): self;

    /**
     * @param int $storeId
     * @return string
     */
    public function getStoreLabel(int $storeId): string;
}
