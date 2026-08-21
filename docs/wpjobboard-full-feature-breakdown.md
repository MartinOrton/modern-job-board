# WPJobBoard — Complete Feature Breakdown

**Version at time of writing:** 5.12.1 (released 13 Oct 2025)
**Vendor:** Simpliko (lead dev "Greg")
**Model:** Self-hosted WordPress plugin, perpetual licence, no SaaS component
**Sources:** wpjobboard.net marketing pages + full knowledge base (110 articles across 9 categories)

---

## 1. Core Architecture

- WordPress plugin, not a theme. Works with any theme including Genesis, Headway, Thesis, WooFramework.
- Custom database tables (not WP post types for jobs) — hence the dedicated `Wpjb_Model_Job` / `Wpjb_Model_Resume` classes and the separate admin panels under **Job Board** and **Settings (WPJB)**.
- Entity model: **Jobs**, **Applications**, **Companies/Employers**, **Candidates/Resumes**, **Payments**, **Memberships**, **E-mail Alerts**.
- One-click install and activate. Creates default pages on activation.
- WPMU / multisite compatible.
- Translation-ready, WPML compatible.
- PHP templating engine mirroring WordPress theme structure; templates overridable.
- Hooks, filters and actions throughout for extension without core edits.
- Health Check panel (`Settings (WPJB) / Health Check`) for verifying cron events like `wpjb_event_import`.

---

## 2. Job Board — Front End

### Listing & browsing
- Jobs list with configurable jobs-per-page.
- Featured jobs float to top of list with distinct (yellowish) background.
- "New" badge for jobs posted within a configurable number of days.
- Filled jobs can be auto-hidden from the list.
- Expired jobs: optionally still viewable at their detail URL but removed from the list.
- Related jobs block on detail pages (toggleable).
- Bookmark button on job detail pages (toggleable).
- Apply Online button (toggleable).
- Job detail pages carry their own permalinks under the site's normal permalink rules.

### Search
- Basic search bar on the jobs list, with three modes: **Disabled**, **Enabled**, **Enabled with live search** (AJAX, no page reload).
- Advanced Search Form as a separate shortcode, fields configurable by drag-and-drop.
- Search dimensions: keyword, location (country, state, city, zip), job type, category, date posted.
- Active search parameters displayed to the visitor and refinable by single click.
- Map view: `[wpjb_map]` shortcode, with a full-width interactive map template in the bundled theme.

### Applications
- Fully custom application forms via the drag-and-drop custom fields editor.
- Field types include text, radio, dropdown, date (with configurable min/max), file upload, and field groups.
- Per-field validation rules.
- Multiple file attachments, configurable allowed extensions, file count and max size per field.
- Option to restrict applications to registered users only.
- Application notifications sent to admins and to named per-job stakeholder emails.
- All applications and their metadata stored in the plugin's database and browsable in admin.

---

## 3. Application Methods (add-on)

Per-job choice of how candidates apply — mix and match on a single listing:

| Method | Behaviour |
|---|---|
| Apply Online Form | Standard form; notifications sent to one or more listed emails |
| External URL | Apply button redirects to an external ATS or careers page |
| WhatsApp | Applicant contacts employer via international WhatsApp number |
| LinkedIn | Deprecated — no longer available under LinkedIn's current API terms |

- Backward-compatibility toggle so jobs without a method set fall back to default behaviour.
- WhatsApp option hidden by default; enabled in configuration.
- Available to front-end job posters too, not just admins.
- Importable via XML (`<application_methods>` tag) and CSV (`job.application_methods.email` / `.url` / `.whatsapp` headers), comma-separated for multiple emails.

---

## 4. Employer Portal

- Employers post, edit and manage listings 24/7 from `[wpjb_employer_panel]`.
- Status control: de-list, mark filled, schedule future go-live, republish old listings.
- Every one of these capabilities is individually toggleable by the admin.
- Per-listing applicant shortlists, one click.
- Membership dashboard: active packages, usages remaining, days to expiry, rebuy, cancel recurring subscription, archive of past usages.
- Payments History section with invoice downloads (with Sliced Invoices add-on).
- Analytics button per listing (with Google Analytics add-on).
- Company profile editing.
- Employer default visibility: Private (hidden from employers list) or Public.
- WordPress-native password reset.

### Employer moderation controls
- Moderation on/off — only approved members can log in.
- Employer approval: **Instant** or **By Administrator**.

