# Modern Job Board — Freemium Feature Comparison

**Product:** Modern Job Board (WordPress plugin)  
**Version:** 0.9.0-beta.1  
**Model:** Freemium (proprietary — not open source)  
**Last updated:** July 5, 2026  
**Purpose:** Handoff document for AI or human reviewers planning licensing, gating, marketing, or product decisions.

---

## Executive summary

Modern Job Board is a self-hosted WordPress job board plugin sold as **freemium**:

- **Free** — core listings, applications, dashboards, blocks/shortcodes, SEO schema, community support.
- **Pro ($149/year)** — unlimited jobs, WooCommerce monetization, custom fields, CSV/XML import-export, email support.
- **Business ($299/year)** — everything in Pro plus REST API, XML feeds, webhooks, priority support, and 2 hours custom development per year.

**Critical implementation note:** Tier assignments below reflect the **planned commercial model** (documented in marketing, `LICENSE.txt`, and `readme.txt`). **PHP license enforcement is not implemented yet** — all features currently work regardless of plan until gating is built.

**Purchase flow today:** CTAs use `mailto:hello@martinorton.com` with tier-specific subjects. No automated checkout or license-key validation exists in the plugin yet.

---

## Plan overview

| | **Free** | **Pro** | **Business** |
|---|:---:|:---:|:---:|
| **Annual price** | $0 | $149/year | $299/year |
| **License** | Proprietary (free tier) | Proprietary + paid license | Proprietary + paid license |
| **Best for** | Launching a basic job board | Monetizing and scaling | Integrations and high-volume sites |
| **Support** | Community | Email | Priority + 2 hrs custom dev/year |

---

## Core job board

| Feature | Free | Pro | Business |
|---|:---:|:---:|:---:|
| Job listings shortcode/block (`[mjb_jobs]`) | ✓ | ✓ | ✓ |
| AJAX search and filters (keyword, location, category, type, company) | ✓ | ✓ | ✓ |
| Pretty `/jobs/` search URLs | ✓ | ✓ | ✓ |
| Frontend job submission (`[mjb_job_form]`) | ✓ | ✓ | ✓ |
| Job archive and single-job templates | ✓ | ✓ | ✓ |
| Taxonomies: job category, location, type | ✓ | ✓ | ✓ |
| Company profiles (custom post type + company filter) | ✓ | ✓ | ✓ |
| Featured job listings | ✓ | ✓ | ✓ |
| Auto job expiration (background cron) | ✓ | ✓ | ✓ |
| Schema.org JobPosting JSON-LD | ✓ | ✓ | ✓ |
| Google Maps embed on job pages (API key in settings) | ✓ | ✓ | ✓ |
| Gutenberg blocks for all core shortcodes | ✓ | ✓ | ✓ |
| All six shortcodes (see inventory below) | ✓ | ✓ | ✓ |
| **Active published job limit** | Limited | **Unlimited** | **Unlimited** |

> **Open decision:** Free-tier job cap is marketed as “limited” but no numeric limit or enforcement exists in code yet.

---

## Users, applications, and security

| Feature | Free | Pro | Business |
|---|:---:|:---:|:---:|
| Employer registration (`[mjb_employer_registration]`) | ✓ | ✓ | ✓ |
| Candidate registration (`[mjb_candidate_registration]`) | ✓ | ✓ | ✓ |
| Employer dashboard (`[mjb_dashboard]`) | ✓ | ✓ | ✓ |
| Candidate dashboard (`[mjb_candidate_dashboard]`) | ✓ | ✓ | ✓ |
| Internal on-site applications | ✓ | ✓ | ✓ |
| External apply URLs | ✓ | ✓ | ✓ |
| Application workflow statuses | ✓ | ✓ | ✓ |
| Secure resume storage | ✓ | ✓ | ✓ |
| Protected resume downloads (authenticated, nonce-gated) | ✓ | ✓ | ✓ |
| Apply with stored profile/CV | ✓ | ✓ | ✓ |
| Application email notifications | ✓ | ✓ | ✓ |
| Honeypot spam protection (always on) | ✓ | ✓ | ✓ |
| Optional reCAPTCHA v2 | ✓ | ✓ | ✓ |
| IP rate limiting (applications and registration) | ✓ | ✓ | ✓ |
| Employer dashboard analytics (views, applications, conversion rate) | ✓ | ✓ | ✓ |

