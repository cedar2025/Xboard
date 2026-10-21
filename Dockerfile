# syntax=docker/dockerfile:1
FROM php:8.2.35-cli-alpine AS runtime

COPY --from=mlocati/php-extension-installer:2.12.3 /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer:2.10.3 /usr/bin/composer /usr/local/bin/composer

# Install these extensions separately to preserve the ARM64 compiler workarounds.
RUN CFLAGS="-O0" install-php-extensions pcntl && \
    CFLAGS="-O0 -g0" install-php-extensions bcmath

# Use curl retries because PECL's downloader can fail on transient HTTP 500 responses.
RUN <<'SH'
set -eu
mkdir -p /tmp/extensions
# Start with bundled extensions; append verified PECL sources below.
set -- pdo_mysql sockets zip

while read -r package checksum; do
    archive="/tmp/extensions/$package.tgz"
    source_dir="/tmp/extensions/$package"

    curl -fsSL --retry 5 --connect-timeout 20 --max-time 180 \
        "https://pecl.php.net/get/$package.tgz" -o "$archive"
    printf '%s  %s\n' "$checksum" "$archive" | sha256sum -c -
    tar -xzf "$archive" -C /tmp/extensions
    mv /tmp/extensions/package.xml "$source_dir/package.xml"
    set -- "$@" "$source_dir"
done <<'EXTENSIONS'
igbinary-3.2.16 8bf25d465abc7973d9e2c9a3039a5f8eea635b23bc1477017ff3999ff95836da
redis-6.3.0 0d5141f634bd1db6c1ddcda053d25ecf2c4fc1c395430d534fd3f8d51dd7f0b5
EXTENSIONS

IPE_PROCESSOR_COUNT=2 install-php-extensions "$@"
rm -rf /tmp/extensions
SH

# Cache Swoole separately so changes here do not rebuild the other extensions.
RUN <<'SH'
set -eu
package=swoole-6.2.3
checksum=21dfa1c4a4a09f4d1cb4b154f7defdc91fbabafc8d44a77f8f618b9742c67304

mkdir -p /tmp/swoole-src
curl -fsSL --retry 5 --connect-timeout 20 --max-time 180 \
    "https://pecl.php.net/get/$package.tgz" -o /tmp/swoole.tgz
printf '%s  %s\n' "$checksum" /tmp/swoole.tgz | sha256sum -c -
tar -xzf /tmp/swoole.tgz -C /tmp/swoole-src
mv /tmp/swoole-src/package.xml "/tmp/swoole-src/$package/package.xml"

IPE_PROCESSOR_COUNT=2 IPE_SWOOLE_WITHOUT_IOURING=1 \
    install-php-extensions "/tmp/swoole-src/$package"
rm -rf /tmp/swoole-src /tmp/swoole.tgz
SH

RUN apk --no-cache add \
        shadow \
        sqlite \
        mysql-client \
        mysql-dev \
        mariadb-connector-c \
        git \
        patch \
        supervisor \
        redis \
        caddy && \
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
