<?php

namespace Transbank\Webpay\Controller\Oneclick;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    protected $resultPageFactory;
    private $customerSession;
    private $urlBuilder;

    public function __construct(
        Context $context,
        PageFactory $resultPageFactory,
        CustomerSession $customerSession,
        UrlInterface $urlBuilder
    )
    {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->customerSession = $customerSession;
        $this->urlBuilder = $urlBuilder;
    }

    public function execute()
    {
        if (!$this->customerSession->isLoggedIn()) {
            $this->customerSession->setBeforeAuthUrl($this->urlBuilder->getUrl('customer/oneclick/index'));

            return $this->resultRedirectFactory->create()->setPath('customer/account/login');
        }

        $this->_view->loadLayout();
        $this->_view->renderLayout();
        $this->resultPageFactory->create();
    }
}
