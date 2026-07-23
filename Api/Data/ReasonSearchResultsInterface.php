<?php

declare(strict_types=1);

namespace MageOS\RMA\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

/**
 * @api
 */
interface ReasonSearchResultsInterface extends SearchResultsInterface
{
    /**
     * @return \MageOS\RMA\Api\Data\ReasonInterface[]
     */
    public function getItems(): array;

    /**
     * @param \MageOS\RMA\Api\Data\ReasonInterface[] $items
     * @return $this
     */
    public function setItems(array $items): self;
}
