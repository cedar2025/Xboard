FROM phpswoole/swoole:php8.1-alpine

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

# 安装基础软件包，包括 gettext (提供 envsubst)
RUN install-php-extensions pcntl bcmath inotify \
    && apk --no-cache add shadow supervisor nginx sqlite nginx-mod-http-brotli mysql-client git patch gettext \
    && addgroup -S -g 1000 www && adduser -S -G www -u 1000 www

# 设置工作目录
WORKDIR /www

# 复制项目文件和配置文件
COPY .docker /
COPY . /www
COPY .env.example /www/.env.example

# 生成环境变量文件并安装依赖
RUN envsubst < /www/.env.template > /www/.env \
    && composer install --optimize-autoloader --no-cache --no-dev \
    && php artisan storage:link \
    && chown -R www:www /www \
    && chmod -R 775 /www

# 启动 supervisor
CMD /usr/bin/supervisord --nodaemon -c /etc/supervisor/supervisord.conf
