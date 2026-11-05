# CLAUDE.md — vatertagstour.de

> Project-specific conventions. Global preferences, hard rules, and security defaults
> live in `~/.claude/CLAUDE.md` and are loaded automatically — don't repeat them here.
> This file is for what's true about *this* project only.

## Stack & commands

- Rebuild of the live WordPress site vatertagstour.de (German Vatertag affiliate site).
- Laravel 13 (PHP 8.5 locally), SQLite for local dev. Fresh rebuild started 2026-11-05;
  the older Windows-machine build (2026-09) is not the base for this repo.
- Install: `composer install && cp .env.example .env && php artisan key:generate && php artisan migrate`
- Dev server: `php artisan serve`
- Test: `php artisan test`
- Lint / format: `vendor/bin/pint`

## Structure

- `*.WordPress.*.xml` — WXR export of the live site, source data for the content import.
  Gitignored; keep it local, never commit it.
- Export contents (2026-11-05): 51 `product`, 34 `post`, 8 `page`, 33 `envira` galleries,
  699 attachments, ACF field groups. No WooCommerce despite `product`/`shop` post types.

## Conventions

- The live WordPress site stays up throughout; this rebuild is local-only until
  deployment is decided.

## Gotchas

- Hosting is Wint.Global / Plesk (no SSH). Plesk Git path must be the base folder,
  document root `<base>/public` — otherwise `.env` gets served.
