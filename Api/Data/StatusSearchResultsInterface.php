<?php

declare(strict_types=1);

namespace MageOS\RMA\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

/**
 * @api
 */
interface StatusSearchResultsInterface extends SearchResultsInterface
{
    /**
     * @return \MageOS\RMA\Api\Data\StatusInterface[]
     */
    public function getItems(): array;

    /**
     * @param StatusInterface[] $items
     * @return $this
     */
    public function setItems(array $items): self;
}
