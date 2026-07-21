<?php

declare(strict_types=1);

namespace MageOS\RMA\Controller\Adminhtml;

abstract class ResolutionType extends AbstractLookupController
{
    const ADMIN_RESOURCE = 'MageOS_RMA::rma_resolution_type';

    /**
     * @return string
     */
    protected function getMenuId(): string
    {
        return 'MageOS_RMA::rma_resolution_type';
    }

    /**
     * @return string
     */
    protected function getBreadcrumbLabel(): string
    {
        return 'Resolution Types';
    }
}
