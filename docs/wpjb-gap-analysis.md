# WPJobBoard → Modern Job Board gap analysis

**Source:** `docs/wpjobboard-full-feature-breakdown.md` (WPJB 5.12.1)  
**Against:** MJB `0.9.0-beta.68` codebase  
**Date:** 2026-08-11  
**Purpose:** Identify functionality to implement (or deliberately skip) for competitive parity.

**Tier A status:** A1–A8 implemented in `0.9.0-beta.66` (pragmatic depth — not a full WPJB clone).  
**Tier B status:** B1–B10 implemented in `0.9.0-beta.67` (pragmatic depth).  
**Tier C status:** C1–C10 implemented in `0.9.0-beta.68` (pragmatic depth).

**Legend**

| Status | Meaning |
|--------|---------|
| **Have** | Present in MJB (may differ in UX/depth) |
| **Partial** | Exists in weaker/narrower form |
| **Missing** | Not integrated — candidate for build |
| **Skip** | Out of scope / wrong architecture / low ROI vs WPJB |

---

## Executive summary

MJB already covers a large **core board** (jobs, companies, applications, dual portals, packages via Woo, alerts, saved jobs, talent pool, REST, webhooks, CSV/XML tools, private files, reCAPTCHA, freemium license).  

WPJobBoard’s remaining advantages are concentrated in:

1. **Monetisation depth** (native memberships, resume-access products, discounts, invoices, multi-gateway UX, SA PayFast-first story)  
2. **Ingestion** (Indeed / CareerBuilder / ZipRecruiter live backfill / Broadbean)  
3. **Distribution polish** (Google for Jobs field mapper + preview, Indeed XML out, social auto-post)  
4. **Resume marketplace** (browse/search resumes, access tiers, anonymizer)  
5. **Apply routing extras** (WhatsApp; multi-email stakeholders)  
6. **Employer ops** (filled/schedule/republish, shortlist one-click, membership usage UI, co-workers depth)  
7. **Admin payments/memberships panels** as first-class screens  

**Do not** chase custom DB tables or their templating engine — MJB’s CPT/Woo architecture is a product choice, not a gap.

---

## 1. Core architecture

| WPJB | MJB | Status | Notes |
|------|-----|--------|-------|
| Custom tables for jobs/resumes | CPTs (`job_listing`, `job_application`, `company`, `mjb_resume`) | **Skip** | Different architecture; stay on WP posts |
| One-click pages on activate | Setup wizard | **Have** | |
| Multisite | Standard WP | **Partial** | No dedicated WPMU testing/docs |
| WPML | Text domain + i18n board | **Partial** | Not full WPML certified suite |
| Health Check for cron | Health Check panel | **Have** | Jobs → Health Check |
| Theme-agnostic plugin | Yes | **Have** | + optional MJB theme |

---

## 2. Job board front end

| WPJB | MJB | Status | Action if building |
|------|-----|--------|-------------------|
| Jobs list, per-page | `[mjb_jobs]` + AJAX | **Have** | |
| Featured to top + style | Featured meta + ordering | **Partial** | Stronger “featured strip” styling / yellow highlight |
| “New” badge (N days) | `MJB_Job_Ops` + list badges | **Have** | Setting `mjb_new_job_badge_days` |
| Hide filled from list | `_job_filled` + listing query filter | **Have** | Setting `mjb_hide_filled_jobs` |
| Expired: hide list, keep URL | Expiration meta + cron | **Partial** | Confirm detail URL still works when expired |
| Related jobs on detail | Single job related block | **Have** | Setting `mjb_related_jobs_count` |
| Bookmark on detail | Saved jobs | **Have** | |
| Apply Online toggle | Application method | **Partial** | Toggle off apply entirely per job |
| Live search | AJAX filter | **Have** | |
| Advanced search shortcode, DnD fields | Filter enable UI | **Partial** | No separate advanced form builder |
| Active facets chips | Partial in search UX | **Partial** | Refine “remove one filter” chips |
| Map shortcode | `[mjb_map]` / `[mjb_jobs_map]` | **Have** | Google Maps multi-marker map |
| Custom application form DnD | Custom fields for applications | **Partial** | No full DnD form builder |
| Multi-file application uploads | Extra attachments | **Have** | Up to 3 extra PDF/DOC on apply |
| Apply restricted to registered | Can require login / profile apply | **Partial** | Make explicit “registered only” setting |

---

## 3. Application methods

