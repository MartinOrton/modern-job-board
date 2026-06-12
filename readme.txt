=== Modern Job Board ===
Contributors: martinorton
Tags: jobs, job board, careers, recruitment, woocommerce
Requires at least: 6.4
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.9.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A lightweight, extensible WordPress job board with employer and candidate dashboards, applications, and WooCommerce monetization.

== Description ==

Modern Job Board helps you run a professional recruitment site on WordPress without monthly SaaS fees. Employers can post jobs from the frontend, review applications, and track performance. Candidates can register, upload resumes, and apply to roles.

= Key features =

* AJAX job search with pretty URLs
* Employer and candidate registration
* Frontend employer dashboard with application workflow
* Candidate dashboard with resume management
* Internal applications or external apply URLs
* WooCommerce pay-per-post, job credits, and paid CV access
* Custom fields builder for jobs and applications
* CSV and XML import/export tools
* REST API and XML feed for aggregators
* Outbound webhooks with retry queue
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

Full setup and developer docs are available in the plugin repository `docs/` folder and on the project website.

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

= 1.9.0 =
* Frontend template and stylesheet refresh
* Gutenberg blocks for all core shortcodes
* Documentation, demo seed script, and wordpress.org readme
* Local development sync tooling

== Upgrade Notice ==

= 1.9.0 =
Adds Gutenberg blocks, polished frontend templates, and documentation for public demos.