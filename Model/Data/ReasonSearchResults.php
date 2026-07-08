<?php

declare(strict_types=1);

namespace MageOS\RMA\Model\Data;

use MageOS\RMA\Api\Data\ReasonInterface;
use MageOS\RMA\Api\Data\ReasonSearchResultsInterface;
use Magento\Framework\Api\SearchResults;

class ReasonSearchResults extends SearchResults implements ReasonSearchResultsInterface
{
    /**
     * @return ReasonInterface[]
     */
    public function getItems(): array
    {
        return parent::getItems();
    }

    /**
     * @param ReasonInterface[] $items
     * @return $this
     */
    public function setItems(array $items): self
    {
        parent::setItems($items);

        return $this;
    }
}