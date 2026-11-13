<?php

declare(strict_types=1);

namespace MageOS\RMA\Api\Email;

use MageOS\RMA\Api\Data\RMAInterface;

/**
 * @api
 */
interface SenderInterface
{
    /**
     * Email types passed to the "rma_email_send_before" event
     */
    const EMAIL_TYPE_CUSTOMER_NEW = 'customer_new';
    const EMAIL_TYPE_CUSTOMER_STATUS_CHANGE = 'customer_status_change';
    const EMAIL_TYPE_ADMIN_NEW = 'admin_new';

    /**
     * @param RMAInterface $rma
     * @return void
     */
    public function sendCustomerNewRmaEmail(RMAInterface $rma): void;

    /**
     * @param RMAInterface $rma
     * @param int $newStatusId
     * @return void
     */
    public function sendCustomerStatusChangeEmail(RMAInterface $rma, int $newStatusId): void;

    /**
     * @param RMAInterface $rma
     * @return void
     */
    public function sendAdminNewRmaEmail(RMAInterface $rma): void;
}
