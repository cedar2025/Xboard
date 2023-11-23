FROM phpswoole/swoole:php8.1-alpine

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions pcntl

RUN apk --no-cache add shadow supervisor nginx sqlite nginx-mod-http-brotli mysql-client

#复制项目文件以及配置文件
WORKDIR /www
COPY .docker /
COPY . /www
RUN composer install --optimize-autoloader --no-cache --no-dev \
&& php artisan storage:link \
&& chmod -R 777 ./

CMD [ "/usr/bin/supervisord", "--nodaemon", "-c" ,"/etc/supervisor/supervisord.conf" ]