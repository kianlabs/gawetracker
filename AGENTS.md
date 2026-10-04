# GaweTracker — Agent & Contributor Guidelines

## Project Overview

GaweTracker is a personal job application tracker built with Laravel 13 and pure CSS (no Vite/Node/React). All UI copy is in Indonesian. Single-user auth only.

## Stack

- **Backend**: Laravel 13, PHP 8.3+, MySQL
- **Frontend**: Blade templates, pure CSS at `public/css/shadcn.css`
- **No JavaScript additions** — use existing vanilla JS patterns in views only
- **Testing**: PHPUnit via `php artisan test`

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
# configure DB_* in .env
php artisan migrate --seed
php artisan serve
```

Default credentials: `kyan@gawetracker.test` / `password`

## Key Conventions

### CSS
- All styles live in `public/css/shadcn.css` — append new rules at the end
- CSS variables: `--background`, `--foreground`, `--muted`, `--muted-foreground`, `--border`, `--radius`, `--primary`, `--card`
- Existing utility classes: `section-card`, `section-header`, `section-title`, `section-body`, `page-header`, `page-title`, `page-subtitle`, `page-actions`, `metric-card`, `metric-strip`, `badge`, `badge-{secondary,info,warning,success,destructive,purple}`, `btn`, `btn-{outline,default,destructive,sm}`, `list-row`, `co-logo`, `co-badge`, `heatmap-*`, `offer-alert`

### Models
- `JobApplication` — main model; statuses: `wishlist`, `applied`, `screening`, `interview`, `offer`, `hired`, `rejected`
- `Company` — canonical employer registry; `Company::findOrCreateByName()` normalises spellings via `App\Support\Company\CompanyNameNormalizer` so "PT Tokopedia", "Tokopedia, PT" and "tokopedia.com" are one row
- `JobPosting` — a job discovered from Glints/Jobstreet; distinct from `JobApplication` (advertisement vs. decision). Idempotent on `(source, external_id)`
- `StatusHistory` — records every status transition; `from_status`, `to_status`, `note`
- `OfferDetail` — salary, benefits, `deadline_at` (date) for offer-stage applications
- `InterviewChecklist` — per-application checklist items
- `JobApplication::interview_result` — free-text outcome/notes per interview stage (PRD user story 5)
- `User::weekly_target` — per-user weekly application target (default 8); read via `User::weeklyTarget()`
- `JobApplication::logoUrl()` — nullable Attribute; returns Google Favicon URL for known companies, `null` for unknowns (views show initial-letter badge as fallback)

### Controllers
- `JobApplicationController` — CRUD, export CSV, quick-add, quick-status
- `KanbanController` — kanban view
- `DashboardController` — dashboard with metrics, follow-up queue, expiring offer alerts; `updateTarget()` persists the weekly target (`POST /dashboard/target`)
- `AnalyticsController` — funnel, rejection analysis, time-to-response, weekly volume, 12-month heatmap
- `OfferController` — offer comparison matrix
- `InterviewChecklistController` — checklist CRUD

### Discovery (external job boards)
- `App\Services\Discovery\JobSource` — contract every board implements (`name()`, `search()`)
- `GlintsSource` — Glints GraphQL. Endpoint `/api/v2-alc/graphql`, operation `searchJobs`
  (NOT `searchJobsV3` — that one resolves but silently ignores the keyword), keyword field
  `SearchTerm` as a **plain string**, pagination by `limit`/`offset` (NOT `page`/`pageSize`).
  Requires browser-like headers or Glints' firewall returns HTML.
- `JobstreetSource` — SEEK v5 REST at `id.jobstreet.com/api/jobsearch/v5/search` (`siteKey=ID-Main`)
- `JobDiscoveryService` — runs all sources, persists via `JobPosting`; one failing board never aborts the run
- `JobDiscoveryController` — the "Cari Kerja" page (`/discovery`): live search form, filter by
  source/promoted, "+ Lamar" promotes a `JobPosting` into a `JobApplication` (idempotent)
- `php artisan jobs:discover "<keyword>" --limit=30` — pull postings into the DB
- `php artisan companies:backfill [--dry-run]` — link legacy applications to canonical companies

### Views
- Extend `layouts.app`
- All user-facing text in Indonesian
- Logo pattern: `@if ($app->logo_url)` → `<img class="co-logo" onerror="...">` + hidden `<span class="co-badge">` → `@else` → visible `<span class="co-badge">` → `@endif`

## Tests

```bash
php artisan test          # 204 tests, 1020 assertions
php artisan test --filter SomeTest
```

Tests live in `tests/Feature/` and `tests/Unit/`. Do not break existing assertions. Run the suite after any change.
Tests run on in-memory SQLite (`phpunit.xml`), the local app runs on MySQL.

## Environment Pitfall

A leaked `DB_CONNECTION=sqlite` / `DB_DATABASE=/tmp/gt_smoke_*.sqlite` in the shell **silently overrides `.env`**:
`php artisan migrate` then reports success while writing to a throwaway SQLite file, and the real MySQL DB
stays empty (the app 500s with "table doesn't exist"). Before trusting artisan output run
`unset DB_CONNECTION DB_DATABASE DB_URL`, or check `DB::connection()->getDriverName()` is `mysql`.

### The shared-MySQL trap (read before any destructive command)

The local `.env` points `DB_DATABASE=gawetracker` at `127.0.0.1:3306` — **the same database the production
container `gawetracker-app` uses** (it reaches it as host `mysql8`). Running artisan from the host with the
ambient `.env` therefore operates on production data. A `migrate:fresh --force` run this way once wiped
every table in production.

Rules:

- **Never** run `migrate:fresh`, `migrate:refresh`, `db:wipe`, or any destructive DB command from the host.
- Prefix every artisan call that touches the real DB with `unset DB_CONNECTION DB_DATABASE DB_URL`.
- For production writes, run artisan **inside the container** (`docker exec gawetracker-app php artisan …`);
  its env already points at the correct database and never at a dev override.
- Read-only `migrate --force` / `migrate:status` are safe, but still verify the target first.
- Tests are unaffected: `phpunit.xml` forces in-memory SQLite.
- To exercise destructive migrations, use an explicitly isolated database (a throwaway container), never the
  shared one.

A safety net backs this up: `AppServiceProvider` calls `DB::prohibitDestructiveCommands()` whenever the
default MySQL connection points at the shared `gawetracker` database, so `db:wipe`,
`migrate:fresh|refresh|reset|rollback` refuse to run. Set `GAWETRACKER_ALLOW_DESTRUCTIVE_DB=true` only for a
deliberately isolated rebuild.

## What Not to Do

- Do not add Node, Vite, React, or npm packages
- Do not add new JavaScript files or inline `<script>` blocks beyond existing patterns
- Do not use Clearbit Logo API (dead — returns 404)
- Do not display fake/invented metrics without real database calculations
- Do not add decorative elements without functional purpose (see DESIGN.md)
