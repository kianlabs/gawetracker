# Graph Report - gawetracker  (2026-10-19)

## Corpus Check
- 144 files · ~121,916 words
- Verdict: corpus is large enough that graph structure adds value.
- Unclassified: 34 file(s) not represented in the graph (top: (none) 20, .eml 4, .example 2)

## Summary
- 961 nodes · 1724 edges · 97 communities (22 shown, 75 thin omitted)
- Extraction: 94% EXTRACTED · 6% INFERRED · 0% AMBIGUOUS · INFERRED: 102 edges (avg confidence: 0.88)
- Token cost: 0 input · 0 output

## Graph Freshness
- Built from commit: `d70cf766`
- Run `git rev-parse HEAD` and compare to check if the graph is stale.
- Run `graphify update .` after code changes (no API cost).

## Community Hubs (Navigation)
- JobEmailParser
- GlintsSource
- composer.json
- JobPosting
- JobstreetSource
- GoogleOAuthService
- GaweTracker
- JobApplication
- .findOrCreateByName
- package.json
- JobMatchService
- Illuminate\Http\RedirectResponse
- GoogleLoginTest
- Controller
- Illuminate\Http\Request
- DashboardTest
- AnalyticsTest
- JobApplicationCrudTest
- JobApplicationDetailTest
- JobDiscoveryTest
- OfferChecklistTest
- AnalyticsController.php
- AuthController.php
- UserFactory.php
- Illuminate\Database\Schema\Blueprint
- StoreJobApplicationRequest
- GmailConnectionTest
- TestCase
- Deploy GaweTracker to Oracle Cloud (Always Free)
- Illuminate\Database\Seeder
- KanbanTest
- QuickAddExportTest
- User
- Illuminate\Foundation\Testing\RefreshDatabase
- AuthTest
- EmailIngestionTest
- bootstrap/app.php
- Illuminate\Support\Facades\Schema
- Illuminate\Database\Migrations\Migration
- Deploy GaweTracker via Cloudflare Tunnel
- JobMatchTest
- JobApplicationController
- JobApplicationTest
- AppServiceProvider
- 0001_01_01_000001_create_cache_table.php
- 2026_10_01_042138_add_indexes_to_job_applications_table.php
- 2026_10_03_000001_add_gmail_oauth_to_users_table.php
- 2026_10_05_000001_add_company_id_to_job_applications_table.php
- 2026_10_06_000000_add_weekly_target_to_users_table.php
- 2026_10_06_000001_add_interview_result_to_job_applications_table.php
- 2026_10_18_140000_add_match_score_to_job_applications_table.php
- 2026_10_18_155440_add_api_token_to_users_table.php
- 2026_10_18_155940_add_user_id_to_job_applications_table.php
- Deploy GaweTracker to Fly.io
- GmailSetupCommandTest
- 2026_09_30_110730_create_status_histories_table.php
- 2026_10_05_000000_create_companies_table.php
- 2026_10_05_000002_create_job_postings_table.php
- provision.sh
- Screenshots
- release.sh
- deploy-container.sh
- backup.sh
- keepalive.sh
- analytics/index.blade.php
- create.blade.php
- edit.blade.php
- applications/index.blade.php
- kanban.blade.php
- show.blade.php
- dashboard.blade.php
- discovery/index.blade.php
- integrations/index.blade.php
- offers/index.blade.php

## God Nodes (most connected - your core abstractions)
1. `JobApplication` - 54 edges
2. `User` - 46 edges
3. `TestCase` - 42 edges
4. `GlintsSource` - 37 edges
5. `JobstreetSource` - 32 edges
6. `GoogleOAuthService` - 28 edges
7. `JobEmailParser` - 25 edges
8. `GlintsSourceTest` - 25 edges
9. `GoogleLoginTest` - 20 edges
10. `JobDiscoveryService` - 19 edges

## Surprising Connections (you probably didn't know these)
- `Controllers` --references--> `AnalyticsController`  [INFERRED]
  AGENTS.md → app/Http/Controllers/AnalyticsController.php
- `Controllers` --references--> `JobApplicationController`  [INFERRED]
  AGENTS.md → app/Http/Controllers/JobApplicationController.php
