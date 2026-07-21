<?php

declare(strict_types=1);

namespace MageOS\RMA\Model\RMA;

class StatusCodes
{
    const NEW_REQUEST = 'new_request';
    const NEED_DETAILS = 'need_details';
    const APPROVED = 'approved';
    const REJECTED = 'rejected';
    const SHIPPED_BY_CUSTOMER = 'shipped_by_customer';
    const RECEIVED_BY_ADMIN = 'received_by_admin';
    const CANCELED_BY_CUSTOMER = 'canceled_by_customer';
    const RESOLVED = 'resolved';
    const STATUS_EVENT_MAP = [
        self::APPROVED => 'rma_approved_after',
        self::REJECTED => 'rma_rejected_after',
        self::SHIPPED_BY_CUSTOMER => 'rma_shipped_by_customer_after',
        self::RECEIVED_BY_ADMIN => 'rma_received_after',
        self::CANCELED_BY_CUSTOMER => 'rma_canceled_after',
        self::RESOLVED => 'rma_resolved_after',
    ];

    const PROTECTED_CODES = [
        self::NEW_REQUEST,
        self::APPROVED,
        self::REJECTED,
        self::SHIPPED_BY_CUSTOMER,
        self::RECEIVED_BY_ADMIN,
        self::CANCELED_BY_CUSTOMER,
        self::RESOLVED,
    ];

    /**
     * @param string $code
     * @return bool
     */
    public static function isProtected(string $code): bool
    {
        return in_array($code, self::PROTECTED_CODES, true);
    }
}
