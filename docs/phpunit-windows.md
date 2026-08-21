# PHPUnit on Windows (#36)

## Requirements

- PHP **7.4+** (8.x recommended) with extensions:
  - `mysqli` or `mysqlnd` (WordPress test suite)
  - `mbstring`, `json`, `curl`, `xml`, `zip`, `gd` or `imagick` (optional)
  - `zlib` (gzdecode for city index)
- Composer
- From the plugin root: `composer install` then `composer test`

## Local by Flywheel / Local WP

Local’s site PHP often differs from the CLI `php` on PATH.

1. Open **Local → site → Open site shell** (uses the site’s PHP).
2. Or add Local’s PHP to PATH, e.g.  
   `C:\Users\<you>\AppData\Roaming\Local\lightning-services\php-8.x.x\bin\win64`
3. Confirm: `php -v` and `php -m | findstr mysqli`

## Run tests

```bat
cd "C:\Users\marti\4Mation Digital\modern-job-board"
composer test
```

Equivalent:

```bat
vendor\bin\phpunit
```

## Common failures

| Symptom | Fix |
|--------|-----|
| `mysqli` missing | Use Local site PHP or install full PHP build with mysqli |
| Memory exhausted | `php -d memory_limit=512M vendor\bin\phpunit` |
| Path spaces | Quote the plugin path; run from plugin root |
| Tests pass but site 500 | Sync `includes/` to Local plugin path and hard-refresh |

## CI

GitHub Actions (if configured) uses Linux PHP with mysqli. Windows docs are for local development only.