---

## WordPress admin

| Feature | Free | Pro | Business |
|---|:---:|:---:|:---:|
| Tabbed admin shell (Dashboard loads first) | ✓ | ✓ | ✓ |
| Admin tabs: Dashboard, Jobs, Applications, Companies, Resumes, Settings, Setup, Custom Fields, Tools | ✓ | ✓ | ✓ |
| AJAX tab loading with paging on data grids | ✓ | ✓ | ✓ |
| Setup wizard (auto-create frontend pages) | ✓ | ✓ | ✓ |
| Settings: listing duration, maps, security, integrations UI | ✓ | ✓ | ✓ |
| Site-wide admin performance dashboard | ✓ | ✓ | ✓ |
| **Custom fields builder** (jobs and applications) | — | ✓ | ✓ |
| **Tools: CSV export** (jobs and applications) | — | ✓ | ✓ |
| **Tools: CSV import** (jobs) | — | ✓ | ✓ |
| **Tools: XML import** (upload file or remote URL) | — | ✓ | ✓ |

---

## Monetization (WooCommerce) — Pro and above

Requires WooCommerce. Employer payments go through the site owner’s WooCommerce gateways; **site owner keeps 100% of revenue**.

| Feature | Free | Pro | Business |
|---|:---:|:---:|:---:|
| Pay-per-post (publish job on payment) | — | ✓ | ✓ |
| Job credit packages (bulk tiers, qty meta on products) | — | ✓ | ✓ |
| Paid CV/application unlocks (per-application purchase) | — | ✓ | ✓ |
| Time-based unlimited CV access passes | — | ✓ | ✓ |
| Employer credit balance shown in dashboard | — | ✓ | ✓ |
| Refund, cancel, and expiry lifecycle handling | — | ✓ | ✓ |

**WooCommerce product meta used:** `_mjb_package_qty` (credits), `_mjb_cv_access_duration` (days), job ID and unlock application ID on order line items.

---

## Integrations and developer features

| Feature | Free | Pro | Business |
|---|:---:|:---:|:---:|
| WordPress actions and filters (`mjb_*` hooks) | ✓ | ✓ | ✓ |
| **REST API v1** — public job search | — | — | ✓ |
| **REST API v2** — authenticated employer/candidate endpoints | — | — | ✓ |
| **REST analytics** — `GET /wp-json/mjb/v2/analytics` | — | — | ✓ |
| **XML job feed** — `/feed/job-listings` | — | — | ✓ |
| **Outbound webhooks** | — | — | ✓ |
| Webhook events: `application.submitted`, `application.status_updated`, `job.submitted` | — | — | ✓ |
| Webhook HMAC signing (`X-MJB-Signature`) | — | — | ✓ |
| Webhook retry queue (up to 5 attempts, exponential backoff) | — | — | ✓ |

### REST API v1 (Business)

- `GET /wp-json/mjb/v1/jobs`
- `GET /wp-json/mjb/v1/jobs/search/in/{location}/category/{category}/type/{type}/keyword/{keyword}/page/{page}/per-page/{per_page}/`

### REST API v2 (Business)

- `GET /wp-json/mjb/v2/applications`
- `PATCH /wp-json/mjb/v2/applications/{id}`
- `GET /wp-json/mjb/v2/analytics`
- `GET|PATCH /wp-json/mjb/v2/candidate/profile`

### Notable hooks (all tiers today)

- `mjb_job_listing_query_args`
- `mjb_before_employer_dashboard`
- `mjb_dashboard_application_row`
- `mjb_before_delete_job`
- `mjb_before_activate_paid_job` / `mjb_after_activate_paid_job`
- `mjb_job_view_recorded`

---

## Shortcode and Gutenberg block inventory

| Shortcode | Block | Purpose |
|---|---|---|
| `[mjb_jobs]` | Jobs | Searchable job listings |
| `[mjb_job_form]` | Job Form | Frontend job submission |
| `[mjb_dashboard]` | Employer Dashboard | Employer job and application management |
| `[mjb_candidate_dashboard]` | Candidate Dashboard | Candidate profile and applications |
| `[mjb_employer_registration]` | Employer Registration | Employer signup |
| `[mjb_candidate_registration]` | Candidate Registration | Candidate signup |

