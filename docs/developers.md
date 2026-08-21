# Developer Guide

**Full human-facing reference** (sidebar TOC, endpoint tables, curl examples) lives on the marketing docs site:

- Source: `modern-job-board-website/docs/index.html`
- Live path: `/docs/`

This file is a short in-repo cheat sheet for agents and contributors.

## REST API v1 (public)

- `GET /wp-json/mjb/v1/jobs`
- `GET /wp-json/mjb/v1/jobs/search/in/{location}/category/{category}/type/{type}/keyword/{keyword}/page/{page}/per-page/{per_page}/`

Responses include `X-WP-Total`, `X-WP-TotalPages`, and a canonical `Link` header.

## REST API v2 (authenticated)

- `GET /wp-json/mjb/v2/applications`
- `PATCH /wp-json/mjb/v2/applications/{id}`
- `GET /wp-json/mjb/v2/analytics`
- `GET|PATCH /wp-json/mjb/v2/candidate/profile`

Business plan required. Application passwords recommended.

## XML feed

`/feed/job-listings` (Business)

## Webhooks

Configure URLs and an optional HMAC secret under **Settings → Integrations**.

Events:

- `application.submitted`
- `application.status_updated`
- `job.submitted`

Failed deliveries are retried with exponential backoff. Header: `X-MJB-Signature` (HMAC-SHA256 of raw body).

## Useful hooks

- `mjb_job_listing_query_args`
- `mjb_license_plan` / `mjb_license_can`
- `mjb_before_employer_dashboard`
- `mjb_application_submitted` / `mjb_job_submitted`
- `mjb_webhook_payload`

## Development

```bash
composer test
composer phpcs
composer make-pot
composer make-charts-css
```