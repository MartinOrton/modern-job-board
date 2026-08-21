=== Modern Job Board ===
Contributors: martinorton
Tags: jobs, job board, careers, recruitment, woocommerce
Requires at least: 6.4
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 0.9.0-beta.88
License: Proprietary
License URI: https://martinorton.com/modern-job-board

A proprietary freemium WordPress job board with employer and candidate dashboards, applications, and optional WooCommerce monetization on paid plans.

== Description ==

Modern Job Board is a **proprietary freemium** plugin — free tier available, paid plans for advanced features. Run a professional recruitment site on WordPress without a SaaS platform; listing data lives on your site under your WordPress install. Employers can post jobs from the frontend, review applications, and track performance. Candidates can register, upload resumes, and apply to roles.

= Key features =

* AJAX job search with pretty URLs
* Employer and candidate registration
* Frontend employer dashboard with application workflow
* Candidate dashboard with resume management
* Internal applications or external apply URLs
* WooCommerce pay-per-post, job credits, and paid CV access (Pro plan)
* Custom fields builder for jobs and applications (Pro plan)
* CSV and XML import/export tools (Pro plan)
* REST API and XML feed for aggregators (Business plan)
* Outbound webhooks with retry queue (Business plan)
* Gutenberg blocks for all core shortcodes
* Schema.org JobPosting markup

= Shortcodes =

* `[mjb_jobs]` — job search and listings
* `[mjb_job_form]` — frontend job submission
* `[mjb_dashboard]` — employer dashboard
* `[mjb_employer_registration]` — employer signup
* `[mjb_candidate_registration]` — candidate signup
* `[mjb_candidate_dashboard]` — candidate profile and applications

= Documentation =

Full setup and developer docs are available in the plugin `docs/` folder and on the project website. See LICENSE.txt for plan details.

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/modern-job-board`, or install through the WordPress plugins screen.
2. Activate the plugin through the Plugins screen.
3. Go to **Modern Job Board → Setup** and create the required frontend pages.
4. Configure settings under **Modern Job Board → Settings**.

== Frequently Asked Questions ==

= Does this work with any theme? =

Yes. The plugin is shortcode and block based. Basic frontend styles are included, and templates are provided for job archives and single job pages.

= Can I charge employers to post jobs? =

Yes, with WooCommerce. You can sell pay-per-post products or job credit packages.

= Is there a REST API? =

Yes. Public job search is available at `/wp-json/mjb/v1/jobs`. Authenticated employer and candidate endpoints are available under `/wp-json/mjb/v2/`.

== Screenshots ==

1. Job listings with AJAX filters
2. Employer dashboard with analytics
3. Admin setup wizard
4. Gutenberg block inserter
5. Single job application form

== Changelog ==

= 0.9.0-beta.88 =
* GitHub catch-up: first public snapshot after 0.9.0-beta.6
* Version strings aligned; 233 PHPUnit tests
* Recruiter dashboard JS; ignore generated city-prefix data
* See CHANGELOG.md for full notes

= 0.9.0-beta.68 =
* WPJB Tier C polish: time-trap spam, IP bans/log, completeness bar, filter chips, multi-file apply
* XML board backup, social publish hooks, hashed paths, health check, who-can-post
* See CHANGELOG.md for full notes

= 0.9.0-beta.67 =
* WPJB Tier B parity: jobs map, featured companies/ad banners, ingest webhook, empty-list backfill
* Candidate alert slots, employer trial, payments panel, email templates, Mailchimp, feature-this-job
* See CHANGELOG.md for full notes

= 0.9.0-beta.66 =
* WPJB Tier A parity: Google Jobs mapper/preview, filled/schedule/republish, New badge, related jobs
* WhatsApp apply, multi notify emails, resume access matrix, candidate anonymizer
* Employer package usage dashboard, subscribe-to-this-search alerts
* See CHANGELOG.md for full notes

= 0.9.0-beta.65 =
* Release hygiene: version strings aligned; PHPCS clean; smoke runner; resume lifecycle harden
* Admin: Settings subtabs, Jobs shell/editor polish, Jobs list delete + confirm modal
* P0.1 correctness: always-copy resume on apply; REST/job-edit/storage verified
* See CHANGELOG.md for full notes

= 0.9.0-beta.50 =
* Product backlog modules #6–#42 (alerts, talent pool, brand, PWA, etc.)

= 0.9.0-beta.9 =
* License/plan enforcement (Free job cap, Pro/Business gates)
* Purchase flow: checkout URLs, vendor key issue, WooCommerce license products
* See CHANGELOG.md for full notes

= 0.9.0-beta.1 =
* Pre-stable beta versioning. Treat as beta until 1.0.0 stable.

== Upgrade Notice ==

= 0.9.0-beta.88 =
Pre-stable beta. Keep test installs updated; not a production 1.0 release.