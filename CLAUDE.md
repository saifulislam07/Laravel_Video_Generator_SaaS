# Video Generator SaaS — agent guide

Laravel 13 app that composites a cartoon character over a real background into a
short vertical video, rendered by the **Shotstack Edit API**. See `README.md` for
setup and `PROJECT_PLAN.md` for the design + phase history.

## Stack

- Laravel 13, PHP 8.3+
- Livewire 3 + **Volt** (single-file components in `resources/views/livewire/**`)
- Customer UI: Tailwind + Alpine (`@alpinejs/sort` for drag-reorder)
- Admin UI (`/admin`): **AdminLTE 3** via `jeroennoten/laravel-adminlte` — Volt
  components render through the `layouts.admin` wrapper (`#[Layout('layouts.admin')]`),
  never `#[Layout('adminlte::page')]` directly (nested `@extends` breaks the slot).
  FontAwesome 5 icon names only.
- DB: MySQL (`video_generator`); tests use sqlite `:memory:`
- Queue: `database` driver locally (no Redis); Reverb for broadcasting
- Roles: `spatie/laravel-permission` (`User::isAdmin()`, `role:admin` middleware)
- Images: Intervention Image **v4** (`Image::decodePath()`, `->encodeUsingFileExtension()` — NOT v3's `read()`/`toPng()`)

## Conventions

- Models use the attribute style: `#[Fillable([...])]` + a `casts()` method (see `app/Models/User.php`).
- Business logic lives in `app/Services/**` (`VideoRenderService`, `ShotstackPayloadBuilder`,
  `CreditService`, `Billing/*`, `Social/*`, `CharacterService`, `BackgroundImageService`).
- External-API side effects go through a job: `CheckRenderStatusJob`, `DownloadRenderJob`, `PublishRenderJob`.
- Config knobs: `config/video.php`, `config/billing.php`, `config/services.php`.
- Every feature has a Pest test in `tests/Feature/`. Run `php artisan test` — keep it green.

## Gotchas

- Stale `public/hot` with no `npm run dev` → unstyled pages. `npm run build` or delete it.
- Volt actions can't be named `render` (reserved) — the render-panel uses `render_`.
- `#[Layout]` on a Volt full-page component injects into the layout's `content` section
  (or `{{ $slot }}`).
- Shotstack auth is the `x-api-key` header, not a bearer token. Asset URLs it fetches
  must be absolute + public (`APP_URL`).
- Payment gateways & Meta Graph fall back to a mock / hidden state when their env
  credentials are blank — don't assume they're wired.

## Local logins (seeded)

- User: `test@example.com` / `password`
- Admin: `admin@example.com` / `password`
