# Getting Started

## Requirements

- WordPress 6.4+
- PHP 7.4+
- Optional: WooCommerce for monetization

## Installation

1. Upload `modern-job-board` to `wp-content/plugins/`.
2. Activate **Modern Job Board** in the Plugins screen.
3. Open **Modern Job Board → Setup** and click **Create Missing Pages**.
4. Review **Modern Job Board → Settings** for listing duration, maps, security, and integrations.

## Demo content (local)

```powershell
.\bin\sync-local-test.ps1
php bin/seed-demo.php "C:\Users\marti\4Mation Digital\mjb-local-test"
```

## Frontend pages

| Page | Shortcode / Block |
|------|-------------------|
| Jobs | `[mjb_jobs]` |
| Post a Job | `[mjb_job_form]` |
| Employer Dashboard | `[mjb_dashboard]` |
| Candidate Dashboard | `[mjb_candidate_dashboard]` |
| Employer Registration | `[mjb_employer_registration]` |
| Candidate Registration | `[mjb_candidate_registration]` |

All shortcodes are also available as Gutenberg blocks under **Modern Job Board** in the block inserter.

## Recommended next steps

1. Create a few job categories, locations, and types in the admin.
2. Post a test job from the frontend job form.
3. Register a candidate account and submit a test application.
4. Configure WooCommerce products if you plan to monetize listings or CV access.