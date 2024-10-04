# Changelog
Todos los cambios notables a este proyecto serán documentados en este archivo.

El formato está basado en [Keep a Changelog](http://keepachangelog.com/en/1.0.0/)
y este proyecto adhiere a [Semantic Versioning](http://semver.org/spec/v2.0.0.html).

# [2.3.0] - 2024-10-04

Esta versión no tiene cambios en el comportamiento de las operaciones de la API.

## Agrega:
- Se agrega la información de entorno para las transacciones de Webpay en la tabla de transacciones.

## Actualiza:
- Se refina el flujo de pago de Webpay y Onelick.
- Se refina el flujo de envío de correo.
- Se homologan los comprobantes de pago de Oneclick y Webpay.
- Se aclara la información en caso de que de error la transacción de prueba en la pantalla de diagnóstico.

## Borra:
- Se elimina la creación de variables dinámicas para eliminar advertencias en PHP 8.

# [2.2.1] - 2024-04-22
## Fixed
- Se corrige el valor de path_config para que sea aceptado desde las versiones 2.4.6 en adelante.
- Se corrige el test de conexión en entorno de producción.
- Se corrige el registro de los datos de la transacción en la base de datos para Oneclick.

# [2.2.0] - 2024-04-12
## Fixed
- Se corrige el retorno de una inscripción Oneclick cuando es rechazada por el usuario.
- Se corrige el retorno de transacción Webpay Plus cuando es rechazada por el usuario.
- Se modifica la vista de configuración del plugin.
- Se corrige bug de vista duplicada al listar tarjetas desde el administrador de cuenta de usuario.
- Se corrige un bug relacionado a cancelaciones simultáneas en el formulario de Transbank.
## Added
- Se muestra ahora la última versión del plugin disponible desde la vista de diagnóstico.
- Se agrega la opción de reembolso parcial para Oneclick.
- Se agrega reembolso parcial y total para Webpay Plus.

## Changed
- Se quita el servicio para recolectar métricas.
- Se modifica el campo de API Key a tipo password.
- Se mejora la gestión de la clase de logs.
- Se remueven referencias deprecadas a la integración con SOAP.

# [2.1.5] - 2023-03-24
## Added
- Se corrige error en el archivo UpgradeSchema que no permitía actualizar la base de datos.

# [2.1.4] - 2023-02-16
## Added
- Se corrige error de sintaxis en los archivos CreateWebpayM22 y CommitWebpayM22.

# [2.1.3] - 2023-02-14
## Added
- Se agrega la capacidad para cambiar el título del plugin en el otro scope.

# [2.1.2] - 2023-02-12
## Added
- Se mejora el log detallado para darle seguimiento a los errores.

# [2.1.1] - 2023-01-30
## Added
- Se agrega un servicio para recolectar datos que nos permitira darle mayor seguimiento a las versiones del plugin y las versiones de Magento mas usadas.

# [2.1.0] - 2022-12-14
## Added
- Capacidad para hacer reversas en Oneclick si el **Estado de Pago Exitoso** se cambia a proccesing.

## Fixed
- Se corrige el error que evitaba mostrar el título personalizado en Oneclick y mantenía el título por defecto.
- Se cambia el método **addSuccessMessage()** por **addComplexSuccessMessage()** que permite desplegar mensajes de exito utilizando algunos tags en HTML.

# [2.0.2] - 2022-11-22
## Fixed
- Se corrige retorno al sitio de éxito cuando la transacción es aprobada autorizando un pago de Oneclick.

# [2.0.1] - 2022-10-04
## Added
- Se ha regresado a la versión del SDK 2.0.

# [2.0.0-beta] - 2022-09-19
## Added
- Se agrego la opción de inscribir tarjetas utilizando Oneclick.
- Se agrego un módulo de administración para Oneclick.
- Se agrego un módulo en el perfil del usuario para administrar sus tarjetas inscritas.
- Se agrego soporte para agregar multiples tarjetas por usuario.
- Se ha actualizado la versión del SDK a la 3.0.

# [1.3.0-beta] - 2022-06-02
## Added
- Se agrego en la configuración del plugin una opción para modificar el comportamiento del correo electrónico al realizar una compra.
- Se agrego en la configuración del plugin una opción para crear el invoice al realizar una compra.

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
