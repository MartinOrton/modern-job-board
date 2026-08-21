# Modern Job Board — local & production deploy notes

**Audience:** operators deploying the plugin on Local WP, VPS, shared hosting, or managed WordPress.  
**Related:** [REMOTE_SETUP.md](../REMOTE_SETUP.md) (SSH + nginx snippet), [purchase.md](purchase.md), [security-repass.md](security-repass.md), [release-checklist.md](release-checklist.md).

---

## 1. Storage layout (durable under `wp-content/`)

User files are **not** stored inside the plugin directory (plugin updates would wipe them) and **not** in the Media Library.

| Path | Contents | Web access |
|------|----------|------------|
| `wp-content/mjb-private/` | Resumes / CVs (hashed names) | **Must be denied** — PHP download only |
| `wp-content/mjb-brand/` | Company logos, candidate photos | Public by design (guess-resistant hashed names) |

Subdirs (created on activate / first upload): `resumes/`, `logos/`, `photos/` with year/month nesting.

### Legacy paths (still readable if present)

Older installs may have files under:

- `wp-content/plugins/modern-job-board/mjb-private/`
- `wp-content/plugins/modern-job-board/mjb-brand/`
- `wp-content/uploads/mjb-private/`, `uploads/mjb-resumes/`, `uploads/mjb-brand/`

Keep **deny rules** for legacy private paths until you are sure nothing remains there.

### What survives a plugin update

| Survives | Wiped / replaced on update |
|----------|----------------------------|
| `wp-content/mjb-private/**` | Plugin PHP/CSS/JS under `plugins/modern-job-board/` |
| `wp-content/mjb-brand/**` | |
| Options, posts, usermeta in the DB | |

**Do not** put CVs under the plugin folder. Rely on `MJB_Private_Uploads` (default).

### Permissions

- Directories: typically `755` (or host default for `wp-content`).
- PHP must write to `wp-content/mjb-private` and `mjb-brand` (same user as WordPress).
- After first activation, confirm both folders exist and contain `index.php` (and `.htaccess` / `web.config` under private).

---

## 2. Block direct access to private files

Resumes are served only via authenticated download URLs:

`?mjb_download=application|resume&mjb_id=…&mjb_nonce=…`

Direct HTTP GETs to files under `mjb-private` must fail.

### Apache / LiteSpeed

Plugin writes `wp-content/mjb-private/.htaccess` with `Require all denied` (and legacy Deny rules).  
Usually **no extra config** if `.htaccess` is honoured (`AllowOverride` enabled).

**Verify:** open `https://yoursite.example/wp-content/mjb-private/` or a known file URL → **403**.

### IIS

Plugin writes `wp-content/mjb-private/web.config` denying all users.  
Confirm the site uses that `web.config` (not overridden by parent).

### nginx (required — ignores `.htaccess`)

Inside the site’s `server { }` block (adjust if WordPress is in a subdirectory):

```nginx
# MJB durable private storage (CVs) — never serve statically
location ~* /wp-content/mjb-private/ {
    deny all;
    return 403;
}

# Legacy private paths
location ~* /wp-content/plugins/modern-job-board/mjb-private/ {
    deny all;
    return 403;
}
location ~* /wp-content/uploads/mjb-(private|resumes)/ {
    deny all;
    return 403;
}
```

Brand assets stay public:

```nginx
# Optional: no directory listing if your nginx defaults allow it
location ~* /wp-content/mjb-brand/ {
    # allow static files; hashed names only
    try_files $uri =404;
}
```

Apply:

```bash
sudo nginx -t && sudo systemctl reload nginx
```

### Local by Flywheel / Local WP

Local often uses **nginx**. Either:

1. Edit the site’s nginx conf (Local → site → **Go to site folder** → conf), add the `mjb-private` blocks, restart site; or  
2. Confirm that requesting `/wp-content/mjb-private/` returns **403** after config.

If Local uses Apache for that site, `.htaccess` is usually enough.

### Cloudflare / reverse proxies

Rules that cache static assets under `/wp-content/` must **not** cache `mjb-private` (it should already be 403). Prefer:

- Cache Everything **exclude** `/wp-content/mjb-private/*`
- Or rely on origin 403 (safest: never put private files on a public CDN)

---

## 3. Cache guidance

