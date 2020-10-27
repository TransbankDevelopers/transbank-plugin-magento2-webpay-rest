# Changelog
Todos los cambios notables a este proyecto serán documentados en este archivo.

El formato está basado en [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
y este proyecto adhiere a [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

# [3.4.2] - 2020-08-19
## Fixed
- Arregla error con descuentos de uso limitado
- Ahora toma valor desde orden y no cotización

# [3.4.1] - 2020-04-06
## Fixed
- Arregla el uso de la configuración 'Estado de nueva orden' y se ordena código [PR #75](https://github.com/TransbankDevelopers/transbank-plugin-magento2-webpay/pull/75)
- Cambia versión de tcpf a un rango en vez de una versión específica para evitar problemas de actualización en el futuro [PR #76](https://github.com/TransbankDevelopers/transbank-plugin-magento2-webpay/pull/76)
- Agrega script para crear nueva tabla al instalar el plugin (antes solo se creaba al actualizar). También se crea código para eliminar la tabla al desintstalar [PR #77](https://github.com/TransbankDevelopers/transbank-plugin-magento2-webpay/pull/77)

# [3.4.0] - 2020-04-06
## Added	
- Mejora estabilidad de plugin, guardando token de la orden en base de datos en reemplazo de variables de sesión [PR #73](https://github.com/TransbankDevelopers/transbank-plugin-magento2-webpay/pull/73)
- Se cambia visibilidad de las funciones internas del plugin de `private` a `protected` para permitir herencia.  [PR #73](https://github.com/TransbankDevelopers/transbank-plugin-magento2-webpay/pull/73)
## Fixed
- Se soluciona exportación de diagnostico en PDF [PR #71](https://github.com/TransbankDevelopers/transbank-plugin-magento2-webpay/pull/71)
- Se incluyen instrucciones en el README sobre como pasar a producción [PR #70](https://github.com/TransbankDevelopers/transbank-plugin-magento2-webpay/pull/70)

# [3.3.0] - 2020-02-05
## Added	
- Agrega TCPDF como dependencia [https://github.com/TransbankDevelopers/transbank-plugin-magento2-webpay/pull/66]
## Fixed
- Se cambia el mensaje cuando una trasacción es cancelada [https://github.com/TransbankDevelopers/transbank-plugin-magento2-webpay/pull/67]
- Se elimina bootstrap and jquery de las librerias [https://github.com/TransbankDevelopers/transbank-plugin-magento2-webpay/pull/64]

# [3.2.7] - 2020-01-15
## Fixed	
- Corrige compatibilidad con php 7.0.

# [3.2.6] - 2019-10-21	
## Fixed	
- Se remueve campo "extras" de composer.json para poder subir el plugin a Magento marketplace
- Se añade autoload a composer.json

## [3.2.5] - 2019-10-18
### Fixed
- Se actualiza versión del SDK a la última versión para resolver la conexión con
los servicios SOAP

## [3.2.4] - 2019-10-16
### Fixed
- Corrige manejo de stock cuando el pago no es completado y la orden es cancelada. La cantidad es retornada al stock disponible.

## [3.2.3] - 2019-08-20
### Fixed
- Corrige duplicación de órdenes de compra al inicial el pago con Webpay.

## [3.2.2] - 2019-07-01
### Fixed
- Para evitar errores en la instalación, se removió la especificación de módulos de Magento en composer.json.

## [3.2.1] - 2019-06-26
### Fixed
- Corrige verificación de la versión de php, actualizado hasta php 7.2.

## [3.2.0] - 2019-06-24
### Changed
- Se añade soporte comprobado al plugin hasta php 7.2 + Magento 2.3.1.

## [3.1.12] - 2019-05-13
### Fixed
- Corrige la redirección al voucher de una transacción confirmada en estado "Esperando".

## [3.1.11] - 2019-04-17
### Fixed
- Corrige configuración, Ya no es necesario incluir el certificado de Webpay

## [3.1.10] - 2019-04-04
### Fixed
- Corrige despliegue de información en el detalle de la transacción realizada, ahora se visualiza toda la información

## [3.1.9] - 2019-01-22
### Changed
- Se elimina la condición de VCI == "TSY" || VCI == "" para evaluar la respuesta de getTransactionResult debido a que
esto podría traer problemas con transacciones usando tarjetas internacionales.

## [3.1.8] - 2018-12-27
### Added
- Agrega logs de transacciones para poder obtener los datos como token, orden de compra, etc.. necesarios para el proceso de certificación.

## [3.1.7] - 2018-12-26
### Fixed
- Corrige problemas con el sistema de configuración de plugin.

## [3.1.6] - 2018-12-17
### Fixed
- Actualiza sdk php a 1.4.4 que corrige problema de carga de clases Linux.

## [3.1.5] - 2018-11-30
### Changed
- Se mejora la experiencia de pago con webpay.
### Fixed
- Se corrige un problema al cargar el sdk de webpay

## [3.1.4] - 2018-11-28
### Changed
- Se mejora la experiencia de pago con webpay.
- Se elimina configuración de logs en sección de administración.

## [3.1.3] - 2018-11-27
### Changed
- Se mejora la creación del pdf de diagnóstico.
- Se elimina la comprobación de la extención mcrypt dado que ya no es necesaria por el plugin.

## [3.1.2] - 2018-11-21
### Changed
- Se corrigen varios problemas internos del plugin para entregar una mejor experiencia en magento2 con Webpay.
- Ahora el certificado de transbank Webpay es opcional.
- Ahora soporta php 7.1

## [3.1.1] - 2018-08-24
### Changed
- Se modifica código de comercio y certificados.

## [3.1.0] - 2018-07-11
### Added
- Se agregan validaciones de depencias en instalacion a través de composer
### Modificado
- Se modifica herramienta de diagnostico, metodo es desde ahora ondemand.
- Se realizan correcciones a obtencion de orden de compra.
- Se realizan correcciones a flujo de compra considerando anulacion por parte del cliente.

## [3.0.4] - 2018-05-28
### Changed
- Se modifica certificado de servidor para ambiente de integracion.

## [3.0.3] - 2018-05-18
### Modificado
- Se corrige SOAP para registrar versiones.
