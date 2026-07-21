<?php

declare(strict_types=1);

namespace MageOS\RMA\Controller\Adminhtml;

abstract class Reason extends AbstractLookupController
{
    const ADMIN_RESOURCE = 'MageOS_RMA::rma_reason';

    /**
     * @return string
     */
    protected function getMenuId(): string
    {
        return 'MageOS_RMA::rma_reason';
    }

    /**
     * @return string
     */
    protected function getBreadcrumbLabel(): string
    {
        return 'Reasons';
    }
}
