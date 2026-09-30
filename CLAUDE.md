# GaweTracker — Claude Guidelines

Same as AGENTS.md — read that file first.

## Additional Notes for Claude

- When editing views, always read the relevant blade file before writing — line numbers shift after prior edits
- `public/css/shadcn.css` is ~1100+ lines; append new CSS at the end, never restructure existing rules
- Company logo resolution lives in `JobApplication::resolveDomain()` — the keyword map covers 21 major Indonesian tech companies; unknown companies return `null` from `logoUrl()`
- Seeder in `database/seeders/JobApplicationSeeder.php` has 15 realistic Indonesian tech company applications; the test in `JobApplicationTest.php` asserts exactly 15
- Filter URL state is preserved via `->withQueryString()` on the paginator in `JobApplicationController::index()`
- Offer deadline alerts show when `offer_details.deadline_at` is within 3 days and the application status is `offer`
- Heatmap data comes from `AnalyticsController::calculateHeatmap()` — 53 weeks × 7 days grid, level 0-4 based on daily application count
