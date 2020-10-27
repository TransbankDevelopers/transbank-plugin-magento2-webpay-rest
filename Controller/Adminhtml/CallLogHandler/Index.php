<?php
namespace Transbank\Webpay\Controller\Adminhtml\CallLogHandler;

use Transbank\Webpay\Model\LogHandler;

class Index extends \Magento\Backend\App\Action {

    public function __construct(\Magento\Backend\App\Action\Context $context) {
        parent::__construct($context);
    }

    /**
     * @Override
     */
    public function execute() {
        $log = new LogHandler();
        if ($_POST["action_check"] == 'true') {
            $log->setLockStatus(true);
            $log->setparamsconf($_POST['days'], $_POST['size']);
        } else {
            $log->setLockStatus(false);
        }
    }
}
