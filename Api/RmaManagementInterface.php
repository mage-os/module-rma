<?php

declare(strict_types=1);

namespace MageOS\RMA\Api;

use MageOS\RMA\Api\Data\RMAInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;

/**
 * @api
 */
interface RmaManagementInterface
{
    /**
     * Create an RMA with its items and attachments in one transaction, after the eligibility and quantity checks
     *
     * @param \Magento\Sales\Api\Data\OrderInterface $order
     * @param int|null $customerId
     * @param string $customerEmail
     * @param string $customerName
     * @param int $reasonId
     * @param int $resolutionTypeId
     * @param array $selectedItems order item id => ['qty_requested' => int, 'condition_id' => int|null]
     * @param string $attachmentsJson
     * @return \MageOS\RMA\Api\Data\RMAInterface
     * @throws CouldNotSaveException
     * @throws LocalizedException
     */
    public function createRma(
        OrderInterface $order,
        ?int $customerId,
        string $customerEmail,
        string $customerName,
        int $reasonId,
        int $resolutionTypeId,
        array $selectedItems,
        string $attachmentsJson = ''
    ): RMAInterface;
}
