# Modern Job Board — remaining tasks

**Last updated:** 2026-08-09  
**Status legend:** `[ ]` todo · `[~]` in progress · `[x]` done  

**→ Stable 1.0 work queue:** [1.0-checklist.md](1.0-checklist.md) (P0/P1 only). This file is the historical product backlog (#1–#42).

---

## P0 — Ship / commercial readiness

- [x] **1. License / plan enforcement** — Free 10-job cap; gate Pro/Business features in PHP (not marketing-only).
- [x] **2. Purchase / license-key flow** — Gateway-agnostic checkout URLs, key activation, WC key fulfillment, vendor issue form; any WooCommerce-supported gateway (PayPal, Stripe, Square, Mollie, etc.); mailto until checkout live.
- [x] **3. Stable release checklist** — `docs/release-checklist.md`; version **0.9.0-beta.9**; PHPUnit + PHPCS green. Operator: smoke mjb.local via checklist §4 when convenient.
- [x] **4. Security re-pass** — REST, private uploads, download roots, admin caps reviewed; SSRF feed hardening + download headers + safe redirects. See `docs/security-repass.md`.
- [x] **5. Local/prod deploy notes** — `docs/deploy.md` (storage, nginx/Apache/IIS/Local, cache/CDN, updates, backups); `REMOTE_SETUP.md` points here.

## P1 — License hardening (anti-casual-piracy)

- [x] **6. Remote license server (plugin client)** — `MJB_License_Remote`: domain activate, check-in cron, grace, plan filter. Configure `mjb_license_api_url` or `MJB_LICENSE_API_URL`. Host API still operator-owned (see spec below).
- [x] **7. Licensed update endpoint (plugin client)** — Injects package URL from `/v1/updates/check` when token valid.

## P1 — Niceboard product parity (high value)

- [x] **8. Job seeker email alerts** — `MJB_Job_Alerts` CPT + AJAX save + daily/weekly cron digests.
- [x] **9. Saved / bookmarked jobs** — Candidate dashboard list + apply from saved (0.9.0-beta.29).
- [x] **10. Talent pool** — `[mjb_talent_pool]` + `/jobs/talent/{id}/` with `_candidate_is_public` privacy.
- [x] **11. Board-native packaging UI** — `[mjb_packages]` + WC product fields (featured days, duration).
- [x] **12. Posting subscriptions** — Subscription package flag for WC Subscriptions products.
- [x] **13. Coupons for job posts** — Standard WooCommerce coupons on package products (documented in Packages admin).
- [x] **14. Partner backfill** — Partner feed registry + daily cron hook `mjb_partner_import_feed`.
- [x] **15. Collaborator invites** — `mjb_collaborator` role + invite email flow.
- [x] **16. Private job search mode** — Setting “Require login to browse jobs”.

## P2 — Seeker / employer UX polish

- [x] **17. Multi-step AJAX registration** — Wizard (3 steps) + AJAX final submit for candidate/employer (0.9.0-beta.30).
- [x] **18. Company hover preview** — AJAX motto/site/social + `mjb-company-preview.js`.
- [x] **19. Media-rich listings** — Gallery IDs + video URL meta on job listings.
- [x] **20. Employer auto-approve rules** — Trusted employers auto-publish after first approved listing.
- [x] **21. Application pipeline polish** — Bulk status update on employer applications view.
- [x] **22. One-click apply improvements** — `MJB_Applications::profile_completeness()`.
- [x] **23. Embeddable job widget** — `/jobs/embed.js` + iframe frame + admin snippet.
- [x] **24. Filter enable/disable UI** — Settings toggles for keywords/location/category/type/company.
- [x] **25. Homepage building blocks** — `mjb_home_blocks` option + helpers (audience/search/featured).

## P3 — Branding / i18n / SEO as product

- [x] **26. Brand panel** — Logo/colors/banner/nav light-dark admin + CSS variables.
- [x] **27. Full text customization UI** — gettext overrides admin.
- [x] **28. Multi-language product mode** — `[mjb_language_switcher]` + cookie locale filter.
- [x] **29. Auto SEO landing polish** — Title parts + meta description for category/city/company.
- [x] **30. Blog integration package** — `[mjb_related_jobs]` + auto-append on posts.

## P4 — Hardening from recent work

- [x] **31. Audience cards on `/jobs/`** — “Submit CV” / “Post a job” → login when logged out.
- [x] **32. Legacy URL redirects** — `/candidate-registration/` etc. → `/jobs/…`.
- [x] **33. Company archive redirects** — `/company/` → `/jobs/companies/`.
- [x] **34. Release/ops checklist** — See `docs/release-checklist.md` (covers P0 #3 process).
- [x] **35. Docs shortcode samples** — Escaped `[[mjb_*]]` in marketing docs; docs rebuilt as reference shell (2026-07-27).
- [x] **35b. Docs reference UX** — Bullhorn-style sidebar TOC + endpoint sections; `modern-job-board-website/docs/index.html` + docs CSS.
- [x] **36. PHPUnit on Windows** — `docs/phpunit-windows.md`.

## P5 — Later / optional

- [x] **37. In-app messaging** — `MJB_Messaging` CPT + inbox shortcode + AJAX send.
- [x] **38. SMS notifications** — Pluggable `mjb_sms_send` filter + status hooks.
- [x] **39. Native multi-currency pricing UI** — Existing `mjb_currency` setting (packages use WC currency).
- [x] **40. Board analytics export** — CSV export admin page.
- [x] **41. Public API rate limits + API keys UI** — Keys admin + 60/min rate limit on `/wp-json/mjb/*`.
- [x] **42. Mobile app / PWA** — Manifest + service worker (opt-in setting).

---

## Spec: remote license + updates (tasks **6** + **7**)

**Goal:** Raise the cost of casual nulling (domain-bound keys + stale forks without updates). Not unbreakable PHP DRM.

**When:** After first paid customers or first null sighting — not blocking beta.

### Hosting (vendor)

- Small API (e.g. Cloudflare Worker + KV/D1, or a minimal PHP app on your host).
- Secrets: signing key **only on server** (not in the public plugin zip for verification of server responses — use asymmetric or separate HMAC secret).
- Admin: issue key, list activations, **revoke** key or domain.

### Plugin UX (unchanged for customers)

1. Paste key in **Settings → License & plan** → Save.  
2. Plugin POSTs activate; stores `site_token` + plan + `valid_until`.  
3. Daily cron re-validates; **7-day grace** if server unreachable.  
4. Offline signed keys (current) remain as **fallback** or migration path until all keys are remote-issued.

### API sketch

Base: `https://license.example.com/v1` (placeholder).

#### `POST /v1/activate`

```http
Content-Type: application/json

{
  "key": "MJB-PRO-00000000-…",
  "domain": "jobs.customer.com",
  "plugin_version": "0.9.0-beta.9",
  "site_url": "https://jobs.customer.com"
}
```

**200**

```json
{
  "ok": true,
  "plan": "pro",
  "expires": "2027-07-27",
  "site_token": "…opaque…",
  "check_in_hours": 24,
  "grace_days": 7
}
```

**4xx** — invalid, expired, revoked, domain mismatch, activation limit.

#### `POST /v1/check`

```json
{
  "site_token": "…",
  "domain": "jobs.customer.com",
  "plugin_version": "0.9.0-beta.9"
}
```

**200** — `{ "ok": true, "plan": "pro", "expires": "…", "revoked": false }`  
**403** — revoked / wrong domain → plugin reverts to Free after grace.

#### `POST /v1/deactivate` (optional)

Releases domain slot when moving hosts.

#### `GET /v1/updates/check` (task **7**)

WordPress-style or custom JSON:

```json
{
  "site_token": "…",
  "domain": "…",
  "slug": "modern-job-board",
  "version": "0.9.0-beta.9"
}
```

**200** — package URL (signed, short-lived) + new version, **only if** license valid.  
**403** — no update metadata (nulled / revoked installs stay frozen).

### Plugin implementation notes

- New class e.g. `MJB_License_Remote` beside offline `MJB_License`.  
- Filter `mjb_license_plan` already allows remote plan to win.  
- Constants: `MJB_LICENSE_API_URL`, optional `MJB_LICENSE_PLAN` override for dev.  
- Never hard-brick mid-request; demote plan on failed check after grace.  
- PHPUnit: mock HTTP for activate/check/update.

### Out of scope

- ionCube / source encryption.  
- Phone-home that blocks Free tier.  
- Perfect anti-piracy guarantees.

---

## Spec: multi-step AJAX registration (task **17**)

**Goal:** Short, modern signup without full-page reloads. Dashboards remain the place to edit/complete profile later.

### UX

- Single page / shortcode (existing candidate + employer registration URLs).
- **3 steps max**, numbered progress (`1 About · 2 … · 3 Account`) + short labels; optional checkmarks on completed steps.
- **No page refresh** between steps (show/hide panels). Final step submits via **AJAX**.
- Visual system: match login / register-card layout (equal card, Continue/Back, not full-width wall of fields).
- A11y: `aria-current="step"`, focus management on step change, keyboard Back/Continue.

### Candidate steps (suggested)

1. **About you** — first name, last name (required); phone/city optional.
2. **Resume** — resume file required; photo optional.
3. **Account** — email, password, confirm (required).

Defer to dashboard: LinkedIn, website, headline, experience, bio, open-to-work / remote / public (pre-fill sensible defaults on create if still stored today).

### Employer steps (suggested)

1. **You** — name fields as today.
2. **Company** — company name (+ logo if already collected).
3. **Account** — email, password, confirm.

### Technical

- One JS module (e.g. `mjb-registration-wizard.js`) shared by both audiences via data attributes / config.
- Client validation per step; optional AJAX “email available?” before final.
- **Single create** on final step: `FormData` + nonce + honeypot + reCAPTCHA; file held in DOM across steps.
- Server re-validates all fields; reuse existing handlers / extract shared create service.
- Keep rate limit, account-approval, redirects (dashboard vs pending).
- PHPUnit for create paths; smoke on mjb.local.

### Out of scope for this task

- Multi-page URL steps (`?step=2`).
- Creating half-users mid-wizard.
- In-dashboard profile completeness UI (can follow as a small related task).

---

## Already largely done (do not re-open unless regressing)

- Core listings, AJAX filters, pretty `/jobs/` routes
- Employer/candidate registration, login, dashboards
- Applications, private resumes, statuses
- Companies under `/jobs/companies/`
- WooCommerce pay-per-post, credits, paid CV
- Custom fields, CSV/XML import, webhooks, REST v1/v2
- Security fixes from plugin review workflow
- Demo nav under `/jobs/`, logo to root, login/register card UX
- Offline license gating + purchase commerce (P0 #1–#2)
