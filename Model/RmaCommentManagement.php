<?php

declare(strict_types=1);

namespace MageOS\RMA\Model;

use MageOS\RMA\Api\Data\CommentInterface;
use MageOS\RMA\Api\Data\CommentSearchResultsInterface;
use MageOS\RMA\Api\CommentRepositoryInterface;
use MageOS\RMA\Api\RMARepositoryInterface;
use MageOS\RMA\Api\RmaCommentManagementInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

class RmaCommentManagement extends AbstractRmaManagement implements RmaCommentManagementInterface
{
    /**
     * @param RMARepositoryInterface $rmaRepository
     * @param SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory
     * @param CommentRepositoryInterface $commentRepository
     */
    public function __construct(
        RMARepositoryInterface $rmaRepository,
        SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory,
        protected readonly CommentRepositoryInterface $commentRepository
    ) {
        parent::__construct($rmaRepository, $searchCriteriaBuilderFactory);
    }

    /**
     * @param int $rmaId
     * @param SearchCriteriaInterface $searchCriteria
     * @return CommentSearchResultsInterface
     * @throws NoSuchEntityException
     */
    public function getList(int $rmaId, SearchCriteriaInterface $searchCriteria): CommentSearchResultsInterface
    {
        $this->validateRmaExists($rmaId);

        return $this->commentRepository->getList(
            $this->buildScopedSearchCriteria($rmaId, $searchCriteria)
        );
    }

    /**
     * @param int $rmaId
     * @param CommentInterface $comment
     * @return CommentInterface
     * @throws NoSuchEntityException
     * @throws CouldNotSaveException
     * @throws LocalizedException
     */
    public function save(int $rmaId, CommentInterface $comment): CommentInterface
    {
        $this->validateRmaExists($rmaId);
        $comment->setRmaId($rmaId);

        return $this->commentRepository->save($comment);
    }
}
