# Developer Guide

## REST API v1 (public)

- `GET /wp-json/mjb/v1/jobs`
- `GET /wp-json/mjb/v1/jobs/search/in/{location}/category/{category}/type/{type}/keyword/{keyword}/page/{page}/per-page/{per_page}/`

Responses include `X-WP-Total`, `X-WP-TotalPages`, and a canonical `Link` header.

## REST API v2 (authenticated)

- `GET /wp-json/mjb/v2/applications`
- `PATCH /wp-json/mjb/v2/applications/{id}`
- `GET /wp-json/mjb/v2/analytics`
- `GET|PATCH /wp-json/mjb/v2/candidate/profile`

## XML feed

`/feed/job-listings`

## Webhooks

Configure URLs and an optional HMAC secret under **Settings → Integrations**.

Events:

- `application.submitted`
- `application.status_updated`
- `job.submitted`

Failed deliveries are retried up to five times with exponential backoff.

## Useful hooks

- `mjb_job_listing_query_args`
- `mjb_before_employer_dashboard`
- `mjb_dashboard_application_row`
- `mjb_before_delete_job`

## Development

```bash
composer test
composer phpcs
composer make-pot
composer make-charts-css
```