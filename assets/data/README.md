# City autocomplete data

`country-names.php` ships in git.

`cities-by-prefix/` is **generated** from GeoNames (~10k gzip shards, ~90MB) and is **not** committed. Local and production copies are rebuilt with:

```powershell
php bin/build-world-cities.php
```

The script reads raw TSVs from `%TEMP%/mjb-geonames/letter-raw` and writes prefix shards to `assets/data/cities-by-prefix/`. Without those shards, city autocomplete returns no suggestions; the rest of the plugin still runs.
