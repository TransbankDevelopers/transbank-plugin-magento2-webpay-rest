#!/bin/bash
chsh -s /bin/bash www-data;
php bin/magento module:disable Transbank_Webpay --clear-static-content;
php bin/magento module:enable Transbank_Webpay --clear-static-content;
php bin/magento setup:upgrade && php bin/magento setup:di:compile;
