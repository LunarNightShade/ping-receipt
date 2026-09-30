#!/bin/sh
set -e

cd /app

# On first boot, create a .env so the framework has configuration to read.
if [ ! -f .env ]; then
    cp .env.example .env
fi

# Generate an application encryption key if one isn't set yet. To keep sessions
# valid across container restarts, set a fixed APP_KEY via compose instead
# (see the README).
if ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force
fi

# Make sure the SQLite database file exists, then apply any pending migrations.
if [ ! -f database/database.sqlite ]; then
    touch database/database.sqlite
fi
php artisan migrate --force

# Hand off to FrankenPHP (the image's default command, passed in as CMD).
exec "$@"
