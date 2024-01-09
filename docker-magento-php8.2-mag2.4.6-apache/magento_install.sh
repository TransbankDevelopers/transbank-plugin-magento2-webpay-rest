#!/bin/bash
echo "INSTALANDO MAGENTO PARA PRUEBAS CON EL PLUGIN TBK"
php -f bin/magento setup:install --base-url=${MAGENTO_HOST} \
--backend-frontname=admin --db-host=${MAGENTO_DATABASE_HOST} \
--db-name=${MAGENTO_DATABASE_NAME} --db-user=${MAGENTO_DATABASE_USER} \
--db-password=${MAGENTO_DATABASE_PASSWORD} --admin-firstname=${MAGENTO_FIRST_NAME} \
--admin-lastname=${MAGENTO_LAST_NAME} --admin-email=${MAGENTO_EMAIL} \
--admin-user=${MAGENTO_USERNAME} --admin-password=${MAGENTO_PASSWORD} --language="es_CL" \
--currency="CLP" --timezone="America/Santiago" \
--search-engine=elasticsearch7 --elasticsearch-host=elasticsearch \
--elasticsearch-port=9200 --use-rewrites=1;

# Ativamos el cron importante para la indexación
# https://experienceleague.adobe.com/docs/commerce-operations/configuration-guide/cli/configure-cron-jobs.html
php bin/magento cron:install;

# Establecemos el modo a desarrollo
# https://experienceleague.adobe.com/docs/commerce-operations/configuration-guide/setup/application-modes.html
php bin/magento deploy:mode:set developer;
