# Modern Job Board

A feature-rich WordPress job board plugin with employer and candidate dashboards, applications, WooCommerce monetization, REST API, webhooks, and Gutenberg blocks.

**Current version:** 0.9.0-beta.2 (pre-stable beta — not a production/stable release)

Modern Job Board is **proprietary freemium** — **not open source**. There is a free tier; Pro, Business, and Complete Site are paid. The free license does **not** grant ownership of the plugin software. See [LICENSE.txt](LICENSE.txt) and the [pricing page](../modern-job-board-website/index.html#pricing).

## Quick start

1. Install and activate the plugin.
2. Open **Modern Job Board → Setup** and create the required pages.
3. Configure **Modern Job Board → Settings**.

See [docs/getting-started.md](docs/getting-started.md) for full setup instructions.

## Local demo

```powershell
composer sync-local
composer seed-demo
```

See [DEMO.md](DEMO.md) for details. The marketing site in `modern-job-board-website/` links to the local demo URL configured in `js/script.js`.

## Shortcodes & blocks

| Shortcode | Purpose |
|-----------|---------|
| `[mjb_jobs]` | Job search and listings |
| `[mjb_job_form]` | Frontend job submission |
| `[mjb_dashboard]` | Employer dashboard |
| `[mjb_candidate_dashboard]` | Candidate profile and applications |
| `[mjb_employer_registration]` | Employer signup |
| `[mjb_candidate_registration]` | Candidate signup |

All shortcodes are available as Gutenberg blocks under **Modern Job Board**.

## Developer docs

- [docs/developers.md](docs/developers.md) — REST, webhooks, hooks
- [REMOTE_SETUP.md](REMOTE_SETUP.md) — nginx resume protection and SSH workflow

## Development

```bash
composer install
composer test
composer phpcs
composer make-pot
```

## Links

- [Product site & pricing](../modern-job-board-website/index.html#pricing)
- [Documentation site](../modern-job-board-website/docs/index.html)

---
Copyright © 2026 Modern Job Board.