# Modern Job Board — Freemium Feature Comparison

**Product:** Modern Job Board (WordPress plugin)  
**Version:** 0.9.0-beta.93  
**Model:** Freemium (proprietary) + Complete Site service  
**Last updated:** August 19, 2026  
**Purpose:** Handoff document for AI or human reviewers planning licensing, gating, marketing, or product decisions.

---

## Executive summary

Modern Job Board is a self-hosted WordPress job board product sold as **freemium plugin licenses** plus an optional **done-for-you site build**:

- **Free ($0)** — core board features; **capped at 100 active job listings**; community support. Best for testing the waters.
- **Pro ($149/year)** — unlimited jobs, WooCommerce monetization, custom fields, import/export, email support. Best for monetizing.
- **Business ($299/year)** — everything in Pro plus REST API, XML feeds, webhooks, priority support + 2 hrs custom dev/year. Best for integrations.
- **Complete Site ($1,200–2,000 setup, includes 1 year Business; then $299/year)** — full WordPress site build (theme, branding, pages, hosting setup), MJB installed and configured, Business features included. Best for “I just want a working job board site.”

**Implementation status:** PHP license/plan gating is **implemented** (`MJB_License`: Free 100-job cap; Pro/Business feature gates). Purchase/activation flow is **implemented** (`MJB_License_Commerce`: checkout URLs, offline key activation, WooCommerce license products, vendor key issuer). See `docs/purchase.md`.

**Purchase flow:** Gateway-agnostic checkout URLs in Settings → License & plan. With WooCommerce, **any WooCommerce-supported payment gateway** works (e.g. PayPal, Stripe, Square, Mollie, Razorpay, or regional options where the merchant can onboard). Empty URLs fall back to structured sales mailto. Woo products with **MJB plugin license** meta auto-email a signed key on order complete. Manual bank transfer via vendor key issuer. See `docs/purchase.md`.

---

## Plan overview

| | **Free** | **Pro** | **Business** | **Complete Site** |
|---|:---:|:---:|:---:|:---:|
| **Price** | $0 | $149/year | $299/year | **$1,200–2,000 setup** (incl. 1 yr Business) then $299/year |
| **Active job listings** | Capped at 100 | Unlimited | Unlimited | Unlimited |
| **Core board, applications, dashboards, analytics, SEO, security** | ✓ | ✓ | ✓ | ✓ |
| **WooCommerce monetization** | — | ✓ | ✓ | ✓ (configured for you) |
| **Custom fields + import/export** | — | ✓ | ✓ | ✓ (set up for you) |
| **REST API, XML feed, webhooks** | — | — | ✓ | ✓ |
| **Full WordPress site build** (theme, branding, pages, hosting setup) | — | — | — | ✓ |
| **MJB installed & configured** | Self | Self | Self | ✓ |
| **Support** | Community | Email | Priority + 2 hrs dev/yr | Priority + 2 hrs dev/yr |
| **Best for** | Testing the waters | Monetizing | Integrations | "I just want a working job board site" |

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
| **Active published job limit** | **100** | **Unlimited** | **Unlimited** |

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

Requires WooCommerce. Employer payments go through the site’s WooCommerce gateways; **the site operator receives employer payments** (no platform cut from MJB).

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

## Support and hosting model

| | **Free** | **Pro** | **Business** | **Complete Site** |
|---|:---:|:---:|:---:|:---:|
| Runs on your WordPress (listing data on your site) | ✓ | ✓ | ✓ | ✓ |
| Proprietary freemium license | ✓ | ✓ | ✓ | ✓ |
| Avoids renting a hosted SaaS job board | ✓ | ✓ | ✓ | ✓ |
| Community support | ✓ | ✓ | ✓ | ✓ |
| Email support | — | ✓ | ✓ | ✓ |
| Priority support + 2 hrs dev/yr | — | — | ✓ | ✓ |
| Full site build & MJB configured for you | — | — | — | ✓ |

---

## vs. SaaS job boards (positioning)

| Dimension | Modern Job Board | Typical SaaS (e.g. NiceBoard) |
|---|---|---|
| **Hosting** | Self-hosted WordPress | Vendor-hosted |
| **Cost model** | Freemium licenses + optional Complete Site | Recurring monthly subscription |
| **Listing / applicant data** | Stored on your WordPress site | Vendor servers |
| **Software license** | Proprietary freemium | SaaS terms |
| **Customization** | Themes, hooks, WP plugins; paid tiers unlock more product features | Settings-limited |
| **Employer monetization** | WooCommerce (Pro+) | Often Stripe Connect |
| **Extensibility** | Hooks, filters, REST (Business) | Limited API on higher tiers |

---

## Implementation status (as of v0.9.0-beta.88)

| Area | Status |
|---|---|
| All features listed above | **Built** in plugin codebase (plugin licenses only) |
| Marketing site pricing (`modern-job-board-website`) | **Updated** — Free / Pro / Business / Complete Site |
| `LICENSE.txt` | **Proprietary** freemium terms |
| `readme.txt`, `composer.json`, plugin header | **Proprietary** |
| PHP license / plan gating | **Implemented** (`MJB_License` offline keys + plan gates) |
| Free-tier job count limit (10) | **Enforced** (`MJB_License::can_publish_job`) |
| Complete Site service delivery | **Sales/service process** (not a plugin SKU alone) |
| License key validation | **Implemented** (offline signed keys; remote client in `MJB_License_Remote` — host API is operator-owned) |
| Automated checkout / key delivery | **Implemented** (gateway-agnostic checkout URLs + WC license products + vendor key form) |
| PHPUnit test suite | **233 tests** (as of 2026-08-19) |

### Suggested gating map for future implementation

When building enforcement, these are the natural cut points aligned with marketing:

| Plan constant | Features to gate |
|---|---|
| `free` | Default; enforce **100 active job listings** |
| `pro` | WooCommerce class, custom fields admin tab, tools import/export, unlimited jobs |
| `business` | REST API v1/v2, XML feed, webhooks + queue |
| `complete_site` | Business features + service fulfillment (build, install, configure) |

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
- Complete Site: `Modern Job Board Complete Site`

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