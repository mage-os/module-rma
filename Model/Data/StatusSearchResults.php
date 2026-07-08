<?php

declare(strict_types=1);

namespace MageOS\RMA\Model\Data;

use MageOS\RMA\Api\Data\StatusInterface;
use MageOS\RMA\Api\Data\StatusSearchResultsInterface;
use Magento\Framework\Api\SearchResults;

class StatusSearchResults extends SearchResults implements StatusSearchResultsInterface
{
    /**
     * @return StatusInterface[]
     */
    public function getItems(): array
    {
        return parent::getItems();
    }

    /**
     * @param StatusInterface[] $items
     * @return $this
     */
    public function setItems(array $items): self
    {
        parent::setItems($items);

        return $this;
    }
}