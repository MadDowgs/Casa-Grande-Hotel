#!/bin/sh
# Starts the Casa Grande Hotel container: waits for MySQL, migrates, caches config, runs Apache.
set -e
cd /var/www/html

if [ -z "$APP_KEY" ]; then
  echo "ERROR: APP_KEY is not set. Generate one with:  echo \"base64:\$(openssl rand -base64 32)\"" >&2
  exit 1
fi

echo "Waiting for the database at ${DB_HOST}:${DB_PORT:-3306} ..."
tries=0
until php -r 'try { new PDO("mysql:host=".getenv("DB_HOST").";port=".(getenv("DB_PORT") ?: 3306).";dbname=".getenv("DB_DATABASE"), getenv("DB_USERNAME"), getenv("DB_PASSWORD")); } catch (Throwable $e) { exit(1); }'; do
  tries=$((tries + 1))
  if [ "$tries" -ge 40 ]; then echo "ERROR: database is not reachable" >&2; exit 1; fi
  sleep 2
done

php artisan package:discover --ansi
php artisan migrate --force
php artisan db:seed --force          # idempotent: only inserts or updates the demo rooms
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec apache2-foreground
