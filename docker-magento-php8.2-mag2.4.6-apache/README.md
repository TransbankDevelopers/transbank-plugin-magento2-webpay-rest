![Magento 2](https://cdn.rawgit.com/rafaelstz/magento2-snippets-visualstudio/master/images/icon.png)

#  Magento 2.4.6 Docker para desarrollo
Esta es una maquina docker preparada para trabajar en modo desarrollo, obtiene el código de Magento
desde su repositorio https://github.com/magento/magento2.git 

### Apache + PHP 8.2 + MariaDB 10.2

## Requerimientos

**MacOS:**

Instalar [Docker](https://docs.docker.com/docker-for-mac/install/), [Docker-compose](https://docs.docker.com/compose/install/#install-compose) y [Docker-sync](https://github.com/EugenMayer/docker-sync/wiki/docker-sync-on-OSX).

**Windows:**

Instalar [Docker](https://docs.docker.com/docker-for-windows/install/), [Docker-compose](https://docs.docker.com/compose/install/#install-compose) y [Docker-sync](https://github.com/EugenMayer/docker-sync/wiki/docker-sync-on-Windows).

**Linux:**

Instalar [Docker](https://docs.docker.com/engine/installation/linux/docker-ce/ubuntu/) y [Docker-compose](https://docs.docker.com/compose/install/#install-compose).

### Como usar

### Construir el contenedor 
Permite crear la imagen base de Magento, esto solo es necesario cada vez que se modifica el archivo Dockerfile

```
docker compose build
```

### Iniciar el contenedor

```
docker compose up
```

### Para el contenedor

```
docker compose stop
```

### Borrar el contenedor

```
docker compose down
```

### Copiar e instalar el plugin
Permite copiar el codigo actual del plugin hacia el contenedor e instalarlo
el valor 'php8.2-mag2.4.6-apache_magento' es el nombre del contenedor (container_name) donde corre
Magento. Este debe ejecutarse desde la carpeta raiz del proyecto 


```
docker cp . php8.2-mag2.4.6-apache_magento:/var/www/html/app/code/Transbank/Webpay
docker exec php8.2-mag2.4.6-apache_magento /var/www/html/plugin_install.sh
```

**Web server:** http://localhost/

**Admin:** http://localhost/admin

    user: user
    password: user123

