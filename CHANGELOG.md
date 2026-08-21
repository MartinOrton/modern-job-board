# Changelog

All notable changes to the Modern Job Board plugin will be documented in this file.

## Version source of truth

**Canonical version:** `Version` header and `MJB_VERSION` in `modern-job-board.php`.

Those two strings must stay identical. Mirror them in:

- `readme.txt` → `Stable tag`
- `README.md` → current version line
- `composer.json` → description version (if present)
- product docs that quote a version (e.g. `FREEMIUM-COMPARISON.md`)

Never bump a secondary file without updating `modern-job-board.php` first.

**Public line:** `0.9.0-beta.N` until stable `1.0.0`. WordPress update comparison uses only the plugin header / Stable tag — not this file’s historical section.

**Internal history:** Entries under “Internal pre-beta history” used temporary `1.x` labels during private development. They are **not** public releases and must not be treated as newer than `0.9.0-beta.*`.

## [0.9.0-beta.88] - 2026-08-19

### Changed
- **GitHub catch-up:** `main` now matches this late-beta tree. Public GitHub had been frozen at **0.9.0-beta.6** (tag `v0.9.0-beta.1`, 2026-07-21).
- Version strings aligned to **0.9.0-beta.88** in `README.md`, `readme.txt` Stable tag, `composer.json`, and `FREEMIUM-COMPARISON.md`.
- Generated city-prefix shards (`assets/data/cities-by-prefix/`, ~90MB) and local `.grok/` tooling are gitignored. Rebuild cities with `bin/build-world-cities.php`.
- Documented DM Sans as the single UI typeface (`docs/fonts.md`).
- PHPCS EscapeOutput fixes on Health Check status HTML, recruiter quick-action cards, and the jobs-map marker list.

### Added
- Recruiter dashboard front-end module (`assets/js/mjb-recruiter-dashboard.js`) and job-detail helpers.
- PHPUnit suite is **233** tests / 612 assertions (was 200 at beta.65).

