# Local Demo Setup

## Two local sites (keep them separate)

| Domain | Purpose | WordPress site title |
|--------|---------|----------------------|
| `https://mjb.local` | **Modern Job Board** plugin demo | Modern Job Board |
| `https://martin-orton-design.local` | **Reserved** for your portfolio / design site | Martin Orton Design (when created) |

When you add the design site in Local, create it at `Local Sites\martin-orton-design` so it gets its own database, nginx route, and SSL cert.

### Job board demo (this project)

`C:\Users\marti\Local Sites\mjb\app\public`

- **HTTPS:** `https://mjb.local`
- **Theme:** Twenty Twenty-Five (plugin supplies job page templates/styles)
- **HTTP** also works, but WordPress is configured for HTTPS.

## Start the site

If the Local app is not running the site, start services manually:

```powershell
cd "C:\Users\marti\4Mation Digital\modern-job-board"
.\bin\start-local-site.ps1
```

If the browser warns about the certificate, trust Local's cert once:

```powershell
certutil -addstore -user Root "$env:APPDATA\Local\run\router\nginx\certs\mjb.local.crt"
```

## Sync the plugin

```powershell
cd "C:\Users\marti\4Mation Digital\modern-job-board"
.\bin\sync-local-test.ps1 -WordPressRoot "C:\Users\marti\Local Sites\mjb\app\public"
```

## Seed demo pages and jobs

Use Local's PHP with the site `php.ini` (mysqli + DB port 10004):

```powershell
$env:PHPRC = "$env:APPDATA\Local\run\cp2oegpc-\conf\php"
& "$env:APPDATA\Local\lightning-services\php-8.3.17+1\bin\win64\php.exe" `
  -d auto_prepend_file= `
  "C:\Users\marti\4Mation Digital\modern-job-board\bin\seed-demo.php" `
  "C:\Users\marti\Local Sites\mjb\app\public"
```

## Demo URLs

After seeding:

- `https://mjb.local/jobs/`
- `https://mjb.local/post-a-job/`
- `https://mjb.local/employer-dashboard/`
- `https://mjb.local/candidate-dashboard/`

Update `MJB_DEMO_BASE` in `modern-job-board-website/js/script.js` if your hostname differs.