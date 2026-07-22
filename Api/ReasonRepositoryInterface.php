<?php

declare(strict_types=1);

namespace MageOS\RMA\Api;

use MageOS\RMA\Api\Data\ReasonInterface;
use MageOS\RMA\Api\Data\ReasonSearchResultsInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * @api
 */
interface ReasonRepositoryInterface
{
    /**
     * @param int $entityId
     * @return \MageOS\RMA\Api\Data\ReasonInterface
     * @throws NoSuchEntityException
     */
    public function get(int $entityId): ReasonInterface;

    /**
     * @param ReasonInterface $reason
     * @return \MageOS\RMA\Api\Data\ReasonInterface
     * @throws CouldNotSaveException
     */
    public function save(ReasonInterface $reason): ReasonInterface;

    /**
     * @param ReasonInterface $reason
     * @return bool
     * @throws CouldNotDeleteException
     */
    public function delete(ReasonInterface $reason): bool;

    /**
     * @param int $entityId
     * @return bool
     * @throws CouldNotDeleteException
     * @throws NoSuchEntityException
     */
    public function deleteById(int $entityId): bool;

    /**
     * @param SearchCriteriaInterface $searchCriteria
     * @return \MageOS\RMA\Api\Data\ReasonSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): ReasonSearchResultsInterface;
}
