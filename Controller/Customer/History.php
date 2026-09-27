<?php

declare(strict_types=1);

namespace MageOS\RMA\Controller\Customer;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;

class History implements HttpGetActionInterface
{
    /**
     * @param PageFactory $resultPageFactory
     * @param RedirectFactory $resultRedirectFactory
     * @param CustomerSession $customerSession
     */
    public function __construct(
        protected readonly PageFactory $resultPageFactory,
        protected readonly RedirectFactory $resultRedirectFactory,
        protected readonly CustomerSession $customerSession
    ) {
    }

    /**
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        if (!$this->customerSession->isLoggedIn()) {
            return $this->resultRedirectFactory->create()->setPath('customer/account/login');
        }

        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->set(__('Withdrawals and Returns'));

        return $resultPage;
    }
}
