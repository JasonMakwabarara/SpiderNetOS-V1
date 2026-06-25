# SpiderNetOS

An Attio-inspired **AI Operating System (AIOS)**: a multi-tenant platform that combines a
flexible custom-object data model, a semantic "universal context" layer (pgvector RAG),
LLM-driven automation (the **Atlas** assistant), agents, and workflow automation.

- **Backend:** Laravel 12 (PHP 8.2+), PostgreSQL + [pgvector], Redis (queue + cache).
- **Frontend:** Vue 3 + Vite SPA in [`cockpit/`](cockpit/) ("the cockpit").
- **Auth:** Laravel Sanctum token auth; strict server-derived multi-tenancy.

> The repository root also contains a minimal Tailwind/Vite setup and a Laravel `welcome`
> view, but the product frontend is the **cockpit** SPA.

## Architecture

```
cockpit/ (Vue 3 SPA)  ──HTTP──▶  Laravel API (routes/api.php)
                                   ├─ Sanctum auth + tenant scope (per-user tenant_id)
                                   ├─ InferenceService ──▶ ProviderResolver ──▶ ai_providers (DB)
                                   ├─ EmbeddingService ──▶ memories.embedding (pgvector)
                                   ├─ Records / ObjectTypes / Attributes (custom-object model)
                                   ├─ Atlas (LLM tool-calling loop)
                                   └─ Flows + Agents (queued jobs)
PostgreSQL (pgvector) + Redis (queue/cache) via docker-compose
```

### AI providers — no hardcoded keys
AI provider credentials are **never** stored in code or `.env`. They live, encrypted at rest,
in the `ai_providers` table and are managed from the super-admin provider panel.
`ProviderResolver` returns the ordered list of usable providers for the current tenant
(tenant overrides first, then platform defaults), and `InferenceService` / `EmbeddingService`
iterate that list as a fallback chain.

### Multi-tenancy
The active tenant is always derived from the authenticated user (`users.tenant_id`).
Client-supplied `X-Tenant-ID` headers are ignored/rejected. A global Eloquent scope enforces
isolation on every tenant-owned model.

## Local setup

Prerequisites: PHP 8.2+, Composer, Node 18+, Docker (for Postgres + Redis).

```bash
cp .env.example .env
composer install
php artisan key:generate

# Start Postgres (pgvector) + Redis + queue worker
docker compose up -d

php artisan migrate
php artisan db:seed

# Cockpit SPA
cd cockpit
npm install
npm run dev
```

Serve the API with `php artisan serve` (http://localhost:8000) and the cockpit with
`npm run dev` (Vite). Set `VITE_API_BASE_URL` in `cockpit/.env` if the API is elsewhere.

## Tests & quality gates

```bash
./vendor/bin/pint --test     # code style
php artisan test             # PHPUnit (auth, tenant isolation, provider resolution, RAG)
cd cockpit && npm run build  # frontend build
```

CI additionally runs a secret scan (gitleaks). See `.github/workflows/`.

## Security

API/AI keys must never be committed. See [`RUNBOOK.md`](RUNBOOK.md) for the secret-rotation
and (if ever needed) git-history-purge procedure.

[pgvector]: https://github.com/pgvector/pgvector