### Employee Manager (add-on)
- Employers add co-workers/managers to their company account.
- Co-workers log in under their own accounts but get full employer permissions: post jobs, edit company profile, manage jobs and applications, purchase memberships.
- Email verification flow with customisable verification email.
- Removable from the Co-Workers panel at any time.

---

## 5. Candidate Portal & Resume Database

### Candidate dashboard `[wpjb_candidate_panel]`
- Update resume, view submitted applications, manage job bookmarks, manage account.
- **My Alerts** panel for self-managing job alerts (added post-2017).
- Resume completeness progress bar.

### Resume database
- Browse by category; search by keyword, location, category, experience, education.
- Advanced resume search form, drag-and-drop configurable.
- Resume form fully extensible via custom fields.
- Featured candidates with numeric **featuring level** — higher number ranks higher in search results.

### Privacy & access controls
- **Resumes Privacy:** hide contact details only, or hide the entire resume list and detail pages.
- **Grant Resumes Access** — five tiers: All / Registered members / Employers / Verified employers / Premium members.
- **On Application** toggle: employers can view full resumes of candidates who applied to *their* jobs regardless of the above.
- Resumes approval: **Instant** or **By Administrator**.
- Resume default visibility: Private or Public.
- Search bar on resumes list toggleable.

### Candidate Anonymizer (add-on, Business licence)
Built explicitly for GDPR:
- **Hide Surname** — displays "Joe D." everywhere including the browser title tag.
- **Hide Surname Exception** — full surname visible to any registered user, or only to premium employers. Admin always sees full.
- **Slug Pattern** — four built-in URL patterns (`joe-d`, `resume-123`, `joe-123`, `joe-doe`), collision-handled with auto-incrementing suffix, plus a `wpjb_ca_slug` filter for custom patterns.
- **Robots Restrictions** — adds noindex to resume detail pages.
- Bulk re-anonymiser to regenerate all existing slugs.

---

## 6. Admin Panel

### Job management
- Add, approve, edit, delete jobs from native WP UI.
- Admin-only job properties hidden from posters (e.g. only admins can flip **Is Featured**).
- Unlimited job types and job categories.
- List table columns modifiable via template overrides (jobs and resumes).
- Admin menu itself is alterable.

### Publishing controls
- **Who Can Post Jobs:** Anyone / Employer / Administration.
- **Hold For Moderation:** independently per listing origin — Free, Paid, and Package (membership-posted) jobs.
- **Job Edition:** allow or block employer self-editing.

### Other panels
- Applications browser with suggestion/autocomplete when editing.
- Employers panel — see how many and which jobs each employer posted, grant/revoke resume access.
- Candidates panel — view, approve, edit resumes.
- Payments panel — full log with external gateway transaction IDs, error messages on failure, "Mark as Paid" for manual/offline approval.
- Memberships panel — add, edit, remove employer memberships manually.
- E-mail Alerts panel — list of all subscribers with email, created date, last run, frequency, and keyword params.

---

## 7. Monetisation

### Pricing scheme types
1. **Single Job Posting** — title, price, currency, active flag, **Is Featured** flag, **Visible** (days live after activation). Set price to 0 for free listings.
2. **Single Resume Access** — same minus featured/visible.
3. **Employer Membership Package** — built by bundling Single Job Postings and Single Resume Access items with per-item usage counts.
4. **Candidate Membership** — paid candidate tiers.

### Membership options
- **Trial** — auto-activates for every newly registered employer.
- **Recurring** — creates a plan in Stripe and charges monthly. **Stripe only.**
- **Recurrence** — expiry in days, 0 = never expires.
- Candidate memberships additionally control: **Have Access** (which pages), **Is Searchable** (whether employers can find them), **Featured Level** (search rank boost), **Alert Slots** (how many job alerts they may create).
- "Membership Default" configuration defines behaviour for users *without* a valid membership.

### Payment gateways
- **PayPal IPN** and **Stripe** built in.
- **PayFast** available as an add-on — relevant for South African deployments.
- **Payment API** documented with working examples, including a Bank Transfer / cash example for offline payment.
- Per-gateway config: availability toggle, custom display title, and display order.
- If only one gateway is enabled the payment method tabs are hidden entirely.
- Currency list extensible via the `wpjb_list_currency` filter (ZAR is not built in but the docs give the exact ZAR snippet).
- **Taxes** configuration panel.

