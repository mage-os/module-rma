<?php

declare(strict_types=1);

namespace MageOS\RMA\Model\Data;

use MageOS\RMA\Api\Data\ItemInterface;
use MageOS\RMA\Api\Data\ItemSearchResultsInterface;
use Magento\Framework\Api\SearchResults;

class ItemSearchResults extends SearchResults implements ItemSearchResultsInterface
{
    /**
     * @return ItemInterface[]
     */
    public function getItems(): array
    {
        return parent::getItems();
    }

    /**
     * @param ItemInterface[] $items
     * @return $this
     */
    public function setItems(array $items): self
    {
        parent::setItems($items);

        return $this;
    }
}