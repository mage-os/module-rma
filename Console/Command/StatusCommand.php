<?php

declare(strict_types=1);

namespace MageOS\RMA\Console\Command;

use MageOS\RMA\Api\Data\RMAInterface;
use MageOS\RMA\Api\RMARepositoryInterface;
use MageOS\RMA\Api\StatusRepositoryInterface;
use MageOS\RMA\Api\ReasonRepositoryInterface;
use MageOS\RMA\Api\ResolutionTypeRepositoryInterface;
use MageOS\RMA\Api\ItemConditionRepositoryInterface;
use MageOS\RMA\Model\ResourceModel\Item\CollectionFactory as ItemCollectionFactory;
use Magento\Framework\Console\Cli;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\OrderItemRepositoryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class StatusCommand extends Command
{
    const ARG_INCREMENT_ID = 'increment_id';

    /**
     * @param RMARepositoryInterface $rmaRepository
     * @param StatusRepositoryInterface $statusRepository
     * @param ReasonRepositoryInterface $reasonRepository
     * @param ResolutionTypeRepositoryInterface $resolutionTypeRepository
     * @param ItemConditionRepositoryInterface $itemConditionRepository
     * @param OrderRepositoryInterface $orderRepository
     * @param OrderItemRepositoryInterface $orderItemRepository
     * @param ItemCollectionFactory $itemCollectionFactory
     */
    public function __construct(
        protected readonly RMARepositoryInterface $rmaRepository,
        protected readonly StatusRepositoryInterface $statusRepository,
        protected readonly ReasonRepositoryInterface $reasonRepository,
        protected readonly ResolutionTypeRepositoryInterface $resolutionTypeRepository,
        protected readonly ItemConditionRepositoryInterface $itemConditionRepository,
        protected readonly OrderRepositoryInterface $orderRepository,
        protected readonly OrderItemRepositoryInterface $orderItemRepository,
        protected readonly ItemCollectionFactory $itemCollectionFactory
    ) {
        parent::__construct();
    }

    /**
     * @return void
     */
    protected function configure(): void
    {
        $this->setName('rma:status')
            ->setDescription('Show detailed information about an RMA by its increment ID')
            ->addArgument(
                self::ARG_INCREMENT_ID,
                InputArgument::REQUIRED,
                'The RMA increment ID (e.g. RMA-000001)'
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $incrementId = $input->getArgument(self::ARG_INCREMENT_ID);

        try {
            $rma = $this->rmaRepository->getByIncrementId($incrementId);
        } catch (NoSuchEntityException) {
            $output->writeln(sprintf('<error>RMA "%s" not found.</error>', $incrementId));
            return Cli::RETURN_FAILURE;
        }

        $this->renderRmaInfo($output, $rma);
        $this->renderItems($output, $rma);

        return Cli::RETURN_SUCCESS;
    }

    /**
     * @param OutputInterface $output
     * @param RMAInterface $rma
     * @return void
     */
    protected function renderRmaInfo(OutputInterface $output, RMAInterface $rma): void
    {
        $output->writeln('');
        $output->writeln(sprintf('<info>RMA: %s</info>', $rma->getIncrementId()));
        $output->writeln(str_repeat('-', 50));

        $rows = [
            ['Entity ID', (string)$rma->getEntityId()],
            ['Increment ID', $rma->getIncrementId()],
            ['Order #', '#' . $this->getOrderIncrementId($rma->getOrderId())],
            ['Customer Name', $rma->getCustomerName()],
            ['Customer Email', $rma->getCustomerEmail()],
            ['Store ID', (string)$rma->getStoreId()],
            ['Status', $this->getStatusLabel($rma->getStatusId())],
            ['Reason', $this->getReasonLabel($rma->getReasonId())],
            ['Resolution Type', $this->getResolutionTypeLabel($rma->getResolutionTypeId())],
            ['Created At', $rma->getCreatedAt()],
            ['Updated At', $rma->getUpdatedAt()],
        ];

        $table = new Table($output);
        $table->setStyle('compact');

        foreach ($rows as $row) {
            $table->addRow([sprintf('<comment>%s:</comment>', $row[0]), $row[1]]);
        }

        $table->render();
    }

    /**
     * @param OutputInterface $output
     * @param RMAInterface $rma
     * @return void
     */
    protected function renderItems(OutputInterface $output, RMAInterface $rma): void
    {
        $collection = $this->itemCollectionFactory->create();
        $collection->addFieldToFilter('rma_id', $rma->getEntityId());

        $output->writeln('');
        if ($collection->getSize() === 0) {
            $output->writeln('<comment>No items.</comment>');
            return;
        }
        $output->writeln('<info>Items:</info>');

        $table = new Table($output);
        $table->setHeaders(['Product', 'SKU', 'Qty Requested', 'Qty Approved', 'Qty Returned', 'Condition']);

        foreach ($collection as $item) {
            try {
                $orderItem = $this->orderItemRepository->get($item->getOrderItemId());
                $orderItemName = $orderItem->getName();
                $orderItemSku = $orderItem->getSku();
            } catch (NoSuchEntityException) {
                $orderItemName = 'N/A';
                $orderItemSku = 'N/A';
            }

            $table->addRow([
                $orderItemName,
                $orderItemSku,
                (string)$item->getQtyRequested(),
                $item->getQtyApproved() !== null ? (string)$item->getQtyApproved() : '-',
                $item->getQtyReturned() !== null ? (string)$item->getQtyReturned() : '-',
                $item->getConditionId() ? $this->getConditionLabel($item->getConditionId()) : '-',
            ]);
        }

        $table->render();
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

    /**
     * @param int $statusId
     * @return string
     */
    protected function getStatusLabel(int $statusId): string
    {
        try {
            return $this->statusRepository->get($statusId)->getLabel();
        } catch (NoSuchEntityException) {
            return 'Unknown';
        }
    }

    /**
     * @param int $reasonId
     * @return string
     */
    protected function getReasonLabel(int $reasonId): string
    {
        try {
            return $this->reasonRepository->get($reasonId)->getLabel();
        } catch (NoSuchEntityException) {
            return 'Unknown';
        }
    }

    /**
     * @param int $resolutionTypeId
     * @return string
     */
    protected function getResolutionTypeLabel(int $resolutionTypeId): string
    {
        try {
            return $this->resolutionTypeRepository->get($resolutionTypeId)->getLabel();
        } catch (NoSuchEntityException) {
            return 'Unknown';
        }
    }

    /**
     * @param int $conditionId
     * @return string
     */
    protected function getConditionLabel(int $conditionId): string
    {
        try {
            return $this->itemConditionRepository->get($conditionId)->getLabel();
        } catch (NoSuchEntityException) {
            return 'Unknown';
        }
    }
}
