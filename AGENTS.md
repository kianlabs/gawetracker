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
- `StatusHistory` — records every status transition; `from_status`, `to_status`, `note`
- `OfferDetail` — salary, benefits, `deadline_at` (date) for offer-stage applications
- `InterviewChecklist` — per-application checklist items
- `JobApplication::logoUrl()` — nullable Attribute; returns Google Favicon URL for known companies, `null` for unknowns (views show initial-letter badge as fallback)

### Controllers
- `JobApplicationController` — CRUD, export CSV, quick-add, quick-status
- `KanbanController` — kanban view
- `DashboardController` — dashboard with metrics, follow-up queue, expiring offer alerts
- `AnalyticsController` — funnel, rejection analysis, time-to-response, weekly volume, 12-month heatmap
- `OfferController` — offer comparison matrix
- `InterviewChecklistController` — checklist CRUD

### Views
- Extend `layouts.app`
- All user-facing text in Indonesian
- Logo pattern: `@if ($app->logo_url)` → `<img class="co-logo" onerror="...">` + hidden `<span class="co-badge">` → `@else` → visible `<span class="co-badge">` → `@endif`

## Tests

```bash
php artisan test          # 77 tests, 598 assertions
php artisan test --filter SomeTest
```

Tests live in `tests/Feature/JobApplicationTest.php`. Do not break existing assertions. Run the suite after any change.

## What Not to Do

- Do not add Node, Vite, React, or npm packages
- Do not add new JavaScript files or inline `<script>` blocks beyond existing patterns
- Do not use Clearbit Logo API (dead — returns 404)
- Do not display fake/invented metrics without real database calculations
- Do not add decorative elements without functional purpose (see DESIGN.md)