| Layer | Guidance |
|-------|----------|
| **Page cache** (WP Rocket, LiteSpeed, nginx FastCGI, etc.) | Cache public job list / single job pages if you want. **Do not cache** employer/candidate dashboards, registration, login, job form, checkout, or any URL with `mjb_download`. |
| **Object cache** (Redis/Memcached) | Fine; plugin uses options/transients normally. |
| **CDN** | Cache `mjb-brand` images OK. Never origin-pull or cache `mjb-private`. |
| **Logged-in users** | Exclude logged-in traffic from full-page cache (standard WP practice). |
| **REST** | `mjb/v1` jobs may be cached carefully (public). **Do not** cache `mjb/v2/*` (auth). |
| **Feeds** | `/feed/job-listings` is public Business feature; short cache TTL is OK if plan unlocks it. |

**Cookies / query strings:** Resume downloads use query args + nonces — ensure your cache never stores those responses (they should only run for logged-in authorized users and `nocache_headers()` is sent).

---

## 4. Deploy / update workflow

### Production install

1. Upload plugin to `wp-content/plugins/modern-job-board/` (zip or git deploy).
2. Activate plugin (creates storage dirs + protection files).
3. Apply **nginx** deny rules if the host uses nginx.
4. **Setup** wizard: create pages if needed.
5. **Settings → License & plan**: Free, or paste key / set checkout URLs for your sales site.
6. Optional: `define('MJB_LICENSE_PLAN', 'business');` in `wp-config.php` for forced plan (dev/staging only unless intentional).
7. Smoke: jobs list, apply, download resume as employer (works), hit private file URL (403).

### Plugin update

1. Replace only `plugins/modern-job-board/` (or run updater).
2. **Do not** delete `wp-content/mjb-private` or `mjb-brand`.
3. Re-test private URL 403 and one resume download.
4. Clear page cache after major releases.

### Sales site (you selling licenses)

Separate or same WordPress with WooCommerce + **any WooCommerce-supported payment gateway** (PayPal, Stripe, Square, Mollie, Razorpay, regional gateways, etc.—where you can open a merchant account):

- Product meta **MJB plugin license** → Pro/Business.
- Checkout URLs in Settings (or leave mailto).
- See [purchase.md](purchase.md).

### Backups

Include in backup scope:

- Database  
- `wp-content/mjb-private/`  
- `wp-content/mjb-brand/`  
- Plugin code is replaceable; **CV data is not**.

---

## 5. Environment checklist

### Local (mjb.local)

```powershell
cd "C:\Users\marti\4Mation Digital\modern-job-board"
composer sync-local
# optional: composer seed-demo
```

- [ ] Plugin active, no fatals  
- [ ] `wp-content/mjb-private` and `mjb-brand` exist  
- [ ] Private path returns 403 in browser  
- [ ] Employer can download application resume when authorized  

### Production

- [ ] HTTPS on  
- [ ] nginx/Apache/IIS private deny verified  
- [ ] File permissions allow PHP writes to storage dirs  
- [ ] Page cache excludes dashboards + downloads  
- [ ] Backups include `mjb-private` + `mjb-brand`  
- [ ] License key or Free plan intentional  
- [ ] (Optional) XML feed import only to public HTTPS hosts — private IPs blocked by plugin  

---

## 6. Quick verify commands

```bash
# Expect 403 (nginx/Apache)
curl -I "https://YOUR_DOMAIN/wp-content/mjb-private/"

# Expect 200 only with a valid authenticated download URL from the dashboard
# (do not paste real nonces in tickets)
```

---

## 7. Troubleshooting

| Symptom | Check |
|---------|--------|
| Resume upload fails | Disk space; write permission on `wp-content/`; PHP `upload_max_filesize` / `post_max_size` ≥ 5 MB for CVs |
| 404 on logo/photo | Brand URL; file under `mjb-brand/`; CDN purge |
| Direct private URL still 200 | **nginx** missing deny rule, or wrong site conf, or CDN serving old file |
| Files “disappeared” after update | Were they under `plugins/.../mjb-private`? Migrate to `wp-content/mjb-private`; plugin still *reads* legacy paths |
| Download 403 for employer | Ownership, paid CV access, expired nonce (reload dashboard) |

---

*P0 #5 — Local/prod deploy notes. Keep this file updated when storage paths or download endpoints change.*