### Other revenue features
- **Discount codes** — by fixed price or percentage, with expiry and usage limits.
- **Promotions** panel.
- **Banners add-on** — up to 5 banner slots injected at chosen positions between jobs on the list (position 0 = above list, position 5 = after 5th job), plus 2 banner slots on job detail pages (one after the data table, one before the application form). Accepts arbitrary HTML including `<script>` tags, so AdSense, affiliate and direct-sold creative all work.
- **Featured Companies add-on.**
- **Sliced Invoices integration** (Business licence) — generates a real invoice for every purchase (job posting, membership, resume access). Buyer billing data can be auto-mapped from Company or Job custom fields; supports a custom Tax ID field. Employers download invoices from their Payments History.

### Known monetisation gaps (confirmed by vendor in KB comments)
- No à-la-carte "add featured for +$X" checkbox — you must create separate normal and featured listing products.
- No overage pricing when a membership's job quota is exhausted.
- No country/tax-based dynamic pricing out of the box.
- Listing types sort by name only; display order not configurable without core edits.
- Front-end posted jobs must always have an expiration date.

---

## 8. Job Ingestion (syndicate in)

| Source | Mechanism | Notes |
|---|---|---|
| **Indeed** | Scheduled import via API key | Requires Indeed publisher ID |
| **CareerBuilder** | Scheduled import via API key | |
| **ZipRecruiter** | Live backfill, not stored import | |
| **Broadbean / AdCourier** | Push into WPJB via webhook URL | `admin-ajax.php?action=wpjb_broadbean` |
| **XML** | Full import API | Documented schema |
| **CSV** | One entity type per file | |
| **REST API** | Programmatic create | See §11 |
| **LinkedIn** | ✗ Not supported, and explicitly not planned | Vendor confirmed |

### Scheduled import detail
- Configure: source engine, keyword, target category, country, location, "posted within" (3/7/30 days), max jobs per import.
- Runs **once daily** via wp-cron regardless of the "posted within" setting.
- Capped at **25 jobs per import run**, but unlimited schedules can be created.
- "Import Once" button for immediate manual runs.
- Indeed-imported descriptions are truncated to 250 characters (Indeed API limitation).
- No keyword AND-logic — "dental management" matches jobs containing just "management".
- No job type selection on import.

### ZipRecruiter backfill (distinct behaviour)
- Not a stored import — jobs are fetched live and displayed inline.
- Separate toggles for backfilling the jobs list vs. the jobs search.
- **Backfill When** threshold: an integer; ZipRecruiter results append only when native results fall below that number (0 = only when list is completely empty).
- Auto-inserts the required "Jobs by ZipRecruiter" attribution link.
- Default keyword and location required, used to tailor backfill to your niche.

---

## 9. Distribution (syndicate out)

