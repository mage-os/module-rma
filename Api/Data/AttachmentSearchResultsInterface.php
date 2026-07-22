<?php

declare(strict_types=1);

namespace MageOS\RMA\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

/**
 * @api
 */
interface AttachmentSearchResultsInterface extends SearchResultsInterface
{
    /**
     * @return \MageOS\RMA\Api\Data\AttachmentInterface[]
     */
    public function getItems(): array;

    /**
     * @param AttachmentInterface[] $items
     * @return $this
     */
    public function setItems(array $items): self;
}
