# StockPilot

Wholesale warehouse / distribution operations (ERP-lite): inventory, quotes, orders, shipments, and related ops modules.

Greenfield Laravel rebuild of a real production Core PHP system. Industry-agnostic — not limited to a single product vertical.

> Internal planning notes: see [`PROJECT_CONTEXT.md`](PROJECT_CONTEXT.md).

## Stack

- Laravel 13
- Inertia.js + Vue 3 (Vite) + Laravel Breeze auth
- Spatie Laravel Permission (roles)
- Docker Compose: PHP-FPM, Nginx, PostgreSQL 16, pgAdmin

## Requirements

- Docker Desktop (or Docker Engine + Compose v2)
- Node.js 22+ (for Vite builds; or use a Node Docker image)

## Quick start

```bash
cp .env.example .env

docker compose build
docker compose up -d

docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed

# Frontend assets (on host with Node, or via Docker):
npm install
npm run build
# docker run --rm -v "$PWD":/app -w /app node:22-bookworm npm run build
```

- App: **http://localhost:8080**
- Login: **http://localhost:8080/login**
- pgAdmin (DB UI): **http://localhost:8081**
  - Login email: `admin@example.com`
  - Login password: `secret`
  - After login, register/add server:
    - Host/Name: `postgres`
    - Port: `5432`
    - Username: `stockpilot`
    - Password: `secret`
    - Maintenance database: `stockpilot`

### Demo users (after `db:seed`)

| Email | Password | Role |
|-------|----------|------|
| `admin@stockpilot.test` | `password` | admin |
| `staff@stockpilot.test` | `password` | staff |

Postgres itself (`localhost:5432`) is not a website — use pgAdmin or a desktop client (TablePlus, DBeaver).

### Useful commands

```bash
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan tinker
npm run build
npm run dev
docker compose logs -f
docker compose down
```

## Project layout (Docker)

| Service | Role | Port |
|---------|------|------|
| `nginx` | Web server | `8080` → 80 |
| `app` | PHP 8.4 FPM | — |
| `postgres` | PostgreSQL 16 | `5432` (host, not HTTP) |
| `pgadmin` | DB UI in browser | `8081` → 80 |

## Frontend notes

Vue pages live in `resources/js/Pages`. Auth screens come from Breeze (Inertia). Roles/permissions are shared to the frontend via Inertia `auth.user`.

**Dev tip:** for day-to-day Vue edits use `npm run dev` (HMR). `npm run build` is for production-style assets served by Docker/nginx without the Vite dev server.

## Status

Catalog phase 1 ready: categories, products, piece/pallet stock, stock movements.
Clients module ready.
Quotes module ready (header + line items, tax totals, statuses).
Shipments module ready (vehicle/driver, optional quote link, statuses).
Ops summary dashboard ready.
Database: PostgreSQL (local + Railway target).
Next: finish Railway demo with Postgres.

## Deploy on Railway (demo)

Repo uses `Dockerfile.railway` + `railway/start.sh` (migrate/seed then `php artisan serve` on `$PORT`).

1. Sign up at [railway.com](https://railway.com), link GitHub, deploy `fatih-tutar/StockPilot`.
2. Add **PostgreSQL** on the same project.
3. **StockPilot → Settings → Deploy**
   - **Custom Start Command**: leave **empty** (Dockerfile `CMD` handles start)
   - Remove any pre-deploy that only runs migrate if you prefer start.sh (start.sh already migrates)
4. **Settings → Networking → Generate Domain**; set target port to **8080** if asked.
5. Variables (Raw Editor example):

```env
APP_NAME=StockPilot
APP_ENV=production
APP_KEY=base64:...
APP_DEBUG=false
APP_URL=https://YOUR-APP.up.railway.app
APP_LOCALE=tr
LOG_CHANNEL=stderr
DB_CONNECTION=pgsql
DB_URL=${{Postgres.DATABASE_URL}}
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=database
CACHE_STORE=database
PORT=8080
```

`APP_URL` must be **https://** (not http). Wrong scheme → blank white page (mixed content).

6. Redeploy. Login: `admin@stockpilot.test` / `password`.

**PHP version:** `composer.json` requires `^8.4`.