| WPJB | MJB | Status | Action |
|------|-----|--------|--------|
| Internal form | Yes | **Have** | |
| External URL | Yes | **Have** | |
| WhatsApp apply | Method + `_application_whatsapp` | **Have** | `wa.me` deep link |
| Multi stakeholder emails | Multi notify CSV | **Have** | Parsed via `MJB_Job_Ops::parse_emails` |
| LinkedIn apply | Dead in WPJB | **Skip** | |

---

## 4. Employer portal

| WPJB | MJB | Status | Action |
|------|-----|--------|--------|
| Post/edit/manage jobs | Dashboard + job form | **Have** | |
| De-list / mark filled / schedule go-live / republish | Filled + schedule + republish | **Have** | Admin meta + dashboard actions + cron |
| Per-capability toggles | — | **Missing** | Admin flags for what employers may do |
| Applicant shortlist one-click | Application statuses (incl. shortlisted) | **Partial** | One-click shortlist + bulk already partly there |
| Membership dashboard (usage, expiry, rebuy) | Package usage block + WC | **Partial** | Usage UI on employer dashboard; rebuy via packages shortcode |
| Payments history + invoices | WC orders | **Partial** | Invoice PDF needs WC/Sliced-style add-on or skip |
| Per-listing analytics | Analytics module | **Partial** | Employer-facing per-job views/apps chart |
| Company profile edit | Company CPT + registration | **Have** | |
| Employer public/private visibility | — | **Missing** | Company visibility flag |
| Employer approval Instant / Admin | Registration approval settings | **Have** | |
| Co-workers / Employee Manager | Collaborators | **Partial** | Deepen company-scoped co-workers + verify email |

---

## 5. Candidate portal & resume database

| WPJB | MJB | Status | Action |
|------|-----|--------|--------|
| Candidate dashboard | Yes | **Have** | |
| My Alerts | Job alerts | **Have** | |
| Resume completeness bar | Profile completeness + UI bar | **Have** | Candidate dashboard progress |
| **Browse/search resume database** | Talent pool + access gate | **Partial** | Access matrix gates browse; deeper filters still Tier B polish |
| Featured candidates + rank level | Meta hook `_candidate_feature_level` | **Partial** | Meta reserved; rank UI still light |
| Resume privacy tiers (5 levels) | `MJB_Resume_Privacy` matrix | **Have** | all / registered / employers / verified / premium |
| On-application unlock full resume | Paid CV / unlock | **Partial** | Align wording with “applied to my jobs” |
| Resume approval Instant/Admin | Candidate approval setting | **Have** | |
| Resume private/public default | Public talent flag | **Partial** | |
| Candidate Anonymizer (GDPR) | `MJB_Resume_Privacy` | **Have** | Surname hide + talent noindex; slug hashing still optional |

---

## 6. Admin panel

| WPJB | MJB | Status | Action |
|------|-----|--------|--------|
| Job CRUD | Tabbed admin + CPT | **Have** | |
| Admin-only featured flag | Meta on job | **Partial** | Hide featured from frontend poster if desired |
| Unlimited types/categories | Taxonomies | **Have** | |
| Who can post: Anyone / Employer / Admin | `mjb_who_can_post` | **Have** | Settings → Job posting rules |
| Hold for moderation Free/Paid/Package | Pending + payment flows | **Partial** | Independent moderation by origin |
| Block employer self-edit | — | **Missing** | Setting |
| Applications browser | Admin applications tab | **Have** | |
| Employers panel + resume access grants | Companies tab (thinner) | **Partial** | Employer analytics + grant resume access |
| Candidates panel | Resumes tab | **Partial** | Approve/edit UX |
| **Payments panel** (gateway IDs, mark paid) | WC + settings product IDs | **Missing** as first-class UI | Or document WC as system of record |
| **Memberships panel** | — | **Missing** | Manual membership assign if not pure WC |
| Email alerts admin list | Alerts CPT | **Partial** | Richer admin list (last run, params) |

---

## 7. Monetisation

| WPJB | MJB | Status | Action |
|------|-----|--------|--------|
| Single job posting products | WC submission product / packages | **Have** (via WC) | |
| Single **resume access** product | CV unlock product | **Have** | |
| Employer membership packages | Packages + subscription flag | **Partial** | Stronger bundle UI + usage counts |
| Candidate memberships | — | **Missing** | Paid candidate tiers (alerts slots, searchable) |
| Trial membership auto on register | Trial credits/CV days | **Have** | Settings → Memberships & trials |
| Recurring Stripe-native plans | WC Subscriptions optional | **Partial** | Document WC Subscriptions; no Stripe-only path |
| PayPal + Stripe built-in | **Any WC gateway** | **Have** (different model) | Strength for SA (Payfast via WC) |
| PayFast add-on | Via WC | **Have** path | Ensure docs highlight SA |
| Discount codes | WC coupons | **Have** | |
| Banners between jobs | Ad banner slots | **Have** | Admin Ad banners + inject after Nth card |
| Featured companies | `[mjb_featured_companies]` | **Have** | Company featured meta |
| Sliced Invoices | — | **Skip / later** | WC PDF invoices ecosystem |
| Taxes panel | WC tax | **Have** path | |
| À-la-carte “feature this job +$X” | Feature product + dashboard CTA | **Have** | Beats WPJB default |

