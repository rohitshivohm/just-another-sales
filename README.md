# AI Sales Calling Assistant Dashboard (PHP Edition)

A production-ready **PHP + SQLite** web application designed for shared hosting environments where Node.js process hosting is unavailable.

## Why this version

You requested a version that can run live on standard PHP hosting. This implementation removes the Node/Express runtime and uses native PHP APIs + SQLite so you can deploy on common cPanel/shared servers.

## Core capabilities

- Admin authentication (login/logout with token sessions)
- Dashboard metrics:
  - Total Leads
  - Calls Today
  - Connected Calls
  - Hot Leads
  - Conversion Rate
- Lead management:
  - Manual lead creation
  - CSV upload (`name,phone,city`)
  - Search + filter by status/city
- Calling system:
  - Single lead call trigger
  - Bulk call trigger
  - Sequential queue processing
  - Duplicate queue prevention
  - Retry logic (max 2 attempts)
- Call logs:
  - Lead details
  - Status, duration, attempts
  - Transcript + AI summary
  - Full conversation modal
- AI configuration panel:
  - Language style (English / Hinglish)
  - Tone (formal / friendly)
  - Opening script / question flow / closing statement
- Lead scoring:
  - Auto classifies to Hot / Warm / Cold from conversation outcomes

## Architecture

- `index.php`: app entrypoint, serves dashboard UI
- `api.php`: central API router
- `php/lib/bootstrap.php`: DB connection, migrations, seed data, common helpers
- `php/lib/auth.php`: login/session token auth
- `php/lib/call_engine.php`: AI call simulation + scoring
- `php/lib/call_service.php`: queue orchestration + retry handling
- `public/`: frontend assets (`index.html`, `app.js`, `styles.css`)
- `data/app.sqlite`: persistent database file

## Local run

```bash
php -S 0.0.0.0:8000
```

Open: `http://localhost:8000`

Default login (first run):

- Username: `admin`
- Password: `admin123`

Optional env vars:

- `ADMIN_USERNAME`
- `ADMIN_PASSWORD`

## Deployment on PHP hosting

1. Upload project files to web root (`public_html` or equivalent).
2. Ensure PHP has SQLite PDO enabled.
3. Ensure `data/` is writable by PHP.
4. Visit your domain and login.

## API routes (via `api.php?route=...`)

- `POST auth/login`
- `POST auth/logout`
- `GET dashboard/metrics`
- `GET leads`
- `POST leads`
- `POST leads/upload-csv`
- `POST calls/start`
- `POST calls/bulk-start`
- `GET calls/queue`
- `GET calls/logs`
- `GET config`
- `PUT config`

All routes except login require `Authorization: Bearer <token>`.
