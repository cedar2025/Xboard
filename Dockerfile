FROM phpswoole/swoole:php8.2-alpine AS runtime

RUN php -r 'if (version_compare(PHP_VERSION, "8.2.35", "<")) { \
    fwrite(STDERR, "PHP >= 8.2.35 is required for security fixes; found " . PHP_VERSION . "\n"); exit(1); \
}'

COPY --from=mlocati/php-extension-installer:2.12.3 /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer:2.10.3 /usr/bin/composer /usr/local/bin/composer

# Install these extensions separately to preserve the ARM64 compiler workarounds.
RUN CFLAGS="-O0" install-php-extensions pcntl && \
    CFLAGS="-O0 -g0" install-php-extensions bcmath

RUN IPE_PROCESSOR_COUNT=2 install-php-extensions zip

# Keep Redis independent of Alpine's package version to preserve RDB 14 compatibility.
COPY --from=redis:8-alpine /usr/local/bin/redis-* /usr/local/bin/

RUN apk --no-cache add \
        shadow \
        sqlite \
        mysql-client \
        mysql-dev \
        mariadb-connector-c \
        git \
        patch \
        supervisor \
        libssl3 \
        libcrypto3 \
        caddy && \
    redis-server --version && \
    redis-cli --version && \
    addgroup -S -g 1000 www && \
    adduser -S -G www -u 1000 www && \
    (getent group redis || addgroup -S redis) && \
    (getent passwd redis || adduser -S -G redis -H -h /data redis) && \
    php -r 'foreach (["swoole", "pcntl", "posix", "bcmath", "pdo_mysql", "pdo_sqlite", "sockets", "zip", "igbinary", "redis"] as $extension) { \
        if (!extension_loaded($extension)) { fwrite(STDERR, "Missing PHP extension: {$extension}\n"); exit(1); } \
    }'

FROM runtime AS app

WORKDIR /www

COPY .docker /

ARG CACHEBUST=1
ARG REPO_URL=https://github.com/cedar2025/Xboard
ARG BRANCH_NAME=master

RUN echo "Attempting to clone branch: ${BRANCH_NAME} from ${REPO_URL} with CACHEBUST: ${CACHEBUST}" && \
    rm -rf ./* && \
    rm -rf .git && \
    git config --global --add safe.directory /www && \
    git clone --depth 1 --branch ${BRANCH_NAME} ${REPO_URL} . && \
    git submodule update --init --recursive --force

COPY .docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY .docker/caddy/Caddyfile /etc/caddy/Caddyfile
COPY .docker/php/zz-xboard.ini /usr/local/etc/php/conf.d/zz-xboard.ini

RUN composer install --no-cache --no-dev --no-security-blocking \
    && php artisan storage:link \
    && chown -R www:www /www \
    && chmod -R 775 /www \
    && mkdir -p /data \
    && chown redis:redis /data

ENV ENABLE_WEB=true \
    ENABLE_HORIZON=true \
    ENABLE_REDIS=true \
    ENABLE_WS_SERVER=true \
    ENABLE_CADDY=true

EXPOSE 7001
COPY .docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh
ENTRYPOINT ["/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
