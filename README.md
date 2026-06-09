# Modern Job Board

A powerful, feature-rich WordPress plugin for creating a professional job board. This plugin handles everything from job submissions and candidate management to monetization and developer extensibility.

## 🚀 Key Features

### 📋 Job Management
- **Frontend Submission**: Easy-to-use form for employers to post jobs.
- **Job Expiration**: Automatic expiration logic with background cleanup (Cron).
- **Featured Listings**: Highlight premium jobs to increase visibility.
- **Company Profiles**: Dedicated profiles for employers.

### 🔍 Advanced Search & Display
- **AJAX Filtering**: Filter by keywords, location, job type, and category without page reloads.
- **Google Maps**: Visual location display on single job pages.
- **SEO Ready**: Automatic Schema.org (`JobPosting`) structured data for better indexing in Google for Jobs.

### 👥 User Roles & Dashboards
- **Employer System**: registration, account management, and a frontend dashboard to manage jobs and view applications.
- **Candidate System**: registration, profile management, and CV/Resume uploads.
- **Secure Resume Storage**: Resumes are stored in a dedicated, admin-only area and separated from the public media library.
- **Apply with Profile**: Logged-in candidates can apply to jobs instantly using their stored resume.

### 💰 Monetization (WooCommerce)
- **Pay-Per-Post**: Charge employers for individual job submissions.
- **Job Credits/Packages**: Sell bulk job listing packs.
- **Paid CV Access**: Monetize access to the candidate database using single unlocks or time-based passes.

### 🛠️ Admin & Developer Tools
- **Custom Fields Builder**: Create unlimited custom fields for jobs and applications via a simple UI.
- **CSV Import/Export**: Bulk import jobs or export your data for external analysis.
- **REST API**: Expose listings via JSON (`/wp-json/mjb/v1/jobs`).
- **XML Feeds**: Standardized feed for aggregators (`/feed/job-listings`).
- **Hooks & Filters**: Highly extensible with dozens of developer hooks.

## 📥 Installation

1. Upload the `modern-job-board` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Configure settings under **Job Board > Settings**.

## 🧩 Shortcodes

- `[mjb_jobs]`: Displays the job search filter and listing loop.
- `[mjb_job_form]`: The frontend job submission form for employers.
- `[mjb_dashboard]`: The employer dashboard (manage jobs/applications).
- `[mjb_employer_registration]`: Registration form for employers.
- `[mjb_candidate_registration]`: Registration form for candidates.
- `[mjb_candidate_dashboard]`: Candidate profile and resume management.

## 💻 Developer Information

### REST API
- **Endpoint**: `GET /wp-json/mjb/v1/jobs`
- **Params**: `per_page` (default 10)

### XML Feed (Indeed/Google Jobs)
- **URL**: `yourdomain.com/feed/job-listings`



---
Copyright © 2026 Modern Job Board.