All blocks appear under **Modern Job Board** in the Gutenberg inserter.

---

## Support and ownership

| | **Free** | **Pro** | **Business** |
|---|:---:|:---:|:---:|
| Self-hosted — 100% data ownership | ✓ | ✓ | ✓ |
| No SaaS platform lock-in | ✓ | ✓ | ✓ |
| Community support | ✓ | ✓ | ✓ |
| Email support | — | ✓ | ✓ |
| Priority support | — | — | ✓ |
| Installation assistance | — | — | ✓ |
| Custom development (2 hours/year) | — | — | ✓ |

---

## vs. SaaS job boards (positioning)

| Dimension | Modern Job Board | Typical SaaS (e.g. NiceBoard) |
|---|---|---|
| **Hosting** | Self-hosted WordPress | Vendor-hosted |
| **Cost model** | Freemium + optional annual plans | Recurring monthly subscription |
| **Data ownership** | 100% on your server | Vendor servers |
| **Customization** | Full WordPress + code access | Settings-limited |
| **Employer monetization** | WooCommerce (Pro+) | Often Stripe Connect |
| **Extensibility** | Hooks, filters, REST (Business) | Limited API on higher tiers |

---

## Implementation status (as of v0.9.0-beta.1)

| Area | Status |
|---|---|
| All features listed above | **Built** in plugin codebase |
| Marketing site pricing (`modern-job-board-website`) | **Updated** — 3-tier freemium |
| `LICENSE.txt` | **Proprietary** freemium terms |
| `readme.txt`, `composer.json`, plugin header | **Proprietary** (GPL removed) |
| PHP license / plan gating | **Not implemented** |
| Free-tier job count limit | **Not defined or enforced** |
| License key validation | **Not implemented** |
| Automated checkout | **Not implemented** (mailto CTAs only) |
| PHPUnit test suite | **101 tests passing** |

### Suggested gating map for future implementation

When building enforcement, these are the natural cut points aligned with marketing:

| Plan constant | Features to gate |
|---|---|
| `free` | Default; enforce job count cap |
| `pro` | WooCommerce class, custom fields admin tab, tools import/export, unlimited jobs |
| `business` | REST API v1/v2, XML feed, webhooks + queue |

**Key PHP classes to wrap or guard:**

- `MJB_WooCommerce` — monetization
- `MJB_Custom_Fields` — custom fields builder
- `MJB_Tools` — CSV/XML import and export
- `MJB_Rest_Api`, `MJB_Rest_Api_V2` — REST
- `MJB_Feeds` — XML syndication feed
- `MJB_Webhooks`, `MJB_Webhook_Queue` — outbound events

---

## Repository and environment paths

| Item | Path |
|---|---|
| Plugin repo | `C:\Users\marti\4Mation Digital\modern-job-board` |
| Marketing site | `C:\Users\marti\4Mation Digital\modern-job-board-website` |
| Local WordPress demo | `C:\Users\marti\Local Sites\mjb\app\public` |
| Live demo URL | `https://mjb.local/` |
| Plugin (synced locally) | `wp-content/plugins/modern-job-board` |
| Theme | `wp-content/themes/modern-job-board-theme` |

---

## Contact and licensing

- **License:** Proprietary — see `LICENSE.txt`
- **Sales / support email:** hello@martinorton.com
- **Author:** Martin Orton — https://martinorton.com
- **Product URI:** https://martinorton.com/modern-job-board

### Mailto CTA subjects (current marketing)

- Free: `Modern Job Board Free`
- Pro: `Modern Job Board Pro`
- Business: `Modern Job Board Business`

---

## Related files in this repository

- `LICENSE.txt` — proprietary license terms
- `readme.txt` — WordPress-style readme with tier annotations
- `COMPARISON.md` — MJB vs NiceBoard SaaS comparison
- `docs/getting-started.md` — installation and page setup
- `docs/developers.md` — REST, webhooks, hooks reference
- `README.md` — developer quick start

---

*Copyright © 2026 Modern Job Board. This document describes the intended freemium product structure; actual enforcement may lag marketing until license gating ships.*