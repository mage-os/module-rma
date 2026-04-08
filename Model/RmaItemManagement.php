<?php

declare(strict_types=1);

namespace MageOS\RMA\Model;

use MageOS\RMA\Api\Data\ItemInterface;
use MageOS\RMA\Api\Data\ItemSearchResultsInterface;
use MageOS\RMA\Api\ItemRepositoryInterface;
use MageOS\RMA\Api\RMARepositoryInterface;
use MageOS\RMA\Api\RmaItemManagementInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

class RmaItemManagement extends AbstractRmaManagement implements RmaItemManagementInterface
{
    /**
     * @param RMARepositoryInterface $rmaRepository
     * @param SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory
     * @param ItemRepositoryInterface $itemRepository
     */
    public function __construct(
        RMARepositoryInterface $rmaRepository,
        SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory,
        protected readonly ItemRepositoryInterface $itemRepository
    ) {
        parent::__construct($rmaRepository, $searchCriteriaBuilderFactory);
    }

    /**
     * @param int $rmaId
     * @param SearchCriteriaInterface $searchCriteria
     * @return ItemSearchResultsInterface
     * @throws NoSuchEntityException
     */
    public function getList(int $rmaId, SearchCriteriaInterface $searchCriteria): ItemSearchResultsInterface
    {
        $this->validateRmaExists($rmaId);

        return $this->itemRepository->getList(
            $this->buildScopedSearchCriteria($rmaId, $searchCriteria)
        );
    }

    /**
     * @param int $rmaId
     * @param ItemInterface $item
     * @return ItemInterface
     * @throws NoSuchEntityException
     * @throws CouldNotSaveException
     * @throws LocalizedException
     */
    public function save(int $rmaId, ItemInterface $item): ItemInterface
    {
        $this->validateRmaExists($rmaId);
        $item->setRmaId($rmaId);

        return $this->itemRepository->save($item);
    }

    /**
     * @param int $rmaId
     * @param int $itemId
     * @return bool
     * @throws NoSuchEntityException
     * @throws CouldNotDeleteException
     * @throws LocalizedException
     */
    public function deleteById(int $rmaId, int $itemId): bool
    {
        $this->validateRmaExists($rmaId);

        $item = $this->itemRepository->get($itemId);
        if ($item->getRmaId() !== $rmaId) {
            throw new LocalizedException(__('Item does not belong to this RMA.'));
        }

        return $this->itemRepository->delete($item);
    }
}
