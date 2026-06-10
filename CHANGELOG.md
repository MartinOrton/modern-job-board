# Changelog

All notable changes to the Modern Job Board plugin will be documented in this file.

## [1.8.0] - 2026-06-10
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

## [1.7.2] - 2026-06-10
### Added
- **XML / RSS job import**: Bulk import from MJB feed XML, compatible RSS files, or remote feed URLs (Tools → Import).
- **Duplicate-safe imports**: XML items matched by GUID or link are skipped on re-import.
- **Setup page wizard**: Admin **Setup** screen creates missing pages for all six frontend shortcodes.
- **Activation bootstrap**: Plugin activation auto-creates any missing shortcode pages.
- **Shared job importer**: CSV and XML imports share `MJB_Job_Importer` for consistent company and taxonomy handling.

### Improved
- **Page resolver**: `[mjb_jobs]` page is now tracked via `mjb_jobs_page_id`.
- **Admin dashboard**: Quick link to the Setup wizard.

## [1.7.1] - 2026-06-10
### Added
- **Pretty search URLs**: Path-based job search routes instead of exposed query strings.
- **REST search paths**: Canonical API endpoint at `/wp-json/mjb/v1/jobs/search/...`.
- **Automatic 301 redirects**: Legacy `?search_keywords=` style URLs redirect to pretty paths.

### Improved
- **Job filter forms** submit to `/jobs/in/{location}/category/{category}/type/{type}/keyword/{keyword}/page/{n}/`.
- **REST responses** include a `Link: rel="canonical"` header pointing at the pretty search URL.

## [1.7.0] - 2026-06-10
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

## [1.6.0] - 2026-06-10
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

## [1.5.0] - 2026-06-10
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

## [1.4.0] - 2026-06-09
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

## [1.3.0] - 2026-06-09
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

## [1.2.1] - 2025-12-28
### Improved
- **Resume Management**:
  - Moved Resumes to a dedicated "Resumes" Custom Post Type for better organization.
  - Added new admin menu "Resumes" restricted to Administrators.
  - Implemented custom upload directory (`/wp-content/uploads/mjb-resumes/`) to keep candidate files separate from the main Media Library.
  - Updated "Apply with Profile" to support the new secure resume objects.

## [1.2.0] - 2025-12-21
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

## [1.1.0] - 2025-12-15
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

## [1.0.0] - 2025-11-20
### Added
- Initial release.
- Job Listings and Company CPTs.
- Basic Frontend Submission Form.
- Frontend Job Dashboard.
- Search and Filtering (AJAX).
- Google Maps Integration.
- Schema.org Structured Data.
- Application Methods (Email & External).
