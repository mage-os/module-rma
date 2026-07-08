<?php

declare(strict_types=1);

namespace MageOS\RMA\Model\Data;

use MageOS\RMA\Api\Data\RMAInterface;
use MageOS\RMA\Api\Data\RMASearchResultsInterface;
use Magento\Framework\Api\SearchResults;

class RMASearchResults extends SearchResults implements RMASearchResultsInterface
{
    /**
     * @return RMAInterface[]
     */
    public function getItems(): array
    {
        return parent::getItems();
    }

    /**
     * @param RMAInterface[] $items
     * @return $this
     */
    public function setItems(array $items): self
    {
        parent::setItems($items);

        return $this;
    }
}