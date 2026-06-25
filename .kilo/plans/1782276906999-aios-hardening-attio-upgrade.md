# SpiderNetOS — Hardening + Attio-Inspired AIOS Upgrade

Implementation-ready plan combining (a) security/correctness hardening and (b) an Attio-inspired
AIOS product roadmap. Detailed phases: 0, 1, 2, 8. Phases 3–7 and 9 are sequenced roadmap with
schema sketches and dependencies.

> This plan must be executed by an implementation-capable agent. It involves source edits,
> migrations, and mutating git/history commands.

---

## Cross-cutting constraints (apply to every phase)

1. **No hardcoded AI providers.** No provider literals, no `env('OPENAI_API_KEY')` in app code.
   - Providers are stored in a DB table `ai_providers` and managed from a **super-admin dashboard panel**.
   - Per-provider fields: `type` (openai | ollama | azure_openai | anthropic | custom_openai_compatible),
     `name`, `base_url`, `api_key` (encrypted at rest), `chat_model`, `embedding_model`, `enabled`,
     `priority` (fallback order), `scope` (platform | tenant), `tenant_id` (nullable), `config` JSON.
   - **Resolution model: platform-global defaults + optional per-tenant override.** A `ProviderResolver`
     returns the ordered list of usable providers for the current tenant: tenant-scoped enabled providers
     first (by `priority`), then platform-scoped enabled providers. `InferenceService` and `EmbeddingService`
     consume the resolver and iterate fallback order.
2. **Secrets never in code or plaintext.** Provider API keys stored with Laravel `encrypted` casts.
   `.env` holds only bootstrap secrets (APP_KEY, DB, mail, queue). No AI keys in `.env`.
3. **Super-admin tier.** Add `users.is_super_admin` (boolean) in Phase 0; gate the provider panel and
   platform settings with a `super-admin` gate/middleware. Phase 8 folds this flag into the RBAC role system.
4. **Tenant isolation is server-derived.** Tenant always comes from the authenticated user
   (`users.tenant_id`), never a client header. Applies to all new models.

---

## Grounded current state (verified against code)

- `User` model already has `tenant_id` in `$fillable` and a `tenant()` relation — partial tenancy schema exists.
- `TenantMiddleware.php` trusts client `X-Tenant-ID` and **defaults to tenant 1** (insecure). `TenantIsolation.php` is empty.
- Live OpenAI key committed in 8 places: `inference/app.py`, `public/index_broken.php`, `public/agent-run.php`,
  `public/index.txt`, `public/agent-run.txt`, `.env.txt`, `app/Models/.env.txt`, `database/seeders/.env.txt`.
- `EventLogger::log()` inserts into an `events` table that has **no migration** (never works).
- `SendEmailJob` exists but `FlowController::execute` sends mail synchronously instead.
- Migrations exist only as `.php` for tenants/agents/flows/users/personal_access_tokens/cache. The
  `memories`, `feature_packs`, and the "all tables" variants are `.txt` stubs that never migrate.
- `HannahService::chat()` calls `$this->chat()` with a mismatched signature (recursion/fatal); unused.
- `InferenceService` (Ollama→OpenAI, retries, cost cap) is solid but **not wired into any controller**; uses
  wrong model `gemma4:2b`; reads `env()` directly.
- `AtlasController::chat` is `str_contains` keyword matching, not LLM.
- RAG = substring search over `data/memory.json` (`routes/api.php:42-95`); pgvector dependency unused.
- `/atlas/chat` and `/rag/*` routes have **no auth**.
- Frontend: no route guards; `api.js` baseURL hardcoded `http://localhost:8000`; Signup route missing.
- Feature packs: `storage/feature-packs/sales-pack.json`, `test-pack.json` exist.
- docker-compose includes only Postgres (pgvector) + Redis; app/cockpit/python not containerized.

---

## PHASE 0 — Foundation + Secrets + AI Provider Panel (DETAILED, unblocks everything)

### 0A. Secret remediation (do first)
- [ ] Revoke/rotate the leaked OpenAI key at the provider.
- [ ] Remove the secret from all 8 files; delete `.txt`/`.env.txt` shadow files entirely.
- [ ] Rewrite git history to purge the secret (`git filter-repo` or BFG); force-push if remote exists; notify collaborators to re-clone.
- [ ] Add CI secret scanning (gitleaks or trufflehog) as a required check.
- [ ] Confirm `.gitignore` excludes `.env`, `*.env.txt`, archives, `node_modules/`, `composer.phar`.

