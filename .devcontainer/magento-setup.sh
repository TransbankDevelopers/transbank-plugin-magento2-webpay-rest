#!/usr/bin/env bash
set -euo pipefail

# --- Environment Variables
MAGENTO_ZIP_URL="https://github.com/magento/magento2/archive/refs/tags/${MAGENTO_VERSION}.zip"
PLUGIN_SRC="${MAGENTO_DIR}/workspace"
CAPTURE_BUTTON_BLOCK_FILE="${MAGENTO_DIR}/app/code/Magento/Sales/Block/Adminhtml/Order/Invoice/View.php"
CAPTURE_CONTROLLER_FILE="${MAGENTO_DIR}/app/code/Magento/Sales/Controller/Adminhtml/Order/Invoice/Capture.php"

log() {
  echo -e "\n\033[1;32m==>\033[0m $*"
  return 0
}

# --- Utility functions
wait_port() {
  local host="$1" port="$2" name="$3"

  log "Esperando ${name} (${host}:${port})"

  if timeout 60s bash -c "until nc -z $host $port; do sleep 2; done"; then
    echo "${name} listo"
    return 0
  else
    echo "ERROR: ${name} no respondió" >&2
    return 1
  fi
}

wait_es_yellow() {
  log "Esperando Elasticsearch"

  if timeout 90s bash -c "until curl -fsS 'http://elasticsearch:9200/_cluster/health?wait_for_status=yellow&timeout=5s' >/dev/null; do sleep 2; done"; then
    echo "ES listo"
    return 0
  else
    echo "ERROR: ES falló" >&2
    return 1
  fi
}

cleanup_transbank_null_config() {
  log "Limpiando core_config_data (NULL/vacío)..."

  mysql -h "${MAGENTO_DB_HOST}" \
        -u "${MAGENTO_DB_USER}" \
        -p"${MAGENTO_DB_PASSWORD}" \
        "${MAGENTO_DB_NAME}" \
        -e "
DELETE FROM core_config_data
WHERE path LIKE 'payment/transbank_%'
  AND (value IS NULL OR TRIM(value) = '');
" || true

  return 0
}

configure_chile_store() {
  log "Configurando tienda solo para Chile (CL)..."

  php bin/magento config:set general/country/default CL
  php bin/magento config:set general/country/allow CL

  php bin/magento config:set shipping/origin/country_id CL
  php bin/magento config:set shipping/origin/city Santiago
  php bin/magento config:set shipping/origin/postcode 0000000
  php bin/magento config:set shipping/origin/region_id 0

  php bin/magento config:set general/locale/code es_CL
  php bin/magento config:set currency/options/base CLP
  php bin/magento config:set currency/options/default CLP
  php bin/magento config:set currency/options/allow CLP

  return 0
}

# --- Wait for MySQL and Elasticsearch
wait_port db 3306 "MySQL" || exit 1
wait_es_yellow || exit 1

cd "$MAGENTO_DIR"

# --- 1. Magento installation (Only if it doesn't exist)
if [[ ! -f "bin/magento" ]]; then
    log "Descargando Magento ${MAGENTO_VERSION}..."
    curl -fsSL "${MAGENTO_ZIP_URL}" -o /tmp/magento.zip
    unzip -q /tmp/magento.zip -d /tmp/magento-src
    SRC_FOLDER=$(find /tmp/magento-src -maxdepth 1 -type d -name "magento2-*" | head -n 1)
    cp -a "${SRC_FOLDER}/." .
    rm -rf /tmp/magento-src /tmp/magento.zip

    composer config --no-interaction allow-plugins.dealerdirect/phpcodesniffer-composer-installer true
    composer config --no-interaction allow-plugins.laminas/laminas-dependency-plugin true
    composer config --no-interaction allow-plugins.magento/magento-composer-installer true
    composer install --no-interaction --prefer-dist
fi

# --- 2. Configure local repository
log "Verificando repositorio local..."

# Add repository only if it doesn't exist in Magento's composer.json
if ! grep -q "local-plugin" composer.json; then
    composer config repositories.local-plugin path "${PLUGIN_SRC}"
    composer config minimum-stability dev
    composer config prefer-stable true
fi

