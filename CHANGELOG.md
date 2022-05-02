# Changelog
Todos los cambios notables a este proyecto serán documentados en este archivo.

El formato está basado en [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
y este proyecto adhiere a [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

# [1.2.4] - 2022-04-29
## Added
- Se agrego en la configuración del plugin una opción para modificar el comportamiento del correo electrónico al realizar una compra.

# [1.1.4] - 2022-01-26
## Fixed
- Se corrige error en deshabilitación por anulación de pago con Webpay en usuarios con sesión iniciada en el comercio.
- Se corrige checkout forzado a 1 columna y se oculta header y footer en checkout. Muchas gracias por tu aporte @HeikelV
- Se agrega nueva versión mínima a librería Monolog. Muchas gracias por tu aporte @asterion
- Se modifican los pasos de desinstalación del plugin y las referencias a la versión SOAP del repositorio.

# [1.1.3] - 2021-09-15
## Fixed
- Se elimina librería Zend\Log por incompatibilidad con versiones >=2.4.3 de Magento. Se agrega librería recomendada (Monolog) y se prueba retrocompatibilidad hasta la versión 2.2.0 de Magento.

# [1.1.2] - 2021-04-30
## Fixed
- Se corrige versión de SDK de PHP requerida para el plugin. Esto soluciona error al redireccionar a la pasarela de pago.

# [1.1.1] - 2021-04-29
## Fixed
- Se corrigen errores de compatibilidad con PHP 7.0.

# [1.1.0] - 2021-04-28
## Added 
- Se actualiza SDK de PHP a versión 2.0, por lo que ahora se usa la API v1.2 de Transbank.

## Fixed
- Se corrige retorno al sitio de éxito cuando la transacción es aprobada.

# [1.0.2] - 2021-02-17
## Fixed
- Change the minimum amount to $50CLP on Readme 
- Remove unused error description on failed payments
- Aplicar StyleCI
- Arreglar título del medio de pago
- Agregar port binding a docker de desarrollo
- Arreglar NOTICE que aparecía en pagos rechazados
- Cambiar entityID por IncrementId para identificar ordenes


# [1.0.1] - 2020-11-05
## Fixed
- Se modifica versión minima requerida del SDK PHP de Transbank
- Varias modificaciones a documentación

# [1.0.0] - 2020-11-02
## First version
Primera versión del Plugin basado en la versión 3.4.2 del plugin de SOAP
