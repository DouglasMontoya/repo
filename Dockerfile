# Usar la imagen oficial de Ubuntu
FROM ubuntu:latest

# Actualizar el sistema e instalar los paquetes necesarios
RUN apt-get update && apt-get upgrade -y && \
    DEBIAN_FRONTEND=noninteractive apt-get install -y apache2 php libapache2-mod-php php-mysql php-cli php-mbstring php-xml php-json php-zip php-gd php-curl

# Habilitar el módulo de reescritura de Apache
RUN a2enmod rewrite

# Deshabilitar open_basedir en PHP
RUN sed -i 's/^open_basedir =.*/;open_basedir =/' /etc/php/*/apache2/php.ini

# Copiar los archivos del proyecto al directorio /var/www/html del contenedor
COPY ./externos/* /var/www/html/

# Exponer el puerto 80 para que Apache sea accesible
EXPOSE 80

# Iniciar Apache en segundo plano
CMD apachectl -D FOREGROUND