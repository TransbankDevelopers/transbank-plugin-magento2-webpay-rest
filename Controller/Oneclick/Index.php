<?php
 
namespace Transbank\Webpay\Controller\Oneclick;
use Magento\Framework\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;
 
class Index extends \Magento\Framework\App\Action\Action 
{
    protected $resultPageFactory;

    public function __construct(Context $context, PageFactory $resultPageFactory)
    {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
    }

    public function execute() 
    {
        $this->_view->loadLayout();
        $this->_view->renderLayout();
        return $this->resultPageFactory->create();
    }
}