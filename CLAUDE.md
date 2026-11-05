# CLAUDE.md — vatertagstour.de

> Project-specific conventions. Global preferences, hard rules, and security defaults
> live in `~/.claude/CLAUDE.md` and are loaded automatically — don't repeat them here.
> This file is for what's true about *this* project only.

## Stack & commands

- Rebuild of the live WordPress site vatertagstour.de (German Vatertag affiliate site).
- Target stack: not scaffolded yet in this repo.
- Install / build / test / lint: `TODO` once the app is scaffolded.

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
