# DevContainer – Magento Webpay Module

Este **devcontainer** proporciona un entorno completo de desarrollo para el módulo **Webpay de Magento**.

## 🚀 Inicio rápido

1. Abre el proyecto en **VS Code**.
2. Cuando se te solicite, selecciona **“Reopen in Container”**.
3. Espera a que se construya el contenedor (la primera vez puede tardar algunos minutos).
4. Una vez listo, Magento estará disponible en:  
   👉 **http://localhost:8000**

## 📋 Servicios incluidos

-   **Magento 2.4.3** con **PHP 7.4**
-   **MySQL 8** como base de datos
-   **Elasticsearch** para la búsqueda optimizada del catálogo
-   **Apache** como servidor web
-   **Extensiones de VS Code** para trabajar con PHP y Magento
-   **Composer** para la gestión de dependencias PHP

## 🔗 URLs de acceso

| Servicio      | URL                         | Credenciales                                    |
| ------------- | --------------------------- | ----------------------------------------------- |
| Magento       | http://localhost:8000       | cliente@cliente.com / <ver variable de entorno> |
| Panel Admin   | http://localhost:8000/admin | admin / admin123                                |
| Base de datos | VS Code SQLTools / MySQL    | magento / magento                               |

## 🛠️ Herramientas de desarrollo

### Administración de base de datos con VS Code

El devcontainer incluye extensiones para trabajar directamente con la base de datos.

#### SQLTools

-   **Acceso**: `Ctrl/Cmd + Shift + P` → `SQLTools: Connect`
-   **Conexiones preconfiguradas**:
    -   **Magento MySQL** – Base de datos principal
    -   **MySQL Root** – Acceso administrativo completo
-   **Funcionalidades**:
    -   Exploración de tablas
    -   Ejecución de consultas
    -   Exportación de datos

## 📁 Estructura del proyecto en el contenedor

```
/var/www/html/workspace/                                # Código fuente del plugin (montado desde el host)
/var/www/html/                                          # Instalación de Magento
/var/www/html/vendor/transbank/webpay-magento2-rest     # Módulo Webpay (enlazado desde /var/www/html/workspace)
```

## 🔧 Configuración del módulo

El módulo Webpay se monta automáticamente en:

```
/var/www/html/vendor/transbank/webpay-magento2-rest
```

Además:

-   Se activa el módulo
-   Se instalan productos de prueba
-   Se configura la tienda automáticamente

> ⚠️ **Nota**: En caso de tener la carpeta vendor o el archivo composer.lock dentro de la carpeta root del plugin, eliminar ambos por que pueden generar inconsistencia en la instalación del plugin.

### Desarrollo del módulo

Los cambios en el plugin **no se reflejan automáticamente** en Magento. Se recomienda ejecutar los siguientes comandos después de cada cambio:

```bash
rm -rf generated/code/* var/cache/* var/page_cache/* var/view_preprocessed/*
php bin/magento cache:flush
chmod -R 777 var generated pub
```

Notas adicionales:

-   Los logs se almacenan en `.devcontainer/logs/`
-   La carpeta de Magento está incluida en **Intelephense** para contar con referencias del core

## 📦 Dependencias

Las dependencias de **Composer** se instalan automáticamente la primera vez que se crea el contenedor.

Para instalar nuevas dependencias manualmente:

```bash
cd /var/www/html
composer require nueva-dependencia
```

## 🗄️ Base de datos

### Configuración por defecto

-   **Host**: `db`
-   **Puerto**: `3306`
-   **Base de datos**: `magento`
-   **Usuario**: `magento`
-   **Contraseña**: `magento`

## 📝 Notas de desarrollo

1. **Permisos**: el usuario del contenedor es `root`, con acceso completo.
2. **Persistencia**: los datos de Magento persisten entre reinicios.
3. **Refresco de código**: no todos los cambios se reflejan automáticamente.
4. **Logs**: disponibles en `.devcontainer/logs`.

## 🧩 Edición del devcontainer

Si se realizan cambios en el devcontainer, es necesario reconstruir la imagen para que estos se apliquen.

### Reconstruir el devcontainer

-   VS Code → Paleta de comandos → **Dev Containers: Rebuild Container**
-   O desde el menú Remote → **Reopen in Container**

> ⚠️ **Nota**: la reconstrucción elimina datos no persistentes. Realiza respaldos si es necesario.

## 🔄 Forzar instalación limpia

Para realizar una instalación completamente limpia:

1. Elimina los contenedores:

```
magento-transbank-plugin-mysql
magento-transbank-plugin-elastic
magento-transbank-plugin
```

2. Elimina la imagen:

```
transbank-plugin-magento2-webpay-rest_devcontainer-magento
```

3. Elimina los volúmenes:

```
transbank-plugin-magento2-webpay-rest_devcontainer_db_data
transbank-plugin-magento2-webpay-rest_devcontainer_es_data
transbank-plugin-magento2-webpay-rest_devcontainer_magento_data
```