---

## 8. Job ingestion (syndicate in)

| WPJB | MJB | Status | Action |
|------|-----|--------|--------|
| Indeed scheduled import | — | **Missing** | High effort / API partner |
| CareerBuilder import | — | **Missing** | |
| ZipRecruiter **live backfill** | Partner import / XML schedules | **Partial** | Backfill-when-empty is unique WPJB win |
| Broadbean push webhook | — | **Missing** | Valuable for agencies |
| XML / CSV import | Tools + XML importer + schedules | **Have** | |
| REST create jobs | REST v1/v2 | **Have** | |
| LinkedIn import | — | **Skip** | WPJB also won’t do it |

---

## 9. Distribution (syndicate out)

| WPJB | MJB | Status | Action |
|------|-----|--------|--------|
| Google for Jobs JSON-LD + field map + preview | `MJB_Google_Jobs` | **Have** | Type map + static fields + editor preview/validate |
| Indeed XML feed out | XML feed (Business) | **Partial** | Indeed-specific tags |
| RSS by category / search | Feed | **Partial** | Query-based RSS |
| Twitter / LinkedIn / FB auto-post | Share buttons (manual) | **Missing** | Auto-post on publish |
| Social share from job | Share UI | **Have** | |

---

## 10. Email

| WPJB | MJB | Status | Action |
|------|-----|--------|--------|
| Template groups + autovariables | Emails class | **Partial** | Admin HTML template editor |
| Advanced HTML emails | Basic notifications | **Partial** | |
| Job alerts widget + “subscribe to this search” | Alerts + subscribe CTA | **Have** | `mjb_after_job_search_form` subscribe UI |
| Alert slots by membership | — | **Missing** | With candidate memberships |
| MailChimp | Opt-in + API sync | **Have** | Employer/candidate registration |

---

## 11. Developer surface

| WPJB | MJB | Status | Action |
|------|-----|--------|--------|
| REST read/write jobs, apps, resumes, companies | REST v1/v2 | **Have** | Extend write coverage if needed |
| Token-per-user / API keys | API keys + rate limits | **Have** | |
| Payment API for custom gateways | WC | **Skip** as native — use WC | |
| 19 shortcodes | Core set + extras | **Partial** | Map shortcode parity list |
| 12 widgets | Blocks primarily | **Partial** | Widgets optional if blocks cover |
| Gutenberg blocks | Yes | **Have** | **Advantage over WPJB** |
| Snippets repo | Filters/actions | **Partial** | Public snippets docs |

---

## 12. Import / export

| WPJB | MJB | Status | Action |
|------|-----|--------|--------|
| Full XML board export (all entities) | XML board backup | **Have** | Tools → Export |
| Filtered partial export | — | **Missing** | Export current filtered admin view |
| CSV field picker | CSV export | **Partial** | Column chooser |
| Zip import | — | **Partial** | |

---

## 13. Security & anti-spam

| WPJB | MJB | Status | Action |
|------|-----|--------|--------|
| Private uploads + nginx deny | `mjb-private` + deploy docs | **Have** | Ops deny still P0.4 |
| Path hashing | — | **Missing** | Optional obfuscation |
| Per-entity file protection | Resumes/apps protected | **Partial** | |
| Honeypot | Application guard | **Have** | |
| Time trap | Form loaded-at field | **Have** | Configurable min seconds |
| reCAPTCHA per form | reCAPTCHA settings | **Have** | Confirm all forms |
| Spam logging / ban list | — | **Missing** | |

---

## 14–16. Theme / add-ons / licensing

| Area | MJB | Status |
|------|-----|--------|
| Bundled Jobeleon-like theme | Separate MJB theme + marketing site | **Have** (different packaging) |
| Gallery / portfolio | Job media gallery + video | **Partial** |
| Banners / featured companies | Promotions module | **Have** |
| Dropbox upload | — | **Skip / later** |
| Freemium Free/Pro/Business | Yes | **Have** — different commercial model than WPJB Personal/Business |

---

## Prioritised build backlog (recommended)

Only **Missing** or meaningful **Partial** items that improve parity or create advantage. Ordered for product impact vs effort.

