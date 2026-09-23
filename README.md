# StockPilot

Wholesale warehouse / distribution operations (ERP-lite): inventory, quotes, orders, shipments, and related ops modules.

Greenfield Laravel rebuild of a real production Core PHP system. Industry-agnostic — not limited to a single product vertical.

> Internal planning notes: see [`PROJECT_CONTEXT.md`](PROJECT_CONTEXT.md).

## Stack (phase 0)

- Laravel 13
- Inertia.js + Vue 3 (Vite)
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
docker compose exec app php artisan migrate

# Frontend assets (on host with Node, or via Docker):
npm install
npm run build
# docker run --rm -v "$PWD":/app -w /app node:22-bookworm npm run build
```

- App: **http://localhost:8080**
- phpMyAdmin: **http://localhost:8081** (user `stockpilot` / pass `secret`)

MySQL itself (`localhost:3306`) is not a website — use phpMyAdmin or a DB client.

### Useful commands

```bash
docker compose exec app php artisan migrate
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

Vue pages live in `resources/js/Pages`. The home route renders `Welcome.vue` via Inertia (`routes/web.php`). Same repo as Laravel — not a separate frontend project.

## Status

Phase 0 skeleton with Inertia + Vue hello. Auth and domain modules come next.
