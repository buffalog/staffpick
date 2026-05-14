# StaffPick — Railway deploy runbook

Stands up a live StaffPick demo on Railway: two services in one project — a
SQL Server database (`db`) and the Next.js app (`app`).

> **Why this shape**: Railway has no managed SQL Server. StaffPick's data
> layer is Prisma's `sqlserver` provider, so the `db` service runs the stock
> `mcr.microsoft.com/azure-sql-edge` Docker image with a volume for
> persistence. Azure SQL Edge is **EOL'd by Microsoft** — this is a
> demo-grade posture, not production. The production path is Ed's Azure SQL;
> see `docs/tech-debt.md`.

## Prerequisites

- `railway` CLI installed (`brew install railway`) — v4.44+ confirmed working.
- The repo pushed to GitHub (done: `jeremy1745/staffpick`, private).
- `sqlcmd` installed locally (`brew install sqlcmd`) — used for one-off DB setup.
- A strong SA password ready. SQL Server requires 8+ chars, 3 of 4 classes
  (upper/lower/digit/symbol). Example shape: `Demo_StaffPick_2026!`
  Generate one and keep it handy — you'll use it in three places.

## 1. Create the project

```bash
cd ~/projects/staffpick
railway login          # opens browser
railway init           # name it "staffpick"
```

## 2. Add the `db` service (Azure SQL Edge)

In the Railway dashboard, inside the `staffpick` project:

1. **+ New → Docker Image** → `mcr.microsoft.com/azure-sql-edge:latest`
2. Rename the service to **`db`** (Settings → Service name). The internal
   hostname becomes `db.railway.internal`.
3. **Variables** — add:
   - `ACCEPT_EULA` = `Y`
   - `MSSQL_SA_PASSWORD` = your strong SA password
4. **Settings → Volumes** — add a volume, mount path **`/var/opt/mssql`**.
   Without this the database is wiped on every redeploy.
5. **Settings → Resources** — give it **at least 2 GB RAM**. SQL Server will
   not start under ~2 GB.
6. **Settings → Networking → TCP Proxy** — enable it on port **1433**. Railway
   gives you a public `host:port` (e.g. `roundhouse.proxy.rlwy.net:43219`).
   You need this temporarily for DB setup from your laptop; you can disable it
   afterward.
7. Deploy. Wait for the service to go green (~1–2 min for SQL Server to boot).

## 3. Create the `staffpick` database + load schema and seed

All run from your laptop, pointed at the TCP proxy from step 2.6. Substitute
`<PROXY_HOST>`, `<PROXY_PORT>`, `<SA_PASSWORD>`.

```bash
# Create the database (Prisma's sqlserver provider does NOT create it)
sqlcmd -S <PROXY_HOST>,<PROXY_PORT> -U sa -P "<SA_PASSWORD>" \
  --trust-server-certificate -Q "CREATE DATABASE staffpick"

# Point a throwaway env at the proxy and run migrations + seed
export DATABASE_URL="sqlserver://<PROXY_HOST>:<PROXY_PORT>;database=staffpick;user=sa;password=<SA_PASSWORD>;encrypt=true;trustServerCertificate=true"
pnpm prisma migrate deploy
pnpm exec tsx prisma/seed.ts
unset DATABASE_URL
```

The seed prints the platform admin + tenant-staff TOTP `otpauth://` URIs and
the provider emails — **copy that output**, you need the TOTP URIs to log in.

You can now disable the TCP proxy (step 2.6) if you want the DB private-only —
the `app` service reaches it over `db.railway.internal` regardless.

## 4. Add the `app` service (Next.js)

1. **+ New → GitHub Repo** → select `jeremy1745/staffpick`. Railway detects
   the `Dockerfile` and builds from it.
2. Rename the service to **`app`**.
3. **Variables** — set everything from `.env.production.example`:
   - `DATABASE_URL` = `sqlserver://db.railway.internal:1433;database=staffpick;user=sa;password=<SA_PASSWORD>;encrypt=true;trustServerCertificate=true`
     (note: **internal** host `db.railway.internal`, not the proxy)
   - `AUTH_SECRET` and `NEXTAUTH_SECRET` = `openssl rand -base64 32` (same value both)
   - `RESEND_API_KEY` = your Resend key, or leave empty (emails log to `railway logs`)
   - `RESEND_FROM` = `StaffPick <noreply@staffpick.local>`
   - `NEXT_PUBLIC_TURNSTILE_SITE_KEY` = `1x00000000000000000000AA`
   - `TURNSTILE_SECRET_KEY` = `1x0000000000000000000000000000000AA`
   - `NODE_ENV` = `production`
   - `LOG_LEVEL` = `info`
   - (`AUTH_URL`, `NEXTAUTH_URL`, `ALLOWED_ORIGINS` — set in step 5, after the
     domain exists)
4. Deploy. The Dockerfile runs `prisma generate` + `next build`.

## 5. Public domain + auth URLs

1. `app` service → **Settings → Networking → Generate Domain**. You get
   `https://<something>.up.railway.app`.
2. Add the remaining `app` variables with that domain:
   - `AUTH_URL` = `https://<domain>`
   - `NEXTAUTH_URL` = `https://<domain>`
   - `ALLOWED_ORIGINS` = `https://<domain>`
3. Redeploy the `app` service (Railway does this automatically on a variable
   change).

## 6. Verify

- `https://<domain>/login` → renders the staff login form.
- `https://<domain>/intake` → renders the public referral webform.
- Log in as a seeded tenant-staff user (`angela.searcy@fcts.local`,
  password `LocalDev_Pa55word!`, TOTP from the seed output in step 3).
  Lands on `/dashboard`.
- Walk one case through the lifecycle (see the FigJam operational diagram
  linked in the README).

## Ongoing deploys

The `app` service auto-deploys on every push to `main`. Schema changes need a
migration applied to the live DB: re-enable the TCP proxy and run
`pnpm prisma migrate deploy` against it (step 3), or add a Railway pre-deploy
command of `pnpm prisma migrate deploy` to the `app` service.

## Notes / gotchas

- **EOL database** — Azure SQL Edge is not maintained by Microsoft. Fine for a
  demo Judd can click through; not a production answer. Migrating to Ed's
  Azure SQL is a `DATABASE_URL` swap — no code change, the Prisma provider is
  identical.
- **Seed credentials are dev-grade** — `LocalDev_Pa55word!` and the fixed E2E
  TOTP secret ship in `prisma/seed.ts`. Acceptable for a private demo; rotate
  before anything real.
- **Resend without a key** — Provider OTP login codes fall back to logs. To
  actually log in as a Provider on the demo, either set `RESEND_API_KEY` or
  pull the code from `railway logs` on the `app` service.
- **Memory** — if the `db` service crashloops, it's almost always RAM. SQL
  Server needs ≥2 GB.