### Tier A — High impact, competitive “must consider”

| # | Feature | Status (beta.66) | Notes |
|---|---------|------------------|-------|
| A1 | **Google for Jobs field mapper + validation preview** | **Done** | `class-mjb-google-jobs.php` |
| A2 | **Filled / schedule / republish** employer controls | **Done** | Admin meta + dashboard + cron |
| A3 | **New badge + hide filled + related jobs** | **Done** | `MJB_Job_Ops` listing settings |
| A4 | **WhatsApp apply + multi notify emails** | **Done** | Method + email CSV parse |
| A5 | **Resume access matrix + talent search depth** | **Done** (depth pragmatic) | 5 tiers; talent search depth partial |
| A6 | **Candidate anonymizer (GDPR)** | **Done** | Surname + noindex |
| A7 | **Employer package usage dashboard** | **Done** | `MJB_Packages::render_employer_usage` |
| A8 | **Subscribe to this search** alerts | **Done** | After search form CTA |

### Tier B — Strong differentiators or SA-relevant

| # | Feature | Status (beta.67) | Notes |
|---|---------|------------------|-------|
| B1 | **Jobs map shortcode** | **Done** | `[mjb_map]` / `class-mjb-jobs-map.php` |
| B2 | **Featured companies + ad banners** | **Done** | `class-mjb-promotions.php` |
| B3 | **Indeed/CareerBuilder import or Broadbean webhook** | **Done** (webhook) | REST ingest + partner XML feeds |
| B4 | **ZipRecruiter-style backfill when empty** | **Done** | Empty-list CTA + sourced jobs |
| B5 | **Candidate memberships + alert slots** | **Done** | Free slots + WC candidate pack |
| B6 | **Trial membership on employer register** | **Done** | Credits/CV days once on register |
| B7 | **Admin payments / memberships panels** | **Done** | Payments submenu + WC links |
| B8 | **Email template admin (HTML + variables)** | **Done** | Merge-tag editor |
| B9 | **MailChimp opt-in** | **Done** | Registration opt-in + API |
| B10 | **À-la-carte “feature this job +$X”** | **Done** | Feature product + dashboard CTA |

### Tier C — Nice / polish

| # | Feature | Status (beta.68) | Notes |
|---|---------|------------------|-------|
| C1 | Time-trap anti-spam | **Done** | `MJB_Application_Guard` time field |
| C2 | Spam log + IP ban list | **Done** | Spam log admin + banned IPs setting |
| C3 | Completeness progress bar UI | **Done** | Candidate dashboard bar |
| C4 | Active filter chips | **Done** | Under search form |
| C5 | Multi-file application fields | **Done** | Extra PDF/DOC attachments |
| C6 | Full board XML backup export | **Done** | Tools export XML backup |
| C7 | Social auto-post (X/LinkedIn/FB) | **Done** | Webhook + share URL payloads |
| C8 | Secure path hashing | **Done** | HMAC filenames + path sign/verify |
| C9 | Health Check cron panel | **Done** | Jobs → Health Check |
| C10 | Who-can-post / job-edition admin toggles | **Done** | Anyone / Employer / Admin |

### Explicitly skip (unless a customer pays)

- Replace CPTs with custom tables  
- Replicate WPJB templating engine  
- Apply with LinkedIn / LinkedIn job import  
- Native Stripe-only memberships (prefer WC Subscriptions)  
- Bullhorn/Greenhouse connectors (unless Completeness Site upsell)  
- AI matching/parsing as 1.0 requirement  

---

## MJB advantages to keep marketing (vs WPJB)

From the breakdown + current code:

- **Gutenberg blocks** (WPJB: shortcodes only)  
- **Modern freemium** Free/Pro/Business + license commerce path  
- **Application pipeline statuses / bulk** (WPJB applications are flatter)  
- **WooCommerce-native** payments → any gateway including Payfast without a paid PayFast extension  
- **Webhooks queue**, API keys, PWA, messaging, SMS hooks, embed widget  
- **Private CV storage** model + deploy docs  
- Active product development vs maintenance-grade WPJB releases  

---

## Suggested product decision

1. **1.0 still ships** against current P0/P1 checklist (stability + commerce host).  
2. **Tier A + B + C complete** through `0.9.0-beta.68`. Resume **1.0 checklist** (P0.4 / P1 / ship) before claiming stable.  
3. Do **not** attempt full WPJB clone; price/position on freemium + modern UX + WC + API.

---

## Next implementation step

WPJB parity tiers A–C closed at pragmatic depth. **Next: 1.0 checklist** residual (P0.4 / P1 / ship hygiene) — not more WPJB clone work unless a customer pays.
