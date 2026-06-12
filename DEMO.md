# Local Demo Setup

Primary WordPress install (Local WP):

`C:\Users\marti\Local Sites\martin-orton-design\app\public`

- **HTTPS:** `https://martin-orton-design.local`
- **HTTP** also works, but WordPress is configured for HTTPS.

## Start the site

If the Local app is not running the site, start services manually:

```powershell
cd "C:\Users\marti\4Mation Digital\modern-job-board"
.\bin\start-local-site.ps1
```

If the browser warns about the certificate, trust Local's cert once:

```powershell
certutil -addstore -user Root "$env:APPDATA\Local\run\router\nginx\certs\martin-orton-design.local.crt"
```

## Sync the plugin

```powershell
cd "C:\Users\marti\4Mation Digital\modern-job-board"
.\bin\sync-local-test.ps1 -WordPressRoot "C:\Users\marti\Local Sites\martin-orton-design\app\public"
```

## Seed demo pages and jobs

Use Local's PHP with the site `php.ini` (mysqli + DB port 10004):

```powershell
$env:PHPRC = "$env:APPDATA\Local\run\cp2oegpc-\conf\php"
& "$env:APPDATA\Local\lightning-services\php-8.3.17+1\bin\win64\php.exe" `
  -d auto_prepend_file= `
  "C:\Users\marti\4Mation Digital\modern-job-board\bin\seed-demo.php" `
  "C:\Users\marti\Local Sites\martin-orton-design\app\public"
```

## Demo URLs

After seeding:

- `https://martin-orton-design.local/jobs/`
- `https://martin-orton-design.local/post-a-job/`
- `https://martin-orton-design.local/employer-dashboard/`
- `https://martin-orton-design.local/candidate-dashboard/`

Update `MJB_DEMO_BASE` in `modern-job-board-website/js/script.js` if your hostname differs.