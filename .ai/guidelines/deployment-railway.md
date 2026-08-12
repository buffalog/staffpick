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

Pushes to `main` trigger a Railway build. The Dockerfile builds the image (PHP 8.4, `pdo_pgsql`, Node 22 for Vite assets). On container start, `start.sh` runs:
1. Clears config cache
2. Runs `php artisan migrate --force`
3. Runs each seeder individually (RolesAndPermissions suppresses duplicate key errors on subsequent boots)
4. Caches routes and views
5. Starts `php artisan serve` on `$PORT`

Railway's managed Postgres provisions the database itself, so there is no create-if-missing step.

### Database: PostgreSQL 18

The database is Railway managed Postgres (`ghcr.io/railwayapp-templates/postgres-ssl:18`) via the `pgsql` driver. Railway injects `DATABASE_URL` plus the `PG*` vars, and the `pgsql` connection reads `DATABASE_URL`, which takes precedence over the discrete `DB_*` values. **Railway names the database `railway`, not `staffpick` — never hardcode a database name.**

Migrated off Azure SQL Edge in the postgres-migration PR. Prod and staging held no patient data, so it was an engine swap plus a re-provision rather than a data migration.

**Constraints that apply to new migrations — do not violate these:**

1. **Never guard DDL behind a driver check that can silently no-op.** Eleven migrations once opened with `if (... getDriverName() !== 'sqlsrv') { return; }`, which creates nothing while reporting SUCCESS. That silently dropped 16 unique constraints and CI stayed green, because nothing asserted an index existed. `tests/Feature/StaffPick/UniqueIndexEnforcementTest.php` now asserts every one of them exists AND rejects a duplicate. Add a case there whenever you add a unique index.

2. **Partial unique indexes are raw DDL.** `$table->unique()` on Postgres emits `ADD CONSTRAINT`, so the backing index is constraint-owned and `DROP INDEX` refuses it — drop the constraint instead. The collision-hardening indexes are free-standing `CREATE UNIQUE INDEX ... WHERE ...` and are dropped with `DROP INDEX IF EXISTS` (no `ON table` clause; that is SQL Server syntax).

3. **Boolean predicates take no comparison.** Write `WHERE is_active`, never `WHERE is_active = 1` — `$table->boolean()` is a real Postgres boolean and `boolean = integer` has no operator.

4. **`ALTER COLUMN` takes two statements.** `ALTER COLUMN y TYPE varchar(n)` and `ALTER COLUMN y SET NOT NULL` are separate, and a cross-family cast needs an explicit `USING`.

5. **JSON columns must be declared `$table->json()`, not `$table->text()`.** A JSON-path `where('data->key', ...)` compiles to the native `->>` operator, which has no `text` overload. A text column throws "operator does not exist" at query time.

6. **Cascade cycles** — a historical SQL Server restriction, but all `sp_*` tables still use plain `unsignedBigInteger` for `tenant_id` with no FK constraint, and the unique-index tests insert arbitrary tenant ids on that assumption. Adding FKs on `tenant_id` is a deliberate, separate change.

7. **No `dropColumn` inside `Schema::create`** — invalid on any DB.

### Local development vs Railway

- **Migrations run locally now.** Use a Postgres 18 container on 5433, so it does not collide with a Homebrew Postgres already on 5432:
  ```bash
  docker run -d --name staffpick-pg -e POSTGRES_PASSWORD='StaffPick_Dev_2026!' \
    -e POSTGRES_DB=staffpick_test -p 5433:5432 postgres:18
  ```
  The committed `.env.testing` already points at it; CI overrides host/port/database.
- `php artisan test` can exhaust the default 128M `memory_limit` locally. Run `php -d memory_limit=-1 vendor/bin/phpunit` instead.
- SQLite in-memory is NOT a valid substitute for StaffPick feature tests — Postgres enforces DDL rules and predicate typing that SQLite does not.
- `pdo_pgsql` returns integer-family columns as PHP `int` and booleans as `bool`; only `numeric`/`decimal` come back as strings. The old pdo_sqlsrv string-coercion trap (`28 !== "28"` 403'ing the legitimate owner of `/offers/{token}`) is therefore gone. Model `$casts` and `ModelCastCoverageTest` are kept regardless, because `pluck()` bypasses casts on any driver.
