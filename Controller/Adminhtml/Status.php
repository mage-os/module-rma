<?php

declare(strict_types=1);

namespace MageOS\RMA\Controller\Adminhtml;

abstract class Status extends AbstractLookupController
{
    const ADMIN_RESOURCE = 'MageOS_RMA::rma_status';

    /**
     * @return string
     */
    protected function getMenuId(): string
    {
        return 'MageOS_RMA::rma_status';
    }

    /**
     * @return string
     */
    protected function getBreadcrumbLabel(): string
    {
        return 'Statuses';
    }
}
