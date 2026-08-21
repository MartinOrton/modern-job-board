# Modern Job Board — release checklist

Use this before tagging a public or private beta zip. Canonical version: **`Version` header + `MJB_VERSION`** in `modern-job-board.php`.

**Going to stable 1.0.0?** Use the ordered work queue in [1.0-checklist.md](1.0-checklist.md) first (P0 blockers, then P1 commercial). This file is the mechanical tag/zip checklist once that queue is green.

## 1. Version bump

- [ ] Bump `Version` and `MJB_VERSION` in `modern-job-board.php` (same string).
- [ ] `readme.txt` → `Stable tag`
- [ ] `README.md` → current version line
- [ ] `composer.json` → description version (if present)
- [ ] Product docs that quote a version (`FREEMIUM-COMPARISON.md`, etc.)
- [ ] `CHANGELOG.md` — new section with date and user-facing notes
- [ ] `readme.txt` changelog snippet for the new tag (short)

**Public line:** `0.9.0-beta.N` until stable `1.0.0`.

## 2. Automated checks

From plugin root:

```powershell
cd "C:\Users\marti\4Mation Digital\modern-job-board"
composer test
composer phpcs
```

- [ ] PHPUnit green
- [ ] PHPCS clean (or only pre-existing documented exceptions)

## 3. Package hygiene

- [ ] No secrets, vendor test clutter, or local paths in the zip
- [ ] `.distignore` / export excludes product-docs, `.git`, `vendor` test deps as intended
- [ ] `LICENSE.txt` present

## 4. Smoke on mjb.local

```powershell
composer sync-local
```

- [ ] Plugin activates without fatals
- [ ] Jobs list + AJAX filter
- [ ] Employer + candidate login
- [ ] Job submit (pending) + application
- [ ] Settings → License & plan loads; Free cap notice if Free
- [ ] With Pro/Business key (or `MJB_LICENSE_PLAN`): gated admin tabs match plan
- [ ] If WooCommerce present: pay-per-post product settings still load on Pro

## 5. Marketing / docs (if release is customer-facing)

- [ ] Pricing CTAs still correct (mailto or product URLs)
- [ ] `docs/purchase.md` accurate for WooCommerce gateway options
- [ ] Landing import shortcodes escaped if re-importing content
- [ ] Prod: `docs/deploy.md` private-path deny + backup scope reviewed

## 6. Tag & distribute

- [ ] Git tag matching version (e.g. `v0.9.0-beta.9`)
- [ ] Zip from clean tree
- [ ] Store key issuance process ready (vendor form / Woo product meta)

## Pre-1.0 note

Beta releases are **not** “stable” WordPress.org-style releases. Do not claim 1.0 until P0 #4 security re-pass and intentional API freeze.
