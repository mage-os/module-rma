<?php

declare(strict_types=1);

namespace MageOS\RMA\Model\Data;

use MageOS\RMA\Api\Data\AttachmentInterface;
use MageOS\RMA\Api\Data\AttachmentSearchResultsInterface;
use Magento\Framework\Api\SearchResults;

class AttachmentSearchResults extends SearchResults implements AttachmentSearchResultsInterface
{
    /**
     * @return AttachmentInterface[]
     */
    public function getItems(): array
    {
        return parent::getItems();
    }

    /**
     * @param AttachmentInterface[] $items
     * @return $this
     */
    public function setItems(array $items): self
    {
        parent::setItems($items);

        return $this;
    }
}