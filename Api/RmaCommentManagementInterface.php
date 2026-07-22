<?php

declare(strict_types=1);

namespace MageOS\RMA\Api;

use MageOS\RMA\Api\Data\CommentInterface;
use MageOS\RMA\Api\Data\CommentSearchResultsInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * @api
 */
interface RmaCommentManagementInterface
{
    /**
     * @param int $rmaId
     * @param SearchCriteriaInterface $searchCriteria
     * @return \MageOS\RMA\Api\Data\CommentSearchResultsInterface
     * @throws NoSuchEntityException
     */
    public function getList(int $rmaId, SearchCriteriaInterface $searchCriteria): CommentSearchResultsInterface;

    /**
     * @param int $rmaId
     * @param CommentInterface $comment
     * @return \MageOS\RMA\Api\Data\CommentInterface
     * @throws NoSuchEntityException
     * @throws CouldNotSaveException
     * @throws LocalizedException
     */
    public function save(int $rmaId, CommentInterface $comment): CommentInterface;
}