### 0B. Repo hygiene
- [ ] `.gitignore`: `node_modules/`, `*.zip`, `*.tar.gz`, `composer.phar`, `.env*` (except `.env.example`), `*.bak`, `*.backup`.
- [ ] `git rm --cached` committed `node_modules/`, archives, `composer.phar`.
- [ ] Delete `.txt`/`.bak`/`.backup` shadow copies of `.php`/`.vue` and junk files (`$id,`, `status,`, `description,`, `name,`, `request-`).
- [ ] Replace boilerplate `README.md` with real product README; expand `RUNBOOK.md`.

### 0C. Real migrations (replace `.txt` stubs)
- [ ] `memories`: `id, tenant_id, source_type, source_id, content (text), embedding vector(1536), metadata json, created_at` (pgvector column; dimension configurable to match embedding model).
- [ ] `feature_packs`: align with `FeaturePack` model + `Tenant::featurePacks()`.
- [ ] `events`: `id uuid, type, aggregate_id, data json, tenant_id nullable, created_at, updated_at` (so `EventLogger` works; reused for audit in Phase 8).
- [ ] Add to `agents`: `type` and `pack_id` (already in `Agent::$fillable`); FK `pack_id → feature_packs`.
- [ ] Add `users.is_super_admin` (boolean, default false).
- [ ] Delete all `.txt` migration stubs after porting.

### 0D. AI provider system (the "no hardcoding" requirement)
- [ ] Migration `ai_providers` (fields per cross-cutting constraint 1); `api_key` uses encrypted cast.
- [ ] `AiProvider` model with tenant global scope variant that also surfaces platform-scoped rows.
- [ ] `ProviderResolver` service: returns ordered usable providers for current tenant (tenant overrides → platform defaults), filtered by `enabled`, ordered by `priority`.
- [ ] Refactor `InferenceService`:
  - constructor takes `ProviderResolver` (no `env()`); iterate providers as fallback chain.
  - keep retry/backoff + daily cost cap (track cost per tenant+provider in `events`/cache).
  - correct model names come from provider config (no `gemma4:2b` literal).
  - TLS verification ON (remove `verify=false` / `CURLOPT_SSL_VERIFYPEER=false`).
- [ ] New `EmbeddingService` (provider-resolved embedding model) — used in Phase 2.
- [ ] Super-admin panel (Phase 0 minimal): `super-admin` gate using `users.is_super_admin`; routes + cockpit
  view `cockpit/src/views/admin/Providers.vue` for CRUD on platform providers; tenant-admin override panel
  can land with Phase 8 RBAC.

### 0E. Wire the consolidated AI layer
- [ ] Replace inline cURL in `routes/api.php` RAG, Guzzle in `AgentController::run`, and `str_contains` paths
      so all AI calls go through `InferenceService`.
- [ ] Remove `LLMService`, `HannahService` (Hannah returns properly in Phase 7), and the unused Python
      `inference/` + `intelligence/` stubs (consolidation decision: single Laravel AI service).
- [ ] `FlowController::execute` dispatches `SendEmailJob` (queue) instead of synchronous `Mail::raw`.
- [ ] docker-compose: add `app` (php-fpm/artisan), `cockpit` (node/vite), keep Postgres+pgvector + Redis;
      add queue worker service.

**Phase 0 validation:** no secret in tree or history; `gitleaks` passes; `php artisan migrate:fresh` succeeds;
an AI call resolves a provider from DB (no env/literal) and falls back when the first provider is disabled.

---

## PHASE 1 — Flexible data model (DETAILED, Attio foundation)

Tenant-scoped custom-object system mirroring the existing `Agent` tenant global-scope pattern.

- [ ] `object_types`: `id, tenant_id, slug, name, icon, is_system, timestamps` (unique slug per tenant).
- [ ] `attributes`: `id, object_type_id, slug, name, type (text|number|date|currency|select|multiselect|relationship|checkbox|ai), config json, is_required, position`.
- [ ] `records`: `id, object_type_id, tenant_id, data json, created_by, timestamps`.
- [ ] `record_links`: `id, from_record_id, to_record_id, attribute_id` (graph associations).
- [ ] Models: `ObjectType`, `Attribute`, `Record`, `RecordLink` with tenant global scope.
- [ ] `RecordController` (generic) + routes: `GET/POST/PUT/DELETE /api/objects`,
      `/api/objects/{slug}/attributes`, `/api/objects/{slug}/records` with filter/sort query params.
