# Screenshots Guide

## How to Add Screenshots to README

### 1. Take Screenshots (Manual)

With the dev server running (`php artisan serve`), take screenshots of these 5 views:

1. **Dashboard** → `http://127.0.0.1:8000/dashboard`
   - Shows: Metrics (total, active, win rate), follow-up queue, recent applications
   
2. **Applications List** → `http://127.0.0.1:8000/applications`
   - Shows: Table with company logos, filters, search, pagination
   
3. **Kanban Board** → `http://127.0.0.1:8000/applications/kanban`
   - Shows: Cards across 6 stages (wishlist → hired/rejected)
   
4. **Analytics** → `http://127.0.0.1:8000/analytics`
   - Shows: Funnel chart, rejection breakdown, heatmap, weekly volume
   
5. **Offer Comparison** → `http://127.0.0.1:8000/offers`
   - Shows: Side-by-side matrix comparing offers

### 2. Save Screenshots

- Use browser screenshot tool (F12 → Cmd/Ctrl+Shift+P → "Capture full size screenshot")
- Or use OS screenshot tool
- Save as: `dashboard.png`, `applications.png`, `kanban.png`, `analytics.png`, `offers.png`
- Place in: `public/screenshots/` directory

### 3. Update README.md

Add after the **## Features** section:

```markdown
## Screenshots

### Dashboard
![Dashboard](public/screenshots/dashboard.png)
*Track all applications at a glance with key metrics, follow-up reminders, and recent activity.*

### Applications List
![Applications List](public/screenshots/applications.png)
*Filter and search through all applications with company logos and status badges.*

### Kanban Board
![Kanban Board](public/screenshots/kanban.png)
*Visual pipeline across 6 recruitment stages with drag-and-drop cards.*

### Analytics & Heatmap
![Analytics](public/screenshots/analytics.png)
*Funnel conversion, rejection analysis, time-to-response, and 12-month activity heatmap.*

### Offer Comparison
![Offer Comparison](public/screenshots/offers.png)
*Side-by-side matrix to compare salary, benefits, work scheme, and deadlines.*
```

### 4. Commit

```bash
git add public/screenshots/
git add README.md
git commit -m "Add screenshots to README"
git push
```

## Alternative: Use GitHub Issues for Images

If screenshots are large, upload to a GitHub issue and use the generated URLs:

1. Go to https://github.com/kianlabs/gawetracker/issues/new
2. Drag images into the comment box
3. GitHub generates URLs like `https://user-images.githubusercontent.com/...`
4. Copy URLs and use in README: `![Dashboard](https://user-images...)`
5. Close the issue without submitting

This keeps the repo size small.
