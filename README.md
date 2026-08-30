# Video Generator SaaS

Put a **cartoon character on a real background photo** and render a short vertical
video (Reels / Shorts, 1080×1920) for Facebook & Instagram. Rendering is done in
the cloud by the **Shotstack Edit API** — the app builds the timeline, submits it,
tracks status in real time and delivers the MP4.

Built with **Laravel 13, Livewire 3 + Volt, Tailwind, Alpine, Laravel Reverb**.

---

## Features

- Email/password auth (Laravel Breeze, Livewire stack)
- Background image upload with auto-resize/optimise (Intervention Image)
- System cartoon character library with multiple poses (placeholder art included)
- Project → ordered scenes; per scene: background, character pose (drag to position + scale), caption, duration
- Drag-to-reorder scenes, preview timeline, live Shotstack payload preview
- Cloud render + self-polling status job + Shotstack webhook
- Real-time status via Reverb / Echo (with `wire:poll` fallback)
- Dashboard: project list, in-browser video player, download, retry
- Credit system (5 free), `credit_transactions` ledger, pricing page, bKash / SSLCommerz checkout (real API + mock fallback), auto-refund on render failure
- Render email notifications; optional S3 archiving of finished videos
- One-click publish to a Facebook Page / Instagram Reels (Meta Graph API)
- Admin panel — **AdminLTE 3** (`jeroennoten/laravel-adminlte`), spatie/laravel-permission: users & credits, system character CRUD, render moderation, stats

See [`PROJECT_PLAN.md`](PROJECT_PLAN.md) for the full design & phase roadmap and
[`DEPLOYMENT.md`](DEPLOYMENT.md) for production setup.

---

## Local setup

### Requirements

- PHP **8.3+** with `gd`, `pdo_mysql`, `mbstring`, `intl`, `curl`, `zip`, `bcmath`
- Composer 2, Node 20+
- MySQL 8 (or SQLite for a quick start)
- Redis is **optional** locally — the app falls back to the `database` queue driver

### Install

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate

# MySQL (matches .env.example defaults: DB_CONNECTION=mysql, DB_DATABASE=video_generator)
mysql -u root -e "CREATE DATABASE video_generator CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
#   …or use SQLite: set DB_CONNECTION=sqlite in .env and `touch database/database.sqlite`

php artisan migrate --seed        # seeds admin + system characters + a test user
php artisan storage:link
php artisan adminlte:install --only=assets   # admin panel static assets → public/vendor
npm run build
```

Seeded logins (**change in production**):

| Role | Email | Password |
| --- | --- | --- |
| User | `test@example.com` | `password` |
| Admin | `admin@example.com` | `password` |

### Run

```bash
php artisan serve                 # http://localhost:8000
npm run dev                       # asset watcher (separate terminal; creates public/hot)
php artisan queue:work            # required for renders to progress
php artisan reverb:start          # optional — real-time status (else wire:poll is used)
```

> If pages look unstyled: `npm run dev` isn't running and `public/hot` is stale —
> delete `public/hot` or run `npm run build`.

### Rendering videos

Renders need a Shotstack key. Create a free **stage** key at
<https://dashboard.shotstack.io/> and set in `.env`:

```
SHOTSTACK_API_KEY=your-stage-key
SHOTSTACK_ENV=stage
```

Without a key the "Render video" button shows a clear error; everything else works.
Stage renders are free and watermarked; `SHOTSTACK_ENV=production` is paid & clean.

---

## Environment variables

| Key | Purpose |
| --- | --- |
| `APP_URL` | Public URL — Shotstack fetches uploaded images from `APP_URL/storage/...`, so it must be reachable |
| `DB_*` | Database connection |
| `QUEUE_CONNECTION` | `database` locally, `redis` in production |
| `SHOTSTACK_API_KEY` / `SHOTSTACK_ENV` | Shotstack Edit API credentials (`stage` \| `production`) |
| `SHOTSTACK_TEMPLATE_ID` | Optional Shotstack template id (reserved) |
| `SHOTSTACK_WEBHOOK_SECRET` | If set, a signed `callback` URL is added to each render and verified on the webhook |
| `BROADCAST_CONNECTION` / `REVERB_*` / `VITE_REVERB_*` | Laravel Reverb (real-time) |
| `BKASH_*` / `SSLCZ_*` | Payment gateways — real API when set, mock checkout when blank |
| `RENDER_ARCHIVE_ENABLED` / `RENDER_ARCHIVE_DISK` | Copy finished renders to a durable disk (default off) |
| `RENDER_NOTIFICATIONS_ENABLED` | Email the owner on render done/failed (default on) |
| `FACEBOOK_APP_ID` / `FACEBOOK_APP_SECRET` / `FACEBOOK_REDIRECT_URI` | Meta Graph API for publishing to FB/IG (optional) |

Full lists: [`.env.example`](.env.example) (local), [`.env.production.example`](.env.production.example) (production).

---

## Configuration knobs

- [`config/video.php`](config/video.php) — output canvas (1080×1920), upload rules, scene duration limits, Shotstack caption styling
- [`config/billing.php`](config/billing.php) — free credits, cost per render, credit packages, gateway map

---

## Tests

```bash
php artisan test
```

Pest feature suite covers auth, asset upload, scene builder, the Shotstack payload
builder, the render pipeline (mocked HTTP), credits/billing, the admin panel, and a
sign-up → render end-to-end flow.

---

## Key directories

```
app/Services/                 ShotstackPayloadBuilder, VideoRenderService, CreditService,
                              BackgroundImageService, CharacterService, Billing/*
app/Jobs/CheckRenderStatusJob.php    self-requeuing render poller
app/Events/ProjectRenderStatusUpdated.php   broadcast on projects.{id}
resources/views/livewire/     Volt page + component classes (dashboard, projects/*, billing/*, admin/*)
deploy/                        supervisor + nginx config examples
```
