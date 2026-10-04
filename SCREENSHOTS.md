# Screenshots

Screenshots for the README live in `public/screenshots/` and are **committed**
(they render inline on GitHub). Five views are captured:

| File | View | URL |
|---|---|---|
| `dashboard.png` | Dashboard | `/` |
| `applications.png` | Applications list | `/applications` |
| `kanban.png` | Kanban board | `/applications/kanban` |
| `analytics.png` | Funnel & analytics | `/analytics` |
| `offers.png` | Offer comparison | `/offers` |

They are embedded in `README.md` under the **## Screenshots** section.

## Regenerating

Capture from a local instance seeded with realistic data so the pages aren't
empty. The shots were produced with headless Chromium (Puppeteer) against
`php artisan serve`, logging in as the seeded user first.

Manual alternative: with `php artisan serve` running, open each URL and use the
browser's full-page screenshot (F12 → Cmd/Ctrl+Shift+P → "Capture full size
screenshot"), saving into `public/screenshots/` with the names above.

## Notes

- Keep the files reasonably small; GitHub renders them inline but very large
  PNGs slow the page. If they grow, upload to a GitHub issue and use the
  generated `user-images.githubusercontent.com` URLs instead (keeps repo small).
- Screenshots must reflect **real** seeded data — never mock/placeholder images.
