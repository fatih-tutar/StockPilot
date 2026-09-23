# StockPilot

Wholesale warehouse / distribution operations (ERP-lite): inventory, quotes, orders, shipments, and related ops modules.

Greenfield Laravel rebuild of a real production Core PHP system. Industry-agnostic — not limited to a single product vertical.

> Internal planning notes: see [`PROJECT_CONTEXT.md`](PROJECT_CONTEXT.md).

## Stack (phase 0)

- Laravel 13
- Docker Compose: PHP-FPM, Nginx, MySQL 8, phpMyAdmin
- (Next) Inertia + Vue, auth, domain modules

## Requirements

- Docker Desktop (or Docker Engine + Compose v2)

## Quick start

```bash
cp .env.example .env
# APP_KEY is generated below if empty

docker compose build
docker compose up -d

docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
```

- App: **http://localhost:8080**
- phpMyAdmin: **http://localhost:8081** (user `stockpilot` / pass `secret`)

MySQL itself (`localhost:3306`) is not a website — use phpMyAdmin or a DB client.

### Useful commands

```bash
docker compose exec app php artisan migrate
docker compose exec app php artisan tinker
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

## Status

Phase 0 skeleton — runnable Laravel + Docker. Business features come next.