- [ ] Attribute-driven validation (typed values per attribute definition).
- [ ] Seed system objects `people`, `companies`, `deals`; align `sales-pack.json` to create real records.
- [ ] Cockpit `Records.vue` (object list + record table/detail) + router entries.

**Phase 1 validation:** create object type → attributes → record via API; tenant A cannot read tenant B records.

---

## PHASE 2 — Universal Context: semantic layer + LLM Atlas (DETAILED)

Depends on Phase 0 (EmbeddingService, providers) and Phase 1 (records to index).

- [ ] Replace JSON substring RAG (`routes/api.php`) with pgvector cosine similarity over `memories.embedding`.
- [ ] On write of records/notes/docs: generate embeddings via `EmbeddingService` and upsert into `memories` (tenant-scoped).
- [ ] Backfill: index existing records so "ask" spans all data.
- [ ] Rebuild `AtlasController::chat` as an LLM tool-calling loop (via `InferenceService`), exposing tools:
      `create_record`, `search_records`, `semantic_search`, `create_agent`, `create_flow`, `run_agent`.
      Model decides tool use; remove `str_contains` branching. (Cockpit `Atlas.vue` already posts to
      `/api/atlas/chat` — minimal UI change; keep client-side command shortcuts optional, server is authoritative.)
- [ ] Advanced memory: `interactions` table (`tenant_id, user_id, agent_id?, prompt, response, feedback, created_at`);
      summarize+embed into `memories`; per-user/tenant "memory profile" record injected into system prompts.

**Phase 2 validation:** semantic query returns ranked records by vector similarity (not substring); Atlas
creates a record through a tool call; interactions are recorded and retrievable as context.

---

## PHASE 8 — Enhanced security + hardened multi-tenancy (DETAILED, cross-cutting; land after Phase 1)

- [ ] **Tenancy:** rewrite/replace `TenantMiddleware` to resolve tenant strictly from `auth()->user()->tenant_id`;
      ignore/reject client `X-Tenant-ID` (reject on mismatch); remove default-1 behavior; delete empty `TenantIsolation.php`.
- [ ] Apply tenant global scope uniformly across all models (`agents`, `flows`, `object_types`, `attributes`,
      `records`, `record_links`, `memories`, `interactions`, `activities`).
- [ ] **Auth:** put `/atlas/chat` and `/rag/*` behind `auth:sanctum`; audit all routes for missing auth.
- [ ] **Frontend:** Vue router navigation guards (redirect unauthenticated to `/login` before load, not only on 401);
      make `api.js` baseURL env-driven (`import.meta.env.VITE_API_BASE_URL`); register missing Signup route.
- [ ] **RBAC:** add `spatie/laravel-permission`; define roles (super-admin, tenant-admin, member); migrate the
      Phase 0 `is_super_admin` flag into a `super-admin` role; gate record/agent/flow/admin and the provider panels
      (platform panel = super-admin; per-tenant provider override panel = tenant-admin).
- [ ] **Audit:** wire `EventLogger` (now backed by `events`); log auth, record/agent/flow CRUD, flow/agent runs,
      provider changes, security events; expose `GET /api/audit` (tenant-scoped; super-admin sees platform).
- [ ] **Rate limiting:** Laravel rate limiters per tenant/user/token on API routes.

**Phase 8 validation:** automated tests prove tenant A's token cannot access tenant B data on every model;
unauthenticated requests to all protected routes return 401; super-admin-only routes reject non-super-admins;
audit rows written for key actions; rate limit returns 429 past threshold.

---

## ROADMAP (sequenced; schema sketches + dependencies; detail at execution time)

### Phase 3 — AI Attributes (computed fields) — depends on P1, P2
- Attribute `type = ai` with `config.prompt`, `config.output` (text|select|number).
- Queued `ComputeAiAttributeJob` runs prompt through `InferenceService` with record + semantic context; writes back to `records.data`.
- Triggers: on record create/update or on demand. (enrichment / classification / summarization, e.g. lead score.)

