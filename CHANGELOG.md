# Changelog

All notable changes to the Modern Job Board plugin will be documented in this file.

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

## [1.0.0] - 2024-11-20
### Added
- Initial release.
- Job Listings and Company CPTs.
- Basic Frontend Submission Form.
- Frontend Job Dashboard.
- Search and Filtering (AJAX).
- Google Maps Integration.
- Schema.org Structured Data.
- Application Methods (Email & External).
