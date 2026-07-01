# E2E Tests — Transbank Magento2 Plugin

Tests end-to-end con Playwright que validan los flujos de pago del plugin Transbank sobre una instancia real de Magento2 + MySQL corriendo en el devcontainer.

Los tests se ejecutan **desde tu máquina** (no dentro del devcontainer); Docker solo se usa para levantar Magento2 y MySQL.

## Qué se valida actualmente

### Webpay Plus

| Test                     | Archivo                                                        | Descripción                                                                                                                                         |
| ------------------------ | -------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------- |
| Pago exitoso             | `webpay-plus/payment-validate/successful-payment.spec.js`      | Flujo completo: login → carrito → checkout → pago en Transbank → confirmación de orden                                                              |
| Lock previene duplicados | `webpay-plus/payment-validate/concurrent-lock-success.spec.js` | Simula dos requests retornando con el mismo token simultáneamente. Verifica que no se creen órdenes duplicadas                                      |
| Retry con lock ocupado   | `webpay-plus/payment-validate/concurrent-retry-success.spec.js`| Fuerza el timeout de `GET_LOCK` adquiriendo el lock externamente. Verifica que el reintento interno procesa la transacción correctamente             |
| Reintentos agotados      | `webpay-plus/payment-validate/concurrent-retry-failure.spec.js`| Duplica el request en el retorno. Ambos requests agotan los reintentos internos contra un lock externo. Verifica página de error sin duplicar la orden |

## Prerequisitos

- Devcontainer corriendo (`docker compose up` desde `.devcontainer/`).
- Plugin Webpay instalado y configurado en modo integración.
- Demo data cargada (el devcontainer ejecuta `setup-demo-data.php` automáticamente; crea el cliente `cliente@cliente.com` y el producto `Producto Demo`).
- Navegadores de Playwright instalados.

## Setup

```bash
cd tests/e2e
pnpm install
pnpm setup
```

Copiar `.env.example` a `.env` y ajustar si es necesario:

```bash
cp .env.example .env
```

## Variables de entorno

| Variable            | Default                  | Descripción                         |
| ------------------- | ------------------------ | ----------------------------------- |
| `BASE_URL`          | `http://localhost:8000`  | URL de la instancia Magento2        |
| `CUSTOMER_EMAIL`    | `cliente@cliente.com`    | Email del cliente de prueba         |
| `CUSTOMER_PASSWORD` | `cliente123!`            | Contraseña del cliente de prueba    |
| `DB_HOST`           | `localhost`              | Host de MySQL (puerto publicado por Docker) |
| `DB_PORT`           | `3306`                   | Puerto de MySQL                     |
| `DB_USER`           | `magento`                | Usuario de MySQL                    |
| `DB_PASSWORD`       | `magento`                | Contraseña de MySQL                 |
| `DB_NAME`           | `magento`                | Nombre de la base de datos          |

## Ejecución

```bash
# Todos los tests
pnpm test

# Tests de Webpay Plus
pnpm test:webpay-plus

# Modo debug (Playwright Inspector)
pnpm test:debug
```

Todos los comandos corren en modo **headed** (con navegador visible).

## Ver resultados

Playwright genera un reporte HTML después de cada ejecución:

```bash
pnpm report
```

Cuando un test falla se guardan automáticamente en `test-results/`:

- **Screenshot** de la página al momento del fallo.
- **Trace** interactivo (se abre con `pnpm playwright show-trace <archivo.zip>`).
- **Video** de la ejecución completa del test.

## Estructura

```
tests/e2e/
├── specs/                        # Tests agrupados por medio de pago
│   └── webpay-plus/
│       └── payment-validate/     # Tests de validación de pago
├── helpers/                      # Funciones reutilizables
│   ├── checkout.js               # Login, carrito, checkout Magento2
│   ├── webpay-form.js            # Formulario de tarjeta Transbank
│   ├── database.js               # Queries a MySQL vía mysql2 + cálculo del lock name
│   ├── concurrent.js             # Helpers de concurrencia (interceptor, holdReturnRequests)
│   └── assertions.js             # Assertions reutilizables (expectOrderConfirmation, expectPaymentError, etc.)
├── playwright.config.js
├── package.json
├── .env.example
└── .env                          # (gitignored)
```

## Notas de implementación

- El retorno de Transbank llega como **POST** a `/checkout/transaction/commitwebpay`. El interceptor extrae el `token_ws` del body y construye una URL GET equivalente para poder duplicar el request (el controlador acepta ambos métodos).
- El lock name se calcula igual que en PHP: `transbank_webpay_lock_` + primeros 40 caracteres de `sha256(token)`. La función `buildLockName` en `database.js` replica exactamente esa lógica.
- El estado de transacción exitosa en Magento2 es `SUCCESS` (en WooCommerce es `approved`).
- Los tests corren con `workers: 1` y `fullyParallel: false` porque comparten estado en la base de datos.
- El timeout general es de 120 segundos por test dado que involucran redirecciones externas a Transbank.
- Con `GET_LOCK(?, 5)` hay 4 intentos × 5s = 20s de espera máxima antes de lanzar un error.
