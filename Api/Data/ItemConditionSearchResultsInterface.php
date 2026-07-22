<?php

declare(strict_types=1);

namespace MageOS\RMA\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

/**
 * @api
 */
interface ItemConditionSearchResultsInterface extends SearchResultsInterface
{
    /**
     * @return \MageOS\RMA\Api\Data\ItemConditionInterface[]
     */
    public function getItems(): array;

    /**
     * @param ItemConditionInterface[] $items
     * @return $this
     */
    public function setItems(array $items): self;
}
