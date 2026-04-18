#!/bin/sh
set -e

echo "[entrypoint] Running database migrations..."
php /www/artisan migrate --force

echo "[entrypoint] Checking core plugins..."
php /www/artisan tinker --execute="App\Services\Plugin\PluginManager::installDefaultPlugins();" 2>/dev/null || true

echo "[entrypoint] Starting services..."
exec "$@"
