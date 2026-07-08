<?php

declare(strict_types=1);

namespace MageOS\RMA\Model\Data;

use MageOS\RMA\Api\Data\ItemConditionInterface;
use MageOS\RMA\Api\Data\ItemConditionSearchResultsInterface;
use Magento\Framework\Api\SearchResults;

class ItemConditionSearchResults extends SearchResults implements ItemConditionSearchResultsInterface
{
    /**
     * @return ItemConditionInterface[]
     */
    public function getItems(): array
    {
        return parent::getItems();
    }

    /**
     * @param ItemConditionInterface[] $items
     * @return $this
     */
    public function setItems(array $items): self
    {
        parent::setItems($items);

        return $this;
    }
}