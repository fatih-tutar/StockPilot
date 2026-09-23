# StockPilot

Wholesale warehouse / distribution operations (ERP-lite): inventory, quotes, orders, shipments, and related ops modules.

Greenfield Laravel rebuild of a real production Core PHP system. Industry-agnostic — not limited to a single product vertical.

> Internal planning notes: see [`PROJECT_CONTEXT.md`](PROJECT_CONTEXT.md).

## Stack

- Laravel 13
- Inertia.js + Vue 3 (Vite) + Laravel Breeze auth
- Spatie Laravel Permission (roles)
- Docker Compose: PHP-FPM, Nginx, MySQL 8, phpMyAdmin

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
- phpMyAdmin: **http://localhost:8081** (user `stockpilot` / pass `secret`)

### Demo users (after `db:seed`)

| Email | Password | Role |
|-------|----------|------|
| `admin@stockpilot.test` | `password` | admin |
| `staff@stockpilot.test` | `password` | staff |

MySQL itself (`localhost:3306`) is not a website — use phpMyAdmin or a DB client.

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
| `mysql` | MySQL 8.4 | `3306` (host, not HTTP) |
| `phpmyadmin` | DB UI in browser | `8081` → 80 |

## Frontend notes

Vue pages live in `resources/js/Pages`. Auth screens come from Breeze (Inertia). Roles/permissions are shared to the frontend via Inertia `auth.user`.

**Dev tip:** for day-to-day Vue edits use `npm run dev` (HMR). `npm run build` is for production-style assets served by Docker/nginx without the Vite dev server.

## Status

Catalog phase 1 ready: categories, products, piece/pallet stock, stock movements.
Clients module ready.
Quotes module ready (header + line items, tax totals, statuses).
Shipments module ready (vehicle/driver, optional quote link, statuses).
Next: Railway demo deploy.
