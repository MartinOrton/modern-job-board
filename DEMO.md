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
- **Theme:** Modern Job Board Theme (marketing landing); plugin supplies job page templates/styles
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

## Import the marketing home page

Builds valid Gutenberg blocks from `modern-job-board-website/index.html` (Hero, Stats, Features, Compare, Developers, Pricing, CTA), creates **Documentation** at `/docs/`, and activates **Modern Job Board Theme**:

```powershell
$env:PHPRC = "$env:APPDATA\Local\run\cp2oegpc-\conf\php"
& "$env:APPDATA\Local\lightning-services\php-8.3.17+1\bin\win64\php.exe" `
  -d auto_prepend_file= `
  "C:\Users\marti\4Mation Digital\modern-job-board\bin\import-landing-home.php" `
  "C:\Users\marti\Local Sites\mjb\app\public" `
  "C:\Users\marti\4Mation Digital\modern-job-board-website"
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

## Seed recruiters, candidates, CVs, and applications

Adds test employers (`employer` role), extra companies/jobs, candidate accounts with PDF resumes, and applications in mixed workflow statuses. Safe to re-run (skips duplicates). All `@mjb.test` accounts use password `MjbTest-2026!`.

```powershell
$env:PHPRC = "$env:APPDATA\Local\run\cp2oegpc-\conf\php"
& "$env:APPDATA\Local\lightning-services\php-8.3.17+1\bin\win64\php.exe" `
  -d auto_prepend_file= `
  "C:\Users\marti\4Mation Digital\modern-job-board\bin\populate-test-people.php" `
  "C:\Users\marti\Local Sites\mjb\app\public"
```

Example logins after seeding:

- Recruiter: `recruiter.helios-analytics@mjb.test`
- Candidate (has applications + CV): `candidate.01@mjb.test`
- Candidate (CV only, no applications): `candidate.16@mjb.test`

## Demo URLs

After seeding:

- `https://mjb.local/jobs/`
- `https://mjb.local/post-a-job/`
- `https://mjb.local/jobs/recruiter-dashboard/`
- `https://mjb.local/candidate-dashboard/`

Update `MJB_DEMO_BASE` in `modern-job-board-website/js/script.js` if your hostname differs.