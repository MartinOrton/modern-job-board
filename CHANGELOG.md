# Changelog

All notable changes to the Modern Job Board plugin will be documented in this file.

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
