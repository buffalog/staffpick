# StaffPick — operations runbook

Day-two operations for the live Railway demo. First-time setup is in
[`railway-deploy.md`](./railway-deploy.md); this covers ongoing deploys,
rollback, and demo-data reset.

> **Live**: project `staffpick` on Railway · app at
> `https://app-production-58e5.up.railway.app` · two services: `db` (Azure
> SQL Edge + volume) and `app` (this repo, Dockerfile). All `railway`
> commands run from `~/projects/staffpick` (the project link is per-directory).

## Deploy a new version

```bash
cd ~/projects/staffpick
git push origin main          # keep the repo current
railway up --service app --ci # upload, build the Dockerfile, deploy
```

`railway up` is a manual deploy — the GitHub auto-deploy connection was
skipped (see `railway-deploy.md` § Ongoing deploys to enable it). Watch the
build stream; the deploy is zero-downtime (old version serves until the new
one is healthy).

### If the deploy includes a schema change

Migrations are **not** run automatically. Apply them before or right after
the app deploy, against the DB via the TCP proxy:

```bash
# proxy host:port — Railway dashboard: db service → Settings → Networking,
# or:  railway variables --service db | grep RAILWAY_TCP_PROXY
export DATABASE_URL="sqlserver://<PROXY_HOST>:<PROXY_PORT>;database=staffpick;user=sa;password=<SA_PASSWORD>;encrypt=true;trustServerCertificate=true"
pnpm prisma migrate deploy
unset DATABASE_URL
```

Migrations are forward-only. A bad migration is rolled back by writing a new
corrective migration (or restoring the DB) — there is no `migrate down`.

## Roll back a bad deploy

**App rollback** — Railway keeps deploy history:

- **Dashboard** (cleanest): `app` service → **Deployments** tab → pick the
  last-good deployment → **Redeploy**. Traffic cuts over when it's healthy.
- **CLI**: `railway down` removes the *most recent* deployment (reverts to the
  one before it). Only good for undoing the latest deploy.

**Schema rollback** — if the bad deploy also migrated the DB: the app and
schema roll back independently. Redeploy the old app image, then apply a
corrective migration to bring the schema back. If data was lost, restore from
a DB backup (Azure SQL Edge on Railway has no managed backups — the volume
persists data but isn't point-in-time; treat the demo DB as reconstructible
via the seed, not as a system of record).

## Reset demo data

The 3 walkthrough cases (`DEMO-1/2/3`) are seeded by `prisma/seed-demo.ts`,
which is idempotent — every run wipes the prior `DEMO-*` data and rebuilds it.

```bash
# against the live DB (via the TCP proxy):
export DATABASE_URL="sqlserver://<PROXY_HOST>:<PROXY_PORT>;database=staffpick;user=sa;password=<SA_PASSWORD>;encrypt=true;trustServerCertificate=true"
pnpm exec tsx prisma/seed-demo.ts
unset DATABASE_URL
```

Full reset (reference data + users + demo cases): run the base seed first,
then the demo seed. The base seed is also idempotent (upserts):

```bash
pnpm exec tsx prisma/seed.ts        # tenant, users, providers, ICD-10, measures, rates
pnpm exec tsx prisma/seed-demo.ts   # the 3 demo cases
```

Note: re-running the base seed does **not** wipe cases created through the UI
during a demo — only `seed-demo.ts` manages the `DEMO-*` cases. To clear
UI-created clutter, that's a manual delete (or rebuild the DB from migrations).

## Logs & health

```bash
railway logs --service app          # app runtime logs (errors, emails-without-Resend-key)
railway logs --service db           # SQL Server boot/health
railway service status --service app
curl -sI https://app-production-58e5.up.railway.app/login   # 200 == healthy
```

## Routine checks

- `pnpm exec tsx scripts/audit-routes.ts` — route security audit (every route
  is public-by-design or guarded by `withSession`). Run before any deploy that
  touches `app/`.
- `pnpm typecheck && pnpm lint && pnpm test` — the unit gate.
- `pnpm e2e` — Playwright suite incl. the full 14-phase round-trip
  (`tests/e2e/full-lifecycle.spec.ts`). Needs `pnpm e2e:install` once.
