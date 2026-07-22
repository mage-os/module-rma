<?php

declare(strict_types=1);

namespace MageOS\RMA\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

/**
 * @api
 */
interface RMASearchResultsInterface extends SearchResultsInterface
{
    /**
     * @return \MageOS\RMA\Api\Data\RMAInterface[]
     */
    public function getItems(): array;

    /**
     * @param RMAInterface[] $items
     * @return $this
     */
    public function setItems(array $items): self;
}
