# Security re-pass (P0 #4) — 2026-07-27

Focused review after license/commerce work and prior review fixes. Not a full formal pen-test.

## Scope

| Area | Status |
|------|--------|
| REST v1 public jobs | OK — published search only; per_page capped |
| REST v2 applications | OK — employer scope; empty job list not unscoped; PII/paid CV redaction |
| REST v2 candidate profile | OK — current user only |
| Resume download | OK — login + capability + nonce + allowed storage roots |
| Private uploads | OK — type allowlists, size limits, `wp_check_filetype_and_ext`, path jail |
| Admin tools import/export | OK — `manage_options` + nonces |
| License commerce | OK — `manage_options` + nonce for key issue; WC product meta admin-only |
| Application / registration guard | OK — honeypot, rate limits |
| Dashboard actions | OK — nonces + ownership (admins can view any job apps after this pass) |

## Fixes in this pass

1. **SSRF hardening for XML feed import** — `MJB_Xml_Importer::validate_remote_feed_url()` blocks private/loopback/link-local IPs, `.local`/`.internal` hosts, URL credentials; used by one-shot import and scheduled feeds. Response size limited to 5 MB.
2. **Resume download headers** — `sanitize_file_name` + safer `Content-Disposition` + `X-Content-Type-Options: nosniff`.
3. **Custom fields admin** — `wp_redirect` → `wp_safe_redirect`.
4. **Employer dashboard** — `manage_options` may view applications for any job (consistent with other admin paths).

## Accepted residual risk

| Item | Notes |
|------|--------|
| Offline license salt in zip | Soft DRM; remote license server is P1 #6–#7 |
| Public job REST/XML feed | Intentional product surface (Business feed gated by plan) |
| Webhook URLs may be private IPs | Operators may POST to internal systems; not auto-fetched as SSRF from untrusted input |
| WP application passwords / cookie auth on REST | Standard WordPress; no custom API keys yet (P5) |

## Operator checklist (prod)

- Deny web access to `wp-content/mjb-private/` (nginx/Apache) — see [deploy.md](deploy.md).
- Keep WordPress, PHP, and SSL current.
- Prefer least-privilege admin accounts; license key issuer is `manage_options`.
