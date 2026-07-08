<?php

declare(strict_types=1);

namespace MageOS\RMA\Model\Data;

use MageOS\RMA\Api\Data\ResolutionTypeInterface;
use MageOS\RMA\Api\Data\ResolutionTypeSearchResultsInterface;
use Magento\Framework\Api\SearchResults;

class ResolutionTypeSearchResults extends SearchResults implements ResolutionTypeSearchResultsInterface
{
    /**
     * @return ResolutionTypeInterface[]
     */
    public function getItems(): array
    {
        return parent::getItems();
    }

    /**
     * @param ResolutionTypeInterface[] $items
     * @return $this
     */
    public function setItems(array $items): self
    {
        parent::setItems($items);

        return $this;
    }
}