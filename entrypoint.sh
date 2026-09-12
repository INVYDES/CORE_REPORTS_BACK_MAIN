#!/bin/sh
set -e

# Cloud Run sets PORT (default 8080). Local docker-compose uses 8000.
PORT_TO_USE=${PORT:-8000}

echo "Starting CsRecords API on port $PORT_TO_USE"
echo "APP_ENV=${APP_ENV} DB_HOST=${DB_HOST} DB_DATABASE=${DB_DATABASE} DB_USERNAME=${DB_USERNAME}"

# Ensure storage perms (Cloud Run runs as www-data)
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

# Cache config/routes/views for production, but allow failure if DB not ready yet
if [ "$APP_ENV" = "production" ] || [ "$APP_ENV" = "staging" ]; then
  php artisan config:cache || echo "config:cache failed (DB maybe not ready)"
  php artisan route:cache || echo "route:cache failed"
  php artisan view:cache || echo "view:cache failed"
else
  php artisan config:clear || true
fi

# Run migrations with retry (up to 5 times, 5s between)
echo "Running migrations..."
for i in 1 2 3 4 5; do
  if php artisan migrate --force; then
    echo "Migrations OK"
    break
  fi
  echo "Migrate attempt $i failed, retrying in 5s..."
  sleep 5
  if [ "$i" = "5" ]; then
    echo "WARNING: migrations failed after 5 attempts, continuing startup"
  fi
done

# Seed only if fresh DB (optional: set RUN_SEED=true)
if [ "$RUN_SEED" = "true" ]; then
  php artisan db:seed --class=CoreReportsSeeder --force || echo "Seed failed"
fi

# Warm up storage link
php artisan storage:link 2>/dev/null || true

echo "Launching php artisan serve --host=0.0.0.0 --port=$PORT_TO_USE"
exec php artisan serve --host=0.0.0.0 --port="$PORT_TO_USE"