### Notes
- **beta.69–87** were local cache-bust bumps without published notes. Treat this tag as the published rollup after **beta.68**.
- User-facing work through beta.68 (license gating, product modules #6–#42, WPJB tiers A–C, P0 correctness) is listed in the sections below.

## [0.9.0-beta.68] - 2026-08-11

### Added (WPJB Tier C polish)
- **C1 Time-trap anti-spam:** configurable min seconds on apply/registration forms.
- **C2 Spam log + IP bans:** admin **Spam log** panel; banned IP list; event log on blocks.
- **C3 Completeness bar:** candidate dashboard progress UI from profile completeness helper.
- **C4 Active filter chips:** removable chips under job search form.
- **C5 Multi-file apply:** optional extra PDF/DOC attachments stored on applications.
- **C6 Full board XML backup:** Tools → Export → Download XML backup (jobs/companies/apps/resumes/taxonomies).
- **C7 Social auto-post:** publish webhook + share URL payloads (X/LinkedIn/FB hooks).
- **C8 Secure path hashing:** HMAC hashed filenames + sign/verify path helpers.
- **C9 Health Check:** cron schedule panel under Jobs → Health Check.
- **C10 Who can post:** Anyone / Employer / Admin setting for front-end job form.

## [0.9.0-beta.67] - 2026-08-11

### Added (WPJB Tier B parity)
- **B1 Jobs map:** `[mjb_map]` / `[mjb_jobs_map]` multi-marker map (Google Maps API key).
- **B2 Promotions:** `[mjb_featured_companies]`, company featured meta, admin ad banner slots between job cards, `[mjb_ad_banner]`.
- **B3 Ingestion:** REST webhook `POST /wp-json/mjb/v1/ingest/job` (+ batch) with secret auth (Broadbean/ATS-style JSON).
- **B4 Empty-list backfill:** Settings toggle + partner CTA / sourced jobs when search is empty.
- **B5 Candidate memberships:** free alert slots + WC product “Candidate package” grants extra slots.
- **B6 Employer trial:** grant job credits / CV days once on employer register.
- **B7 Payments panel:** admin **Payments** overview (membership counts + WC deep links + recent MJB orders).
- **B8 Email templates:** admin editor with `{merge}` tags for core notification emails.
- **B9 Mailchimp:** opt-in on registration + API audience sync.
- **B10 À-la-carte feature:** WC “Feature this job” product + employer dashboard Feature → cart.

## [0.9.0-beta.66] - 2026-08-11

### Added (WPJB Tier A parity)
- **A1 Google for Jobs:** field mapper (job type → employmentType), static schema fields, admin JobPosting preview/validation meta box; filter `mjb_job_schema` / `mjb_google_jobs_schema`.
- **A2 Employer ops:** mark filled, schedule go-live (`_job_publish_at` + cron), republish from dashboard.
- **A3 List UX:** “New” badge (N days), hide filled from public lists/search, related jobs on single job.
- **A4 Apply methods:** WhatsApp apply + multi notification emails (CSV).
- **A5 Resume access:** five-tier talent browse matrix (public / registered / employers / verified / premium).
- **A6 Candidate anonymizer:** optional surname hide + talent noindex (GDPR-friendly).
- **A7 Package usage:** employer dashboard credits / published / filled / CV access summary.
- **A8 Subscribe to search:** alert CTA after job filter form.

### Fixed
- Google Jobs type-map sanitizer preserved `FULL_TIME`-style tokens (`sanitize_key` was lowercasing them).

## [0.9.0-beta.65] - 2026-08-09

### Fixed
- **PHPCS:** EscapeOutput on candidate city autocomplete placeholder and job media oEmbed output.

### Changed
- **Release hygiene:** aligned `readme.txt` Stable tag, `README.md`, `composer.json`, and freemium comparison version lines with `MJB_VERSION`; regenerated `languages/modern-job-board.pot`.

## [0.9.0-beta.64] - 2026-08-09

### Fixed
- **Resume lifecycle (P0.1.1):** applying always copies the profile resume into a new private file (`MJB_Resumes::copy_profile_resume_for_application`); copy failure aborts apply. Replacing a profile resume no longer deletes files still referenced by applications (`maybe_retire_profile_resume`).
- **P0 correctness verification:** REST applications scope (empty employer), job-edit payment (no re-bill), and `wp-content` private storage confirmed; covered by existing/new PHPUnit tests.

### Added
- **1.0 work queue:** `docs/1.0-checklist.md`; smoke runner `bin/smoke-p023.php` (P0.2.3: 13 pass / 0 fail / 1 skip WC).

## [0.9.0-beta.63] - 2026-08-08

### Added
- **Admin Jobs list:** Delete action with confirmation modal; trashes job via AJAX (`mjb_admin_delete_job`).

## [0.9.0-beta.50] – [0.9.0-beta.62] - 2026-08

### Added / changed (summary)
- Product modules #6–#42 (alerts, saved jobs, talent pool, brand, PWA, messaging, API keys, etc.).
- Admin shell: Settings **subtabs** per section; Jobs **add/edit** MJB chrome; Tools **wrench** icon; Setup icon iterations.
- Job editor: main-column lock for listing details / locations / featured image / custom fields / job media; native WP two-column layout preserved.
- Registration UX, phone field, city autocomplete, private uploads under `wp-content/mjb-private` and `mjb-brand`.

## [0.9.0-beta.31] - 2026-08-04

### Changed
- **Registration wizards match Sign in styling**: audience-card shell, header icons, auth field chrome (labels/inputs/focus), `btn-sm` CTAs, progress step icons — same colors/fonts as login cards (width can differ).

## [0.9.0-beta.30] - 2026-08-04

### Added
- **Multi-step AJAX registration (#17)**: Candidate (About you → Resume → Account) and employer (You → Company → Account) wizards with progress steps, client validation, optional email availability check, and AJAX final submit. Profile extras deferred to dashboards. No-JS full POST still works.

## [0.9.0-beta.29] - 2026-08-04

### Added
- **Saved jobs dashboard (#9)**: Candidate dashboard section lists bookmarked jobs with company, location, Apply / View / Remove. Stale unpublished IDs are pruned. Remove returns to the dashboard via `redirect_to`.

## [0.9.0-beta.28] - 2026-08-03

### Changed
- **Pretty public board URLs**: apply intent `/jobs/candidate-login/apply/{token}/` (and registration equivalent); recruiter applications `/jobs/recruiter-dashboard/jobs/{id}/applications/`; job edit `/jobs/post-a-job/edit/{id}/`; apply/save actions `/jobs/apply/{token}/` and `/jobs/save/{id}/`; save-login `/jobs/candidate-login/save/`. Legacy query-string links 301 to pretty paths. Flash notices and nonces remain query args.

## [0.9.0-beta.27] - 2026-08-03

### Changed
- **Register benefits cards**: match homepage Features (`.feature-card`) surface, border, hover lift/shadow, and icon treatment; keep compact 2×3 sizes.

## [0.9.0-beta.26] - 2026-08-03

### Changed
- **Pretty password-recovery URLs**: `/jobs/candidate-login/lost-password/` and `/reset-password/` (employer equivalents too) instead of `?mjb_pw=lost`. Legacy query links 301 to the new paths. Recover / set-password card is 2× the sign-in column width (~36rem) and centered.

## [0.9.0-beta.25] - 2026-08-03

### Changed
- **Inline field validation (not post-submit only)**: `mjb-form-validation.js` validates on blur and live-revalidates after a field is touched; covers auth cards (login / lost / reset), registration, job, application, and dashboard forms (`.mjb-auth-card-form` added). Adds minlength/maxlength/pattern checks and password confirmation matching. Invalid styles apply via `.mjb-has-field-validation` and auth forms.

## [0.9.0-beta.9] - 2026-07-27

### Added
- **License / plan enforcement (P0.1)**: offline keys `MJB-{PLAN}-{YYYYMMDD|00000000}-{checksum}`; plans Free / Pro / Business / Complete Site. Free caps published jobs at 10; Pro unlocks WooCommerce, custom fields, and tools; Business unlocks REST API, XML feeds, and webhooks. Settings → License & plan; override via `MJB_LICENSE_PLAN` or filter `mjb_license_plan`.
- **Purchase / license commerce (P0.2)**: gateway-agnostic checkout URLs (SA-friendly: Woo + Payfast/Yoco, not Stripe-as-merchant); mailto fallback; **Buy plan** CTAs; vendor **Issue a license key** form; WooCommerce **MJB plugin license** product meta emails a signed key on order complete. Operator guide: `docs/purchase.md`.
- **Release checklist**: `docs/release-checklist.md`. Task backlog: remote license server + licensed updates (P1 #6–#7) sketched in `docs/tasks.md`.

### Security
- **P0.4 re-pass**: remote XML feed import rejects private/loopback hosts (SSRF); feed fetch size capped; resume download `Content-Disposition` hardened; custom-fields admin uses `wp_safe_redirect`. Notes: `docs/security-repass.md`.

### Documentation
- **P0.5 deploy notes**: `docs/deploy.md` — durable `wp-content/mjb-private` + `mjb-brand`, nginx/Apache/IIS/Local deny rules, cache/CDN, update/backup workflow. `REMOTE_SETUP.md` defers to it.

## [0.9.0-beta.8] - 2026-07-23


### Changed
- **Demo URLs under `/jobs/`**: plugin shortcode pages nest under the Jobs page (`/jobs/post-a-job/`, dashboards, registration). Company archive rewrite is `/jobs/companies/`. Job search rewrites skip reserved first segments. Theme demo nav uses those URLs; **logo links to site root**.
- **Candidate / employer login pages**: new shortcodes `[mjb_candidate_login]` and `[mjb_employer_login]` (login form + button to registration). Demo nav icons open login when logged out, dashboards when logged in.

### Fixed
- **Audience card / content-page links**: `render_audience_cards()` passed fallback paths as the `get_page_url()` query-args argument, producing broken URLs like `/docs/?/post-a-job/=…`. Paths are now the 4th argument; string 3rd args are treated as fallbacks defensively.
- **Docs shortcodes**: documentation samples use escaped `[[mjb_*]]` shortcodes so the docs page no longer expands live jobs/registration UIs.
- **REST v2 applications ownership**: employers with zero jobs (and any non-admin empty job list) no longer receive an unscoped list of all applications. Foreign `job_id` filters are always rejected for non-admins.
- **REST v2 paid CV access**: application PII (`candidate_name`, `candidate_email`, `message`, `resume_url`) is redacted when paid CV access is enabled and the employer has not unlocked the application, matching the employer dashboard.
- **Profile resume on apply**: applications copy the profile resume file so replacing a profile resume no longer breaks past application downloads.
- **Job edit payment**: editing a listing no longer demotes status to pending, consumes credits, or redirects to checkout.
- **New company ownership**: frontend “new company” only reuses a company owned by the current employer; never attaches to another employer’s company by name.
- **Page resolver cache**: only published pages are accepted from options; trash/draft/private caches are cleared and re-scanned.
- **Upload validation**: max size always enforced; client extension alone no longer bypasses `wp_check_filetype_and_ext`.
- **Custom fields admin**: create/delete requires `manage_options` (nonce alone is not enough).
- **Resume download path**: only streams files under allowed MJB storage roots (blocks poisoned absolute paths).
- **Application form sanitization**: nonce and fields use `wp_unslash`; failed insert uses `error_application_failed`.
- **single-job.php escaping**: application UI uses `esc_html_e` / `esc_attr` / markup outside translated strings.

### Changed
- **Storage location**: `mjb-private/` and `mjb-brand/` live under **`wp-content/`** (not inside the plugin folder, not under `uploads/`). Plugin updates no longer wipe CVs/logos. Legacy plugin-local and uploads paths remain readable. Private tier writes Apache `.htaccess` + IIS `web.config`; nginx still needs server config (see `REMOTE_SETUP.md`).
- Local sync script excludes runtime storage dirs so MIR does not wipe uploaded files.
- nginx hardening docs updated for `wp-content/mjb-private/` plus legacy paths.

## [0.9.0-beta.7] - 2026-07-23

### Added
- **Niceboard-style employer registration**: company-first form (name, tagline, logo, description, website, socials, contact name, email, password + confirm), creates a `company` CPT owned by the employer.
- **Niceboard-style candidate registration**: profile + resume upload + photo, location, experience, preferences, email + password + confirm; resume required at signup.
- **Private uploads** (`MJB_Private_Uploads`): CVs under plugin `mjb-private/` (web-denied, PHP download only); logos/photos under plugin `mjb-brand/` with hashed names. **Never** creates Media Library attachments.
- **Account approval** (`MJB_Account_Status`): optional employer/candidate admin approval (settings toggles), blocks login while pending, Users list column + approve action, approval email.
- Signup confirmation emails for employers and candidates (pending vs approved).
- Candidate dashboard fields aligned with the new profile model.

### Changed
- Email is used as login identity (no separate username field on registration forms).
- Version `0.9.0-beta.7`.

## [0.9.0-beta.6] - 2026-07-21

### Added
- Shared frontend form validation (`assets/js/mjb-form-validation.js`) for application and job forms.
- Job importer company normalize/match/dedupe helpers and CLI tooling (`bin/dedupe-companies.php`, `bin/populate-test-jobs.php`).
- Tests for form validation helpers and job importer company handling.

### Changed
- Version strings aligned to `0.9.0-beta.6` across plugin header, `readme.txt`, README, and product docs.
- Product feature-breakdown `.docx`/`.pdf` files moved out of the plugin package to `product-docs/modern-job-board/`.
- Marketing copy no longer uses “not open source” phrasing; remains proprietary freemium.

### Fixed
- PHPCS `EscapeOutput` failures on custom-field required attributes in job submission shortcode.

## [0.9.0-beta.1] - 2026-07-11

### Changed
- Switched to pre-stable beta versioning (`0.9.0-beta.1`). Stable target remains `1.0.0`.
- Theme aligned to the same beta channel (`0.9.0-beta.1`).

## Internal pre-beta history (formerly labeled 1.x)

> Not part of the public version line. Labels below are historical development markers only.

### internal-1.9.0 - 2026-06-10
### Added
- **Gutenberg blocks**: All six core shortcodes available in the block inserter under **Modern Job Board**.
- **Documentation**: `docs/getting-started.md`, `docs/developers.md`, `readme.txt`, and `DEMO.md`.
- **Demo tooling**: `bin/seed-demo.php` and `bin/sync-local-test.ps1` for the local WordPress install.

### Improved
- **Frontend templates**: Refreshed archive and single job layouts with updated cards, filters, and typography.
- **Frontend styles**: Design tokens aligned with the marketing site (DM Sans, teal palette, modern cards).
- **Landing site**: Documentation page, live demo links, and version bump (internal 1.9.0 label).

### internal-1.8.5 - 2026-06-10
### Changed
- **Chart styles in separate file**: Admin performance chart layout and bar widths moved to `assets/css/mjb-charts.css` (utility classes `mjb-chart-w-0` … `mjb-chart-w-100`); embedded `<style>` blocks removed.

### Added
- **`composer make-charts-css`**: Regenerates `mjb-charts.css` from `bin/make-charts-css.php`.

### internal-1.8.4 - 2026-06-10
### Changed
- **No inline CSS**: Removed all `style=""` attributes from templates and PHP output; styles moved to `mjb-style.css`, `mjb-admin.css`, and new `mjb-shared.css`.
- **Class-based UI toggles**: Job form, application form, and AJAX search now show/hide elements via CSS classes instead of JavaScript `style` manipulation.
- **Chart bar widths**: Admin chart bars use scoped stylesheet rules instead of per-element inline widths.

### internal-1.8.3 - 2026-06-10
### Changed
- **Div-based data grids**: Replaced all HTML tables with accessible div grids across employer/candidate dashboards, custom fields admin, and setup wizard.
- **Performance overview**: Employer dashboard totals now use stat cards instead of a summary table.

### Added
- **`MJB_Data_Grid` helper**: Shared renderer for consistent grid markup and responsive mobile layouts.

### internal-1.8.2 - 2026-06-10
### Added
- **Webhook retry queue**: Failed deliveries retry up to 5 times with exponential backoff (cron every 5 minutes).
- **Admin performance charts**: Bar charts for top jobs by views and applications on the Modern Job Board dashboard.
- **Queue visibility**: Admin dashboard and Integrations settings show pending webhook retry counts.

### Improved
- **WPCS escaping pass**: Output escaping fixes across admin, dashboard, shortcodes, tools, feeds, and registration templates.
- **PHPCS ruleset**: Added `WordPress.Security.EscapeOutput` alongside SQL and safe-redirect checks.

### internal-1.8.1 - 2026-06-10
### Added
- **Candidate status emails**: Applicants receive an email when employers change application workflow status.
- **Outbound webhooks**: Configure webhook URLs and optional HMAC secret in Settings → Integrations.
- **Employer analytics**: Job views, applications, and conversion rates on the employer dashboard.
- **REST analytics**: `GET /wp-json/mjb/v2/analytics` returns totals and per-job performance for employers.
- **Job view tracking**: Single job pages increment `_mjb_view_count` once per visitor per hour.

### Improved
- **Page resolver fallbacks**: Registration, applications, job forms, and resume downloads use resolved shortcode pages instead of `home_url('/')`.
- **PHPCS ruleset**: Expanded to include safe redirect checks alongside SQL safety rules.

### internal-1.8.0 - 2026-06-10
### Added
- **Application workflow statuses**: Employers can track applications as New, Reviewed, Shortlisted, Rejected, or Hired from the dashboard.
- **REST API v2 (authenticated)**: Employer endpoints for listing/updating applications; candidate endpoints for reading/updating profile.
- **POST delete job**: Employer dashboard deletes jobs via POST form with nonce (GET delete removed).
- **Extensibility hooks**: Filters/actions across resumes, WooCommerce, emails, employer dashboard, and application status updates.
- **PHPCS in CI**: SQL safety checks via `composer phpcs` in GitHub Actions (expandable ruleset in `phpcs.xml.dist`).
- **i18n baseline**: `languages/modern-job-board.pot` generated via `composer make-pot`.

### Improved
- **Candidate dashboard**: Application status column shows workflow labels instead of WordPress post status.
- **New applications**: Default workflow status is `new` on submission.

### internal-1.7.2 - 2026-06-10
### Added
- **XML / RSS job import**: Bulk import from MJB feed XML, compatible RSS files, or remote feed URLs (Tools → Import).
- **Duplicate-safe imports**: XML items matched by GUID or link are skipped on re-import.
- **Setup page wizard**: Admin **Setup** screen creates missing pages for all six frontend shortcodes.
- **Activation bootstrap**: Plugin activation auto-creates any missing shortcode pages.
- **Shared job importer**: CSV and XML imports share `MJB_Job_Importer` for consistent company and taxonomy handling.

### Improved
- **Page resolver**: `[mjb_jobs]` page is now tracked via `mjb_jobs_page_id`.
- **Admin dashboard**: Quick link to the Setup wizard.

### internal-1.7.1 - 2026-06-10
### Added
- **Pretty search URLs**: Path-based job search routes instead of exposed query strings.
- **REST search paths**: Canonical API endpoint at `/wp-json/mjb/v1/jobs/search/...`.
- **Automatic 301 redirects**: Legacy `?search_keywords=` style URLs redirect to pretty paths.

### Improved
- **Job filter forms** submit to `/jobs/in/{location}/category/{category}/type/{type}/keyword/{keyword}/page/{n}/`.
- **REST responses** include a `Link: rel="canonical"` header pointing at the pretty search URL.

### internal-1.7.0 - 2026-06-10
### Added
- **Candidate "My Applications"**: Application history table on the candidate dashboard matched by email.
- **REST API filters**: `/wp-json/mjb/v1/jobs` supports keywords, location, category, type, `page`, and `per_page` via `MJB_Search`.
- **REST pagination headers**: Responses include `X-WP-Total` and `X-WP-TotalPages`.
- **Shortcode pagination**: `[mjb_jobs]` supports `posts_per_page` attribute with AJAX page controls.
- **Candidate confirmation emails**: Applicants receive a confirmation message after successful submission.
- **Tests**: REST API, feeds, and expanded search ordering/pagination coverage.

### Improved
- **Featured job ordering**: Featured listings sort first across search, shortcode, REST, feed, and archive queries.
- **XML job feed**: Declares `xmlns:mjb`, uses stable item fields (`company`, `location`, `jobType`, `applyUrl`, `featured`).
- **Schema.org JobPosting**: Adds `identifier`, `directApply`, `url`, and mapped `employmentType` values.
- **Cron expiration**: Processes expired jobs in batches (50 per batch, 200 max per run).
- **CSV application export**: Uses stable admin edit links instead of expiring resume nonce URLs.
- **Archive template**: Reuses shared `MJB_Shortcodes::render_job_loop()` for consistent featured styling and expiry display.

### internal-1.6.0 - 2026-06-10
### Added
- **GitHub Actions CI**: PHPUnit workflow runs on push and pull requests to `main`.
- **Registration spam protection**: Honeypot, optional reCAPTCHA, and IP rate limiting on employer and candidate registration forms.
- **Registration rate limiting**: Separate transient bucket for registration attempts (3 per hour per IP).
- **WooCommerce cart authorization**: Job purchase and CV unlock cart links verify job/application ownership.
- **Page resolver cache invalidation**: Clears cached shortcode page IDs when pages are updated, trashed, or deleted.
- **Candidate dashboard page resolver**: Profile and resume form redirects use `[mjb_candidate_dashboard]` URL resolution.
- **Expanded tests**: WooCommerce authorization, resume access, registration guard, and page resolver invalidation.

### Fixed
- **AJAX location filter**: `mjb-ajax-search.js` now reads the location `<select>` introduced in v1.4.
- **Payment redirect**: Job submission uses `wp_safe_redirect()` instead of a JavaScript redirect to checkout.
- **Duplicate application check**: Replaced `get_posts()` meta query with a single `$wpdb` lookup.
- **REMOTE_SETUP.md**: Corrected protected download query string documentation.

### Improved
- **Employer dashboard**: Displays job credit balance and custom application field values in the applications table.
- **Candidate registration redirect**: Sends new candidates to the resolved candidate dashboard page.
- **reCAPTCHA loading**: Also enqueues on registration shortcode pages when enabled.

### internal-1.5.0 - 2026-06-10
### Added
- **Application honeypot**: Hidden honeypot field on internal application forms to block basic bot submissions.
- **Optional reCAPTCHA v2**: Admin settings for site/secret keys; checkbox widget on job application forms when enabled.
- **Shared page resolver**: `MJB_Page_Resolver` auto-detects pages by shortcode for durable URLs.
- **Job form page resolver**: Edit links and job form URLs resolve via `[mjb_job_form]` instead of hardcoded `/post-job/`.
- **nginx resume protection docs**: `REMOTE_SETUP.md` documents the required nginx `location` block for `mjb-resumes`.
- **Expanded tests**: Unit tests for honeypot detection, page resolver, and reCAPTCHA verification.

### Improved
- **Dashboard application counts**: Single batched SQL query replaces per-job `get_posts()` loops (N+1 fix).
- **Dashboard URL resolution**: Delegates to shared `MJB_Page_Resolver`.

### internal-1.4.0 - 2026-06-09
### Added
- **Centralized search builder**: Shared `MJB_Search::build_query_args()` used by shortcodes, AJAX, archives, and main query filtering.
- **Application abuse prevention**: Duplicate-application checks and IP-based rate limiting (5 submissions per hour).
- **PHPUnit test suite**: Initial unit tests for search query building, application guard, and WooCommerce order processing.
- **Dashboard URL resolver**: Auto-detects the page containing `[mjb_dashboard]` for durable email links.

### Fixed
- **Location filter in `[mjb_jobs]`**: Replaced free-text location input with a taxonomy dropdown so filtering works correctly.
- **Application email links**: Notifications now link to the employer dashboard instead of expiring nonce download URLs.
- **SEO filter redirects**: `redirect_to_clean_url()` is now hooked to `template_redirect`.
- **WooCommerce payment timing**: `woocommerce_payment_complete` is handled again, with the existing processed-order guard preventing duplicates.

### Improved
- **Archive location filter**: Reuses the shared location dropdown renderer.
- **Employer registration redirect**: Uses resolved dashboard page URL instead of a hardcoded path.

### internal-1.3.0 - 2026-06-09
### Security
- **Protected resume downloads**: Resumes are blocked from direct public access via `.htaccess` and served through authenticated, nonce-protected download endpoints.
- **Resume upload validation**: Server-side file type and size checks (PDF, DOC, DOCX; max 5 MB) on all resume uploads.
- **Job submission access control**: Frontend job posting now requires a logged-in employer account.
- **Export capability checks**: CSV export/import handlers now verify `manage_options` in addition to nonces.

### Fixed
- **Candidate dashboard resume link**: Fixed broken "View Resume" link for `mjb_resume` post types.
- **CSV import company type**: Import now creates `company` posts instead of the non-existent `job_company` type.
- **WooCommerce double-processing**: Order benefits (credits, unlocks, publishing) are applied once per order via a processed flag.
- **Application custom fields**: Custom application fields now render on the job application form.
- **Application notifications**: Emails now respect per-job notification addresses and use protected resume links.
- **REST API pagination**: `per_page` is capped at 100.

### Added
- **Plugin activation/deactivation hooks**: Registers `employer` and `candidate` roles, secures resume storage, and clears cron on deactivation.
- **User-facing notices**: Forms redirect with clear success and error messages.
- **Paid CV access setting**: Admin toggle to require payment before employers can view candidate details.
- **Conditional asset loading**: Frontend CSS/JS only loads on job board pages and shortcodes.

### internal-1.2.1 - 2025-12-28
### Improved
- **Resume Management**:
  - Moved Resumes to a dedicated "Resumes" Custom Post Type for better organization.
  - Added new admin menu "Resumes" restricted to Administrators.
  - Implemented custom upload directory (`/wp-content/uploads/mjb-resumes/`) to keep candidate files separate from the main Media Library.
  - Updated "Apply with Profile" to support the new secure resume objects.

### internal-1.2.0 - 2025-12-21
### Added
- **Custom Fields Builder**: Admin UI to create custom fields for Job Listings and Applications.
- **CSV Import/Export Tools**: 
  - Export Jobs and Applications to CSV.
  - Bulk import Job Listings via CSV template.
- **Hooks & Filters**: Extensive actions and filters added to shortcodes and application flows for developer extensibility.
- **Integrations**:
  - **Job Feed**: New XML feed at `/feed/job-listings` for aggregators like Indeed and Google Jobs.
  - **REST API**: New JSON endpoint at `/wp-json/mjb/v1/jobs`.
  - **WooCommerce Lifecycle**: Automatic job unpublishing and credit/access revocation on order refund or cancellation.
- **Security Hardening**: Added `index.php` files to all directories to prevent directory listing.

### internal-1.1.0 - 2025-12-15
### Added
- **Monetization System**:
  - Paid Job Listings via WooCommerce.
  - Job Listing Credits/Packages.
  - Paid CV Access (Single Unlock and Time-based Pass).
- **Candidate System**:
  - Candidate Registration and Profiles.
  - CV Upload and Management.
  - "Apply with Profile" functionality.
- **Employer Management**:
  - Frontend Employer Registration.

### internal-1.0.0 - 2025-11-20
### Added
- Initial release.
- Job Listings and Company CPTs.
- Basic Frontend Submission Form.
- Frontend Job Dashboard.
- Search and Filtering (AJAX).
- Google Maps Integration.
- Schema.org Structured Data.
- Application Methods (Email & External).