- **Google for Jobs** — full JSON-LD generator with:
  - Employment Type Map (your job types → Google's recognised types; unmapped types render as "Other")
  - Fields Map (map any custom field to any JSON-LD property, or use **Static Text** for values common to all jobs, or **Inherit**)
  - Live preview pane with red errors and orange warnings, plus a Validate button opening Google's Structured Data Testing Tool
  - Per-job "Google Jobs" sidebar widget in the editor flagging missing required and recommended fields
  - Sitemap generation delegated to an SEO plugin such as Yoast
- **Schema.org markup** on job descriptions.
- **Indeed XML feed** (with additional tags added in later versions).
- **RSS feeds** — all jobs, by category, and dynamically generated from any search query. Feed API extensible via published snippets.
- **Twitter** auto-post of new listings.
- **LinkedIn** automatic sharing — post as your personal profile with configurable comment, title and description using job variables, plus a "Post Test Share" button. *Company page posting has been broken since the v2 API migration; the vendor acknowledges this.*
- **Facebook** integration.
- Social sharing pulls text and images automatically from job posts.
- Editable title tags, heading tags, emphasis and page URLs across the listing lifecycle.

---

## 10. Email

- Fully custom email templates with autovariables.
- **Advanced HTML Emails** support.
- Four documented template groups: emails to Candidates, to Employers, to Administrator, and Other Emails (including the Employee Manager co-worker verification email).
- **Job Alerts** — subscribe two ways:
  1. Job Alerts widget: keyword + email → daily notifications
  2. "Subscribe To This Search" from the search results page → alerts scoped to the exact current search, with user-selected frequency
- Alert slots per candidate limited by membership tier.
- **MailChimp integration** (Business licence) — opt-in checkbox on Employer and Candidate registration forms, separate or shared list IDs per user type, plus a bulk debugger to push existing users into lists. No in-dashboard unsubscribe; unsubscribe is MailChimp-side only.

---

## 11. Developer Surface

### REST API (WPJB 5.8.3+)
- Configure at `Settings (WPJB) / REST API`, click Generate for an encryption key.
- Exposes API URL – Home, API URL, and API Cypher (encryption method).
- Token-per-user model: each API consumer gets a WP user with an `import` capability; deleting the user instantly revokes the token.
- **Read and write** for: jobs, applications, resumes, companies. JSON responses.
- Downloadable PHP example pack covering list and publish for all four entities.
- **Does not support user management / candidate account creation** (vendor confirmed, Feb 2025).

### Other documented APIs
- **Payment API** — full gateway implementation guide with Bank Transfer worked example.
- **XML Import API** — documented schema, demo dataset downloadable.
- **Locations API**
- **Dates API**
- **Custom Fields Rendering**
- **`Wpjb_Model_Job`** and **`Wpjb_Model_Resume`** class references.
- Public snippets repo on GitHub (`simpliko/wpjobboard-snippets`).
- Export tuning filters: `wpjb_export_max_memory`, `wpjb_export_max_file_size`.

### Shortcodes (19 documented)
`[wpjb_jobs_list]` · `[wpjb_jobs_search]` · `[wpjb_jobs_add]` · `[wpjb_single_job]` · `[wpjb_apply_form]` · `[wpjb_map]` · `[wpjb_resumes_list]` · `[wpjb_resumes_search]` · `[wpjb_single_resume]` · `[wpjb_single_company]` · `[wpjb_employers_list]` · `[wpjb_employer_panel]` · `[wpjb_employer_register]` · `[wpjb_candidate_panel]` · `[wpjb_candidate_register]` · `[wpjb_candidate_membership]` · `[wpjb_membership_pricing]` · `[wpjb_login]` · `[wpjb_flash]`

Plus legacy `[wpjobboard-jobs]` / `[wpjobboard-resumes]`, which only function on the pages auto-created at activation.

**5.12.1 addition:** `use_view` parameter on the `[wpjb_single_*]` shortcodes, for cases where the shortcode renders nothing.

### Widgets (12 registered)
WPJobBoard Menu (unified, replaces the two legacy menu widgets) · Recent Jobs · Featured Jobs · Job Alerts · Job Categories · Job Types · Job Feeds · Job Location · Search Jobs
**Deprecated:** Recent Viewed, Job Board Menu, Resumes Menu.

---

## 12. Import / Export (backup & migration)

- Available since 4.3.3.
- **Full XML export** — custom fields, categories, job types, jobs, applications, companies, resumes, with progress indicator and zipped download. Only categories/types with assigned records are exported.
- **Partial export** from the Jobs, Applications, Employers and Candidates panels — exports the *current filtered view* only (e.g. "inactive jobs in New York").
- **XML** partial export supports "Include Additional Data" to pull linked records (e.g. companies attached to exported jobs).
- **CSV** partial export with per-field selection; long text and file fields unchecked by default.
- Import accepts zip, xml or csv. CSV limited to one entity type per file.

---

## 13. Security & Anti-Spam

### Files Security (WPJB 5.10+)
- **Apache:** protected by default via mod_rewrite; settings hidden behind a "Show settings anyway" button.
- **Nginx / Lighttpd:** manual configuration required.
- **Secure Folder Path** — relocate uploads entirely outside the web root (e.g. `/home/server/wpjobboard-uploads/`), 0755. Requires manual migration of existing files.
- **Enable Hashing** + **Hashing Key** — encrypts file paths so URLs don't leak structure.
- **Per-entity protection** — independently protect Jobs, Applications, Resumes, Employers, with a per-field exclude list.
- Vendor's own recommendation: protect **Applications** only, and maybe Resumes. Protected files can't be indexed by search engines and cost more CPU/RAM to serve.

### Anti-spam (WPJB 4.4.4+)
- **HoneyPots** — CSS-hidden field with configurable title and field name (deliberately named to look legitimate to bots).
- **TimeTraps** — encrypted timestamp in a hidden field; submissions faster than a configurable delta (default 2s) are rejected. Configurable encode key.
- **Logging** — IP and message logged for every detection, for manual banning.
- **reCAPTCHA** — configurable per-form.

---

## 14. Bundled Theme: Jobeleon

- Free with every licence (advertised value $79).
- Flat/minimal design.
- Full-width interactive map homepage template with filtering and click-through job cards.
- 4 preset colour schemes (green, red, blue, mint) plus full custom colour control.
- Responsive.
- Built on the WPJB templating engine.

---

## 15. Other Add-ons

| Add-on | Function | Tier |
|---|---|---|
| Application Methods | Per-job apply routing (see §3) | All |
| Employee Manager | Multi-user employer accounts | All |
| Gallery / Portfolio | Clickable image galleries on job, resume and company pages; bundled Lightcase lightbox; configurable thumbnail and image sizes | All |
| Banners | Ad slots in list and detail pages | All |
| Application URL | External apply URLs | All |
| Featured Companies | Promoted company listings | All |
| Terms and Conditions | T&C acceptance | All |
| PayFast Integration | South African gateway | All |
| Broadbean Integration | AdCourier push-in, free download | All |
| Business Analytics (Google Analytics) | Page views + application counts charted in employer panel, wp-admin job editor, and a "Page Views" row on job detail pages. Uses a Google service account with read-only Analytics access. Stats cached hourly to stay under the 5,000 calls/day free API limit. | **Business** |
| Candidate Anonymizer | GDPR anonymisation | **Business** |
| MailChimp Integration | List sync | **Business** |
| Upload From Dropbox | Dropbox file picker on upload fields | **Business** |
| Sliced Invoices Integration | Real invoicing | **Business** |

---

## 16. Licensing & Commercials

| | Personal | Business |
|---|---|---|
| Price | **$97** | **$199** |
| Sites | One | All sites you own |
| Support & updates | 1 year | 1 year |
| Jobs / resumes / applications | Unlimited | Unlimited |
| Jobeleon theme | ✓ | ✓ |
| Source code modification | ✓ | ✓ |
| Business extensions (5+) | ✗ | ✓ |

- Personal → Business upgrade: **$110**.
- Checkout via Avangate (Visa, MasterCard, Amex, Maestro, Solo, Switch, PayPal). VAT applied to EU orders.
- Instant download link on payment.
- Paid customisation services offered, quoted within one business day.
- Extensions downloaded from a client panel at `wpjobboard.net/panel/`, requiring an active licence.

---

## 17. What Is Genuinely Absent

Checked against the entire KB, not just marketing copy:

- **No recruiter-side ATS connectors** — no Bullhorn, Greenhouse, Workday, JobAdder, Vincere. Broadbean is the only recruitment-tooling integration, and it's one-directional (jobs in, applications out by email).
- **No AI anything** — no CV parsing, no matching, no screening, no JD generation, no semantic search. Search is keyword and taxonomy only.
- **No Gutenberg blocks.** Shortcodes only.
- **No candidate pipeline or stage management.** Applications are a flat list per job. No statuses, no kanban, no interview scheduling, no notes, no scorecards. Calling it a "360º ATS" is generous — it's an application inbox.
- **No REST API user management** — can't create candidate accounts programmatically.
- **No LinkedIn job import**, explicitly and permanently declined by the vendor.
- **"Apply with LinkedIn"** is dead — restricted to LinkedIn partners since the v2 API.
- **LinkedIn company page posting** is broken; only personal profiles work.
- **No native multi-currency**, no tax-by-geography, no proration or overage billing.
- **No SLA on support** beyond "under 24 hours" as a marketing claim.

## 18. Maturity Signals

- Marketing pages last meaningfully edited **2014–2015** (og:modified dates). Twitter auto-posting is still headline copy.
- Knowledge base articles range 2013–2025; the newest substantive ones are Files Security (2023), Gallery 2.0 (2024) and Employee Manager (Nov 2025).
- Release cadence is alive but maintenance-grade: 5.12.1 shipped one shortcode parameter, three bug fixes and a typo correction.
- 123 announcement posts on the blog — long, consistent release history.
- Vendor answers KB comments personally, often within 24 hours, with real technical detail and honest "no, that's not possible" answers. That's a genuine asset and the thing testimonials consistently praise.
- Single-developer bus factor.
