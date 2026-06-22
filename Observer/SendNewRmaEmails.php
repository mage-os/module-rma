<?php

declare(strict_types=1);

namespace MageOS\RMA\Observer;

use MageOS\RMA\Api\Data\RMAInterface;
use MageOS\RMA\Api\Email\SenderInterface;
use MageOS\RMA\Helper\ModuleConfig;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;
use Exception;

class SendNewRmaEmails implements ObserverInterface
{
    /**
     * @param SenderInterface $sender
     * @param ModuleConfig $moduleConfig
     * @param LoggerInterface $logger
     */
    public function __construct(
        protected readonly SenderInterface $sender,
        protected readonly ModuleConfig $moduleConfig,
        protected readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        /** @var RMAInterface $rma */
        $rma = $observer->getData('rma');

        if (!$rma instanceof RMAInterface) {
            return;
        }

        if (!$this->moduleConfig->isEnabled((int)$rma->getStoreId())) {
            return;
        }

        if (!$rma->getData('is_new')) {
            return;
        }

        try {
            $this->sender->sendCustomerNewRmaEmail($rma);
        } catch (Exception $e) {
            $this->logger->error('RMA: Failed to send customer new RMA email', [
                'rma_id' => $rma->getEntityId(),
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $this->sender->sendAdminNewRmaEmail($rma);
        } catch (Exception $e) {
            $this->logger->error('RMA: Failed to send admin new RMA email', [
                'rma_id' => $rma->getEntityId(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
