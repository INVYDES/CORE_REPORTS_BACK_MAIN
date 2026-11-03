#!/bin/sh
set -e

# Cloud Run/Railway sets PORT (default 8080). Local docker-compose uses 8000.
PORT_TO_USE=${PORT:-8000}
export PORT="$PORT_TO_USE"

# Numero de procesos del servidor embebido de PHP. Se exporta para que los
# procesos hijos de supervisord lo hereden (PHP_CLI_SERVER_WORKERS).
export PHP_CLI_SERVER_WORKERS="${WEB_WORKERS:-4}"

# Rol del contenedor:
#   web        -> solo servidor HTTP
#   worker     -> worker de cola + scheduler (sin HTTP)
#   scheduler  -> solo scheduler
#   all        -> los tres procesos en este contenedor (por defecto)
ROLE=${CONTAINER_ROLE:-all}

case "$ROLE" in
  web|worker|scheduler|all) ;;
  *)
    echo "CONTAINER_ROLE invalido: '$ROLE' (usa web, worker, scheduler o all)" >&2
    exit 1
    ;;
esac

echo "Starting CsRecords API (role=$ROLE, port=$PORT_TO_USE)"
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

# Las migraciones corren una sola vez: en el rol web (o "all"). Los demas
# procesos esperan a que la tabla exista y se reinician solos si fallan.
if [ "$ROLE" = "web" ] || [ "$ROLE" = "all" ]; then
  if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
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
  fi

  # Warm up storage link
  php artisan storage:link 2>/dev/null || true
fi

# Selecciona los programas de supervisor segun el rol. Los archivos van
# montados en la imagen (/etc/supervisor/conf.d) y se apartan aqui.
SUPERVISOR_DIR=/etc/supervisor/conf.d
DISABLED_DIR=/tmp/supervisor-disabled
mkdir -p "$DISABLED_DIR"

disable_program() {
  if [ -f "$SUPERVISOR_DIR/$1.conf" ]; then
    mv "$SUPERVISOR_DIR/$1.conf" "$DISABLED_DIR/$1.conf"
  fi
}

case "$ROLE" in
  web)
    disable_program queue
    disable_program scheduler
    ;;
  worker)
    disable_program web
    ;;
  scheduler)
    disable_program web
    disable_program queue
    ;;
  all) ;;
esac

echo "Supervisord programs: $(ls "$SUPERVISOR_DIR" 2>/dev/null | tr '\n' ' ')"
exec supervisord -c /etc/supervisor/supervisord.conf
