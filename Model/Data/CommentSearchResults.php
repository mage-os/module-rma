<?php

declare(strict_types=1);

namespace MageOS\RMA\Model\Data;

use MageOS\RMA\Api\Data\CommentInterface;
use MageOS\RMA\Api\Data\CommentSearchResultsInterface;
use Magento\Framework\Api\SearchResults;

class CommentSearchResults extends SearchResults implements CommentSearchResultsInterface
{
    /**
     * @return CommentInterface[]
     */
    public function getItems(): array
    {
        return parent::getItems();
    }

    /**
     * @param CommentInterface[] $items
     * @return $this
     */
    public function setItems(array $items): self
    {
        parent::setItems($items);

        return $this;
    }
}