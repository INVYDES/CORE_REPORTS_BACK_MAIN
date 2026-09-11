# CsRecords - CoreReports

Sistema multi-tenant para gestión de tickets, servicios programados y reportes técnicos con analytics en vivo.

**Stack:** Laravel 12 (PHP 8.2) + Vue 3 + Vite + MySQL 8 + Sanctum + Pinia

## Arquitectura
```
CsRecords/
├── BackEnd-Api/core-api/   # Laravel API (Sanctum, 70 endpoints, 13 views SQL)
├── Front/                  # Vue 3 SPA (Vite, Pinia, Vue Router, ApexCharts)
├── BD/                     # SQL: Tablas.sql + 04_Encuestas_y_Facturacion.sql
├── docker-compose.yml      # db + backend + queue + scheduler + frontend
└── Logico-Relacional.mwb   # MySQL Workbench model
```

## Requisitos
- PHP 8.2, Composer, Node 20, MySQL 8, Docker (opcional)

## Instalación local (sin Docker)
```bash
# Backend
cd BackEnd-Api/core-api
cp .env.example .env
# Edita DB_*, FRONTEND_URL, SENTRY_LARAVEL_DSN, MAIL_*
composer install
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force   # Roles + demo empresa + 4 usuarios
php artisan serve --host=127.0.0.1 --port=8000

# En otra terminal: queue + scheduler
php artisan queue:work --sleep=3 --tries=3
php artisan schedule:work

# Frontend
cd ../../Front
cp .env.example .env
# Edita VITE_API_URL=http://localhost:8000/api/v1
npm ci
npm run dev  # http://localhost:5173/core-reports/
```

## Docker (recomendado)
```bash
docker compose up --build
# Frontend: http://localhost:5173/core-reports/
# Backend:  http://localhost:8000/api/v1/me (Bearer token)
# DB:       localhost:3307 (root/747375)
# Queue + scheduler corren en el servicio `worker` (queue:work + schedule:work)
```

## Usuarios demo (password: `password`)
- `admin.tec@core.com` (AdminTec, rol 1) - ve tickets, reportes, análisis
- `admin.com@core.com` (AdminCom, rol 2) - ve análisis, subáreas
- `tecnico@core.com` (Técnico, rol 3)
- `cliente@core.com` (Cliente, rol 0)

## API
- Versionada: `/api/v1/*`
- Rate limit: `60 req/min` (throttle:60,1) - verificado con 61 req
- Docs: `php artisan scribe:generate` -> `public/docs` (`/api/docs`)
- Telescope: `http://localhost:8000/telescope` (si `TELESCOPE_ENABLED=true` + `APP_ENV=local`)
- Analytics: `GET /api/v1/analytics/all` (11 views SQL, sin cache, en vivo)

## Observabilidad
- Sentry: `SENTRY_LARAVEL_DSN` en .env
- LogRocket: `VITE_LOGROCKET_ID` en Front/.env
- Telescope para queries/jobs/logs

## Lint / Tests / CI
```bash
# Backend
composer run lint        # pint --test
composer run lint:fix    # pint
composer run analyse     # phpstan level 9
php artisan test --coverage --min=70

# Frontend
npm run lint             # eslint
npm run format:check     # prettier
npm run test -- --coverage # vitest 70% threshold
npm run build            # vite build
npm run storybook        # http://localhost:6006 (StatCard)
```
CI: `.github/workflows/ci.yml` (PHP 8.2 + Node 20, install, pint, phpstan, phpunit, eslint, prettier, vitest, build, artifacts). Husky pre-commit: `pint --test && npm run lint && npm run test`.

## Estructura DB
- 13 tablas base + 4 nuevas (areas, equipos, notificaciones, movimientos_inventario) + 6 framework (jobs, cache, sessions...) + 1 encuestas + 10 columnas fiscales en dependencias
- 11 views SQL (`vw_ticket_sla`, `vw_ticket_mttr`, etc.) creadas en migración `2026_09_03_000000_create_core_reports_schema.php`
- Seed: `DatabaseSeeder` -> `CoreReportsSeeder` (roles, empresa demo, áreas, usuarios)

## Troubleshooting
- `Unknown column 'conformidad'` -> views desactualizadas, corre `php artisan migrate:fresh --seed`
- `CSRF token mismatch` -> usa Bearer token, no cookies (stateless)
- `No se puede logear` -> verifica `roles` tiene 4 filas y `php artisan db:seed` corrió
