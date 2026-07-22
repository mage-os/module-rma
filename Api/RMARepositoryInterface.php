<?php

declare(strict_types=1);

namespace MageOS\RMA\Api;

use MageOS\RMA\Api\Data\RMAInterface;
use MageOS\RMA\Api\Data\RMASearchResultsInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * @api
 */
interface RMARepositoryInterface
{
    /**
     * @param int $entityId
     * @return \MageOS\RMA\Api\Data\RMAInterface
     * @throws NoSuchEntityException
     */
    public function get(int $entityId): RMAInterface;

    /**
     * @param string $incrementId
     * @return \MageOS\RMA\Api\Data\RMAInterface
     * @throws NoSuchEntityException
     */
    public function getByIncrementId(string $incrementId): RMAInterface;

    /**
     * @param RMAInterface $rma
     * @return \MageOS\RMA\Api\Data\RMAInterface
     * @throws CouldNotSaveException
     */
    public function save(RMAInterface $rma): RMAInterface;

    /**
     * @param RMAInterface $rma
     * @return bool
     * @throws CouldNotDeleteException
     */
    public function delete(RMAInterface $rma): bool;

    /**
     * @param int $entityId
     * @return bool
     * @throws CouldNotDeleteException
     * @throws NoSuchEntityException
     */
    public function deleteById(int $entityId): bool;

    /**
     * @param SearchCriteriaInterface $searchCriteria
     * @return \MageOS\RMA\Api\Data\RMASearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): RMASearchResultsInterface;
}
