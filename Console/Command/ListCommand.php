<?php

declare(strict_types=1);

namespace MageOS\RMA\Console\Command;

use MageOS\RMA\Api\Data\RMAInterface;
use MageOS\RMA\Api\Data\StatusInterface;
use MageOS\RMA\Api\RMARepositoryInterface;
use MageOS\RMA\Api\StatusRepositoryInterface;
use MageOS\RMA\Model\ResourceModel\Status\CollectionFactory as StatusCollectionFactory;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\FilterGroupBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Console\Cli;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ListCommand extends Command
{
    const OPTION_STATUS = 'status';
    const OPTION_FROM = 'from';
    const OPTION_TO = 'to';
    const OPTION_STORE = 'store';

    /**
     * @param RMARepositoryInterface $rmaRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param FilterBuilder $filterBuilder
     * @param FilterGroupBuilder $filterGroupBuilder
     * @param SortOrderBuilder $sortOrderBuilder
     * @param StatusRepositoryInterface $statusRepository
     * @param StatusCollectionFactory $statusCollectionFactory
     * @param OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        protected readonly RMARepositoryInterface $rmaRepository,
        protected readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        protected readonly FilterBuilder $filterBuilder,
        protected readonly FilterGroupBuilder $filterGroupBuilder,
        protected readonly SortOrderBuilder $sortOrderBuilder,
        protected readonly StatusRepositoryInterface $statusRepository,
        protected readonly StatusCollectionFactory $statusCollectionFactory,
        protected readonly OrderRepositoryInterface $orderRepository
    ) {
        parent::__construct();
    }

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this->setName('rma:list')
            ->setDescription('List RMA requests with optional filters')
            ->addOption(
                self::OPTION_STATUS,
                null,
                InputOption::VALUE_REQUIRED,
                'Filter by status code (e.g. new_request, approved, resolved)'
            )
            ->addOption(
                self::OPTION_FROM,
                null,
                InputOption::VALUE_REQUIRED,
                'Filter by creation date from (format: Y-m-d)'
            )
            ->addOption(
                self::OPTION_TO,
                null,
                InputOption::VALUE_REQUIRED,
                'Filter by creation date to (format: Y-m-d)'
            )
            ->addOption(
                self::OPTION_STORE,
                null,
                InputOption::VALUE_REQUIRED,
                'Filter by store ID'
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filterGroups = [];

        // Filter by status code
        $statusCode = $input->getOption(self::OPTION_STATUS);
        if ($statusCode !== null) {
            $statusId = $this->getStatusIdByCode((string)$statusCode);

            if ($statusId === null) {
                $output->writeln(sprintf('<error>Status code "%s" not found.</error>', $statusCode));
                return Cli::RETURN_FAILURE;
            }

            $filter = $this->filterBuilder
                ->setField(RMAInterface::STATUS_ID)
                ->setConditionType('eq')
                ->setValue($statusId)
                ->create();
            $filterGroups[] = $this->filterGroupBuilder->setFilters([$filter])->create();
        }

        // Filter by date from
        $from = $input->getOption(self::OPTION_FROM);
        if ($from !== null) {
            $filter = $this->filterBuilder
                ->setField(RMAInterface::CREATED_AT)
                ->setConditionType('gteq')
                ->setValue($from . ' 00:00:00')
                ->create();
            $filterGroups[] = $this->filterGroupBuilder->setFilters([$filter])->create();
        }

        // Filter by date to
        $to = $input->getOption(self::OPTION_TO);
        if ($to !== null) {
            $filter = $this->filterBuilder
                ->setField(RMAInterface::CREATED_AT)
                ->setConditionType('lteq')
                ->setValue($to . ' 23:59:59')
                ->create();
            $filterGroups[] = $this->filterGroupBuilder->setFilters([$filter])->create();
        }

        // Filter by store
        $storeId = $input->getOption(self::OPTION_STORE);
        if ($storeId !== null) {
            $filter = $this->filterBuilder
                ->setField(RMAInterface::STORE_ID)
                ->setConditionType('eq')
                ->setValue((int)$storeId)
                ->create();
            $filterGroups[] = $this->filterGroupBuilder->setFilters([$filter])->create();
        }

        $sortOrder = $this->sortOrderBuilder
            ->setField(RMAInterface::CREATED_AT)
            ->setDescendingDirection()
            ->create();

        $searchCriteria = $this->searchCriteriaBuilder->create();
        $searchCriteria->setFilterGroups($filterGroups);
        $searchCriteria->setSortOrders([$sortOrder]);

        $results = $this->rmaRepository->getList($searchCriteria);

        if ($results->getTotalCount() === 0) {
            $output->writeln('<info>No RMA found.</info>');
            return Cli::RETURN_SUCCESS;
        }

        $statusLabels = $this->loadStatusLabels();

        $table = new Table($output);
        $table->setHeaders(['Increment ID', 'Order #', 'Customer Name', 'Customer Email', 'Status', 'Created At']);

        foreach ($results->getItems() as $rma) {
            $orderIncrementId = $this->getOrderIncrementId($rma->getOrderId());
            $statusLabel = $statusLabels[(int)$rma->getStatusId()] ?? 'Unknown';

            $table->addRow([
                $rma->getIncrementId(),
                '#' . $orderIncrementId,
                $rma->getCustomerName(),
                $rma->getCustomerEmail(),
                $statusLabel,
                $rma->getCreatedAt(),
            ]);
        }

        $table->render();
        $output->writeln(sprintf('<info>Total: %d RMA(s)</info>', $results->getTotalCount()));

        return Cli::RETURN_SUCCESS;
    }

    /**
     * @param string $code
     * @return int|null
     */
    protected function getStatusIdByCode(string $code): ?int
    {
        $collection = $this->statusCollectionFactory->create();
        $collection->addFieldToFilter(StatusInterface::CODE, $code);
        $collection->setPageSize(1);

        $status = $collection->getFirstItem();

        return $status->getEntityId() ? (int)$status->getEntityId() : null;
    }

    /**
     * @return array
     */
    protected function loadStatusLabels(): array
    {
        $collection = $this->statusCollectionFactory->create();
        $labels = [];

        foreach ($collection as $status) {
            $labels[(int)$status->getEntityId()] = $status->getLabel();
        }

        return $labels;
    }

    /**
     * @param int $orderId
     * @return string
     */
    protected function getOrderIncrementId(int $orderId): string
    {
        try {
            return $this->orderRepository->get($orderId)->getIncrementId();
        } catch (NoSuchEntityException) {
            return (string)$orderId;
        }
    }
}
