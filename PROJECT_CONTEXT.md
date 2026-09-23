# StockPilot — Project Context

> Read this file at the start of any new chat about this project.
> Source of truth for goals and decisions from the planning conversation (2026-09-21+).
> Update this file as decisions and phases change. Public intro lives in `README.md`.

## What it is

**StockPilot** is a wholesale warehouse / distribution operations app (ERP-lite): inventory, quotes, factory/supplier orders, shipments, clients, pricing, roles/permissions, leave approvals, and related ops modules.

It is a **greenfield rebuild** of a real production Core PHP system historically used by an aluminum wholesaler (**Aluminyum-Deposu**). The product is **not aluminum-specific** — any wholesaler/distributor could use it. Naming avoids industry lock-in on purpose. “Stock” names the core domain; leave/HR and similar modules are supporting ops.

Reference (legacy, do not copy code): `/Users/fatih/PhpstormProjects/Aluminyum-Deposu`

## Owner goals

- Portfolio / job-interview showcase of real-domain skills
- Eventually **replace** the live Core PHP app in production (full feature parity over time)
- Slow, phased delivery — avoid boiling the ocean
- Owner directs requirements and reviews; implementation assisted by AI (frame in interviews as ownership + AI-accelerated delivery, not “AI wrote it unsupervised”)
- Demo must be publishable (preferably free/low-cost hosting)

## Target stack (agreed direction)

| Layer | Choice | Notes |
|-------|--------|--------|
| Backend | Laravel 13 | Fresh app, not a page-by-page port |
| Local/runtime | Docker Compose | nginx + php-fpm + mysql |
| Frontend (near-term) | Inertia.js + Vue 3 | Popular, Laravel-friendly; **not yet installed** |
| Frontend (later) | Separate SPA + Laravel API | Matches owner’s workplace style; **phase 2+** |
| DB | MySQL 8 (local Docker) | Railway supports MySQL or Postgres later |
| CI | GitHub Actions (test + lint) | CI ≠ hosting; **not yet** |
| Hosting (demo) | Railway (preferred) | Domain like `*.up.railway.app` — **after GitHub** |
| Hosting (real low-traffic prod) | Prefer cheap/always-free VPS if free PaaS sleep/limits hurt ops | 5–10 users |

## How we build

1. **Do not copy** legacy PHP into Laravel. Owner describes workflows; we design domain cleanly.
2. Legacy app is **business-rules reference** only.
3. Full parity is the end state; delivery is **module by module**.
4. Phase 0: **runnable Laravel + Docker skeleton**.
5. Railway: deploy only after code exists on GitHub → **GitHub Repository**, then **Database**.

## Legacy domain snapshot (for later modules)

- Catalog & inventory (piece/pallet/depot, low stock, stock activity log)
- Pricing (FX / metal-linked rates historically)
- Quotes → collections
- Factory / custom orders + printable forms/PDF
- Shipments + vehicles
- Clients & factories
- Molds
- Stock movements / count reports
- Ops: jobs/plan, leave, org chart, customer visits
- Admin: users, permissions, company settings, backups
- Multi-company style tenancy existed (`company_id` / similar)

Portfolio-critical core first when features start: **Auth + roles → Products/Stock → Clients → Quotes → Shipments (or factory orders) → dashboard/report**. Then molds, HR/leave, visits, etc.

## Explicit non-goals for phase 0

- No business feature screens yet (beyond a simple welcome page)
- No production data in public repo
- No separate frontend repo yet
- No Railway / GitHub remote until owner asks

## Phase 0 checklist

- [x] Laravel app scaffold
- [x] Docker Compose (nginx, php-fpm, mysql)
- [x] README: how to run
- [x] Confirm `docker compose up` + http://localhost:8080 locally
- [ ] Optional next: Inertia + Vue hello
- [ ] Push to GitHub (when owner asks)
- [ ] Later: Railway from GitHub + DB + `*.up.railway.app`

## Naming

- **Product / repo folder:** `StockPilot` (confirmed 2026-09-23)
- Path: `/Users/fatih/PhpstormProjects/StockPilot`
- Rationale: inventory + operations “pilot”; industry-agnostic; CV-friendly English name
- Rejected alternatives: JobPilot (wrong domain signal), aluminum-locked names
