<?php

declare(strict_types=1);

use Magento\Framework\App\Bootstrap;
use Magento\Framework\Exception\NoSuchEntityException;

$magentoPath = getenv('MAGENTO_DIR') ?: '/var/www/html';
$bootstrapFile = rtrim($magentoPath, '/') . '/app/bootstrap.php';

require $bootstrapFile;

$params = $_SERVER;
$bootstrap = Bootstrap::create(BP, $params);
$objectManager = $bootstrap->getObjectManager();

// Configurar el área es vital para evitar errores de plantillas/traducciones
$state = $objectManager->get(\Magento\Framework\App\State::class);
try {
    $state->setAreaCode('adminhtml');
} catch (\Exception $e) {
}

/** CONFIG */
$websiteId = 1;
$customerEmail = getenv('CUSTOMER_EMAIL') ?: 'cliente@cliente.com';
$customerPassword = getenv('CUSTOMER_PASSWORD') ?: 'cliente';
$productSku = 'cw12201';

/** CLIENTE */
$customerRepo = $objectManager->get(\Magento\Customer\Api\CustomerRepositoryInterface::class);
$customerFactory = $objectManager->get(\Magento\Customer\Api\Data\CustomerInterfaceFactory::class);
$accountManagement = $objectManager->get(\Magento\Customer\Api\AccountManagementInterface::class);

try {
    $customerRepo->get($customerEmail);
    echo "Cliente ya existe: {$customerEmail}\n";
} catch (NoSuchEntityException $e) {
    $customer = $customerFactory->create();
    $customer->setWebsiteId($websiteId);
    $customer->setEmail($customerEmail);
    $customer->setFirstname('Cliente');
    $customer->setLastname('Demo');
    $accountManagement->createAccount($customer, $customerPassword);
    echo "Cliente creado: {$customerEmail}\n";
}

/** PRODUCTO */
$productRepo = $objectManager->get(\Magento\Catalog\Api\ProductRepositoryInterface::class);
$productFactory = $objectManager->get(\Magento\Catalog\Api\Data\ProductInterfaceFactory::class);

try {
    $productRepo->get($productSku);
    echo "Producto ya existe: {$productSku}\n";
} catch (NoSuchEntityException $e) {
    $product = $productFactory->create();
    $product->setSku($productSku);
    $product->setName('Producto Demo');
    $product->setPrice(1200);
    $product->setAttributeSetId(4);
    $product->setStatus(\Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED);
    $product->setVisibility(\Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH);
    $product->setTypeId(\Magento\Catalog\Model\Product\Type::TYPE_SIMPLE);
    $product->setWebsiteIds([$websiteId]);

    $product->setCustomAttribute('tax_class_id', 2);

    $product->setStockData([
        'use_config_manage_stock' => 0,
        'manage_stock' => 1,
        'is_in_stock' => 1,
        'qty' => 1000
    ]);

    $productRepo->save($product);
    echo "Producto creado: {$productSku}\n";
}

echo "\nDemo data OK\n";
