<?php

declare(strict_types=1);

namespace MageOS\RMA\Console\Command;

use MageOS\RMA\Api\Data\RMAInterface;
use MageOS\RMA\Api\Data\StatusInterface;
use MageOS\RMA\Api\RMARepositoryInterface;
use MageOS\RMA\Model\RMA\StatusCodes;
use MageOS\RMA\Model\ResourceModel\Status\CollectionFactory as StatusCollectionFactory;
use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\FilterGroupBuilder;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Console\Cli;
use Magento\Framework\Exception\LocalizedException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CleanupCommand extends Command
{
    const OPTION_DAYS = 'days';
    const OPTION_DRY_RUN = 'dry-run';
    const CLOSED_STATUSES = [
        StatusCodes::RESOLVED,
        StatusCodes::REJECTED,
        StatusCodes::CANCELED_BY_CUSTOMER,
    ];

    /**
     * @param RMARepositoryInterface $rmaRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param FilterBuilder $filterBuilder
     * @param FilterGroupBuilder $filterGroupBuilder
     * @param StatusCollectionFactory $statusCollectionFactory
     */
    public function __construct(
        protected readonly RMARepositoryInterface $rmaRepository,
        protected readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        protected readonly FilterBuilder $filterBuilder,
        protected readonly FilterGroupBuilder $filterGroupBuilder,
        protected readonly StatusCollectionFactory $statusCollectionFactory
    ) {
        parent::__construct();
    }

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this->setName('rma:cleanup')
            ->setDescription('Cancel RMA requests that have been inactive for a given number of days')
            ->addOption(
                self::OPTION_DAYS,
                null,
                InputOption::VALUE_REQUIRED,
                'Number of days of inactivity after which to cancel RMAs'
            )
            ->addOption(
                self::OPTION_DRY_RUN,
                null,
                InputOption::VALUE_NONE,
                'Show which RMAs would be canceled without actually canceling them'
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $days = $input->getOption(self::OPTION_DAYS);

        if (!is_numeric($days) || (int)$days <= 0) {
            $output->writeln('<error>The --days option is required and must be a positive integer.</error>');
            return Cli::RETURN_FAILURE;
        }

        $days = (int)$days;
        $dryRun = (bool)$input->getOption(self::OPTION_DRY_RUN);

        try {
            $canceledStatusId = $this->getStatusIdByCode(StatusCodes::CANCELED_BY_CUSTOMER);
        } catch (LocalizedException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');
            return Cli::RETURN_FAILURE;
        }

        $closedStatusIds = $this->getClosedStatusIds();

        if (empty($closedStatusIds)) {
            $output->writeln('<error>Could not resolve closed status IDs.</error>');
            return Cli::RETURN_FAILURE;
        }

        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        // Filter: updated_at <= cutoff date
        $dateFilter = $this->filterBuilder
            ->setField(RMAInterface::UPDATED_AT)
            ->setConditionType('lteq')
            ->setValue($cutoffDate)
            ->create();

        // Filter: status_id NOT IN closed statuses
        $statusFilter = $this->filterBuilder
            ->setField(RMAInterface::STATUS_ID)
            ->setConditionType('nin')
            ->setValue($closedStatusIds)
            ->create();

        $dateFilterGroup = $this->filterGroupBuilder->setFilters([$dateFilter])->create();
        $statusFilterGroup = $this->filterGroupBuilder->setFilters([$statusFilter])->create();

        $searchCriteria = $this->searchCriteriaBuilder->create();
        $searchCriteria->setFilterGroups([$dateFilterGroup, $statusFilterGroup]);

        $results = $this->rmaRepository->getList($searchCriteria);

        if ($results->getTotalCount() === 0) {
            $output->writeln('<info>No RMA to clean up.</info>');
            return Cli::RETURN_SUCCESS;
        }

        $prefix = $dryRun ? '[DRY-RUN] ' : '';
        $count = 0;

        foreach ($results->getItems() as $rma) {
            $output->writeln(sprintf(
                '%s<comment>Canceling RMA %s (last updated: %s)</comment>',
                $prefix,
                $rma->getIncrementId(),
                $rma->getUpdatedAt()
            ));

            if (!$dryRun) {
                try {
                    $rma->setStatusId($canceledStatusId);
                    $this->rmaRepository->save($rma);
                } catch (LocalizedException $e) {
                    $output->writeln(sprintf(
                        '<error>Failed to cancel RMA %s: %s</error>',
                        $rma->getIncrementId(),
                        $e->getMessage()
                    ));
                    continue;
                }
            }

            $count++;
        }

        $output->writeln(sprintf(
            '<info>%s%d RMA(s) %s.</info>',
            $prefix,
            $count,
            $dryRun ? 'would be canceled' : 'canceled'
        ));

        return Cli::RETURN_SUCCESS;
    }

    /**
     * @param string $code
     * @return int
     * @throws LocalizedException
     */
    protected function getStatusIdByCode(string $code): int
    {
        $collection = $this->statusCollectionFactory->create();
        $collection->addFieldToFilter(StatusInterface::CODE, $code);
        $collection->setPageSize(1);

        $status = $collection->getFirstItem();

        if (!$status->getEntityId()) {
            throw new LocalizedException(__('Status with code "%1" not found.', $code));
        }

        return (int)$status->getEntityId();
    }

    /**
     * @return array
     */
    protected function getClosedStatusIds(): array
    {
        $collection = $this->statusCollectionFactory->create();
        $collection->addFieldToFilter(StatusInterface::CODE, ['in' => self::CLOSED_STATUSES]);

        return array_values(array_map(fn($status) => (int)$status->getEntityId(), $collection->getItems()));
    }
}
