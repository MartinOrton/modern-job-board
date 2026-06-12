# Local Demo Setup

This project includes a WordPress install at:

`C:\Users\marti\4Mation Digital\mjb-local-test`

## Sync the plugin

```powershell
cd "C:\Users\marti\4Mation Digital\modern-job-board"
.\bin\sync-local-test.ps1
```

## Seed demo pages and jobs

```powershell
php bin\seed-demo.php "C:\Users\marti\4Mation Digital\mjb-local-test"
```

## Demo URLs

After seeding, open your local site URL with these paths:

- `/jobs/`
- `/post-a-job/`
- `/employer-dashboard/`
- `/candidate-dashboard/`

Update the demo URL in `modern-job-board-website/js/script.js` if your local hostname differs from the default.