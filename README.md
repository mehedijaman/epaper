# ePaper Management System

Production-ready ePaper platform built with Laravel, Inertia.js, and Vue 3.

It includes a public reader experience (edition browsing, page viewer, hotspots) and an admin panel for managing editions, pages, mappings, ads, categories, and site settings.

## Features

### Public ePaper Experience
- Browse published editions by date.
- Open pages via `/epaper/{date}/page/{pageNo}` routes.
- Viewer with thumbnail rail, page navigation, and zoom controls.
- Interactive hotspots with preview modal and linked hotspot support.
- Share and print actions for hotspot previews.
- Responsive layout for desktop and mobile.

### Admin Console
- Manage editions and page uploads.
- Reorder pages with drag and drop.
- Map hotspots between pages.
- CRUD categories.
- Manage ad slots and ad content.
- Manage site settings (logo + footer content).

### Security and Permissions
- Role-based access control via `spatie/laravel-permission`.
- Built-in roles: `admin`, `operator`.
- Protected `/admin/*` routes with permission middleware.

## Tech Stack

- Backend: Laravel 12, PHP 8.2+
- Frontend: Vue 3, TypeScript, Inertia.js
- UI: Tailwind CSS 4, shadcn-vue style component system
- Auth: Laravel Fortify
- Image processing: `intervention/image-laravel`
- Permissions: `spatie/laravel-permission`

## Requirements

- PHP 8.2+
- Composer
- Node.js 20+ and npm
- MySQL/MariaDB or SQLite

## Quick Start

```bash
git clone <your-repo-url>
cd epaper
cp .env.example .env
composer install
npm install
php artisan key:generate
```

Or use the bundled one-command bootstrap:

```bash
composer run setup
```

Configure database in `.env`, then run:

```bash
php artisan migrate --seed
php artisan storage:link
```

Start development:

```bash
composer run dev
```

`composer run dev` starts Laravel server, queue worker, logs, and Vite together.

## Default Seeded Admin

Seeder behavior:
- If at least one user exists, the oldest user is assigned `admin`.
- If no users exist, an admin user is created from env values:
  - `EPAPER_ADMIN_NAME` (default: `ePaper Admin`)
  - `EPAPER_ADMIN_EMAIL` (default: `admin@example.com`)
  - `EPAPER_ADMIN_PASSWORD` (default: `password`)

Set these in `.env` before `php artisan migrate --seed` for custom credentials.

## Useful Commands

### Development
- `composer run dev` - full local dev stack
- `npm run dev` - Vite only
- `npm run build` - production assets

### Quality
- `php artisan test` - run test suite
- `composer test` - lint (Pint) + tests
- `npx vue-tsc --noEmit` - TypeScript check
- `npm run lint` - ESLint fix mode

### ePaper Utilities
- `php artisan epaper:seed-categories` - seed 16 default category slots
- `php artisan epaper:seed-categories 24` - seed custom number of slots

## Access Control Matrix

- `admin`:
  - categories, ads, settings, editions
- `operator`:
  - editions only

Permissions used:
- `categories.manage`
- `users.manage`
- `ads.manage`
- `settings.manage`
- `editions.manage`

## Key Routes

- Public:
  - `/` (home / latest available edition)
  - `/epaper/{date}`
  - `/epaper/{date}/page/{pageNo}`
- Admin:
  - `/admin` dashboard
  - `/admin/editions/manage`
  - `/admin/hotspots`
  - `/admin/categories`
  - `/admin/ads`
  - `/admin/settings`

## Storage Notes

- Uploaded images and logo rely on Laravel filesystem.
- ePaper storage disk comes from `EPAPER_DISK` (defaults to `public` via `config/epaper.php`).
- Run `php artisan storage:link` so browser-accessible URLs work correctly.

## Deployment Checklist

1. Set production `.env` values (`APP_ENV`, `APP_DEBUG`, DB, mail, queue).
2. Run `composer install --no-dev --optimize-autoloader`.
3. Run `npm ci && npm run build`.
4. Run `php artisan migrate --force`.
5. Run `php artisan storage:link`.
6. Configure queue worker and process supervisor.

## License

MIT
