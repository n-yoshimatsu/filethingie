ARG PHP_IMAGE=php:8.3-apache@sha256:1b8b5b0f31cc4552fa3da2f0f02556a8207cbca6e86ec4882ff7b6a644c84a2b
FROM ${PHP_IMAGE}

WORKDIR /var/www/html
COPY ft2.php config.sample.php hooks.php LICENSE ./
COPY css/ css/
COPY js/ js/
COPY locales/ locales/
COPY plugins/ plugins/
COPY dev/config.test.php ./config.php
COPY dev/runtime.php ./__environment.php
COPY dev/seed.php /opt/filethingie/seed.php
COPY dev/php.ini /usr/local/etc/php/conf.d/zz-filethingie-test.ini
COPY dev/apache-test.conf /etc/apache2/conf-enabled/filethingie-test.conf
RUN php /opt/filethingie/seed.php && chown -R www-data:www-data files
