<?php

declare(strict_types=1);

namespace MageOS\RMA\Api;

use MageOS\RMA\Api\Data\ItemInterface;
use MageOS\RMA\Api\Data\ItemSearchResultsInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * @api
 */
interface RmaItemManagementInterface
{
    /**
     * @param int $rmaId
     * @param SearchCriteriaInterface $searchCriteria
     * @return \MageOS\RMA\Api\Data\ItemSearchResultsInterface
     * @throws NoSuchEntityException
     */
    public function getList(int $rmaId, SearchCriteriaInterface $searchCriteria): ItemSearchResultsInterface;

    /**
     * @param int $rmaId
     * @param ItemInterface $item
     * @return \MageOS\RMA\Api\Data\ItemInterface
     * @throws NoSuchEntityException
     * @throws CouldNotSaveException
     * @throws LocalizedException
     */
    public function save(int $rmaId, ItemInterface $item): ItemInterface;

    /**
     * @param int $rmaId
     * @param int $itemId
     * @return bool
     * @throws NoSuchEntityException
     * @throws CouldNotDeleteException
     * @throws LocalizedException
     */
    public function deleteById(int $rmaId, int $itemId): bool;
}