# --- 3. Install/Update Plugin
if [[ -f "${PLUGIN_SRC}/composer.json" ]]; then

    REAL_PLUGIN_NAME=$(grep '"name":' "${PLUGIN_SRC}/composer.json" | cut -d'"' -f4)

    if composer show "$REAL_PLUGIN_NAME" >/dev/null 2>&1; then
        log "Actualizando plugin: ${REAL_PLUGIN_NAME}..."

        rm -rf "vendor/${REAL_PLUGIN_NAME}"
        composer update "$REAL_PLUGIN_NAME" --no-interaction
    else
        log "Instalando plugin por primera vez: ${REAL_PLUGIN_NAME}..."
        composer require "${REAL_PLUGIN_NAME}:@dev" --no-interaction
    fi
fi

# --- 4. Database setup and installation
if [[ ! -f "app/etc/env.php" ]]; then
    log "Ejecutando setup:install inicial..."
    php bin/magento setup:install \
        --base-url="http://localhost:${MAGENTO_STORE_PORT}" \
        --db-host=${MAGENTO_DB_HOST} \
        --db-name=${MAGENTO_DB_NAME} \
        --db-user=${MAGENTO_DB_USER} \
        --db-password=${MAGENTO_DB_PASSWORD} \
        --backend-frontname=admin \
        --admin-user=${MAGENTO_ADMIN_USER} \
        --admin-password=${MAGENTO_ADMIN_PASSWORD} \
        --admin-email=${MAGENTO_ADMIN_EMAIL} \
        --admin-firstname=Admin \
        --admin-lastname=User \
        --language=es_CL \
        --currency=CLP \
        --timezone=America/Santiago \
        --use-rewrites=1 \
        --search-engine=elasticsearch7 \
        --elasticsearch-host=elasticsearch \
        --elasticsearch-port=9200 \
        --cleanup-database \
        --enable-modules=Transbank_Webpay

    log "Instalando cron"
    php bin/magento cron:install || true
else
    log "Magento ya instalado. Sincronizando posibles cambios en el plugin..."
    php bin/magento setup:upgrade --keep-generated
fi

configure_chile_store

# --- 5. Modify capture button for invoice
log "Modificando el botón de captura para las facturas..."

if grep -Eq "confirmSetLocation\(.*getCaptureUrl" "$CAPTURE_BUTTON_BLOCK_FILE"; then
  echo "El botón ya ha sido modificado, no se requiere ninguna acción."

elif grep -Eq "implements[[:space:]]+HttpPostActionInterface" "$CAPTURE_CONTROLLER_FILE"; then
  echo "Corrigiendo el botón en el bloque..."
  sed -i "s/'onclick' => 'setLocation(\\\'' . \$this->getCaptureUrl() . '\\\')'/'onclick' => \"confirmSetLocation('\" . __('Are you sure you want to capture this invoice?') . \"', '\" . \$this->getCaptureUrl() . \"', {'data': {}})\"/" "$CAPTURE_BUTTON_BLOCK_FILE"

else
  echo "El controller Capture no implementa HttpPostActionInterface. No se aplican cambios."
fi

# --- 6. Code and Cache Refresh
log "Limpiando archivos generados y compilando..."
rm -rf generated/code/* generated/metadata/* var/cache/* var/page_cache/* var/view_preprocessed/* pub/static/frontend/* pub/static/adminhtml/*

php bin/magento deploy:mode:set developer || true
cleanup_transbank_null_config
php bin/magento setup:di:compile

# --- 7. Demo Data
DEMO_FLAG="${MAGENTO_DIR}/var/.demo-data-installed"
if [[ ! -f "${DEMO_FLAG}" ]] && [[ -f "${PLUGIN_SRC}/.devcontainer/setup-demo-data.php" ]]; then
    log "Instalando datos demo..."
    php "${PLUGIN_SRC}/.devcontainer/setup-demo-data.php"
    touch "${DEMO_FLAG}"
fi

php bin/magento indexer:reindex
php bin/magento cache:flush

# Directory Permissions
mkdir -p var generated pub
chmod -R 777 var generated pub
chown -R www-data:www-data var generated pub

log "¡Listo! Accede en http://localhost:${MAGENTO_STORE_PORT}"