### Phase 4 — Real automation engine — depends on P1 (parallel with P2)
- `flows.trigger_type` (manual|webhook|schedule|record_event) + `trigger_config` json.
- Inbound `POST /api/flows/webhook/{token}`; Eloquent observers dispatch flows on record events; Laravel scheduler for schedule triggers.
- Move action loop into queued `RunFlowJob`; add action types `create_record`, `update_record`, `run_agent`, `ai_step` alongside email/slack/webhook.
- **DAG builder:** persist flows as `config.nodes[]` + `config.edges[]`; canvas in `Flows.vue` (e.g. Vue Flow); `RunFlowJob` executes in topological order passing outputs to successors (branching/conditions).

### Phase 5 — Auto-capture + activity timeline — depends on P1
- `activities` table: `record_id, type (email|call|note|meeting|system), payload json, occurred_at, tenant_id`.
- `GET /api/records/{id}/activities` + timeline panel in record detail.
- Inbound email webhook + manual notes first; Google/Microsoft OAuth sync later.

### Phase 6 — Developer platform + views + reporting + predictive — depends on P1, P2
- MCP server exposing records/agents/flows as MCP tools (Laravel route bridge or small service).
- Views: kanban + table over records (group-by attribute), saved filters.
- Reporting: bar/line/pie/funnel aggregation endpoints + charts view.
- Predictive: `ForecastService` (trend + win-rate per stage from deals + activities) via `GET /api/analytics/forecast`; optional Python service for heavier models; forecast charts on dashboard.

### Phase 7 — AI Agent Orchestration (Hannah + autonomous agents) — depends on P2, P4
- Promote `HannahService` into live orchestrator: receives goal, plans sub-tasks, delegates to role agents via P2 tool-calling.
- `Orchestrator` runs multi-agent `RunAgentJob`s; agents call tools (records, semantic_search, flows) and hand off; results + activities written back.
- Autonomous mode: agents triggered by record events (P4) act without prompting (new lead → score + draft outreach).

### Phase 9 — Integration Hub + real-time monitoring — cross-cutting; after P1
- `integrations` table (`type, credentials (encrypted), status`) + OAuth/credential flow + normalized adapter interface.
- Connectors: external CRM (HubSpot/Salesforce import/export to records), email (IMAP/SMTP + inbound capture), Slack app, WhatsApp Cloud API; feed records/activities; usable as flow action targets.
- Monitoring dashboard: metrics endpoint + cockpit view for system health (queue depth, DB/health, error rate), agent performance (runs/latency/cost from `InferenceService` tracking), user activity (audit/activities). Polling first; Laravel Reverb/Echo for live updates later.

---

## Sequencing rationale

Phase 0 → 1 first: nothing else (AI attributes, triggers, timelines, analytics, orchestration) has data to
operate on until secrets are remediated, the AI provider layer is configurable, and a real record/data layer
exists. Phases 2 & 4 proceed in parallel after Phase 1. Phase 3 needs 1+2. Phase 7 needs 2+4. Phases 8
(security/tenancy) and 9 (integrations/monitoring) are cross-cutting; 8 should land right after Phase 1 to
secure the new data layer, 9 sequenced last to harden an existing feature set.

## Global validation / definition of done

- CI required checks: secret scan (gitleaks), `pint`, PHPUnit (auth, tenant-isolation, RAG similarity,
  provider-resolution + fallback), `npm run build`.
- No AI provider literal or `env()` AI key anywhere in app code; providers resolved from DB.
- `migrate:fresh` clean; docker-compose brings up app + cockpit + Postgres(pgvector) + Redis + queue worker.
- Tenant isolation enforced on every model; protected routes reject unauthenticated; super-admin routes reject non-super-admins.
- Marketing claims (RAG/vector search, audit logs, tenant isolation) are now true.

## Risks / watch-items

- History rewrite requires coordination if the repo is shared (force-push + re-clone).
- pgvector embedding dimension must match the configured embedding model; store dimension in provider/config and guard mismatches.
- Per-tenant provider keys increase the secret-management surface — rely on encrypted casts + restricted super-admin/tenant-admin access.
- DAG executor (P4) and multi-agent orchestration (P7) are the highest-complexity items; keep them queued and idempotent.
