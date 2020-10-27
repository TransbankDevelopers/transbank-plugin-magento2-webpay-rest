<?php
return array(
    'backend' =>
        array(
            'frontName' => 'admin',
        ),
    'crypt' =>
        array(
            'key' => '5e56dc6d8f24f711bb4f289cca1beda9',
        ),
    'session' =>
        array(
            'save' => 'files'
        ),
    'db' =>
        array(
            'table_prefix' => '',
            'connection' =>
                array(
                    'default' =>
                        array(
                            'host' => 'db',
                            'dbname' => 'magento',
                            'username' => 'magento',
                            'password' => 'magento',
                            'active' => '1',
                        ),
                ),
        ),
    'resource' =>
        array(
            'default_setup' =>
                array(
                    'connection' => 'default',
                ),
        ),
    'x-frame-options' => 'SAMEORIGIN',
    'MAGE_MODE' => 'production',
    'cache_types' =>
        array(
            'config' => 1,
            'layout' => 1,
            'block_html' => 1,
            'collections' => 1,
            'reflection' => 1,
            'db_ddl' => 1,
            'eav' => 1,
            'customer_notification' => 1,
            'full_page' => 0,
            'config_integration' => 1,
            'config_integration_api' => 1,
            'translate' => 1,
            'config_webservice' => 1,
            'compiled_config' => 1,
        ),
    'install' =>
        array(
            'date' => 'Thu, 05 Jan 2017 22:49:50 +0000',
        )
);
