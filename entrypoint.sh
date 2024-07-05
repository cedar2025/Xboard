#!/bin/sh

set -eu

cat > /www/.env <<-EOF
APP_NAME=${APP_NAME:-XBoard}}
APP_ENV=local
APP_KEY=${APP_KEY:-base64:k9njZYlyNBs9H9PDOynn8s/P+ct9jGDr67/dpt3Pu+4=}
APP_DEBUG=${APP_DEBUG:-false}
APP_URL=${APP_URL:-http://localhost}

ADMIN_SETTING_CACHE=60 #设置缓存时间（单位秒）
LOG_CHANNEL=stack

DB_CONNECTION=${DB_TYPE:-mysql}
DB_HOST=${DB_HOST:-mysql}
DB_PORT=${DB_PORT:-3306}
DB_DATABASE=${DB_DATABASE:-v2board}
DB_USERNAME=${DB_USERNAME:-v2board}
DB_PASSWORD=${DB_PASSWORD}

REDIS_HOST=${REDIS_HOST:-redis}
REDIS_PASSWORD=${REDIS_PASSWORD:-}
REDIS_PORT=${REDIS_PORT:-6379}

#默认将队列驱动和缓存驱动都修改为了redis，请务必安装redis
BROADCAST_DRIVER=log
CACHE_DRIVER=${CACHE_DRIVER:-redis}
QUEUE_CONNECTION=${QUEUE_CONNECTION:-redis}

MAIL_DRIVER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS=null
MAIL_FROM_NAME=null
MAILGUN_DOMAIN=
MAILGUN_SECRET=

# google cloud stoage
ENABLE_AUTO_BACKUP_AND_UPDATE=false
GOOGLE_CLOUD_KEY_FILE=config/googleCloudStorageKey.json
GOOGLE_CLOUD_STORAGE_BUCKET=

# 用于阻止重复安装
INSTALLED=1
EOF

/usr/bin/supervisord --nodaemon -c /etc/supervisor/supervisord.conf