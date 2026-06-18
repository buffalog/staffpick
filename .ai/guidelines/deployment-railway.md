# Deployment

> Curated, project-specific deployment + database constraints. This file lives in
> `.ai/guidelines/` so `php artisan boost:install` composes it into the generated
> guideline block and NEVER overwrites it (Boost only regenerates its own built-in
> guidelines; user files under `.ai/guidelines/` are merged in untouched). The
> built-in generic "deploy with Laravel Cloud" guideline is excluded via
> `config/boost.php` (`guidelines.exclude => ['deployments']`).

## Production: Railway

This application is deployed on **Railway**, not Laravel Cloud or Deployer.

- **Project**: staffpick (ID: `50b12e33-0382-4140-a6ff-7c3153491fff`)
- **Environment**: staging (ID: `3c4299d6-bf25-4acc-9b40-1b0608e4a0e9`)
- **App service**: `10a329c9-9655-4aea-ac9c-f853d47c9cd9`
- **App URL**: `https://app-staging-2263.up.railway.app`
- **DB service**: `5a853239-7ca7-4b66-a01c-b4258805f743`
- **Repo**: `buffalog/staffpick` (GitHub), `main` branch — Railway auto-deploys on push

### How deployment works

Pushes to `main` trigger a Railway build. The Dockerfile builds the image (PHP 8.4, sqlsrv extension, Node 22 for Vite assets). On container start, `start.sh` runs:
1. Creates the `staffpick` database if it doesn't exist (via sqlcmd)
2. Clears config cache
3. Runs `php artisan migrate --force`
4. Runs each seeder individually (RolesAndPermissions suppresses duplicate key errors on subsequent boots)
5. Caches routes and views
6. Starts `php artisan serve` on `$PORT`

### Database: Azure SQL (SQL Server) — CRITICAL

The database is **Azure SQL Edge** via the `sqlsrv` Laravel driver at `db.railway.internal:1433`. This is locked in for HIPAA BAA coverage and is **not changing**.

**SQL Server constraints already handled in all existing migrations — do not violate these:**

1. **Cascade cycles** — SQL Server rejects ANY FK that creates multiple cascade paths to the same ancestor table, regardless of action type. All `sp_*` models use plain `unsignedBigInteger` for `tenant_id` — no FK constraint. Do NOT add constrained FKs that create multiple cascade paths back to `users` or `tenants`.

2. **Index-dependent ALTER COLUMN** — SQL Server blocks `ALTER COLUMN` on any column with a dependent index. Pattern: drop index → alter column → recreate index. See `2024_04_09_095954_table_roadmap_items_adjust_slug_type.php` and `2026_02_21_141433_change_versionable_id_to_integer_in_version_tables.php` for reference implementations.

3. **No fulltext index via Blueprint** — `$table->fullText()` is unsupported by the `sqlsrv` driver. Guard with `if (config('database.default') !== 'sqlsrv')`.

4. **No `dropColumn` inside `Schema::create`** — invalid on any DB, crashes on SQL Server.

5. **No local migration runs** — PHP 8.5 on the dev machine seg faults with the `sqlsrv` extension. Migrations are validated via Railway deploys. Write and validate against migration files as source of truth.

### Local development vs Railway

- The local environment uses Laravel Herd and a local MySQL/SQLite DB for general Laravel work
- The `sp_*` (StaffPick domain) tables only exist on Railway — they are NOT in the local database
- For StaffPick-specific feature tests requiring `sp_*` tables, use a local Azure SQL Edge container:
  ```bash
  docker run -e 'ACCEPT_EULA=1' -e 'MSSQL_SA_PASSWORD=StaffPick_Dev_2026!' \
    -e 'MSSQL_PID=Developer' -p 1433:1433 --name staffpick-db \
    -d mcr.microsoft.com/azure-sql-edge:latest
  sleep 20 && sqlcmd -S 127.0.0.1 -U sa -P 'StaffPick_Dev_2026!' -C -Q "CREATE DATABASE staffpick_test;"
  ```
  Then set `.env.testing` to use `DB_CONNECTION=sqlsrv` pointing at `127.0.0.1:1433`.
- SQLite in-memory is NOT a valid substitute for StaffPick feature tests — SQL Server has different DDL constraints that SQLite does not enforce.
- **`pdo_sqlsrv` (Railway) returns integer/bigint columns as PHP strings; the local FreeTDS `dblib` driver returns ints.** So a raw, un-cast column read (`$offer->provider_id`) is `"28"` in production but `28` locally. A **strict comparison passes locally and fails on Railway** — e.g. `$provider->id !== $offer->provider_id` 403'd the legitimate owner of `/offers/{token}` because `28 !== "28"`. Tests on the dblib container can't catch this. Rules: cast both sides (`(int) $a !== (int) $b`), or compare loosely (`==`), or push the check into a query `where()` instead of comparing in PHP. Eloquent `$casts`/`$fillable` fix declared model attributes, but **un-cast FK columns and any `DB::`-facade results are returned as strings** — never strict-compare those against an int.