- `Discovery (external job boards)` --references--> `JobDiscoveryController`  [INFERRED]
  AGENTS.md → app/Http/Controllers/JobDiscoveryController.php
- `Controllers` --references--> `OfferController`  [INFERRED]
  AGENTS.md → app/Http/Controllers/OfferController.php
- `Models` --references--> `Company`  [INFERRED]
  AGENTS.md → app/Models/Company.php

## Import Cycles
- None detected.

## Communities (97 total, 75 thin omitted)

### Community 0 - "JobEmailParser"
Cohesion: 0.06
Nodes (11): DiscoverJobs, ImportJobEmails, IngestedEmail, {closure#1}(), {closure#2}(), {closure#3}(), EmailIngestionService, GmailClient (+3 more)

### Community 1 - "GlintsSource"
Cohesion: 0.06
Nodes (15): DiscoveredJob, GlintsSource, JobDiscoveryService, JobSource, From the CLI, From the UI, How it works, Job discovery (Glints & JobStreet) (+7 more)

### Community 2 - "composer.json"
Cohesion: 0.04
Nodes (47): pestphp/pest-plugin, php-http/discovery, autoload, autoload-dev, psr-4, psr-4, config, allow-plugins (+39 more)

### Community 3 - "JobPosting"
Cohesion: 0.06
Nodes (19): CSS, Discovery (external job boards), Environment Pitfall, GaweTracker — Agent & Contributor Guidelines, Key Conventions, Models, Project Overview, Setup (+11 more)

### Community 4 - "JobstreetSource"
Cohesion: 0.14
Nodes (4): JobstreetSource, {closure#4}(), {closure#5}(), JobstreetSourceTest

### Community 5 - "GoogleOAuthService"
Cohesion: 0.13
Nodes (3): GmailSetup, AuthController, GoogleOAuthService

### Community 6 - "GaweTracker"
Cohesion: 0.07
Nodes (25): 500 Error, Alternative: Fly.io, Create `fly.toml`, GaweTracker Deployment Guide, Migration Fails, Post-Deployment, Prerequisites, Railway Deployment (Recommended) (+17 more)

### Community 7 - "JobApplication"
Cohesion: 0.16
Nodes (3): {closure#2}(), OfferController, JobApplication

### Community 8 - ".findOrCreateByName"
Cohesion: 0.13
Nodes (4): BackfillCompanies, Company, CompanyNameNormalizer, CompanyNameNormalizerTest

### Community 9 - "package.json"
Cohesion: 0.10
Nodes (20): devDependencies, concurrently, laravel-vite-plugin, tailwindcss, @tailwindcss/vite, vite, optionalDependencies, @laravel/multiplex (+12 more)

### Community 10 - "JobMatchService"
Cohesion: 0.17
Nodes (3): JobMatchService, ExampleTest, JobMatchServiceTest

### Community 11 - "Illuminate\Http\RedirectResponse"
Cohesion: 0.20
Nodes (3): GmailConnectionController, IntegrationController, JobDiscoveryController

### Community 13 - "Controller"
Cohesion: 0.19
Nodes (4): Controllers, DashboardController, InterviewChecklistController, KanbanController

### Community 14 - "Illuminate\Http\Request"
Cohesion: 0.20
Nodes (3): BotApplicationController, Controller, JobMatchController

### Community 24 - "Illuminate\Database\Schema\Blueprint"
Cohesion: 0.23
Nodes (6): {closure#1}(), {closure#2}(), {closure#3}(), {closure#1}(), {closure#2}(), {closure#3}()

### Community 28 - "Deploy GaweTracker to Oracle Cloud (Always Free)"
Cohesion: 0.17
Nodes (11): 0. The one catch: idle reclamation, 1. Create the instance, 2. Connect and provision, 3. Configure the app, 4. Install cron jobs (scheduler, queue, keepalive, backup), 5. Domain + HTTPS, 6. Connect Gmail (and sign in with Google), Deploy GaweTracker to Oracle Cloud (Always Free) (+3 more)

### Community 29 - "Illuminate\Database\Seeder"
Cohesion: 0.25
Nodes (3): DatabaseSeeder, JobApplicationSeeder, UserSeeder

### Community 36 - "bootstrap/app.php"
Cohesion: 0.28
Nodes (3): {closure#1}(), {closure#2}(), {closure#3}()

### Community 39 - "Deploy GaweTracker via Cloudflare Tunnel"
Cohesion: 0.22
Nodes (8): Build, Cloudflare Access, Deploy GaweTracker via Cloudflare Tunnel, Migrate & seed, Operational notes, Run, Trusted proxies, Tunnel ingress

### Community 55 - "Deploy GaweTracker to Fly.io"
Cohesion: 0.40
Nodes (4): After deploy, Deploy GaweTracker to Fly.io, Prerequisites, Steps

### Community 61 - "provision.sh"
Cohesion: 0.67
Nodes (3): DEBIAN_FRONTEND, log(), provision.sh script

### Community 62 - "Screenshots"
Cohesion: 0.50
Nodes (3): Notes, Regenerating, Screenshots

## Knowledge Gaps
- **14 isolated node(s):** `DEBIAN_FRONTEND`, `concurrently`, `tailwindcss`, `@laravel/multiplex`, `layouts.app` (+9 more)
  These have ≤1 connection - possible missing edges or undocumented components. (Counts symbols only; 413 node(s) total have ≤1 connection when file, concept and rationale nodes are included.)
- **75 thin communities (<3 nodes) omitted from report** — run `graphify query` to explore isolated nodes.

## Suggested Questions
_Questions this graph is uniquely positioned to answer:_

- **Why does `JobApplication` connect `JobApplication` to `JobEmailParser`, `JobPosting`, `.findOrCreateByName`, `Illuminate\Http\RedirectResponse`, `Controller`, `Illuminate\Http\Request`, `DashboardTest`, `AnalyticsTest`, `JobApplicationCrudTest`, `JobApplicationDetailTest`, `AnalyticsController.php`, `TestCase`, `Illuminate\Database\Seeder`, `KanbanTest`, `QuickAddExportTest`, `Illuminate\Foundation\Testing\RefreshDatabase`, `JobMatchTest`, `JobApplicationController`, `JobApplicationTest`, `DashboardController.php`?**
  _High betweenness centrality (0.131) - this node is a cross-community bridge._
- **Are the 2 inferred relationships involving `JobApplication` (e.g. with `Discovery (external job boards)` and `Models`) actually correct?**
  _`JobApplication` has 2 INFERRED edges - model-reasoned connections that need verification._
- **What connects `DEBIAN_FRONTEND`, `concurrently`, `tailwindcss` to the rest of the system?**
  _14 weakly-connected nodes found - possible documentation gaps or missing edges._
- **Should `JobEmailParser` be split into smaller, more focused modules?**
  _Cohesion score 0.05516431924882629 - nodes in this community are weakly interconnected._
- **Why does `TestCase` connect `TestCase` to `JobEmailParser`, `GlintsSource`, `JobPosting`, `JobstreetSource`, `GoogleOAuthService`, `.findOrCreateByName`, `GoogleLoginTest`, `DashboardTest`, `AnalyticsTest`, `JobApplicationCrudTest`, `JobApplicationDetailTest`, `JobDiscoveryTest`, `OfferChecklistTest`, `GmailConnectionTest`, `KanbanTest`, `QuickAddExportTest`, `Illuminate\Foundation\Testing\RefreshDatabase`, `AuthTest`, `EmailIngestionTest`, `JobMatchTest`, `JobApplicationTest`, `GmailSetupCommandTest`?**
  _High betweenness centrality (0.094) - this node is a cross-community bridge._
- **Should `GlintsSource` be split into smaller, more focused modules?**
  _Cohesion score 0.06455399061032864 - nodes in this community are weakly interconnected._
- **Why does `User` connect `User` to `JobEmailParser`, `JobPosting`, `GoogleOAuthService`, `DashboardTest`, `AnalyticsTest`, `JobApplicationCrudTest`, `JobApplicationDetailTest`, `JobDiscoveryTest`, `OfferChecklistTest`, `AuthController.php`, `UserFactory.php`, `GmailConnectionTest`, `TestCase`, `Illuminate\Database\Seeder`, `KanbanTest`, `QuickAddExportTest`, `Illuminate\Foundation\Testing\RefreshDatabase`, `AuthTest`, `JobMatchTest`, `JobApplicationTest`?**
  _High betweenness centrality (0.090) - this node is a cross-community bridge._